<?php
declare(strict_types=1);

/**
 * Renderer view sederhana: template PHP biasa di app/views/.
 * Variabel dari $data diekstrak menjadi variabel lokal di template.
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], int $status = 200): void
    {
        $file = BASE_PATH . '/app/views/' . $template . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'View tidak ditemukan: ' . $template;
            return;
        }

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');

        extract($data, EXTR_SKIP);
        require $file;
    }
}
