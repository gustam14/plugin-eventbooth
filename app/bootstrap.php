<?php
declare(strict_types=1);

/**
 * Bootstrap aplikasi: konstanta path, autoloader kelas sederhana,
 * pemuatan .env, zona waktu, dan helper global.
 */

define('BASE_PATH', dirname(__DIR__));

// Autoloader minimal: kelas App di app/<NamaKelas>.php
spl_autoload_register(static function (string $class): void {
    $file = BASE_PATH . '/app/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Konfigurasi dari .env di root proyek (di luar folder public/).
Env::load(BASE_PATH . '/.env');

// Zona waktu platform; bisa diganti lewat APP_TIMEZONE di .env.
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Makassar') ?? 'Asia/Makassar');

if (!function_exists('e')) {
    /** Escape HTML untuk output di template. */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    /** Alihkan browser ke path lain, lalu hentikan eksekusi. */
    function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}
