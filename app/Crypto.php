<?php
declare(strict_types=1);

/**
 * Enkripsi simetris untuk rahasia yang disimpan di database
 * (refresh token Google Drive, Client Secret OAuth, Server Key
 * Midtrans) — Milestone 3.
 *
 * Algoritma: AES-256-GCM (authenticated encryption). Kunci diturunkan
 * dari APP_KEY di .env dengan SHA-256, sesuai format yang disepakati:
 * hasil enkripsi = base64( iv | tag | ciphertext ).
 *
 * APP_KEY dianggap "belum diatur" bila kosong atau masih berisi
 * string contoh dari .env.example. Tanpa APP_KEY, encrypt()
 * mengembalikan null dan isConfigured() false — pemanggil WAJIB
 * memeriksa isConfigured() sebelum menyimpan rahasia apa pun.
 *
 * PERINGATAN: mengganti APP_KEY setelah data terenkripsi tersimpan
 * membuat data lama tidak bisa didekripsi lagi (decrypt() akan
 * mengembalikan null, bukan exception).
 */
final class Crypto
{
    /** String contoh pada .env.example — bukan kunci sungguhan. */
    private const PLACEHOLDER = 'ganti-dengan-string-acak-panjang';

    private const IV_LENGTH = 12;   // Ukuran nonce baku GCM.
    private const TAG_LENGTH = 16;  // Ukuran tag autentikasi GCM.

    /** True bila APP_KEY di .env terisi nilai sungguhan. */
    public static function isConfigured(): bool
    {
        return self::key() !== null;
    }

    /**
     * Enkripsi teks; hasil base64(iv | tag | ciphertext).
     * Mengembalikan null bila APP_KEY belum diatur atau enkripsi gagal.
     */
    public static function encrypt(string $plain): ?string
    {
        $key = self::key();
        if ($key === null) {
            return null;
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );
        if ($cipher === false) {
            return null;
        }

        return base64_encode($iv . $tag . $cipher);
    }

    /**
     * Dekripsi hasil encrypt(). Mengembalikan null pada kegagalan apa
     * pun (format rusak, kunci salah/berubah, data terkorupsi) —
     * tidak pernah melempar exception ke pemanggil.
     */
    public static function decrypt(string $payload): ?string
    {
        $key = self::key();
        if ($key === null) {
            return null;
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $cipher = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plain === false ? null : $plain;
    }

    /** Kunci 32-byte dari APP_KEY, atau null bila belum diatur. */
    private static function key(): ?string
    {
        $raw = Config::appKey();
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '' || $raw === self::PLACEHOLDER) {
            return null;
        }

        return hash('sha256', $raw, true);
    }
}
