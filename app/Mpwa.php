<?php
declare(strict_types=1);

/**
 * MPWA — gateway WhatsApp milik platform (Milestone 6, v0.8.2).
 *
 * Dipakai untuk notifikasi pembayaran lunas ke tenant, dikirim
 * dari webhook Midtrans tepat setelah lisensi dipenuhi. Berbeda
 * dari pengingat kedaluwarsa (ditarik workflow n8n), notifikasi
 * ini harus tiba seketika, jadi website mengirim langsung:
 * API key MPWA disimpan terenkripsi di `app_settings` dari
 * halaman /admin/payments, nomor pengirim memakai pengaturan
 * `mpwa_sender` yang sama dengan pengingat. Semua pengiriman
 * bersifat best-effort — kegagalan gateway tidak pernah boleh
 * menggagalkan pencatatan pembayaran.
 */
final class Mpwa
{
    public const SETTING_API_KEY = 'mpwa_api_key';
    public const GATEWAY_BASE = 'https://wa.eventbooth.id';

    /** API key MPWA terdekripsi, atau null bila belum diatur/rusak. */
    public static function apiKey(PDO $pdo): ?string
    {
        $stored = Settings::get($pdo, self::SETTING_API_KEY);
        if ($stored === null || $stored === '') {
            return null;
        }
        $plain = Crypto::decrypt($stored);

        return $plain !== null && $plain !== '' ? $plain : null;
    }

    /** Simpan API key (terenkripsi). @throws MidtransException Bila APP_KEY belum siap. */
    public static function saveApiKey(PDO $pdo, string $plain, ?int $updatedBy): void
    {
        $encrypted = Crypto::encrypt(trim($plain));
        if ($encrypted === null) {
            throw new MidtransException('API key tidak dapat dienkripsi — APP_KEY server belum diisi.');
        }
        Settings::set($pdo, self::SETTING_API_KEY, $encrypted, true, $updatedBy);
    }

    /**
     * Kirim satu pesan teks lewat MPWA. Mengembalikan true hanya
     * bila gateway menyatakan terkirim.
     */
    public static function sendText(PDO $pdo, string $number, string $message): bool
    {
        $apiKey = self::apiKey($pdo);
        $sender = Whatsapp::normalize(Settings::get($pdo, 'mpwa_sender'));
        $target = Whatsapp::normalize($number);
        if ($apiKey === null || $sender === null || $target === null) {
            return false;
        }
        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init(self::GATEWAY_BASE . '/send-message');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $target,
                'message' => $message,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw)) {
            return false;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) && ($decoded['status'] ?? false) === true;
    }

    /**
     * Notifikasi pembayaran lunas ke tenant (dipanggil webhook
     * setelah lisensi dipenuhi). Best-effort: tidak pernah melempar.
     *
     * @param array<string, mixed> $payment Baris payments.
     * @param array<string, mixed>|null $tenant Baris tenant penerima.
     * @param array<string, mixed>|null $plan Plan yang dibeli.
     * @param array<string, mixed>|null $license Lisensi sesudah dipenuhi.
     */
    public static function notifyPaymentSuccess(PDO $pdo, array $payment, ?array $tenant, ?array $plan, ?array $license): bool
    {
        try {
            if ($tenant === null) {
                return false;
            }
            $number = (string) (($tenant['whatsapp'] ?? '') !== '' ? $tenant['whatsapp'] : ($tenant['phone'] ?? ''));
            if (trim($number) === '') {
                return false;
            }
            $vendor = trim((string) ($tenant['vendor_name'] ?? '')) !== ''
                ? (string) $tenant['vendor_name']
                : (string) ($tenant['name'] ?? 'Tenant');
            $planName = (string) ($plan['name'] ?? 'Lisensi');
            $amount = 'Rp ' . number_format((float) ($payment['gross_amount'] ?? 0), 0, ',', '.');
            $expiry = $license !== null && !empty($license['expires_at'])
                ? License::formatDateId((string) $license['expires_at'])
                : 'tanpa kedaluwarsa';

            $message = 'Halo *' . $vendor . "*,\n\n"
                . "Pembayaran lisensi Plugin Eventbooth Anda telah kami terima. Terima kasih!\n\n"
                . "*Detail Pembayaran*\n"
                . 'Plan: ' . $planName . "\n"
                . 'Jumlah: ' . $amount . "\n"
                . 'Order ID: ' . (string) ($payment['order_id'] ?? '') . "\n"
                . "Status: Lunas\n\n"
                . 'Masa aktif lisensi Anda kini berlaku sampai *' . $expiry . "*.\n\n"
                . "Salam,\n*Tim EventBooth*";

            return self::sendText($pdo, $number, $message);
        } catch (Throwable) {
            return false;
        }
    }
}
