<?php
declare(strict_types=1);

/**
 * PDO factory.
 *
 * Mengembalikan null secara anggun (graceful) bila database belum
 * dikonfigurasi atau koneksi gagal, supaya halaman publik dan
 * /api/health tetap bisa tampil di hosting yang belum disetel.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private static bool $attempted = false;

    /** Pesan error terakhir — hanya untuk log internal, JANGAN ditampilkan ke publik. */
    private static ?string $lastError = null;

    public static function pdo(): ?PDO
    {
        if (self::$attempted) {
            return self::$pdo;
        }
        self::$attempted = true;

        if (!Config::dbConfigured()) {
            return null;
        }

        $db = Config::db();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $db['host'],
            $db['port'],
            $db['name']
        );

        try {
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            self::$pdo = null;
        }

        return self::$pdo;
    }

    /**
     * Status database untuk /api/health — hanya string status,
     * tidak pernah memuat kredensial atau pesan error mentah.
     *
     * @return string 'not-configured' | 'configured-ok' | 'configured-error'
     */
    public static function healthStatus(): string
    {
        if (!Config::dbConfigured()) {
            return 'not-configured';
        }

        $pdo = self::pdo();
        if ($pdo === null) {
            return 'configured-error';
        }

        try {
            $pdo->query('SELECT 1');
            return 'configured-ok';
        } catch (PDOException) {
            return 'configured-error';
        }
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }
}
