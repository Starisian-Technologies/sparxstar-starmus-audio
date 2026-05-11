<?php

declare(strict_types=1);
namespace Starisian\Sparxstar\Starmus\services;

use Aws\S3\S3Client;
use Exception;
use RuntimeException;
use Starisian\Sparxstar\Starmus\helpers\StarmusLogger;
use Starisian\Sparxstar\Starmus\services\interfaces\IStarmusStorageService;
use Throwable;

if ( ! \defined('ABSPATH')) {
    exit;
}

/**
 * S3-Compatible Storage Service (Cloudflare R2 / AWS S3)
 *
 * Implements IStarmusStorageService against the AWS S3 SDK.
 * Application code must depend on IStarmusStorageService — never this concrete class.
 * Provider selected by STARMUS_STORAGE_PROVIDER constant ("r2" or "aws").
 * Ref: Tech Spec v1.0 F-02, CS §0.7.
 */
final class StarmusR2DirectService implements IStarmusStorageService
{
    private S3Client $storage_client;

    private string $bucket;

    private string $public_endpoint;

    private ?StarmusId3Service $id3_service = null;

    /**
     * @throws \RuntimeException When required storage constants are missing or the SDK client
     *                           cannot be initialised. Callers (e.g. StarmusAudioPipeline) must
     *                           catch and decide whether to abort or degrade gracefully.
     */
    public function __construct(StarmusId3Service $id3_service)
    {
        $this->id3_service = $id3_service;

        $provider = \defined('STARMUS_STORAGE_PROVIDER') ? STARMUS_STORAGE_PROVIDER : 'r2';

        if ($provider === 'aws') {
            $this->configureAws();
        } else {
            $this->configureR2();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function upload(string $local_path, string $key, string $content_type, array $metadata = []): ?string
    {
        $handle = fopen($local_path, 'rb');
        if ($handle === false) {
            StarmusLogger::error('Storage upload failed: cannot open file', ['key' => $key]);
            return null;
        }

        try {
            $this->storage_client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $handle,
                'ContentType' => $content_type,
                'CacheControl' => 'public, max-age=31536000',
                'Metadata' => $metadata,
            ]);

            return trailingslashit($this->public_endpoint) . ltrim($key, '/');
        } catch (Exception) {
            StarmusLogger::error('Storage upload failed', ['key' => $key]);
            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            $this->storage_client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (Exception) {
            StarmusLogger::error('Storage delete failed', ['key' => $key]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        try {
            return $this->storage_client->doesObjectExistV2($this->bucket, $key);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPresignedUrl(string $key, int $expires = 3600): ?string
    {
        try {
            $cmd = $this->storage_client->getCommand('GetObject', ['Bucket' => $this->bucket, 'Key' => $key]);
            $request = $this->storage_client->createPresignedRequest($cmd, '+' . $expires . ' seconds');
            return (string) $request->getUri();
        } catch (Exception) {
            StarmusLogger::error('Presigned URL generation failed', ['key' => $key]);
            return null;
        }
    }

    /**
     * Process audio for African networks — generates bandwidth-tiered versions and uploads them.
     * This is the authoritative web-optimized version path. Ref: Tech Spec v1.0 F-05.
     *
     * @param string $local_path Absolute path to the original audio file.
     * @param int $post_id WordPress post ID for storage key namespacing.
     *
     * @return array<string, mixed> Keyed by quality tier ('2g', '3g', 'wifi'), or ['message'] on skip.
     */
    public function processAfricaAudio(string $local_path, int $post_id): array
    {
        if ( ! $this->id3_service->needsAfricaOptimization($local_path)) {
            return ['message' => 'No optimization needed'];
        }

        $results = [];
        $base_name = pathinfo($local_path, PATHINFO_FILENAME);

        $versions = [
            '2g' => ['-b:a', '32k', '-ar', '16000', '-ac', '1'],
            '3g' => ['-b:a', '48k', '-ar', '22050', '-ac', '1'],
            'wifi' => ['-b:a', '64k', '-ar', '44100', '-ac', '1'],
        ];

        foreach ($versions as $quality => $params) {
            $temp_file = $this->createOptimizedVersion($local_path, $params);

            if ($temp_file) {
                $key = \sprintf('audio/%d/%s_%s.mp3', $post_id, $base_name, $quality);
                $url = $this->upload(
                    $temp_file,
                    $key,
                    'audio/mpeg',
                    [
                        'starmus-optimized' => 'africa',
                        'created' => gmdate('c'),
                    ]
                );

                if ($url) {
                    $results[$quality] = [
                        'url' => $url,
                        'size_mb' => round(filesize($temp_file) / (1024 * 1024), 2),
                        'key' => $key,
                    ];
                }

                unlink($temp_file);
            }
        }

        return $results;
    }

    /**
     * Get bandwidth estimates for Africa.
     *
     * @param string $file_path Absolute path to the audio file.
     *
     * @return array<string, mixed>
     */
    public function getAfricaEstimates(string $file_path): array
    {
        if (! is_file($file_path) || ! is_readable($file_path)) {
            StarmusLogger::warning(
                'Unable to estimate Africa bandwidth savings: file is missing or unreadable.',
                [
                    'method' => __METHOD__,
                    'file_path' => $file_path,
                ]
            );

            return [
                'original_mb' => 0.0,
                'africa_2g_mb' => 0.0,
                'cost_savings_usd' => 0.0,
                'bandwidth_savings' => '0%',
                'message' => 'File is missing or unreadable.',
            ];
        }

        $size_bytes = filesize($file_path);

        if ($size_bytes === false) {
            StarmusLogger::warning(
                'Unable to estimate Africa bandwidth savings: filesize() failed.',
                [
                    'method' => __METHOD__,
                    'file_path' => $file_path,
                ]
            );

            return [
                'original_mb' => 0.0,
                'africa_2g_mb' => 0.0,
                'cost_savings_usd' => 0.0,
                'bandwidth_savings' => '0%',
                'message' => 'Unable to determine file size.',
            ];
        }

        $size_mb = $size_bytes / (1024 * 1024);
        return [
            'original_mb' => round($size_mb, 2),
            'africa_2g_mb' => round($size_mb * 0.15, 2),
            'cost_savings_usd' => round($size_mb * 0.13, 2),
            'bandwidth_savings' => '85%',
        ];
    }

    private function configureR2(): void
    {
        if (! \defined('STARMUS_R2_ACCOUNT_ID') || STARMUS_R2_ACCOUNT_ID === '') {
            throw new RuntimeException('STARMUS_R2_ACCOUNT_ID is not defined or is empty.');
        }
        if (! \defined('STARMUS_R2_ACCESS_KEY') || STARMUS_R2_ACCESS_KEY === '') {
            throw new RuntimeException('STARMUS_R2_ACCESS_KEY is not defined or is empty.');
        }
        if (! \defined('STARMUS_R2_SECRET_KEY') || STARMUS_R2_SECRET_KEY === '') {
            throw new RuntimeException('STARMUS_R2_SECRET_KEY is not defined or is empty.');
        }

        if (! \defined('STARMUS_R2_ENDPOINT')) {
            throw new RuntimeException('STARMUS_R2_ENDPOINT is not defined or is empty.');
        }
        // Read via constant() so static analysis cannot infer the literal value; this
        // preserves the empty-string guard when wp-config.php defines the constant as ''.
        $r2_endpoint = (string) \constant('STARMUS_R2_ENDPOINT');
        if ($r2_endpoint === '') {
            throw new RuntimeException('STARMUS_R2_ENDPOINT is not defined or is empty.');
        }

        $this->bucket = \defined('STARMUS_R2_BUCKET') ? STARMUS_R2_BUCKET : 'starmus-audio';
        $account_id = STARMUS_R2_ACCOUNT_ID;
        $this->public_endpoint = $r2_endpoint;

        $this->storage_client = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => \sprintf('https://%s.r2.cloudflarestorage.com', $account_id),
            'credentials' => [
                'key' => STARMUS_R2_ACCESS_KEY,
                'secret' => STARMUS_R2_SECRET_KEY,
            ],
            'use_path_style_endpoint' => true,
        ]);
    }

    private function configureAws(): void
    {
        if (! \defined('STARMUS_S3_BUCKET') || STARMUS_S3_BUCKET === '') {
            throw new RuntimeException('STARMUS_S3_BUCKET is not defined or is empty.');
        }
        if (! \defined('STARMUS_S3_ACCESS_KEY') || STARMUS_S3_ACCESS_KEY === '') {
            throw new RuntimeException('STARMUS_S3_ACCESS_KEY is not defined or is empty.');
        }
        if (! \defined('STARMUS_S3_SECRET_KEY') || STARMUS_S3_SECRET_KEY === '') {
            throw new RuntimeException('STARMUS_S3_SECRET_KEY is not defined or is empty.');
        }

        $this->bucket = STARMUS_S3_BUCKET;
        $region = \defined('STARMUS_S3_REGION') ? STARMUS_S3_REGION : 'us-east-1';

        $this->public_endpoint = \defined('STARMUS_S3_ENDPOINT')
            ? STARMUS_S3_ENDPOINT
            : \sprintf('https://%s.s3.%s.amazonaws.com/', $this->bucket, $region);

        $this->storage_client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => STARMUS_S3_ACCESS_KEY,
                'secret' => STARMUS_S3_SECRET_KEY,
            ],
        ]);
    }

    private function createOptimizedVersion(string $input, array $params): ?string
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'starmus_africa_') . '.mp3';

        $cmd = implode(
            ' ',
            array_merge(
                ['ffmpeg -y -i', escapeshellarg($input)],
                $params,
                ['-f mp3', escapeshellarg($temp_file), '2>/dev/null']
            )
        );

        exec($cmd, $output, $code);

        if ($code === 0 && file_exists($temp_file)) {
            $this->copyMetadata($input, $temp_file);
            return $temp_file;
        }

        return null;
    }

    private function copyMetadata(string $source, string $destination): void
    {
        try {
            $analysis = $this->id3_service->analyzeFile($source);

            if ( ! empty($analysis['comments'])) {
                $tags = [];
                foreach ($analysis['comments'] as $key => $values) {
                    if ( ! empty($values[0])) {
                        $tags[$key] = $values;
                    }
                }

                $tags['comment'] = [($tags['comment'][0] ?? '') . ' [R2-Africa]'];
                $this->id3_service->writeTags($destination, $tags);
            }
        } catch (Throwable $throwable) {
            StarmusLogger::log($throwable);
        }
    }
}
