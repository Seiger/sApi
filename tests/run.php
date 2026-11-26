<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Support/AssetPublisher.php';

use Seiger\sApi\Support\AssetPublisher;

$failures = [];
$tests = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$tests): void {
    $tests++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$tmp = sys_get_temp_dir() . '/sapi-assets-' . bin2hex(random_bytes(4));
mkdir($tmp . '/src', 0755, true);
$source = $tmp . '/src/logo.svg';
$target = $tmp . '/public/assets/site/sapi.svg';
file_put_contents($source, '<svg>v1</svg>');
touch($source, time() - 100);

// Missing target is created together with its directory.
$written = AssetPublisher::sync([$source => $target]);
$assert($written === [$target], 'missing asset is published');
$assert(@file_get_contents($target) === '<svg>v1</svg>', 'published asset has source contents');

// Up-to-date target is left alone.
$assert(AssetPublisher::sync([$source => $target]) === [], 'up-to-date asset is not rewritten');

// Newer source (package update) replaces the stale target.
file_put_contents($source, '<svg>v2</svg>');
touch($source, time());
$assert(AssetPublisher::sync([$source => $target]) === [$target], 'stale asset is republished');
$assert(file_get_contents($target) === '<svg>v2</svg>', 'republished asset has new contents');

// An older source from another package must not downgrade a shared asset.
$older = $tmp . '/src/older.svg';
file_put_contents($older, '<svg>old</svg>');
touch($older, time() - 1000);
$assert(AssetPublisher::sync([$older => $target]) === [], 'older source does not overwrite newer asset');

// Missing sources are skipped without errors.
$assert(AssetPublisher::sync([$tmp . '/src/none.svg' => $tmp . '/public/none.svg']) === [], 'missing source is skipped');
$assert(!file_exists($tmp . '/public/none.svg'), 'missing source creates no file');

// Unwritable target (a directory in place of the file) fails silently.
mkdir($tmp . '/public/blocked.svg', 0755, true);
$assert(AssetPublisher::sync([$source => $tmp . '/public/blocked.svg']) === [], 'unwritable target is skipped');

// Provider registers every asset referenced by the manager views.
$root = dirname(__DIR__);
$provider = (string) file_get_contents($root . '/src/sApiServiceProvider.php');
$views = '';
foreach (glob($root . '/views/{,partials/}*.blade.php', GLOB_BRACE) ?: [] as $view) {
    $views .= file_get_contents($view);
}
$views .= file_get_contents($root . '/plugins/sApiPlugin.php');
preg_match_all("/asset\\('site\\/([^']+)'\\)/", $views, $matches);
foreach (array_unique($matches[1]) as $asset) {
    $assert(str_contains($provider, "public_path('assets/site/{$asset}')"), "provider publishes assets/site/{$asset}");
}
$assert(!str_contains($views, 'sApi::asset('), 'views do not call undefined sApi::asset()');

$cleanup = static function (string $path) use (&$cleanup): void {
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                $cleanup($path . '/' . $item);
            }
        }
        rmdir($path);
    } elseif (file_exists($path)) {
        unlink($path);
    }
};
$cleanup($tmp);

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, array_map(static fn($f) => "FAIL: {$f}", $failures)) . PHP_EOL);
    exit(1);
}

echo "OK ({$tests} assertions)" . PHP_EOL;
