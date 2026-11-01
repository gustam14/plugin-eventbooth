<?php
declare(strict_types=1);

/**
 * Akses konfigurasi aplikasi dari .env (lihat .env.example).
 * Tidak ada rahasia yang di-hardcode di sini.
 */
final class Config
{
    /** Versi aplikasi; naikkan setiap milestone. */
    public static function appVersion(): string
    {
        return '0.20.0';
    }

    /** Nomor milestone blueprint yang sedang berjalan (6 = Checkout Lisensi Midtrans). */
    public static function milestone(): int
    {
        return 6;
    }

    /**
     * APP_KEY mentah dari .env — bahan kunci enkripsi Crypto untuk
     * rahasia yang disimpan di database (refresh token Drive, dst).
     * Nilai ini sendiri TIDAK pernah ditampilkan ke halaman mana pun.
     */
    public static function appKey(): ?string
    {
        return Env::get('APP_KEY');
    }

    /**
     * Redirect URI OAuth Google yang didaftarkan di Google Cloud
     * Console. Kanonis: {APP_URL}/auth/google/callback — di produksi
     * https://plugin.eventbooth.id/auth/google/callback.
     * GOOGLE_REDIRECT_URI di .env menang bila diisi.
     */
    public static function googleRedirectUri(): string
    {
        $fromEnv = Env::get('GOOGLE_REDIRECT_URI');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        return self::appUrl() . '/auth/google/callback';
    }

    public static function appName(): string
    {
        return Env::get('APP_NAME', 'Plugin Eventbooth') ?? 'Plugin Eventbooth';
    }

    public static function appUrl(): string
    {
        return rtrim(Env::get('APP_URL', 'https://plugin.eventbooth.id') ?? 'https://plugin.eventbooth.id', '/');
    }

    public static function appEnv(): string
    {
        return Env::get('APP_ENV', 'production') ?? 'production';
    }

    public static function debug(): bool
    {
        return filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{host: string, port: string, name: string, user: string, pass: string} */
    public static function db(): array
    {
        return [
            'host' => Env::get('DB_HOST', 'localhost') ?? 'localhost',
            'port' => Env::get('DB_PORT', '3306') ?? '3306',
            'name' => Env::get('DB_DATABASE', '') ?? '',
            'user' => Env::get('DB_USERNAME', '') ?? '',
            'pass' => Env::get('DB_PASSWORD', '') ?? '',
        ];
    }

    /**
     * Database dianggap "terkonfigurasi" bila .env memuat host, nama
     * database, dan username. Password boleh kosong (umum di lokal).
     */
    public static function dbConfigured(): bool
    {
        return Env::has('DB_HOST') && Env::has('DB_DATABASE') && Env::has('DB_USERNAME');
    }
}
