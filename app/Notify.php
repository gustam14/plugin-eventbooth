<?php
declare(strict_types=1);

/**
 * Notifikasi WhatsApp tenant (v0.33.0, diminta pemilik):
 * pengaturan per tenant untuk (1) pesan berbagi galeri yang
 * dikirim aplikasi booth ke tamu/klien, (2) notifikasi
 * konfirmasi booking ke klien, (3) notifikasi penugasan ke
 * kru, (4) form booking publik yang otomatis mengisi Buku
 * Klien + Event, plus pemberitahuan ke pemilik usaha saat
 * form booking masuk.
 *
 * Semua pengiriman dijembatani MPWA platform
 * (Mpwa::sendText — API key & nomor pengirim diatur Super
 * Admin) dan bersifat best-effort: kegagalan gateway atau
 * tabel yang belum dimigrasi tidak pernah menggagalkan
 * alur utama.
 */
final class Notify
{
    public const DEFAULT_SHARE = "Halo! Foto sesi {kode_sesi} di event {nama_event} sudah dapat dilihat & diunduh di sini:\n{tautan}\n\nTerima kasih sudah berfoto bersama {nama_vendor}!";
    public const DEFAULT_CLIENT = "Halo {nama_klien}, terima kasih sudah booking di *{nama_vendor}*!\n\nBooking Anda sudah kami catat:\nEvent: {nama_event}\nTanggal: {tanggal}\nPaket: {nama_paket}\nSisa pembayaran: {sisa}\n\nKami akan menghubungimu lagi menjelang hari acara. Terima kasih!";
    public const DEFAULT_CREW = "Halo {nama_kru}, kamu ditugaskan sebagai kru pada event *{nama_event}*.\n\nTanggal: {tanggal}\nLokasi: {venue}\n\nMohon konfirmasi kesiapanmu ke {nama_vendor}. Terima kasih!";

    /** Pengaturan bawaan bila tenant belum punya baris pengaturan. */
    public static function defaults(): array
    {
        return [
            'share_enabled' => true,
            'share_template' => self::DEFAULT_SHARE,
            'client_enabled' => false,
            'client_template' => self::DEFAULT_CLIENT,
            'crew_enabled' => false,
            'crew_template' => self::DEFAULT_CREW,
            'booking_form_enabled' => false,
            'owner_notify_enabled' => true,
        ];
    }

    /**
     * Pengaturan tenant (template kosong diganti bawaan).
     * Toleran tabel belum dimigrasi: mengembalikan bawaan.
     *
     * @return array<string, mixed>
     */
    public static function settings(PDO $pdo, int $tenantId): array
    {
        $settings = self::defaults();
        try {
            $stmt = $pdo->prepare('SELECT * FROM tenant_notifications WHERE tenant_id = :id LIMIT 1');
            $stmt->execute(['id' => $tenantId]);
            $row = $stmt->fetch();
        } catch (PDOException) {
            return $settings;
        }
        if ($row === false) {
            return $settings;
        }
        $settings['share_enabled'] = (int) ($row['share_enabled'] ?? 1) === 1;
        $settings['client_enabled'] = (int) ($row['client_enabled'] ?? 0) === 1;
        $settings['crew_enabled'] = (int) ($row['crew_enabled'] ?? 0) === 1;
        $settings['booking_form_enabled'] = (int) ($row['booking_form_enabled'] ?? 0) === 1;
        $settings['owner_notify_enabled'] = (int) ($row['owner_notify_enabled'] ?? 1) === 1;
        foreach (['share_template', 'client_template', 'crew_template'] as $field) {
            $stored = isset($row[$field]) ? trim((string) $row[$field]) : '';
            if ($stored !== '') {
                $settings[$field] = $stored;
            }
        }

        return $settings;
    }

    /**
     * Validasi isian formulir pengaturan.
     *
     * @param array<string, mixed> $input Isi $_POST.
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function validateSettings(array $input): array
    {
        $errors = [];
        $data = [
            'share_enabled' => isset($input['share_enabled']),
            'client_enabled' => isset($input['client_enabled']),
            'crew_enabled' => isset($input['crew_enabled']),
            'booking_form_enabled' => isset($input['booking_form_enabled']),
            'owner_notify_enabled' => isset($input['owner_notify_enabled']),
        ];
        $defaults = [
            'share_template' => self::DEFAULT_SHARE,
            'client_template' => self::DEFAULT_CLIENT,
            'crew_template' => self::DEFAULT_CREW,
        ];
        foreach ($defaults as $field => $default) {
            $template = trim((string) ($input[$field] ?? ''));
            if ($template === '' || $template === $default) {
                $data[$field] = null;
            } elseif (self::length($template) > 2000) {
                $errors[$field] = 'Template maksimal 2.000 karakter.';
                $data[$field] = null;
            } else {
                $data[$field] = $template;
            }
        }

        return [$data, $errors];
    }

    /** @param array<string, mixed> $data Hasil validateSettings(). */
    public static function save(PDO $pdo, int $tenantId, array $data): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO tenant_notifications
                (tenant_id, share_enabled, share_template, client_enabled, client_template,
                 crew_enabled, crew_template, booking_form_enabled, owner_notify_enabled)
             VALUES
                (:tenant_id, :share_enabled, :share_template, :client_enabled, :client_template,
                 :crew_enabled, :crew_template, :booking_form_enabled, :owner_notify_enabled)
             ON DUPLICATE KEY UPDATE
                share_enabled = VALUES(share_enabled),
                share_template = VALUES(share_template),
                client_enabled = VALUES(client_enabled),
                client_template = VALUES(client_template),
                crew_enabled = VALUES(crew_enabled),
                crew_template = VALUES(crew_template),
                booking_form_enabled = VALUES(booking_form_enabled),
                owner_notify_enabled = VALUES(owner_notify_enabled)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'share_enabled' => !empty($data['share_enabled']) ? 1 : 0,
            'share_template' => $data['share_template'],
            'client_enabled' => !empty($data['client_enabled']) ? 1 : 0,
            'client_template' => $data['client_template'],
            'crew_enabled' => !empty($data['crew_enabled']) ? 1 : 0,
            'crew_template' => $data['crew_template'],
            'booking_form_enabled' => !empty($data['booking_form_enabled']) ? 1 : 0,
            'owner_notify_enabled' => !empty($data['owner_notify_enabled']) ? 1 : 0,
        ]);
    }

    /** Isi placeholder {kunci} pada template. */
    public static function render(string $template, array $vars): string
    {
        $map = [];
        foreach ($vars as $key => $value) {
            $map['{' . $key . '}'] = (string) $value;
        }

        return strtr($template, $map);
    }

    /** Apakah gateway MPWA platform siap (API key + pengirim diatur Super Admin). */
    public static function gatewayReady(PDO $pdo): bool
    {
        try {
            return Mpwa::apiKey($pdo) !== null
                && Whatsapp::normalize(Settings::get($pdo, 'mpwa_sender')) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /* -----------------------------------------------------------------
     * Pengiriman-pengiriman
     * --------------------------------------------------------------- */

    /** WA konfirmasi booking ke klien (saat event untuknya dibuat). */
    public static function notifyClientBooking(PDO $pdo, int $tenantId, int $eventId): bool
    {
        try {
            $settings = self::settings($pdo, $tenantId);
            if (empty($settings['client_enabled'])) {
                return false;
            }
            $tenant = Tenant::find($pdo, $tenantId);
            $event = Event::findForTenant($pdo, $tenantId, $eventId);
            if ($tenant === null || $event === null || empty($event['client_id'])) {
                return false;
            }
            $client = ClientBook::findForTenant($pdo, $tenantId, (int) $event['client_id']);
            if ($client === null || empty($client['whatsapp'])) {
                return false;
            }
            $remaining = Event::clientRemaining($event);
            $message = self::render((string) $settings['client_template'], [
                'nama_klien' => (string) $client['client_name'],
                'nama_event' => (string) $event['name'],
                'tanggal' => !empty($event['starts_at']) ? License::formatDateId((string) $event['starts_at']) : '—',
                'nama_paket' => (string) ($event['client_package_name'] ?? '—'),
                'sisa' => $remaining === null ? '—' : ($remaining > 0 ? Package::formatPrice($remaining) : 'Lunas'),
                'nama_vendor' => Tenant::displayName($tenant),
            ]);

            return Mpwa::sendText($pdo, (string) $client['whatsapp'], $message);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * WA penugasan ke kru yang BARU ditugaskan pada event.
     *
     * @param array<string, mixed> $event Baris event.
     * @param array<int, array<string, mixed>> $members Baris crew_members yang baru ditugaskan.
     * @return int Jumlah pesan terkirim.
     */
    public static function notifyCrewAssigned(PDO $pdo, int $tenantId, array $event, array $members): int
    {
        try {
            $settings = self::settings($pdo, $tenantId);
            if (empty($settings['crew_enabled']) || $members === []) {
                return 0;
            }
            $tenant = Tenant::find($pdo, $tenantId);
            if ($tenant === null) {
                return 0;
            }
            $sent = 0;
            foreach ($members as $member) {
                if (empty($member['phone'])) {
                    continue;
                }
                $message = self::render((string) $settings['crew_template'], [
                    'nama_kru' => (string) $member['name'],
                    'nama_event' => (string) $event['name'],
                    'tanggal' => !empty($event['starts_at']) ? License::formatDateId((string) $event['starts_at']) : '—',
                    'venue' => (string) ($event['venue'] ?? '—'),
                    'nama_vendor' => Tenant::displayName($tenant),
                ]);
                if (Mpwa::sendText($pdo, (string) $member['phone'], $message)) {
                    $sent++;
                }
            }

            return $sent;
        } catch (Throwable) {
            return 0;
        }
    }

    /** WA ke pemilik usaha saat form booking publik diisi calon klien. */
    public static function notifyOwnerNewBooking(PDO $pdo, int $tenantId, array $request): bool
    {
        try {
            $settings = self::settings($pdo, $tenantId);
            if (empty($settings['owner_notify_enabled'])) {
                return false;
            }
            $tenant = Tenant::find($pdo, $tenantId);
            if ($tenant === null) {
                return false;
            }
            $number = Tenant::whatsappOf($tenant);
            if ($number === null) {
                return false;
            }
            $message = "*Booking baru masuk* lewat form booking " . Tenant::displayName($tenant) . ":\n\n"
                . 'Nama: ' . (string) ($request['name'] ?? '—') . "\n"
                . 'WhatsApp: ' . (string) ($request['whatsapp'] ?? '—') . "\n"
                . 'Tanggal acara: ' . (!empty($request['event_date']) ? License::formatDateId((string) $request['event_date']) : '—') . "\n"
                . 'Paket: ' . (string) ($request['package_name'] ?? '—') . "\n\n"
                . 'Klien & event drafnya sudah dibuat otomatis di dashboard (Buku Klien & Event). Silakan tindak lanjuti.';

            return Mpwa::sendText($pdo, $number, $message);
        } catch (Throwable) {
            return false;
        }
    }

    /** Pesan pengingat sisa pembayaran ke klien (kirim manual dari menu Notifikasi). */
    public static function reminderMessage(array $tenant, array $event, array $client): string
    {
        $remaining = Event::clientRemaining($event);
        $lines = [
            'Halo ' . (string) $client['client_name'] . ', ini pengingat ramah dari *' . Tenant::displayName($tenant) . '*.',
            '',
            'Untuk booking event *' . (string) $event['name'] . '*'
                . (!empty($event['starts_at']) ? ' pada ' . License::formatDateId((string) $event['starts_at']) : '')
                . (!empty($event['client_package_name']) ? ' (paket ' . (string) $event['client_package_name'] . ')' : '') . ',',
        ];
        if ($remaining === null) {
            $lines[] = 'mohon konfirmasi kesiapan Anda menjelang hari acara. Terima kasih!';
        } elseif ($remaining > 0) {
            $lines[] = 'sisa pembayaran saat ini sebesar *' . Package::formatPrice($remaining) . '*. '
                . 'Mohon dilunasi sebelum hari acara ya. Terima kasih!';
        } else {
            $lines[] = 'pembayaran Anda sudah *lunas*. Terima kasih, sampai jumpa di hari acara!';
        }

        return implode("\n", $lines);
    }

    /** Pesan tautan album ke klien (kirim manual dari menu Notifikasi). */
    public static function albumMessage(array $tenant, array $event): string
    {
        $url = Album::galleryUrl(Config::appUrl(), isset($tenant['slug']) ? (string) $tenant['slug'] : null, (string) $event['slug']);

        return 'Halo! Album foto event *' . (string) $event['name'] . '* sudah bisa dilihat & diunduh di sini:' . "\n"
            . $url . "\n\nTerima kasih sudah memakai jasa " . Tenant::displayName($tenant) . '!';
    }

    /* -----------------------------------------------------------------
     * Form booking publik
     * --------------------------------------------------------------- */

    /** Catat satu permintaan booking dari form publik (toleran tabel belum ada). */
    public static function logRequest(PDO $pdo, int $tenantId, array $data): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO booking_requests
                    (tenant_id, client_id, event_id, name, whatsapp, address, event_date, package_name, notes)
                 VALUES
                    (:tenant_id, :client_id, :event_id, :name, :whatsapp, :address, :event_date, :package_name, :notes)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'client_id' => $data['client_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'name' => (string) ($data['name'] ?? ''),
                'whatsapp' => $data['whatsapp'] ?? null,
                'address' => $data['address'] ?? null,
                'event_date' => $data['event_date'] ?? null,
                'package_name' => $data['package_name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (PDOException) {
            // Log bersifat tambahan; abaikan kegagalan.
        }
    }

    /**
     * Permintaan booking terakhir tenant (untuk menu Notifikasi).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function requests(PDO $pdo, int $tenantId, int $limit = 20): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM booking_requests WHERE tenant_id = :id ORDER BY id DESC LIMIT ' . max(1, $limit)
            );
            $stmt->execute(['id' => $tenantId]);
            $rows = $stmt->fetchAll();

            return is_array($rows) ? $rows : [];
        } catch (PDOException) {
            return [];
        }
    }

    /** Apakah nomor WA ini sudah mengirim form dalam beberapa menit terakhir (anti-duplikat). */
    public static function recentDuplicate(PDO $pdo, int $tenantId, string $normalizedWa, ?string $eventDate): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM booking_requests
                  WHERE tenant_id = :id AND whatsapp = :wa
                    AND created_at >= (NOW() - INTERVAL 10 MINUTE)'
            );
            $stmt->execute(['id' => $tenantId, 'wa' => $normalizedWa]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
