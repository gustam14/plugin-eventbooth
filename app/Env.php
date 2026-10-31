<?php
declare(strict_types=1);

/**
 * Parser .env sederhana tanpa composer/framework.
 *
 * Format: satu KEY=value per baris; baris kosong dan baris yang diawali
 * tanda # diabaikan. Nilai boleh dibungkus tanda kutip tunggal/ganda.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$loaded = true;

        if (!is_file($path)) {
            // .env belum dibuat: aplikasi tetap jalan dengan nilai default
            // (dipakai /api/health untuk melaporkan db: not-configured).
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Buang tanda kutip pembungkus bila ada.
            $length = strlen($value);
            if ($length >= 2) {
                $first = $value[0];
                if (($first === '"' || $first === "'") && $value[$length - 1] === $first) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key !== '') {
                self::$values[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    /** True bila kunci ada dan nilainya tidak kosong. */
    public static function has(string $key): bool
    {
        return isset(self::$values[$key]) && self::$values[$key] !== '';
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
