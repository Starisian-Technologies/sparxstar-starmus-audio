<?php

declare(strict_types=1);
namespace Starisian\Sparxstar\Starmus\services\interfaces;

if ( ! \defined('ABSPATH')) {
    exit;
}

/**
 * Storage service abstraction for S3-compatible providers (Cloudflare R2, AWS S3).
 *
 * Application code must program against this interface, never against a provider SDK
 * directly. This satisfies CS §0.7 (Infrastructure Provider Selection) and
 * Platform Integrity Map Invariant 1 (no guarantee may depend on third-party plugin
 * cooperation). Ref: Tech Spec v1.0 F-02.
 *
 * @package Starisian\Sparxstar\Starmus\services\interfaces
 */
interface IStarmusStorageService
{
    /**
     * Upload a local file to the configured storage bucket.
     *
     * @param string $local_path  Absolute path to the local file.
     * @param string $key         Object key (path) within the bucket.
     * @param string $content_type MIME type for the stored object.
     * @param array<string, string> $metadata Optional key-value metadata to attach.
     *
     * @return string|null Public URL of the stored object, or null on failure.
     */
    public function upload(string $local_path, string $key, string $content_type, array $metadata = []): ?string;

    /**
     * Delete an object from the configured storage bucket.
     *
     * @param string $key Object key within the bucket.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(string $key): bool;

    /**
     * Check whether an object exists in the configured storage bucket.
     *
     * @param string $key Object key within the bucket.
     *
     * @return bool True if the object exists.
     */
    public function exists(string $key): bool;

    /**
     * Generate a pre-signed URL for temporary access to an object.
     *
     * @param string $key     Object key within the bucket.
     * @param int    $expires Validity period in seconds (default: 3600).
     *
     * @return string|null Pre-signed URL, or null if the provider does not support it.
     */
    public function getPresignedUrl(string $key, int $expires = 3600): ?string;
}
