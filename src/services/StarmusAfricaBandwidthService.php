<?php

declare(strict_types=1);
namespace Starisian\Sparxstar\Starmus\services;

use Starisian\Sparxstar\Starmus\data\interfaces\IStarmusAudioDAL;
use Starisian\Sparxstar\Starmus\data\StarmusAudioDAL;

if ( ! \defined('ABSPATH')) {
    exit;
}

/**
 * Bandwidth-Optimized Audio Service for Africa/Gambia
 *
 * Minimal FFmpeg wrapper focused on extreme bandwidth conservation.
 */
final class StarmusAfricaBandwidthService
{
    /**
     * Approximate mobile data cost per MB (USD) by country ISO-3166-1 alpha-2.
     *
     * Sources: GSMA Intelligence, Alliance for Affordable Internet (A4AI) 2024 reports.
     * Costs reflect prepaid 1 GB bundles divided by 1024. Update annually.
     *
     * @var array<string, float>
     */
    private const COUNTRY_COST_PER_MB = [
        'BF' => 0.17, // Burkina Faso
        'CD' => 0.18, // Dem. Rep. Congo
        'CI' => 0.10, // Côte d'Ivoire
        'CM' => 0.13, // Cameroon
        'ET' => 0.11, // Ethiopia
        'GH' => 0.08, // Ghana
        'GM' => 0.15, // Gambia
        'KE' => 0.05, // Kenya
        'MG' => 0.11, // Madagascar
        'ML' => 0.18, // Mali
        'MW' => 0.14, // Malawi
        'MZ' => 0.12, // Mozambique
        'NG' => 0.06, // Nigeria
        'RW' => 0.07, // Rwanda
        'SN' => 0.10, // Senegal
        'TZ' => 0.09, // Tanzania
        'UG' => 0.12, // Uganda
        'ZA' => 0.04, // South Africa
        'ZM' => 0.09, // Zambia
        'ZW' => 0.16, // Zimbabwe
    ];

    /**
     * Fallback cost per MB used when the country code is not in the map.
     *
     * @var float
     */
    private const DEFAULT_COST_PER_MB = 0.15;

    private string $ffmpeg_path;

    public function __construct(?IStarmusAudioDAL $dal = null)
    {
        $dal = $dal ?: new StarmusAudioDAL();
        $this->ffmpeg_path = $dal->get_ffmpeg_path() ?: 'ffmpeg';
    }

    /**
     * Generate ultra-low bandwidth versions for African networks
     */
    public function createAfricaOptimized(string $input_path): array
    {
        $base_name = pathinfo($input_path, PATHINFO_FILENAME);
        $dir = \dirname($input_path);

        return [
        // 2G networks (EDGE) - 32kbps, 16kHz
        'africa_2g' => $this->convert(
            $input_path,
            \sprintf('%s/%s_2g.mp3', $dir, $base_name),
            [
        '-b:a',
        '32k',
        '-ar',
        '16000',
        '-ac',
        '1',
            ]
        ),

        // 3G networks - 48kbps, 22kHz
        'africa_3g' => $this->convert(
            $input_path,
            \sprintf('%s/%s_3g.mp3', $dir, $base_name),
            [
        '-b:a',
        '48k',
        '-ar',
        '22050',
        '-ac',
        '1',
            ]
        ),

        // WiFi/4G - 64kbps, 44kHz
        'africa_wifi' => $this->convert(
            $input_path,
            \sprintf('%s/%s_wifi.mp3', $dir, $base_name),
            [
        '-b:a',
        '64k',
        '-ar',
        '44100',
        '-ac',
        '1',
            ]
        ),
        ];
    }

    /**
     * Minimal conversion with aggressive compression
     */
    private function convert(string $input, string $output, array $params): ?string
    {
        $cmd = implode(
            ' ',
            array_merge(
                [$this->ffmpeg_path, '-i', escapeshellarg($input)],
                $params,
                ['-f mp3', escapeshellarg($output), '2>/dev/null']
            )
        );

        exec($cmd, $out, $code);
        return $code === 0 ? $output : null;
    }

    /**
     * Generate a short preview clip (Pipeline 2 requirement)
     */
    public function generatePreviewClip(string $input_path, int $duration = 30): ?string
    {
        $output_path = \dirname($input_path) . '/' . pathinfo($input_path, PATHINFO_FILENAME) . '_preview.mp3';

        $cmd = [$this->ffmpeg_path, '-i', escapeshellarg($input_path), '-t', (string) $duration, '-ac', '1', '-ar', '22050', '-b:a', '64k', escapeshellarg($output_path), '2>/dev/null'];

        exec(implode(' ', $cmd), $out, $code);
        return $code === 0 ? $output_path : null;
    }

    /**
     * Returns the data cost per MB (USD) for the given ISO 3166-1 alpha-2
     * country code. Falls back to {@see DEFAULT_COST_PER_MB} for unknown codes.
     *
     * @param string $country_code ISO 3166-1 alpha-2 country code (e.g. 'GM').
     *
     * @return float Cost in USD per MB.
     */
    public function getCountryCostPerMb(string $country_code): float
    {
        $code = strtoupper(trim($country_code));
        return self::COUNTRY_COST_PER_MB[$code] ?? self::DEFAULT_COST_PER_MB;
    }

    /**
     * Estimate data usage and cost for a given audio file.
     *
     * Returns bandwidth consumption figures and an approximate monetary cost
     * based on real prepaid mobile-data pricing for the specified African
     * country. Pass an ISO 3166-1 alpha-2 country code to get a country-specific
     * cost estimate; unknown codes fall back to {@see DEFAULT_COST_PER_MB}.
     *
     * `cost_country` reflects the ISO code whose price was actually applied.
     * It matches `country` when the code is recognised, and is an empty string
     * when the generic {@see DEFAULT_COST_PER_MB} fallback was used instead.
     * Callers can detect a fallback by comparing `country !== cost_country`.
     *
     * @param string $file_path Absolute path to the audio file.
     * @param string $country_code ISO 3166-1 alpha-2 country code (default: 'GM').
     *
     * @return array{
     *     size_mb: float,
     *     cost_estimate_usd: float,
     *     cost_per_mb_usd: float,
     *     country: string,
     *     cost_country: string,
     *     download_time_2g: string,
     *     download_time_3g: string,
     *     recommended: string
     * }|array<never, never>
     */
    public function estimateDataUsage(string $file_path, string $country_code = 'GM'): array
    {
        if ( ! file_exists($file_path)) {
            return [];
        }

        $size_mb      = filesize($file_path) / (1024 * 1024);
        $country_upper = strtoupper(trim($country_code));
        $code_known   = isset(self::COUNTRY_COST_PER_MB[ $country_upper ]);
        $cost_per_mb  = $code_known ? self::COUNTRY_COST_PER_MB[ $country_upper ] : self::DEFAULT_COST_PER_MB;

        return [
            'size_mb'           => round($size_mb, 2),
            'cost_estimate_usd' => round($size_mb * $cost_per_mb, 4),
            'cost_per_mb_usd'   => $cost_per_mb,
            'country'           => $country_upper,
            'cost_country'      => $code_known ? $country_upper : '',
            'download_time_2g'  => round($size_mb / 0.03, 0) . 's', // ~30 KB/s
            'download_time_3g'  => round($size_mb / 0.1, 0) . 's',  // ~100 KB/s
            'recommended'       => $size_mb > 5 ? '2g' : ($size_mb > 2 ? '3g' : 'wifi'),
        ];
    }
}
