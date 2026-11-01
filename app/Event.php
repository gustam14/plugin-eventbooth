<?php
declare(strict_types=1);

/**
 * Repository + validasi event tenant (tabel `events`) — Milestone 5a.
 *
 * Keputusan pemetaan kolom (skema nyata, database/schema.sql):
 *   - Tanggal event memakai `starts_at` / `ends_at` (DATETIME,
 *     boleh NULL) — tidak ada kolom event_date tersendiri.
 *   - Kunci pembayaran per event = `payment_lock_enabled`
 *     (1 = tamu wajib membayar dulu; 0 = mode wedding/gratis,
 *     blueprint §4). Batas pengganti saat kunci mati diatur
 *     `limit_mode` ('none' | 'event_hours' | 'session_quota') +
 *     `session_quota_limit`.
 *   - `slug` unik secara GLOBAL (uq_events_slug) karena menjadi
 *     URL album publik /e/{slug}.
 *   - `drive_account_id` DIKUNCI saat event dibuat (blueprint
 *     §2A: file satu event tidak tersebar ke banyak akun Drive).
 *     Pilihan "auto" pada formulir diselesaikan di sini: akun
 *     google_accounts berstatus 'connected' dengan sisa kuota
 *     paling lega; akun yang kuotanya belum terukur (NULL) hanya
 *     dipilih bila tidak ada akun berkuota terukur — seri dan
 *     keadaan serba-tidak-terukur dimenangkan akun default.
 *     Akun Drive adalah milik platform (diatur Super Admin di
 *     menu Google Drive), bukan milik tenant.
 *   - Status memakai enum skema: 'draft' | 'active' | 'finished'
 *     | 'archived'.
 */
final class Event
{
    /** @var array<int, string> Status sah menurut enum skema. */
    public const STATUSES = ['draft', 'active', 'finished', 'archived'];

    /** @var array<int, string> Mode batas saat kunci pembayaran mati. */
    public const LIMIT_MODES = ['none', 'event_hours', 'session_quota'];

    /* -----------------------------------------------------------------
     * Pembacaan
     * --------------------------------------------------------------- */

    /**
     * Semua event milik tenant + jumlah sesi tiap event,
     * terbaru dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forTenant(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM sessions s
                      WHERE s.event_id = e.id) AS session_count,
                    (SELECT MIN(s.created_at) FROM sessions s
                      WHERE s.event_id = e.id) AS first_session_at,
                    (SELECT COUNT(*) FROM media_files m
                      WHERE m.event_id = e.id) AS media_count
               FROM events e
              WHERE e.tenant_id = :tenant_id
              ORDER BY e.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Event hanya bila milik tenant ini (penjaga kepemilikan). */
    public static function findForTenant(PDO $pdo, int $tenantId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM events
              WHERE id = :id AND tenant_id = :tenant_id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function countForTenant(PDO $pdo, int $tenantId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Daftar event untuk API aplikasi booth: hanya status yang
     * masuk akal dijalankan booth ('draft' dan 'active');
     * 'finished'/'archived' tidak ditawarkan ke aplikasi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forApi(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, slug, starts_at, payment_lock_enabled, drive_account_id
               FROM events
              WHERE tenant_id = :tenant_id
                AND status IN (\'draft\', \'active\')
              ORDER BY id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /* -----------------------------------------------------------------
     * Slug
     * --------------------------------------------------------------- */

    /** Slug unik global untuk events (akhiran -2, -3, … bila bentrok). */
    public static function uniqueSlug(PDO $pdo, string $base, ?int $exceptId = null): string
    {
        $base = $base !== '' ? $base : 'event';
        $slug = $base;
        $suffix = 2;

        while (true) {
            if ($exceptId === null) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE slug = :slug');
                $stmt->execute(['slug' => $slug]);
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE slug = :slug AND id <> :id');
                $stmt->execute(['slug' => $slug, 'id' => $exceptId]);
            }
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    /* -----------------------------------------------------------------
     * Penugasan akun Drive
     * --------------------------------------------------------------- */

    /**
     * Akun Drive platform yang berstatus 'connected' — satu-satunya
     * yang boleh menampung event baru.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function connectedAccounts(PDO $pdo): array
    {
        $connected = [];
        foreach (GoogleAccount::all($pdo) as $account) {
            if ((string) ($account['status'] ?? '') === 'connected') {
                $connected[] = $account;
            }
        }

        return $connected;
    }

    /**
     * Selesaikan penugasan otomatis: akun terhubung yang
     * pertama dikaitkan Super Admin (keputusan pemilik
     * v0.20.0 — sebelumnya: sisa kuota paling lega).
     *
     * @param array<int, array<string, mixed>> $connected Hasil connectedAccounts().
     * @return array<string, mixed>|null
     */
    public static function pickDriveAccount(array $connected): ?array
    {
        // Keputusan pemilik (v0.20.0): penugasan otomatis =
        // akun terhubung yang PERTAMA dikaitkan Super Admin.
        // connectedAccounts() mewarisi urutan id ASC dari
        // GoogleAccount::all() = urutan pengaitan.
        return $connected[0] ?? null;
    }

    /* -----------------------------------------------------------------
     * Validasi & simpan
     * --------------------------------------------------------------- */

    /**
     * Validasi + normalisasi isian formulir event.
     *
     * Akun penyimpanan TIDAK lagi dipilih lewat formulir
     * (v0.20.0): event baru otomatis memakai akun terhubung
     * pertama; event lama mempertahankan akunnya. Hasil
     * ternormalisasi memuat 'drive_account_id' (?int) dan
     * penanda 'drive_auto_missing' (bool) — true bila tidak
     * ada akun terhubung sama sekali, agar pemanggil bisa
     * memberi peringatan ke pengguna.
     *
     * @param array<string, mixed>      $input    Isi $_POST.
     * @param array<string, mixed>|null $existing Baris event (mode ubah) atau null (mode buat).
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function validate(PDO $pdo, array $input, ?array $existing = null): array
    {
        $errors = [];
        $data = [];

        // --- Nama ---------------------------------------------------
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Nama event wajib diisi.';
        } elseif (self::length($name) > 160) {
            $errors['name'] = 'Nama event maksimal 160 karakter.';
        }
        $data['name'] = $name;

        // --- Slug (otomatis dari nama bila dikosongkan) -------------
        $slugInput = strtolower(trim((string) ($input['slug'] ?? '')));
        if ($slugInput === '') {
            $slugInput = Tenant::slugify($name);
        }
        if ($slugInput === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugInput) !== 1) {
            $errors['slug'] = 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.';
            $data['slug'] = $slugInput;
        } else {
            // Slug bersifat unik global; bentrok diselesaikan
            // dengan akhiran -2, -3, … (perilaku yang sama seperti
            // slug tenant), bukan error.
            $data['slug'] = self::uniqueSlug(
                $pdo,
                $slugInput,
                $existing !== null ? (int) $existing['id'] : null
            );
        }

        // --- Lokasi ---------------------------------------------------
        $venue = trim((string) ($input['venue'] ?? ''));
        if (self::length($venue) > 190) {
            $errors['venue'] = 'Lokasi maksimal 190 karakter.';
        }
        $data['venue'] = $venue === '' ? null : $venue;

        // --- Tanggal mulai & selesai (opsional) -----------------------
        $startsRaw = trim((string) ($input['starts_at'] ?? ''));
        $startsAt = self::parseDateTime($startsRaw);
        if ($startsRaw !== '' && $startsAt === null) {
            $errors['starts_at'] = 'Format tanggal mulai tidak sah.';
        }
        $data['starts_at'] = $startsAt;

        $endsRaw = trim((string) ($input['ends_at'] ?? ''));
        $endsAt = self::parseDateTime($endsRaw);
        if ($endsRaw !== '' && $endsAt === null) {
            $errors['ends_at'] = 'Format tanggal selesai tidak sah.';
        }
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
            $errors['ends_at'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }
        $data['ends_at'] = $endsAt;

        // --- Status -----------------------------------------------------
        $status = (string) ($input['status'] ?? ($existing['status'] ?? 'draft'));
        $data['status'] = in_array($status, self::STATUSES, true) ? $status : 'draft';

        // --- Akun Drive penampung ---------------------------------------
        // Keputusan pemilik (v0.20.0): tenant TIDAK memilih akun
        // penyimpanan. Event baru otomatis memakai akun terhubung
        // yang PERTAMA dikaitkan Super Admin (urutan id); event
        // yang sudah punya akun mempertahankannya (terkunci per
        // event). Pilihan manual hanya ada di panel Super Admin.
        $data['drive_auto_missing'] = false;
        $existingAccount = ($existing !== null && !empty($existing['drive_account_id']))
            ? (int) $existing['drive_account_id']
            : null;
        if ($existingAccount !== null) {
            $data['drive_account_id'] = $existingAccount;
        } else {
            try {
                $picked = self::pickDriveAccount(self::connectedAccounts($pdo));
            } catch (PDOException) {
                $picked = null;
            }
            $data['drive_account_id'] = $picked !== null ? (int) $picked['id'] : null;
            $data['drive_auto_missing'] = $picked === null;
        }

        // --- Kunci pembayaran -------------------------------------------
        $data['payment_lock_enabled'] = isset($input['payment_lock_enabled']) ? 1 : 0;

        // --- Batas saat kunci pembayaran mati ----------------------------
        $limitMode = (string) ($input['limit_mode'] ?? 'none');
        $data['limit_mode'] = in_array($limitMode, self::LIMIT_MODES, true) ? $limitMode : 'none';

        $quotaRaw = trim((string) ($input['session_quota_limit'] ?? ''));
        if ($quotaRaw === '') {
            $data['session_quota_limit'] = null;
        } elseif (preg_match('/^\d+$/', $quotaRaw) === 1) {
            $data['session_quota_limit'] = (int) $quotaRaw;
        } else {
            $errors['session_quota_limit'] = 'Batas jumlah sesi harus berupa bilangan bulat 0 atau lebih.';
            $data['session_quota_limit'] = null;
        }

        return [$data, $errors];
    }

    /**
     * @param array<string, mixed> $data Hasil Event::validate().
     * @return int ID event baru.
     */
    public static function create(PDO $pdo, int $tenantId, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO events
                (tenant_id, drive_account_id, name, slug, venue,
                 starts_at, ends_at, payment_lock_enabled,
                 limit_mode, session_quota_limit, status)
             VALUES
                (:tenant_id, :drive_account_id, :name, :slug, :venue,
                 :starts_at, :ends_at, :payment_lock_enabled,
                 :limit_mode, :session_quota_limit, :status)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'drive_account_id' => $data['drive_account_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'venue' => $data['venue'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'payment_lock_enabled' => $data['payment_lock_enabled'],
            'limit_mode' => $data['limit_mode'],
            'session_quota_limit' => $data['session_quota_limit'],
            'status' => $data['status'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data Hasil Event::validate(). */
    public static function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE events
                SET drive_account_id = :drive_account_id,
                    name = :name,
                    slug = :slug,
                    venue = :venue,
                    starts_at = :starts_at,
                    ends_at = :ends_at,
                    payment_lock_enabled = :payment_lock_enabled,
                    limit_mode = :limit_mode,
                    session_quota_limit = :session_quota_limit,
                    status = :status
              WHERE id = :id'
        );
        $stmt->execute([
            'drive_account_id' => $data['drive_account_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'venue' => $data['venue'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'payment_lock_enabled' => $data['payment_lock_enabled'],
            'limit_mode' => $data['limit_mode'],
            'session_quota_limit' => $data['session_quota_limit'],
            'status' => $data['status'],
            'id' => $id,
        ]);
    }

    /* -----------------------------------------------------------------
     * Tampilan
     * --------------------------------------------------------------- */

    /**
     * Perbarui HANYA status + tanggal event (v0.19.0) —
     * dipakai panel Pengaturan Event di Kelola Album agar
     * tenant tak perlu membuka formulir Ubah Event penuh.
     */
    public static function updateScheduleStatus(PDO $pdo, int $eventId, ?string $startsAt, ?string $endsAt, string $status): void
    {
        $stmt = $pdo->prepare(
            'UPDATE events
                SET starts_at = :starts_at, ends_at = :ends_at, status = :status
              WHERE id = :id'
        );
        $stmt->execute([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
            'id' => $eventId,
        ]);
    }

    /** Label Indonesia untuk status event. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'finished' => 'Selesai',
            'archived' => 'Diarsipkan',
            default => 'Draf',
        };
    }

    /** Kelas badge CSS untuk status event. */
    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'status-on',
            'draft' => 'status-warn',
            default => 'status-off',
        };
    }

    /** Label Indonesia untuk mode batas sesi. */
    public static function limitModeLabel(string $mode): string
    {
        return match ($mode) {
            'event_hours' => 'Dibatasi jam event',
            'session_quota' => 'Dibatasi jumlah sesi',
            default => 'Tanpa batas',
        };
    }

    /**
     * Nilai tampilan formulir event: dari baris database (mode
     * ubah), dari isian mentah $_POST (setelah validasi gagal),
     * atau nilai bawaan (mode buat).
     *
     * @param array<string, mixed>|null $event
     * @param array<string, mixed>|null $post
     * @return array<string, mixed>
     */
    public static function formValues(?array $event = null, ?array $post = null): array
    {
        $values = [
            'name' => '',
            'slug' => '',
            'venue' => '',
            'starts_at' => '',
            'ends_at' => '',
            'status' => 'draft',
            'drive_account_id' => 'auto',
            'payment_lock_enabled' => true,
            'limit_mode' => 'none',
            'session_quota_limit' => '',
        ];

        if ($post !== null) {
            foreach (['name', 'slug', 'venue', 'status', 'limit_mode'] as $field) {
                if (isset($post[$field]) && is_string($post[$field])) {
                    $values[$field] = $post[$field];
                }
            }
            foreach (['starts_at', 'ends_at'] as $field) {
                if (isset($post[$field]) && is_string($post[$field])) {
                    // Nilai datetime-local diteruskan apa adanya.
                    $values[$field] = str_replace(' ', 'T', trim($post[$field]));
                }
            }
            if (isset($post['drive_account_id']) && is_string($post['drive_account_id'])) {
                $values['drive_account_id'] = $post['drive_account_id'];
            }
            if (isset($post['session_quota_limit']) && is_string($post['session_quota_limit'])) {
                $values['session_quota_limit'] = $post['session_quota_limit'];
            }
            $values['payment_lock_enabled'] = isset($post['payment_lock_enabled']);
            return $values;
        }

        if ($event !== null) {
            $values['name'] = (string) ($event['name'] ?? '');
            $values['slug'] = (string) ($event['slug'] ?? '');
            $values['venue'] = (string) ($event['venue'] ?? '');
            $values['starts_at'] = self::toLocalInput($event['starts_at'] ?? null);
            $values['ends_at'] = self::toLocalInput($event['ends_at'] ?? null);
            $values['status'] = (string) ($event['status'] ?? 'draft');
            $values['drive_account_id'] = ($event['drive_account_id'] ?? null) !== null
                ? (string) (int) $event['drive_account_id']
                : '';
            $values['payment_lock_enabled'] = (int) ($event['payment_lock_enabled'] ?? 1) === 1;
            $values['limit_mode'] = (string) ($event['limit_mode'] ?? 'none');
            $values['session_quota_limit'] = ($event['session_quota_limit'] ?? null) !== null
                ? (string) (int) $event['session_quota_limit']
                : '';
        }

        return $values;
    }

    /* -----------------------------------------------------------------
     * Helper internal
     * --------------------------------------------------------------- */

    /**
     * Terima format input tanggal umum (datetime-local, spasi,
     * atau tanggal saja) dan kembalikan 'Y-m-d H:i:s'; null bila
     * kosong ATAU tidak terbaca — pemanggil membedakannya dari
     * string mentahnya.
     */
    private static function parseDateTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $raw);
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        $ts = strtotime($raw);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    /** Nilai DATETIME database -> nilai input datetime-local. */
    private static function toLocalInput(mixed $datetime): string
    {
        if (!is_string($datetime) || $datetime === '') {
            return '';
        }
        $ts = strtotime($datetime);
        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
