<?php
declare(strict_types=1);

/**
 * Pesan kilat (flash message) + isian formulir lama, disimpan di
 * session: pesan yang dipasang pada satu request tampil sekali pada
 * request berikutnya (pola post/redirect/get), lalu hilang.
 */
final class Flash
{
    private const MESSAGE_KEY = '_flash_messages';
    private const INPUT_KEY = '_flash_input';

    public static function add(string $type, string $message): void
    {
        Auth::start();

        $list = $_SESSION[self::MESSAGE_KEY] ?? [];
        if (!is_array($list)) {
            $list = [];
        }
        $list[] = ['type' => $type, 'message' => $message];
        $_SESSION[self::MESSAGE_KEY] = $list;
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    /**
     * Ambil semua pesan kilat lalu kosongkan (tampil sekali saja).
     *
     * @return array<int, array{type: string, message: string}>
     */
    public static function pull(): array
    {
        Auth::start();

        $list = $_SESSION[self::MESSAGE_KEY] ?? [];
        unset($_SESSION[self::MESSAGE_KEY]);

        return is_array($list) ? $list : [];
    }

    /**
     * Simpan isian formulir agar bisa ditampilkan lagi setelah
     * redirect (mis. email pada formulir login yang gagal).
     * Kata sandi dan token CSRF tidak pernah disimpan.
     *
     * @param array<string, mixed> $input
     */
    public static function keepInput(array $input): void
    {
        Auth::start();

        $safe = [];
        foreach ($input as $key => $value) {
            if ($key === 'password' || $key === '_csrf') {
                continue;
            }
            if (is_string($value)) {
                $safe[$key] = $value;
            }
        }

        $_SESSION[self::INPUT_KEY] = $safe;
    }

    /**
     * Ambil isian lama lalu kosongkan.
     *
     * @return array<string, string>
     */
    public static function pullInput(): array
    {
        Auth::start();

        $input = $_SESSION[self::INPUT_KEY] ?? [];
        unset($_SESSION[self::INPUT_KEY]);

        return is_array($input) ? $input : [];
    }
}
