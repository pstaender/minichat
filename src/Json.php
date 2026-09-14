<?php

namespace Philipp\Minichat;

/**
 * Small helper for reading/writing JSON files with locking, used as the
 * on-disk storage for config, sessions and usage (no database in this app).
 */
class Json
{
    public static function read(string $path, mixed $default = null): mixed
    {
        if (!is_file($path)) {
            return $default;
        }

        $fh = fopen($path, 'r');
        if (!$fh) {
            return $default;
        }

        flock($fh, LOCK_SH);
        $content = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        if ($content === '' || $content === false) {
            return $default;
        }

        $data = json_decode($content, true);
        return $data === null ? $default : $data;
    }

    public static function write(string $path, mixed $data): void
    {
        self::ensureDir($path);

        $fh = fopen($path, 'c+');
        if (!$fh) {
            throw new \RuntimeException("Cannot open $path for writing");
        }

        flock($fh, LOCK_EX);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    /**
     * Atomically read-modify-write a JSON file under an exclusive lock,
     * so concurrent requests (e.g. usage updates) don't clobber each other.
     */
    public static function update(string $path, callable $mutator, mixed $default = []): mixed
    {
        self::ensureDir($path);

        $fh = fopen($path, 'c+');
        if (!$fh) {
            throw new \RuntimeException("Cannot open $path for writing");
        }

        flock($fh, LOCK_EX);
        $content = stream_get_contents($fh);
        $data = ($content !== '' && $content !== false) ? (json_decode($content, true) ?? $default) : $default;

        $data = $mutator($data);

        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        return $data;
    }

    private static function ensureDir(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
