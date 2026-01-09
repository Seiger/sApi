<?php namespace Seiger\sApi\Discovery;

use Seiger\sApi\sApi;

/**
 * Discovers sApi route providers from Composer metadata and caches a normalized map on disk.
 *
 * Sources:
 * - Vendor providers: `core/composer.lock` (package-level `extra.sapi.route_providers`)
 * - Custom overrides: `core/custom/composer.json` (root-level `extra.sapi.route_providers`)
 *
 * Cache:
 * - `core/storage/cache/sapi_routes_map.php`
 *
 * Cache invalidation:
 * - mtime/size changes for `core/composer.lock`
 * - mtime/size changes for `core/custom/composer.json` (if present)
 * - changes to global API version (`SAPI_VERSION`)
 */
final class RouteProviderDiscovery
{
    public function loadRoutesMap(): array
    {
        $cachePath = $this->cachePath();

        if (is_file($cachePath)) {
            $map = @include $cachePath;
            if (is_array($map) && $this->isCacheValid($map)) {
                return $map;
            }
        }

        $map = $this->rebuildRoutesMap();
        $this->writeCache($map);

        return $map;
    }

    protected function rebuildRoutesMap(): array
    {
        $composerLockPath = $this->composerLockPath();
        $customComposerPath = $this->customComposerPath();

        $lockMtime = is_file($composerLockPath) ? (int)(filemtime($composerLockPath) ?: 0) : 0;
        $lockSize = is_file($composerLockPath) ? (int)(filesize($composerLockPath) ?: 0) : 0;

        $customMtime = is_file($customComposerPath) ? (int)(filemtime($customComposerPath) ?: 0) : 0;
        $customSize = is_file($customComposerPath) ? (int)(filesize($customComposerPath) ?: 0) : 0;

        $globalVersion = $this->normalizeVersion((string)env('SAPI_VERSION', 'v1'));
        $basePath = trim((string)env('SAPI_BASE_PATH', 'api'), '/');

        $vendor = $this->readVendorProviders($globalVersion);
        $custom = $this->readCustomProviders($globalVersion);

        $map = [
            '_meta' => [
                'composer_lock_mtime' => $lockMtime,
                'composer_lock_size' => $lockSize,
                'custom_composer_mtime' => $customMtime,
                'custom_composer_size' => $customSize,
                'global_version' => $globalVersion,
                'base_path' => $basePath,
            ],
        ];

        // Vendor providers first, but skip keys overridden by custom.
        foreach ($vendor as $key => $row) {
            if (isset($custom[$key])) {
                continue;
            }
            $map[$key] = $row + ['source' => 'vendor'];
        }

        // Custom providers last (including overrides).
        foreach ($custom as $key => $row) {
            $map[$key] = $row + ['source' => 'custom'];
        }

        return $map;
    }

    /**
     * @return array<string, array{class:string,endpoint:string,version:string}>
     */
    protected function readVendorProviders(string $globalVersion): array
    {
        $path = $this->composerLockPath();
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $packages = [];
        if (isset($decoded['packages']) && is_array($decoded['packages'])) {
            $packages = array_merge($packages, $decoded['packages']);
        }
        if (isset($decoded['packages-dev']) && is_array($decoded['packages-dev'])) {
            $packages = array_merge($packages, $decoded['packages-dev']);
        }

        $out = [];
        foreach ($packages as $pkg) {
            if (!is_array($pkg)) {
                continue;
            }

            $providers = $pkg['extra']['sapi']['route_providers'] ?? null;
            if (!is_array($providers)) {
                continue;
            }

            foreach ($providers as $descriptor) {
                $normalized = $this->normalizeDescriptor($descriptor, $globalVersion);
                if ($normalized === null) {
                    continue;
                }

                [$key, $row] = $normalized;
                // First win within vendor to keep resolution stable.
                if (!isset($out[$key])) {
                    $out[$key] = $row;
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{class:string,endpoint:string,version:string}>
     */
    protected function readCustomProviders(string $globalVersion): array
    {
        $path = $this->customComposerPath();
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $providers = $decoded['extra']['sapi']['route_providers'] ?? null;
        if (!is_array($providers)) {
            return [];
        }

        $out = [];
        foreach ($providers as $descriptor) {
            $normalized = $this->normalizeDescriptor($descriptor, $globalVersion);
            if ($normalized === null) {
                continue;
            }

            [$key, $row] = $normalized;
            // Custom has priority, last write wins.
            $out[$key] = $row;
        }

        return $out;
    }

    protected function isCacheValid(array $map): bool
    {
        $meta = $map['_meta'] ?? null;
        if (!is_array($meta)) {
            return false;
        }

        $composerLockPath = $this->composerLockPath();
        if (!is_file($composerLockPath)) {
            return false;
        }

        $expectedLockMtime = (int)($meta['composer_lock_mtime'] ?? -1);
        $expectedLockSize = (int)($meta['composer_lock_size'] ?? -1);

        $currentLockMtime = (int)(filemtime($composerLockPath) ?: 0);
        $currentLockSize = (int)(filesize($composerLockPath) ?: 0);

        if ($expectedLockMtime !== $currentLockMtime || $expectedLockSize !== $currentLockSize) {
            return false;
        }

        $customComposerPath = $this->customComposerPath();
        $expectedCustomMtime = (int)($meta['custom_composer_mtime'] ?? 0);
        $expectedCustomSize = (int)($meta['custom_composer_size'] ?? 0);

        $currentCustomMtime = is_file($customComposerPath) ? (int)(filemtime($customComposerPath) ?: 0) : 0;
        $currentCustomSize = is_file($customComposerPath) ? (int)(filesize($customComposerPath) ?: 0) : 0;

        if ($expectedCustomMtime !== $currentCustomMtime || $expectedCustomSize !== $currentCustomSize) {
            return false;
        }

        $expectedGlobalVersion = (string)($meta['global_version'] ?? '');
        $currentGlobalVersion = $this->normalizeVersion((string)env('SAPI_VERSION', 'v1'));

        if ($expectedGlobalVersion !== $currentGlobalVersion) {
            return false;
        }

        $expectedBasePath = (string)($meta['base_path'] ?? '');
        $currentBasePath = trim((string)env('SAPI_BASE_PATH', 'api'), '/');

        return $expectedBasePath === $currentBasePath;
    }

    protected function writeCache(array $map): void
    {
        $path = $this->cachePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = "<?php return " . var_export($map, true) . ";\n";
        $tmp = $path . '.' . uniqid('tmp', true);

        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @rename($tmp, $path);
            @chmod($path, 0664);
            return;
        }

        @unlink($tmp);
    }

    /**
     * @param mixed $descriptor
     * @return array{0:string,1:array{class:string,endpoint:string,version:string}}|null
     */
    private function normalizeDescriptor(mixed $descriptor, string $globalVersion): ?array
    {
        if (!is_array($descriptor)) {
            return null;
        }

        $class = isset($descriptor['class']) ? trim((string)$descriptor['class']) : '';
        if ($class === '') {
            return null;
        }

        $endpoint = isset($descriptor['endpoint']) ? trim((string)$descriptor['endpoint']) : '';
        $endpoint = trim($endpoint, '/');
        $endpoint = strtolower($endpoint);
        if ($endpoint === '' || preg_match('/\\s/', $endpoint)) {
            return null;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $endpoint)) {
            return null;
        }

        $version = null;
        if (array_key_exists('version', $descriptor)) {
            $version = $this->normalizeVersion((string)$descriptor['version']);
        } else {
            $version = $globalVersion;
        }

        // Key format: "{version}/{endpoint}" or "{endpoint}" when version is empty.
        $key = $version !== '' ? ($version . '/' . $endpoint) : $endpoint;

        return [$key, [
            'class' => $class,
            'endpoint' => $endpoint,
            'version' => $version,
        ]];
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        $version = trim($version, '/');
        if ($version === '') {
            return '';
        }

        // Keep version path-safe.
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $version)) {
            return '';
        }

        return $version;
    }

    private function composerLockPath(): string
    {
        return rtrim((string)(defined('EVO_CORE_PATH') ? EVO_CORE_PATH : __DIR__), '/\\') . DIRECTORY_SEPARATOR . 'composer.lock';
    }

    private function customComposerPath(): string
    {
        return rtrim((string)(defined('EVO_CORE_PATH') ? EVO_CORE_PATH : __DIR__), '/\\') . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'composer.json';
    }

    private function cachePath(): string
    {
        $base = defined('EVO_STORAGE_PATH') ? EVO_STORAGE_PATH : (defined('EVO_CORE_PATH') ? EVO_CORE_PATH . 'storage' . DIRECTORY_SEPARATOR : __DIR__);
        return rtrim((string)$base, '/\\') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'sapi_routes_map.php';
    }
}
