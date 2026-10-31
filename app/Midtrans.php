<?php
declare(strict_types=1);

/**
 * Midtrans platform (Milestone 6) — checkout lisensi oleh tenant.
 *
 * Kredensial milik Super Admin disimpan di `app_settings` dari
 * halaman /admin/payments: Client Key teks biasa, Server Key
 * terenkripsi Crypto (APP_KEY). Server Key TIDAK PERNAH dikirim ke
 * peramban; yang keluar ke halaman bayar hanya Client Key + token
 * Snap per pesanan. Status pesanan hanya boleh dianggap lunas dari
 * notifikasi server yang tanda tangannya terverifikasi
 * (verifySignature), bukan dari callback JavaScript Snap.
 */
final class Midtrans
{
    public const SETTING_ENVIRONMENT = 'midtrans_environment';
    public const SETTING_CLIENT_KEY = 'midtrans_client_key';
    public const SETTING_SERVER_KEY = 'midtrans_server_key';

    /**
     * Konfigurasi efektif platform.
     *
     * @return array{environment: string, client_key: ?string, server_key: ?string, configured: bool}
     */
    public static function config(PDO $pdo): array
    {
        $environment = Settings::get($pdo, self::SETTING_ENVIRONMENT) === 'production'
            ? 'production'
            : 'sandbox';
        $clientKey = Settings::get($pdo, self::SETTING_CLIENT_KEY);
        $serverKeyEnc = Settings::get($pdo, self::SETTING_SERVER_KEY);
        $serverKey = $serverKeyEnc !== null && $serverKeyEnc !== ''
            ? Crypto::decrypt($serverKeyEnc)
            : null;

        return [
            'environment' => $environment,
            'client_key' => $clientKey !== null && $clientKey !== '' ? $clientKey : null,
            'server_key' => $serverKey !== null && $serverKey !== '' ? $serverKey : null,
            'configured' => $clientKey !== null && $clientKey !== ''
                && $serverKey !== null && $serverKey !== '',
        ];
    }

    /**
     * Simpan pengaturan dari dashboard Super Admin. Server Key hanya
     * diganti bila $serverKeyPlain diisi; mengosongkannya berarti
     * mempertahankan yang lama.
     *
     * @throws MidtransException Bila enkripsi gagal (APP_KEY kosong).
     */
    public static function saveSettings(
        PDO $pdo,
        string $environment,
        string $clientKey,
        ?string $serverKeyPlain,
        ?int $updatedBy
    ): void {
        Settings::set(
            $pdo,
            self::SETTING_ENVIRONMENT,
            $environment === 'production' ? 'production' : 'sandbox',
            false,
            $updatedBy
        );
        Settings::set($pdo, self::SETTING_CLIENT_KEY, trim($clientKey), false, $updatedBy);

        if ($serverKeyPlain !== null && trim($serverKeyPlain) !== '') {
            $encrypted = Crypto::encrypt(trim($serverKeyPlain));
            if ($encrypted === null) {
                throw new MidtransException(
                    'Server Key tidak dapat dienkripsi — APP_KEY server belum diisi.'
                );
            }
            Settings::set($pdo, self::SETTING_SERVER_KEY, $encrypted, true, $updatedBy);
        }
    }

    /**
     * URL notifikasi webhook aplikasi ini (dari APP_URL) — dikirim
     * sebagai X-Override-Notification pada setiap pembuatan
     * transaksi Snap dan ditampilkan di halaman admin sebagai
     * referensi.
     */
    public static function notifyUrl(): string
    {
        return Config::appUrl() . '/api/payments/midtrans/notify';
    }

    /** URL berkas Snap.js sesuai environment. */
    public static function snapJsUrl(string $environment): string
    {
        return $environment === 'production'
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    /** Basis API Snap sesuai environment. */
    private static function apiBase(string $environment): string
    {
        return $environment === 'production'
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }

    /**
     * Minta token Snap untuk satu pesanan lisensi.
     *
     * @param array<string, mixed> $payment Baris payments (order_id, gross_amount).
     * @param array<string, mixed> $plan    Baris plan yang dibeli.
     * @param array<string, mixed> $tenant  Baris tenant pembeli.
     * @return array{token: string, redirect_url: ?string}
     * @throws MidtransException
     */
    public static function createSnapToken(PDO $pdo, array $payment, array $plan, array $tenant): array
    {
        $config = self::config($pdo);
        if (!$config['configured']) {
            throw new MidtransException('Midtrans platform belum dikonfigurasi Super Admin.');
        }

        $amount = (int) round((float) $payment['gross_amount']);
        $payload = [
            'transaction_details' => [
                'order_id' => (string) $payment['order_id'],
                'gross_amount' => $amount,
            ],
            'item_details' => [[
                'id' => 'PLAN-' . (int) ($plan['id'] ?? 0),
                'price' => $amount,
                'quantity' => 1,
                'name' => mb_substr('Lisensi ' . (string) ($plan['name'] ?? 'Plugin Eventbooth'), 0, 50),
            ]],
            'customer_details' => [
                'first_name' => mb_substr(
                    (string) (($tenant['vendor_name'] ?? '') !== '' ? $tenant['vendor_name'] : ($tenant['name'] ?? 'Tenant')),
                    0,
                    50
                ),
                'email' => (string) ($tenant['email'] ?? ''),
                'phone' => (string) (($tenant['whatsapp'] ?? '') !== '' ? $tenant['whatsapp'] : ($tenant['phone'] ?? '')),
            ],
        ];

        $url = self::apiBase($config['environment']) . '/snap/v1/transactions';
        // Akun Midtrans platform dipakai bersama website utama
        // pemilik, yang URL notifikasi dashboardnya menunjuk ke
        // sistem lain. Header X-Override-Notification mengganti
        // URL notifikasi dashboard KHUSUS untuk transaksi ini,
        // jadi notifikasi checkout lisensi selalu tiba di sini
        // tanpa mengubah pengaturan dashboard Midtrans sama sekali.
        $response = self::httpJson('POST', $url, $payload, (string) $config['server_key'], [
            'X-Override-Notification: ' . self::notifyUrl(),
        ]);

        $token = (string) ($response['token'] ?? '');
        if ($token === '') {
            $messages = $response['error_messages'] ?? $response['status_message'] ?? 'Respons Midtrans tidak memuat token.';
            throw new MidtransException(is_array($messages) ? implode('; ', $messages) : (string) $messages);
        }

        return [
            'token' => $token,
            'redirect_url' => isset($response['redirect_url']) ? (string) $response['redirect_url'] : null,
        ];
    }

    /**
     * Verifikasi signature_key notifikasi: SHA-512 dari
     * order_id + status_code + gross_amount + Server Key, memakai
     * nilai mentah persis seperti yang dikirim Midtrans.
     *
     * @param array<string, mixed> $payload Payload notifikasi ter-decode.
     */
    public static function verifySignature(array $payload, string $serverKey): bool
    {
        $signature = (string) ($payload['signature_key'] ?? '');
        if ($signature === '') {
            return false;
        }
        $expected = hash(
            'sha512',
            (string) ($payload['order_id'] ?? '')
            . (string) ($payload['status_code'] ?? '')
            . (string) ($payload['gross_amount'] ?? '')
            . $serverKey
        );

        return hash_equals(strtolower($expected), strtolower($signature));
    }

    /**
     * Petakan notifikasi Midtrans ke status enum tabel payments.
     *
     * @param array<string, mixed> $payload
     */
    public static function statusFromNotification(array $payload): string
    {
        $transaction = (string) ($payload['transaction_status'] ?? '');
        $fraud = (string) ($payload['fraud_status'] ?? 'accept');

        return match ($transaction) {
            'settlement' => 'paid',
            'capture' => $fraud === 'challenge' ? 'challenge' : ($fraud === 'deny' ? 'denied' : 'paid'),
            'pending' => 'pending',
            'deny' => 'denied',
            'cancel' => 'cancelled',
            'expire' => 'expired',
            'refund', 'partial_refund' => 'refunded',
            'failure' => 'failed',
            default => 'pending',
        };
    }

    /** Label status pembayaran untuk tampilan dashboard. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Lunas',
            'pending' => 'Menunggu Pembayaran',
            'challenge' => 'Perlu Verifikasi',
            'denied' => 'Ditolak',
            'expired' => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan',
            'failed' => 'Gagal',
            'refunded' => 'Dikembalikan',
            default => $status,
        };
    }

    /**
     * HTTP JSON ke API Midtrans dengan Basic Auth Server Key.
     *
     * @param array<string, mixed> $payload
     * @param array<int, string> $extraHeaders Header tambahan (mis. X-Override-Notification).
     * @return array<string, mixed>
     * @throws MidtransException
     */
    private static function httpJson(string $method, string $url, array $payload, string $serverKey, array $extraHeaders = []): array
    {
        if (!function_exists('curl_init')) {
            throw new MidtransException('Ekstensi cURL tidak tersedia di server.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($serverKey . ':'),
            ], $extraHeaders),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new MidtransException('Gagal menghubungi Midtrans: ' . $error);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new MidtransException('Respons Midtrans tidak valid (HTTP ' . $code . ').');
        }
        if ($code >= 400) {
            $messages = $decoded['error_messages'] ?? $decoded['status_message'] ?? ('HTTP ' . $code);
            throw new MidtransException(
                'Midtrans menolak permintaan: ' . (is_array($messages) ? implode('; ', $messages) : (string) $messages)
            );
        }

        return $decoded;
    }
}
