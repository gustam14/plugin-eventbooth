<?php
declare(strict_types=1);

/**
 * Helper respons JSON untuk seluruh API.
 */
final class Json
{
    /** @param array<string, mixed> $data */
    public static function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 400, string $code = 'error'): void
    {
        self::respond([
            'error' => $code,
            'message' => $message,
        ], $status);
    }

    /** Stub 501 untuk grup API yang dibangun pada milestone berikutnya. */
    public static function notImplemented(string $note = ''): void
    {
        self::respond([
            'error' => 'not_implemented',
            'message' => 'Endpoint ini belum diimplementasikan (baru fondasi Milestone 1).',
            'note' => $note,
        ], 501);
    }
}
