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
 * Semua pengiriman dijembatani MPWA (wa.eventbooth.id)
 * memakai API key & nomor pengirim MILIK TENANT SENDIRI
 * (Mpwa::sendTextForTenant — keputusan pemilik v0.34.0:
 * notifikasi tenant tidak memakai nomor Super Admin) dan
 * bersifat best-effort: kegagalan gateway atau tabel yang
 * belum dimigrasi tidak pernah menggagalkan alur utama.
 */
final class Notify
{
    public const DEFAULT_SHARE = "Halo! Foto sesi {kode_sesi} di event {nama_event} sudah dapat dilihat & diunduh di sini:\n{tautan}\n\nTerima kasih sudah berfoto bersama {nama_vendor}!";
    public const DEFAULT_CLIENT = "Halo {nama_klien}, terima kasih sudah booking di *{nama_vendor}*!\n\nBooking Anda sudah kami catat:\nEvent: {nama_event}\nTanggal: {tanggal}\nPaket: {nama_paket}\nSisa pembayaran: {sisa}\n\nKami akan menghubungimu lagi menjelang hari acara. Terima kasih!";
    public const DEFAULT_CREW = "Halo {nama_kru}, kamu ditugaskan sebagai kru pada event *{nama_event}*.\n\nTanggal: {tanggal}\nLokasi: {venue}\n\nMohon konfirmasi kesiapanmu ke {nama_vendor}. Terima kasih!";
    public const DEFAULT_CREW_H1 = "Halo {nama_kru}, pengingat ramah: *besok* kamu bertugas pada event *{nama_event}*.\n\nTanggal: {tanggal}\nJam mulai: {jam}\nLokasi: {venue}\n\nSiapkan peralatan dari sekarang dan mohon datang lebih awal besok. Terima kasih! — {nama_vendor}";
    public const DEFAULT_CREW_H0 = "Halo {nama_kru}, *hari ini* kamu bertugas pada event *{nama_event}*.\n\nJam mulai: {jam}\nLokasi: {venue}\n\nSemangat! Semoga acara berjalan lancar. — {nama_vendor}";

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
            'crew_reminder_enabled' => false,
            'crew_h1_template' => self::DEFAULT_CREW_H1,
            'crew_h0_template' => self::DEFAULT_CREW_H0,
            'booking_form_enabled' => false,
            'owner_notify_enabled' => true,
            'mpwa_sender' => '',
            'mpwa_api_key_set' => false,
            'wa_booking_enabled' => false,
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
        $settings['crew_reminder_enabled'] = (int) ($row['crew_reminder_enabled'] ?? 0) === 1;
        $settings['wa_booking_enabled'] = (int) ($row['wa_booking_enabled'] ?? 0) === 1;
        $settings['booking_form_enabled'] = (int) ($row['booking_form_enabled'] ?? 0) === 1;
        $settings['owner_notify_enabled'] = (int) ($row['owner_notify_enabled'] ?? 1) === 1;
        $settings['mpwa_sender'] = isset($row['mpwa_sender']) ? (string) $row['mpwa_sender'] : '';
        $settings['mpwa_api_key_set'] = Mpwa::tenantApiKey($pdo, $tenantId) !== null;
        foreach (['share_template', 'client_template', 'crew_template', 'crew_h1_template', 'crew_h0_template'] as $field) {
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
            'crew_reminder_enabled' => isset($input['crew_reminder_enabled']),
            'wa_booking_enabled' => isset($input['wa_booking_enabled']),
            'booking_form_enabled' => isset($input['booking_form_enabled']),
            'owner_notify_enabled' => isset($input['owner_notify_enabled']),
        ];
        // Gateway MPWA milik tenant: nomor pengirim perangkat
        // mereka (API key ditangani terpisah, terenkripsi).
        $senderRaw = trim((string) ($input['mpwa_sender'] ?? ''));
        if ($senderRaw === '') {
            $data['mpwa_sender'] = null;
        } else {
            $normalizedSender = Whatsapp::normalize($senderRaw);
            if ($normalizedSender === null) {
                $errors['mpwa_sender'] = 'Nomor pengirim MPWA tidak sah (gunakan format 08… atau 628…).';
            }
            $data['mpwa_sender'] = $normalizedSender;
        }

        $defaults = [
            'share_template' => self::DEFAULT_SHARE,
            'client_template' => self::DEFAULT_CLIENT,
            'crew_template' => self::DEFAULT_CREW,
            'crew_h1_template' => self::DEFAULT_CREW_H1,
            'crew_h0_template' => self::DEFAULT_CREW_H0,
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
                 crew_enabled, crew_template, booking_form_enabled, owner_notify_enabled, mpwa_sender,
                 crew_reminder_enabled, crew_h1_template, crew_h0_template, wa_booking_enabled)
             VALUES
                (:tenant_id, :share_enabled, :share_template, :client_enabled, :client_template,
                 :crew_enabled, :crew_template, :booking_form_enabled, :owner_notify_enabled, :mpwa_sender,
                 :crew_reminder_enabled, :crew_h1_template, :crew_h0_template, :wa_booking_enabled)
             ON DUPLICATE KEY UPDATE
                share_enabled = VALUES(share_enabled),
                share_template = VALUES(share_template),
                client_enabled = VALUES(client_enabled),
                client_template = VALUES(client_template),
                crew_enabled = VALUES(crew_enabled),
                crew_template = VALUES(crew_template),
                booking_form_enabled = VALUES(booking_form_enabled),
                owner_notify_enabled = VALUES(owner_notify_enabled),
                mpwa_sender = VALUES(mpwa_sender),
                crew_reminder_enabled = VALUES(crew_reminder_enabled),
                crew_h1_template = VALUES(crew_h1_template),
                crew_h0_template = VALUES(crew_h0_template),
                wa_booking_enabled = VALUES(wa_booking_enabled)'
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
            'mpwa_sender' => $data['mpwa_sender'] ?? null,
            'crew_reminder_enabled' => !empty($data['crew_reminder_enabled']) ? 1 : 0,
            'crew_h1_template' => $data['crew_h1_template'] ?? null,
            'crew_h0_template' => $data['crew_h0_template'] ?? null,
            'wa_booking_enabled' => !empty($data['wa_booking_enabled']) ? 1 : 0,
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

    /* -----------------------------------------------------------------
     * Pengiriman-pengiriman (semua lewat gateway MPWA milik
     * tenant sendiri — v0.34.0)
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

            return Mpwa::sendTextForTenant($pdo, $tenantId, (string) $client['whatsapp'], $message);
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
                $message = self::render(
                    (string) $settings['crew_template'],
                    self::crewVars($event, $member, Tenant::displayName($tenant))
                );
                if (Mpwa::sendTextForTenant($pdo, $tenantId, (string) $member['phone'], $message)) {
                    $sent++;
                }
            }

            return $sent;
        } catch (Throwable) {
            return 0;
        }
    }

    /** Placeholder bersama untuk pesan-pesan kru. */
    private static function crewVars(array $event, array $member, string $vendorName): array
    {
        $startsAt = isset($event['starts_at']) && is_string($event['starts_at']) ? $event['starts_at'] : '';

        return [
            'nama_kru' => (string) ($member['name'] ?? ''),
            'nama_event' => (string) ($event['name'] ?? ''),
            'tanggal' => $startsAt !== '' ? License::formatDateId($startsAt) : '—',
            'jam' => $startsAt !== '' && ($ts = strtotime($startsAt)) !== false ? date('H.i', $ts) : '—',
            'venue' => (string) ($event['venue'] ?? '—'),
            'nama_vendor' => $vendorName,
        ];
    }

    /**
     * Sapuan pengingat kru H-1 & hari-H (dipanggil endpoint
     * otomasi pukul 06.00 oleh workflow n8n): untuk setiap
     * event besok (h1) dan hari ini (h0) berstatus draf/aktif
     * milik tenant yang mengaktifkan pengingat, kirim WA ke
     * kru bertugas yang belum pernah menerima jenis itu
     * (penanda crew_notifications_sent). Maksimal 100 pesan
     * per sapuan.
     *
     * @return array{h1: int, h0: int, skipped: int}
     */
    public static function sendCrewReminders(PDO $pdo): array
    {
        $counts = ['h1' => 0, 'h0' => 0, 'skipped' => 0];
        $sentTotal = 0;
        try {
            $targets = [
                'h1' => date('Y-m-d', strtotime('+1 day')),
                'h0' => date('Y-m-d'),
            ];
            $settingsCache = [];
            $tenantCache = [];
            foreach ($targets as $kind => $date) {
                $stmt = $pdo->prepare(
                    "SELECT * FROM events
                      WHERE starts_at IS NOT NULL AND DATE(starts_at) = :d
                        AND status IN ('draft', 'active')
                      ORDER BY id ASC"
                );
                $stmt->execute(['d' => $date]);
                foreach ($stmt->fetchAll() as $event) {
                    if ($sentTotal >= 100) {
                        break 2;
                    }
                    $tenantId = (int) $event['tenant_id'];
                    $settingsCache[$tenantId] ??= self::settings($pdo, $tenantId);
                    $settings = $settingsCache[$tenantId];
                    if (empty($settings['crew_reminder_enabled'])) {
                        continue;
                    }
                    if (!Mpwa::tenantGatewayReady($pdo, $tenantId)) {
                        $counts['skipped']++;
                        continue;
                    }
                    $tenantCache[$tenantId] ??= Tenant::find($pdo, $tenantId);
                    $tenant = $tenantCache[$tenantId];
                    if ($tenant === null) {
                        continue;
                    }
                    $template = $kind === 'h1'
                        ? (string) $settings['crew_h1_template']
                        : (string) $settings['crew_h0_template'];
                    foreach (Crew::forEvent($pdo, (int) $event['id']) as $member) {
                        if ($sentTotal >= 100) {
                            break;
                        }
                        if (empty($member['phone'])) {
                            continue;
                        }
                        $check = $pdo->prepare(
                            'SELECT COUNT(*) FROM crew_notifications_sent
                              WHERE event_id = :e AND crew_member_id = :m AND kind = :k'
                        );
                        $check->execute(['e' => (int) $event['id'], 'm' => (int) $member['id'], 'k' => $kind]);
                        if ((int) $check->fetchColumn() > 0) {
                            continue;
                        }
                        $message = self::render($template, self::crewVars($event, $member, Tenant::displayName($tenant)));
                        if (!Mpwa::sendTextForTenant($pdo, $tenantId, (string) $member['phone'], $message)) {
                            $counts['skipped']++;
                            continue;
                        }
                        $mark = $pdo->prepare(
                            'INSERT IGNORE INTO crew_notifications_sent (event_id, crew_member_id, kind)
                             VALUES (:e, :m, :k)'
                        );
                        $mark->execute(['e' => (int) $event['id'], 'm' => (int) $member['id'], 'k' => $kind]);
                        $counts[$kind]++;
                        $sentTotal++;
                    }
                }
            }
        } catch (Throwable) {
            // Best-effort: kembalikan hitungan yang sudah tercapai.
        }

        return $counts;
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
                . 'Nama Acara: ' . (string) ($request['event_name'] ?? '—') . "\n"
                . 'Tanggal: ' . (!empty($request['event_date']) ? License::formatDateId((string) $request['event_date']) : '—') . "\n"
                . 'Jam Mulai: ' . (string) ($request['start_time'] ?? '—') . "\n"
                . 'Paket: ' . (string) ($request['package_name'] ?? '—') . "\n"
                . 'DP: ' . (isset($request['dp_amount']) && $request['dp_amount'] !== null ? Package::formatPrice($request['dp_amount']) : '—') . "\n"
                . 'Lokasi: ' . (string) ($request['address'] ?? '—') . "\n\n"
                . 'Klien & event drafnya sudah dibuat otomatis di dashboard (Buku Klien & Event). Silakan tindak lanjuti.';

            return Mpwa::sendTextForTenant($pdo, $tenantId, $number, $message);
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
            $hasSource = array_key_exists('source', $data);
            $stmt = $pdo->prepare(
                'INSERT INTO booking_requests
                    (tenant_id, client_id, event_id, name, whatsapp, address, event_date, package_name, notes,
                     event_name, start_time, dp_amount' . ($hasSource ? ', source' : '') . ')
                 VALUES
                    (:tenant_id, :client_id, :event_id, :name, :whatsapp, :address, :event_date, :package_name, :notes,
                     :event_name, :start_time, :dp_amount' . ($hasSource ? ', :source' : '') . ')'
            );
            $params = [
                'tenant_id' => $tenantId,
                'client_id' => $data['client_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'name' => (string) ($data['name'] ?? ''),
                'whatsapp' => $data['whatsapp'] ?? null,
                'address' => $data['address'] ?? null,
                'event_date' => $data['event_date'] ?? null,
                'package_name' => $data['package_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'event_name' => $data['event_name'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'dp_amount' => $data['dp_amount'] ?? null,
            ];
            if ($hasSource) {
                $params['source'] = $data['source'] === 'wa' ? 'wa' : 'web';
            }
            $stmt->execute($params);
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

    /* -----------------------------------------------------------------
     * Booking via WhatsApp (v0.36.0): MPWA meneruskan pesan
     * masuk ke POST /api/wa/webhook/{token}; pesan berformat
     * baku (label Nama/Paket/Tanggal/dll.) diproses otomatis
     * seperti form booking web.
     * --------------------------------------------------------------- */

    /** Format kosong yang dibagikan tenant ke calon klien. */
    public const BOOKING_FORMAT_TEXT = "Booking\n\nNama : \nNo WA : \nPaket : \nNama Acara : \nTanggal : \nJam Mulai : \nDP : \nShare Lokasi : ";

    /**
     * Ambil pengirim + teks dari payload webhook MPWA yang
     * bentuknya bisa berbeda antar versi/fork: periksa
     * nama-nama field yang umum + satu tingkat sarang 'data'.
     *
     * @param array<string, mixed> $data
     * @return array{sender: ?string, text: ?string, isGroup: bool, fromMe: bool}
     */
    public static function extractWaMessage(array $data): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data = array_merge($data, $data['data']);
        }

        $sender = null;
        foreach (['sender', 'from', 'number', 'pengirim', 'participant', 'chatId'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && trim((string) $data[$key]) !== '') {
                $sender = (string) $data[$key];
                break;
            }
        }
        if ($sender !== null && str_contains($sender, '@')) {
            $sender = strstr($sender, '@', true) ?: $sender;
        }

        $text = null;
        foreach (['message', 'text', 'body', 'pesan', 'caption'] as $key) {
            if (!isset($data[$key])) {
                continue;
            }
            if (is_string($data[$key]) && trim($data[$key]) !== '') {
                $text = $data[$key];
                break;
            }
            if (is_array($data[$key])) {
                $nested = $data[$key]['conversation']
                    ?? $data[$key]['extendedTextMessage']['text']
                    ?? $data[$key]['text']
                    ?? null;
                if (is_string($nested) && trim($nested) !== '') {
                    $text = $nested;
                    break;
                }
            }
        }

        // Format MPWA: field 'participant' hanya terisi pada
        // pesan grup — perlakukan sebagai penanda grup juga.
        $isGroup = !empty($data['isGroup']) || !empty($data['is_group'])
            || !empty($data['participant'])
            || (isset($data['from']) && is_string($data['from']) && str_contains($data['from'], '@g.us'))
            || (isset($data['chatId']) && is_string($data['chatId']) && str_contains($data['chatId'], '@g.us'));
        $fromMe = !empty($data['fromMe']) || !empty($data['from_me'])
            || (isset($data['type']) && is_string($data['type']) && stripos($data['type'], 'outgoing') !== false);

        return ['sender' => $sender, 'text' => $text, 'isGroup' => $isGroup, 'fromMe' => $fromMe];
    }

    /**
     * Uraikan pesan berformat label menjadi bidang booking.
     *
     * @return array{labels: int, nama: string, no_wa: string, paket: string,
     *               nama_acara: string, tanggal: string, jam: string,
     *               dp: string, lokasi: string}
     */
    public static function parseBookingText(string $text): array
    {
        $patterns = [
            'nama' => '/^\s*nama\s*:\s*(.+?)\s*$/mi',
            'no_wa' => '/^\s*(?:no\.?\s*)?(?:wa|whatsapp|hp|telepon|telp)\s*:\s*(.+?)\s*$/mi',
            'paket' => '/^\s*paket\s*:\s*(.+?)\s*$/mi',
            'nama_acara' => '/^\s*nama\s*acara\s*:\s*(.+?)\s*$/mi',
            'tanggal' => '/^\s*tanggal(?:\s*acara)?\s*:\s*(.+?)\s*$/mi',
            'jam' => '/^\s*jam(?:\s*mulai)?\s*:\s*(.+?)\s*$/mi',
            'dp' => '/^\s*dp\s*:\s*(.+?)\s*$/mi',
            'lokasi' => '/^\s*(?:share\s+)?lokasi\s*:\s*(.+)$/ims',
        ];
        $fields = ['nama' => '', 'no_wa' => '', 'paket' => '', 'nama_acara' => '',
            'tanggal' => '', 'jam' => '', 'dp' => '', 'lokasi' => ''];
        $found = 0;
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $fields[$key] = trim($m[1]);
                $found++;
            }
        }

        return ['labels' => $found] + $fields;
    }

    /** Tanggal Indonesia/angka menjadi Y-m-d; null bila tak sah. */
    public static function parseDateId(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $raw : null;
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                ? sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1])
                : null;
        }
        $months = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11,
            'nopember' => 11, 'desember' => 12,
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6,
            'jul' => 7, 'agu' => 8, 'ags' => 8, 'sep' => 9, 'okt' => 10,
            'nov' => 11, 'des' => 12,
        ];
        if (preg_match('/^(\d{1,2})\s+([a-z]+)\.?\s+(\d{4})$/i', $raw, $m) === 1) {
            $month = $months[strtolower($m[2])] ?? null;
            if ($month !== null && checkdate($month, (int) $m[1], (int) $m[3])) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], $month, (int) $m[1]);
            }
        }

        return null;
    }

    /** Jam "09.00" / "09:00" menjadi "HH:MM"; null bila tak sah. */
    public static function parseTimeId(string $raw): ?string
    {
        if (preg_match('/(\d{1,2})[:.](\d{2})/', $raw, $m) !== 1) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /** Token webhook tenant; dibuat saat pertama diminta. */
    public static function ensureWebhookToken(PDO $pdo, int $tenantId): ?string
    {
        try {
            $stmt = $pdo->prepare('SELECT wa_webhook_token FROM tenant_notifications WHERE tenant_id = :t');
            $stmt->execute(['t' => $tenantId]);
            $row = $stmt->fetch();
            if ($row !== false && !empty($row['wa_webhook_token'])) {
                return (string) $row['wa_webhook_token'];
            }
            $token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare(
                'INSERT INTO tenant_notifications (tenant_id, wa_webhook_token) VALUES (:t, :tok)
                 ON DUPLICATE KEY UPDATE wa_webhook_token = VALUES(wa_webhook_token)'
            );
            $stmt->execute(['t' => $tenantId, 'tok' => $token]);

            return $token;
        } catch (Throwable) {
            return null;
        }
    }

    /** Catat setiap webhook yang tiba (diagnosis format MPWA). */
    public static function logWebhook(PDO $pdo, ?int $tenantId, ?string $sender, bool $matched, string $result, string $rawPayload): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO wa_webhook_logs (tenant_id, sender, matched, result, payload)
                 VALUES (:t, :s, :m, :r, :p)'
            );
            $stmt->execute([
                't' => $tenantId,
                's' => $sender !== null ? mb_substr($sender, 0, 64) : null,
                'm' => $matched ? 1 : 0,
                'r' => mb_substr($result, 0, 48),
                'p' => mb_substr($rawPayload, 0, 3000),
            ]);
        } catch (Throwable) {
            // Log bersifat diagnosis; abaikan kegagalan.
        }
    }

    /**
     * Webhook terakhir milik tenant (untuk panel Notifikasi).
     *
     * @return list<array<string, mixed>>
     */
    public static function webhookLogs(PDO $pdo, int $tenantId, int $limit = 5): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT received_at, sender, matched, result FROM wa_webhook_logs
                  WHERE tenant_id = :t ORDER BY id DESC LIMIT ' . max(1, min(20, $limit))
            );
            $stmt->execute(['t' => $tenantId]);

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Proses satu pesan WA masuk menjadi booking (alur yang
     * sama seperti form booking web) dan kembalikan kode hasil:
     * ignored | disabled | guidance | guidance_limited |
     * duplicate | created | error.
     */
    public static function processWaBooking(PDO $pdo, int $tenantId, string $senderRaw, string $text, ?string $deviceRaw = null): string
    {
        try {
            $settings = self::settings($pdo, $tenantId);
            if (empty($settings['wa_booking_enabled'])) {
                return 'disabled';
            }
            // Bila payload menyebut perangkat pengirim (field
            // 'device' MPWA) dan tenant sudah mendaftarkan nomor
            // pengirimnya, keduanya harus sama — perlindungan
            // bila URL webhook terpasang di perangkat yang salah.
            if ($deviceRaw !== null && trim($deviceRaw) !== '') {
                $expectedSender = Mpwa::tenantSender($pdo, $tenantId);
                if ($expectedSender !== null && $expectedSender !== '') {
                    $deviceNorm = Whatsapp::normalize($deviceRaw);
                    $expectedNorm = Whatsapp::normalize($expectedSender);
                    if ($deviceNorm !== null && $expectedNorm !== null && $deviceNorm !== $expectedNorm) {
                        return 'device_mismatch';
                    }
                }
            }
            $tenant = Tenant::find($pdo, $tenantId);
            if ($tenant === null) {
                return 'error';
            }

            $parsed = self::parseBookingText($text);
            $mentionsBooking = stripos($text, 'booking') !== false;
            if ($parsed['labels'] < 2 && !$mentionsBooking) {
                return 'ignored';
            }

            $date = $parsed['tanggal'] !== '' ? self::parseDateId($parsed['tanggal']) : null;
            $coreOk = $parsed['nama'] !== '' && $parsed['paket'] !== '' && $date !== null;
            if (!$coreOk) {
                // Terlihat seperti niat booking tetapi formatnya
                // belum terbaca: balas contoh format (maks 1x/jam
                // per pengirim agar tidak spam).
                $recent = $pdo->prepare(
                    "SELECT COUNT(*) FROM wa_webhook_logs
                      WHERE tenant_id = :t AND sender = :s AND result = 'guidance'
                        AND received_at > (NOW() - INTERVAL 1 HOUR)"
                );
                $recent->execute(['t' => $tenantId, 's' => mb_substr($senderRaw, 0, 64)]);
                if ((int) $recent->fetchColumn() > 0) {
                    return 'guidance_limited';
                }
                $senderNorm = Whatsapp::normalize($senderRaw);
                if ($senderNorm !== null) {
                    $guide = "Halo! Untuk booking, kirim ulang dengan format berikut ya (tinggal isi setelah tanda titik dua):\n\n"
                        . self::BOOKING_FORMAT_TEXT
                        . "\n\nContoh tanggal: 11 November 2026 · Contoh jam: 09.00";
                    Mpwa::sendTextForTenant($pdo, $tenantId, $senderNorm, $guide);
                }

                return 'guidance';
            }

            $clientWaRaw = $parsed['no_wa'] !== '' ? $parsed['no_wa'] : $senderRaw;
            $normalizedWa = Whatsapp::normalize($clientWaRaw) ?? Whatsapp::normalize($senderRaw);
            if ($normalizedWa === null) {
                return 'guidance';
            }
            if (self::recentDuplicate($pdo, $tenantId, $normalizedWa, $date)) {
                return 'duplicate';
            }

            // Paket dicocokkan ke Daftar Paket tenant (nama
            // ternormalisasi: sama persis atau saling mengandung).
            $packageName = $parsed['paket'];
            $packagePrice = null;
            $normalizeName = static fn (string $v): string => trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($v)));
            $wanted = $normalizeName($parsed['paket']);
            foreach (PackageTemplate::forTenant($pdo, $tenantId) as $tpl) {
                if ((string) ($tpl['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $candidate = $normalizeName((string) $tpl['name']);
                if ($candidate !== '' && ($candidate === $wanted
                    || str_contains($wanted, $candidate) || str_contains($candidate, $wanted))) {
                    $packageName = (string) $tpl['name'];
                    $packagePrice = (float) $tpl['price'];
                    break;
                }
            }

            // DP: abaikan bila formatnya rusak atau melebihi
            // harga paket (booking tetap dicatat).
            $dpAmount = null;
            if ($parsed['dp'] !== '') {
                [$dpParsed, $dpError] = PackageTemplate::parseMoney($parsed['dp']);
                if ($dpError === null && $dpParsed !== null
                    && ($packagePrice === null || $dpParsed <= $packagePrice)) {
                    $dpAmount = $dpParsed;
                }
            }

            $time = self::parseTimeId($parsed['jam']) ?? '09:00';
            $venue = $parsed['lokasi'] !== '' ? $parsed['lokasi'] : null;

            // Buku Klien: pakai ulang nomor yang sama, atau buat.
            $clientId = null;
            foreach (array_unique([$clientWaRaw, $normalizedWa]) as $candidateWa) {
                $stmt = $pdo->prepare('SELECT id FROM client_bookings WHERE tenant_id = :t AND whatsapp = :wa LIMIT 1');
                $stmt->execute(['t' => $tenantId, 'wa' => $candidateWa]);
                $existing = $stmt->fetch();
                if ($existing !== false) {
                    $clientId = (int) $existing['id'];
                    break;
                }
            }
            if ($clientId === null) {
                $clientId = ClientBook::create($pdo, $tenantId, [
                    'client_name' => $parsed['nama'],
                    'whatsapp' => $clientWaRaw,
                    'address' => $venue,
                ]);
            }

            // Event draf otomatis (pola form booking web).
            $eventId = null;
            [$eventData, $eventErrors] = Event::validate($pdo, [
                'name' => $parsed['nama_acara'] !== '' ? $parsed['nama_acara'] : $parsed['nama'],
                'slug' => '',
                'venue' => $venue ?? '',
                'starts_at' => $date . ' ' . $time,
                'ends_at' => '',
                'status' => 'draft',
                'client_id' => (string) $clientId,
                'client_package_name' => $packageName,
                'client_total_price' => $packagePrice !== null ? (string) (int) $packagePrice : '',
                'client_discount' => '',
                'client_dp_paid' => $dpAmount !== null ? (string) (int) $dpAmount : '',
            ], null, $tenantId);
            if ($eventErrors === []) {
                $eventId = Event::create($pdo, $tenantId, $eventData);
            }

            $requestData = [
                'client_id' => $clientId,
                'event_id' => $eventId,
                'name' => $parsed['nama'],
                'whatsapp' => $clientWaRaw,
                'address' => $venue,
                'event_date' => $date,
                'package_name' => $packageName,
                'notes' => 'Booking via WhatsApp',
                'event_name' => $parsed['nama_acara'] !== '' ? $parsed['nama_acara'] : null,
                'start_time' => $time . ':00',
                'dp_amount' => $dpAmount,
                'source' => 'wa',
            ];
            self::logRequest($pdo, $tenantId, $requestData);
            self::notifyOwnerNewBooking($pdo, $tenantId, $requestData);

            // Balasan konfirmasi langsung ke klien di WA.
            $reply = self::render(
                "Halo {nama_klien}, terima kasih! Booking kamu sudah kami terima & tercatat:\n\nAcara: *{nama_event}*\nTanggal: {tanggal}\nJam mulai: {jam}\nPaket: {nama_paket}\nDP tercatat: {dp}\n\nTim {nama_vendor} akan menghubungimu untuk konfirmasi selanjutnya.",
                [
                    'nama_klien' => $parsed['nama'],
                    'nama_event' => $parsed['nama_acara'] !== '' ? $parsed['nama_acara'] : $parsed['nama'],
                    'tanggal' => License::formatDateId($date),
                    'jam' => str_replace(':', '.', $time),
                    'nama_paket' => $packageName,
                    'dp' => $dpAmount !== null ? Package::formatPrice($dpAmount) : '—',
                    'nama_vendor' => Tenant::displayName($tenant),
                ]
            );
            Mpwa::sendTextForTenant($pdo, $tenantId, $normalizedWa, $reply);

            return 'created';
        } catch (Throwable) {
            return 'error';
        }
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
