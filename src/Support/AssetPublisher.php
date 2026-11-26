<?php namespace Seiger\sApi\Support;

/**
 * Keeps manager assets in the public directory in sync with the package,
 * so the UI does not depend on a manual `vendor:publish` run after install or update.
 *
 * @package Seiger\sApi
 * @since 1.1.2
 */
class AssetPublisher
{
    /**
     * Copy each source to its target when the target is missing or older than the source.
     *
     * @param array<string, string> $map source path => target path
     * @return array<int, string> targets that were written
     */
    public static function sync(array $map): array
    {
        $written = [];

        foreach ($map as $source => $target) {
            if (!is_file($source)) {
                continue;
            }

            if (is_file($target) && filemtime($target) >= filemtime($source)) {
                continue;
            }

            $directory = dirname($target);
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                continue;
            }

            // A read-only public dir must not break the manager; publishing stays available via artisan.
            if (@copy($source, $target)) {
                @touch($target, filemtime($source));
                $written[] = $target;
            }
        }

        return $written;
    }
}
