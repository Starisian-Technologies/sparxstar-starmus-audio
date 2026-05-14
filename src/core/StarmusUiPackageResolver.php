<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Starmus\core;

use function file_exists;
use function file_get_contents;
use function filemtime;
use function is_array;
use function is_string;
use function json_decode;

final class StarmusUiPackageResolver
{
    private const MANIFEST_FILE = 'starmus-ui-packages.json';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $manifest = null;

    public function __construct(
        private readonly string $base_url = '',
        private readonly string $base_path = ''
    ) {
    }

    /**
     * @return array{
     *     handle: string,
     *     url: string,
     *     version: string,
     *     type: string,
     *     package: string,
     *     surface: string
     * }
     */
    public function resolve_asset(string $asset_key): array
    {
        $manifest = $this->load_manifest();
        $assets = $manifest['assets'] ?? [];
        $asset = is_array($assets) && isset($assets[$asset_key]) && is_array($assets[$asset_key])
            ? $assets[$asset_key]
            : [];

        $handle = is_string($asset['handle'] ?? null) ? $asset['handle'] : '';
        $type = is_string($asset['type'] ?? null) ? $asset['type'] : 'script';
        $package = is_string($asset['package'] ?? null) ? $asset['package'] : '';
        $surface = is_string($asset['surface'] ?? null) ? $asset['surface'] : '';

        $external_url = is_string($asset['externalUrl'] ?? null) ? $asset['externalUrl'] : '';
        if ($external_url !== '') {
            return [
                'handle' => $handle,
                'url' => $external_url,
                'version' => $this->resolve_version(),
                'type' => $type,
                'package' => $package,
                'surface' => $surface,
            ];
        }

        $min_rel = is_string($asset['min'] ?? null) ? $asset['min'] : '';
        if ($min_rel !== '' && $this->base_path !== '' && file_exists($this->base_path . $min_rel)) {
            return [
                'handle' => $handle,
                'url' => $this->base_url . $min_rel,
                'version' => (string) filemtime($this->base_path . $min_rel),
                'type' => $type,
                'package' => $package,
                'surface' => $surface,
            ];
        }

        $src_rel = is_string($asset['src'] ?? null) ? $asset['src'] : '';
        if ($src_rel !== '' && $this->base_path !== '' && file_exists($this->base_path . $src_rel)) {
            return [
                'handle' => $handle,
                'url' => $this->base_url . $src_rel,
                'version' => (string) filemtime($this->base_path . $src_rel),
                'type' => $type,
                'package' => $package,
                'surface' => $surface,
            ];
        }

        return [
            'handle' => $handle,
            'url' => '',
            'version' => $this->resolve_version(),
            'type' => $type,
            'package' => $package,
            'surface' => $surface,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get_runtime_projection(): array
    {
        $manifest = $this->load_manifest();
        $runtime = $manifest['runtime'] ?? [];

        return is_array($runtime) ? $runtime : [];
    }

    private function resolve_version(): string
    {
        return \defined('STARMUS_VERSION') ? (string) STARMUS_VERSION : '1.0.0';
    }

    /**
     * @return array<string, mixed>
     */
    private function load_manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifest_path = $this->base_path . self::MANIFEST_FILE;
        if ($this->base_path === '' || ! file_exists($manifest_path)) {
            $this->manifest = [];
            return $this->manifest;
        }

        $manifest_json = file_get_contents($manifest_path);
        if ( ! is_string($manifest_json) || $manifest_json === '') {
            $this->manifest = [];
            return $this->manifest;
        }

        $decoded = json_decode($manifest_json, true);
        $this->manifest = is_array($decoded) ? $decoded : [];

        return $this->manifest;
    }
}
