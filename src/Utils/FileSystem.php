<?php

namespace Dennenboom\Harvest\Utils;

class FileSystem
{
    /**
     * Ensure a directory exists.
     *
     * @param string $path
     *
     * @return void
     */
    public static function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Remove a directory and its contents.
     *
     * @param string $path
     *
     * @return void
     */
    public static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \FilesystemIterator($path);

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                self::removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }

    /**
     * Get a human-readable filesize.
     *
     * @param int $bytes
     * @param int $precision
     *
     * @return string
     */
    public static function humanFilesize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Get the modification time of a file.
     *
     * @param string $path
     *
     * @return int
     */
    public static function getModificationTime(string $path): int
    {
        return filemtime($path);
    }
}
