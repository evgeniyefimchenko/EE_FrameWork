<?php

namespace classes\system;

final class VendorAssetService {

    private const DEFAULT_TIMEOUT_SEC = 20;

    private const ASSETS = [
        'bootstrap_css' => [
            'bundle' => 'bootstrap',
            'path' => 'assets/bootstrap/css/bootstrap.min.css',
        ],
        'bootstrap_js' => [
            'bundle' => 'bootstrap',
            'path' => 'assets/bootstrap/js/bootstrap.bundle.min.js',
        ],
        'fontawesome_css' => [
            'bundle' => 'fontawesome',
            'path' => 'assets/fontawesome/css/all.css',
        ],
        'jquery_js' => [
            'bundle' => 'jquery',
            'path' => 'assets/js/plugins/jquery.min.js',
        ],
    ];

    private const BUNDLES = [
        'bootstrap' => [
            [
                'path' => 'assets/bootstrap/css/bootstrap.min.css',
                'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            ],
            [
                'path' => 'assets/bootstrap/js/bootstrap.bundle.min.js',
                'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            ],
        ],
        'fontawesome' => [
            [
                'path' => 'assets/fontawesome/css/all.css',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
            ],
            [
                'path' => 'assets/fontawesome/webfonts/fa-brands-400.woff2',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-brands-400.woff2',
            ],
            [
                'path' => 'assets/fontawesome/webfonts/fa-brands-400.ttf',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-brands-400.ttf',
            ],
            [
                'path' => 'assets/fontawesome/webfonts/fa-regular-400.woff2',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-regular-400.woff2',
            ],
            [
                'path' => 'assets/fontawesome/webfonts/fa-regular-400.ttf',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-regular-400.ttf',
            ],
            [
                'path' => 'assets/fontawesome/webfonts/fa-solid-900.woff2',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-solid-900.woff2',
            ],
            [
                'path' => 'assets/fontawesome/webfonts/fa-solid-900.ttf',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-solid-900.ttf',
            ],
            [
                'path' => 'assets/fontawesome/webfonts/fa-v4compatibility.woff2',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-v4compatibility.woff2',
            ],
            [
                'path' => 'assets/fontawesome/webfonts/fa-v4compatibility.ttf',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-v4compatibility.ttf',
            ],
        ],
        'jquery' => [
            [
                'path' => 'assets/js/plugins/jquery.min.js',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js',
            ],
        ],
        'codemirror' => [
            [
                'path' => 'assets/vendor/codemirror/5.65.10/codemirror.css',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/codemirror.css',
            ],
            [
                'path' => 'assets/vendor/codemirror/5.65.10/theme/monokai.css',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/theme/monokai.css',
            ],
            [
                'path' => 'assets/vendor/codemirror/5.65.10/codemirror.js',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/codemirror.js',
            ],
            [
                'path' => 'assets/vendor/codemirror/5.65.10/mode/xml/xml.js',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/xml/xml.js',
            ],
            [
                'path' => 'assets/vendor/codemirror/5.65.10/mode/javascript/javascript.js',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/javascript/javascript.js',
            ],
            [
                'path' => 'assets/vendor/codemirror/5.65.10/mode/css/css.js',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/css/css.js',
            ],
            [
                'path' => 'assets/vendor/codemirror/5.65.10/mode/htmlmixed/htmlmixed.js',
                'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/htmlmixed/htmlmixed.js',
            ],
        ],
    ];

    private static array $checkedBundles = [];

    public static function localUrl(string $assetKey): string {
        $asset = self::ASSETS[$assetKey] ?? null;
        if (!is_array($asset)) {
            return rtrim((string) ENV_URL_SITE, '/') . '/';
        }

        self::ensureBundle((string) $asset['bundle']);
        return rtrim((string) ENV_URL_SITE, '/') . '/' . ltrim((string) $asset['path'], '/');
    }

    public static function ensureBundle(string $bundleKey): bool {
        $bundleKey = trim($bundleKey);
        if ($bundleKey === '' || !isset(self::BUNDLES[$bundleKey])) {
            return false;
        }

        if (array_key_exists($bundleKey, self::$checkedBundles)) {
            return self::$checkedBundles[$bundleKey];
        }

        if (self::bundleExists($bundleKey)) {
            self::$checkedBundles[$bundleKey] = true;
            return true;
        }

        if (!self::isAutoDownloadEnabled()) {
            self::$checkedBundles[$bundleKey] = false;
            return false;
        }

        $result = self::withBundleLock($bundleKey, static function () use ($bundleKey): bool {
            if (self::bundleExists($bundleKey)) {
                return true;
            }

            $ok = true;
            foreach (self::BUNDLES[$bundleKey] as $file) {
                if (self::fileExists((string) $file['path'])) {
                    continue;
                }
                if (!self::downloadFile((string) $file['url'], (string) $file['path'])) {
                    $ok = false;
                }
            }

            return $ok && self::bundleExists($bundleKey);
        });

        self::$checkedBundles[$bundleKey] = $result;
        return $result;
    }

    public static function localBaseUrl(string $bundleKey): string {
        self::ensureBundle($bundleKey);

        return match ($bundleKey) {
            'codemirror' => rtrim((string) ENV_URL_SITE, '/') . '/assets/vendor/codemirror/5.65.10',
            default => rtrim((string) ENV_URL_SITE, '/'),
        };
    }

    private static function bundleExists(string $bundleKey): bool {
        foreach (self::BUNDLES[$bundleKey] ?? [] as $file) {
            if (!self::fileExists((string) $file['path'])) {
                return false;
            }
        }

        return true;
    }

    private static function fileExists(string $relativePath): bool {
        $path = self::absolutePath($relativePath);
        return is_file($path) && filesize($path) > 0;
    }

    private static function isAutoDownloadEnabled(): bool {
        return !defined('ENV_VENDOR_ASSETS_AUTO_DOWNLOAD') || (bool) ENV_VENDOR_ASSETS_AUTO_DOWNLOAD;
    }

    private static function withBundleLock(string $bundleKey, callable $callback): bool {
        $lockDir = defined('ENV_TMP_PATH')
            ? rtrim((string) ENV_TMP_PATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor_assets'
            : rtrim((string) ENV_SITE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'vendor_assets';
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
            return (bool) $callback();
        }

        $lockPath = $lockDir . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9_.-]+/i', '_', $bundleKey) . '.lock';
        $handle = @fopen($lockPath, 'c');
        if (!is_resource($handle)) {
            return (bool) $callback();
        }

        try {
            @flock($handle, LOCK_EX);
            return (bool) $callback();
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    private static function downloadFile(string $url, string $relativePath): bool {
        $targetPath = self::absolutePath($relativePath);
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            self::log('Cannot create vendor asset directory: ' . $targetDir);
            return false;
        }

        $tmpPath = $targetPath . '.download.' . getmypid() . '.tmp';
        @unlink($tmpPath);

        $ok = function_exists('curl_init')
            ? self::downloadWithCurl($url, $tmpPath)
            : self::downloadWithStreams($url, $tmpPath);

        if (!$ok || !is_file($tmpPath) || filesize($tmpPath) <= 0) {
            @unlink($tmpPath);
            self::log('Cannot download vendor asset: ' . $url . ' -> ' . $relativePath);
            return false;
        }

        if (!@rename($tmpPath, $targetPath)) {
            @unlink($targetPath);
            if (!@rename($tmpPath, $targetPath)) {
                @unlink($tmpPath);
                self::log('Cannot move downloaded vendor asset: ' . $relativePath);
                return false;
            }
        }

        @chmod($targetPath, 0664);
        return true;
    }

    private static function downloadWithCurl(string $url, string $tmpPath): bool {
        $handle = @fopen($tmpPath, 'wb');
        if (!is_resource($handle)) {
            return false;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            @fclose($handle);
            return false;
        }

        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::getTimeout(),
            CURLOPT_USERAGENT => 'EE_FrameWork VendorAssetService',
        ]);

        $ok = curl_exec($curl) !== false;
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        @fclose($handle);

        return $ok && $statusCode >= 200 && $statusCode < 300;
    }

    private static function downloadWithStreams(string $url, string $tmpPath): bool {
        $context = stream_context_create([
            'http' => [
                'timeout' => self::getTimeout(),
                'user_agent' => 'EE_FrameWork VendorAssetService',
            ],
        ]);
        $contents = @file_get_contents($url, false, $context);
        if ($contents === false || $contents === '') {
            return false;
        }

        return @file_put_contents($tmpPath, $contents, LOCK_EX) !== false;
    }

    private static function getTimeout(): int {
        return defined('ENV_VENDOR_ASSETS_DOWNLOAD_TIMEOUT')
            ? max(3, min(120, (int) ENV_VENDOR_ASSETS_DOWNLOAD_TIMEOUT))
            : self::DEFAULT_TIMEOUT_SEC;
    }

    private static function absolutePath(string $relativePath): string {
        return rtrim((string) ENV_SITE_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    }

    private static function log(string $message): void {
        if (defined('ENV_LOGS_PATH') && function_exists('ee_runtime_append_managed_log')) {
            ee_runtime_append_managed_log((string) ENV_LOGS_PATH . 'vendor_assets.txt', '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
            return;
        }

        error_log($message);
    }
}
