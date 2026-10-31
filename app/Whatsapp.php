<?php
declare(strict_types=1);

/**
 * Normalisasi nomor WhatsApp Indonesia (Milestone 4).
 *
 * Nomor disimpan dalam format internasional tanpa "+" dan tanpa
 * pemisah: hanya digit, diawali kode negara 62 — format yang sama
 * yang dipakai tautan wa.me dan gateway WhatsApp (MPWA).
 *
 * Aturan:
 *   - semua karakter selain digit dibuang ("+62 812-345" -> "62812345")
 *   - awalan "62" dipertahankan
 *   - awalan "0" diganti "62"  ("0812…" -> "62812…")
 *   - awalan "8" diberi "62"   ("812…"  -> "62812…")
 *   - hasil akhir wajib 10–15 digit, selain itu tidak sah (null).
 */
final class Whatsapp
{
    /** Nomor ternormalisasi (digit saja, awalan 62) atau null bila tidak sah. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $normalized = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $normalized = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $normalized = '62' . $digits;
        } else {
            return null;
        }

        $length = strlen($normalized);
        if ($length < 10 || $length > 15) {
            return null;
        }

        return $normalized;
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }

    /** Tautan chat untuk nomor yang SUDAH ternormalisasi. */
    public static function link(string $normalizedDigits): string
    {
        return 'https://wa.me/' . $normalizedDigits;
    }
}
