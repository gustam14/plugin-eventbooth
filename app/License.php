<?php
declare(strict_types=1);

/**
 * Repository + logika lisensi aplikasi booth (tabel `licenses` dan
 * `license_devices`) — Milestone 4.
 *
 * Keputusan skema nyata yang diikuti:
 *   - Pencarian kunci oleh API selalu memakai `license_key_hash` =
 *     SHA-256 atas kunci ternormalisasi; `license_key_prefix` =
 *     12 karakter pertama kunci untuk identifikasi cepat.
 *   - Keputusan pemilik (v0.5.1): kunci lisensi harus TETAP TERLIHAT
 *     PENUH di dashboard Super Admin dan dashboard tenant, jadi
 *     sejak migrasi M4b kunci utuh juga disimpan di kolom
 *     `license_key`. Lisensi yang diterbitkan sebelum kolom itu
 *     ada bernilai NULL di kolom tersebut — kuncinya tidak bisa
 *     dimunculkan lagi (UI menampilkan prefix + catatan lisensi
 *     lama). Spanduk satu kali saat penerbitan tetap ditampilkan
 *     sebagai umpan balik langsung.
 *   - Snapshot plan: kolom lama `max_devices` (snapshot bawaan
 *     skema) tetap diisi, ditambah `plan_snapshot` JSON (M4) berisi
 *     seluruh batas & fitur plan saat terbit. Hak akses (entitlement)
 *     dibaca dari snapshot; baris lama tanpa snapshot jatuh ke plan
 *     yang masih terhubung.
 *   - Status tersimpan: pending/active/suspended/revoked/expired.
 *     "expired" juga dihitung dinamis dari expires_at agar lisensi
 *     yang lewat tanggalnya langsung tidak berlaku walau barisnya
 *     belum diperbarui.
 *   - Aktivasi melekat akun (v0.6.0): aplikasi booth bisa masuk
 *     dengan akun tenant (/api/app/login) dan otomatis memakai
 *     lisensi dari currentForTenant(); sesi perangkat dijaga token
 *     acak yang hash-nya disimpan di license_devices.access_token_hash
 *     (migrasi M4c). Jalur kunci (activate/validate) tetap hidup
 *     sebagai cadangan.
 *   - Satu akun, satu lisensi (v0.6.2): tenant hanya boleh punya
 *     SATU lisensi yang belum dicabut (liveForTenant()); penerbitan
 *     lisensi tambahan ditolak. Lisensi yang berjalan hanya tumbuh
 *     lewat perpanjangan masa aktif (extendWithPlan) dan penambahan
 *     batas perangkat (addDevices).
 */
final class License
{
    /** Ambang pengingat perpanjangan (hari sebelum kedaluwarsa). */
    public const REMINDER_THRESHOLDS = [7, 3, 1];

    /**
     * Alfabet kunci tanpa karakter ambigu (tanpa 0/O/1/I/L) agar
     * kunci aman dibaca & diketik ulang dari foto/catatan.
     */
    private const KEY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /* -----------------------------------------------------------------
     * Kunci lisensi
     * --------------------------------------------------------------- */

    /** Kunci baru format EB-XXXX-XXXX-XXXX-XXXX. */
    public static function generateKey(): string
    {
        $alphabet = self::KEY_ALPHABET;
        $max = strlen($alphabet) - 1;

        $groups = [];
        for ($g = 0; $g < 4; $g++) {
            $group = '';
            for ($i = 0; $i < 4; $i++) {
                $group .= $alphabet[random_int(0, $max)];
            }
            $groups[] = $group;
        }

        return 'EB-' . implode('-', $groups);
    }

    /** Normalisasi kunci dari input/API: huruf besar, tanpa spasi. */
    public static function normalizeKey(string $key): string
    {
        return strtoupper(str_replace(' ', '', trim($key)));
    }

    public static function keyHash(string $key): string
    {
        return hash('sha256', self::normalizeKey($key));
    }

    /** Tampilan terpotong kunci dari prefix tersimpan: "EB-XXXX-XXXX ••••". */
    public static function maskedKey(array $license): string
    {
        $prefix = (string) ($license['license_key_prefix'] ?? '');
        return ($prefix !== '' ? $prefix : 'EB-••••') . ' ••••';
    }

    /* -----------------------------------------------------------------
     * Pembacaan
     * --------------------------------------------------------------- */

    /** @return array<string, mixed>|null */
    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM licenses WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Cari lisensi dari kunci mentahnya (untuk API aplikasi booth). */
    public static function findByKey(PDO $pdo, string $plainKey): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM licenses WHERE license_key_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hash' => self::keyHash($plainKey)]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Semua lisensi milik tenant + nama plan & jumlah perangkat aktif.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forTenant(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            'SELECT l.*, p.name AS plan_name,
                    (SELECT COUNT(*) FROM license_devices d
                      WHERE d.license_id = l.id AND d.is_active = 1) AS device_count
               FROM licenses l
               JOIN plans p ON p.id = l.plan_id
              WHERE l.tenant_id = :tenant_id
              ORDER BY l.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Lisensi yang DIPAKAI aplikasi booth saat masuk dengan akun
     * tenant (aktivasi melekat akun, v0.6.0 — meniru DSLRBooth):
     * di antara lisensi tenant yang status efektifnya 'active'
     * (tersimpan active dan belum kedaluwarsa), pilih yang
     * expires_at-nya paling jauh; expires_at NULL (tanpa
     * kedaluwarsa) dianggap paling besar; seri dipecahkan oleh
     * lisensi yang paling baru dibuat. Null bila tidak ada.
     *
     * @return array<string, mixed>|null Baris lisensi (+ plan_name & device_count).
     */
    public static function currentForTenant(PDO $pdo, int $tenantId): ?array
    {
        $active = [];
        foreach (self::forTenant($pdo, $tenantId) as $license) {
            if (self::effectiveStatus($license) === 'active') {
                $active[] = $license;
            }
        }
        if ($active === []) {
            return null;
        }

        usort($active, static function (array $a, array $b): int {
            $expiresA = $a['expires_at'] ?? null;
            $expiresB = $b['expires_at'] ?? null;

            // NULL (tanpa kedaluwarsa) = masa berlaku terbesar.
            if ($expiresA === null && $expiresB === null) {
                return (int) $b['id'] <=> (int) $a['id'];
            }
            if ($expiresA === null) {
                return -1;
            }
            if ($expiresB === null) {
                return 1;
            }

            $byExpiry = (int) strtotime((string) $expiresB) <=> (int) strtotime((string) $expiresA);
            if ($byExpiry !== 0) {
                return $byExpiry;
            }

            // Seri: yang paling baru dibuat menang.
            return (int) $b['id'] <=> (int) $a['id'];
        });

        return $active[0];
    }

    /**
     * "Lisensi hidup" milik tenant (aturan satu akun satu lisensi,
     * v0.6.2): lisensi yang status TERsimpannya bukan 'revoked'.
     * Bila ada lisensi yang sedang dipakai aplikasi, itulah
     * hasilnya (currentForTenant()); bila tidak ada yang aktif,
     * lisensi bukan-revoked paling baru yang menang — mencakup
     * lisensi kedaluwarsa/tersuspensi yang masih bisa dihidupkan
     * lewat Tambah Masa Aktif. Null bila tenant belum punya
     * lisensi sama sekali atau semua lisensinya sudah dicabut —
     * hanya dalam keadaan itu lisensi baru boleh diterbitkan.
     *
     * @return array<string, mixed>|null Baris lisensi (+ plan_name & device_count).
     */
    public static function liveForTenant(PDO $pdo, int $tenantId): ?array
    {
        $current = self::currentForTenant($pdo, $tenantId);
        if ($current !== null) {
            return $current;
        }

        // forTenant() terurut id DESC: baris bukan-revoked pertama
        // adalah lisensi non-cabut paling baru.
        foreach (self::forTenant($pdo, $tenantId) as $license) {
            if ((string) ($license['status'] ?? '') !== 'revoked') {
                return $license;
            }
        }

        return null;
    }

    /**
     * Lisensi aktif yang akan berakhir dalam $days hari ke depan
     * (untuk kartu ringkasan Super Admin & endpoint otomasi).
     * Baris memuat data tenant & plan yang digabung (JOIN).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function expiringWithin(PDO $pdo, int $days): array
    {
        $days = max(0, $days);
        $stmt = $pdo->query(
            "SELECT l.*, p.name AS plan_name,
                    t.name AS tenant_name, t.vendor_name AS tenant_vendor_name,
                    t.contact_name AS tenant_contact_name,
                    t.whatsapp AS tenant_whatsapp, t.phone AS tenant_phone,
                    t.email AS tenant_email
               FROM licenses l
               JOIN tenants t ON t.id = l.tenant_id
               JOIN plans p ON p.id = l.plan_id
              WHERE l.status IN ('active', 'pending')
                AND l.expires_at IS NOT NULL
                AND l.expires_at >= NOW()
                AND l.expires_at <= DATE_ADD(NOW(), INTERVAL {$days} DAY)
              ORDER BY l.expires_at ASC"
        );
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /* -----------------------------------------------------------------
     * Status & hak akses
     * --------------------------------------------------------------- */

    /**
     * Status efektif: 'expired' dihitung dari expires_at walau kolom
     * status masih 'active'.
     *
     * @param array<string, mixed> $license
     */
    public static function effectiveStatus(array $license): string
    {
        $status = (string) ($license['status'] ?? 'pending');

        if (
            $status === 'active'
            && !empty($license['expires_at'])
            && strtotime((string) $license['expires_at']) < time()
        ) {
            return 'expired';
        }

        return $status;
    }

    /** Label Indonesia untuk status lisensi. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'suspended' => 'Ditangguhkan',
            'revoked' => 'Dicabut',
            'expired' => 'Kedaluwarsa',
            default => 'Menunggu Aktivasi',
        };
    }

    /** Kelas badge CSS untuk status lisensi. */
    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'status-on',
            'suspended' => 'status-warn',
            default => 'status-off',
        };
    }

    /**
     * Sisa hari menuju kedaluwarsa (negatif = sudah lewat), atau
     * null bila lisensi tanpa kedaluwarsa.
     *
     * @param array<string, mixed> $license
     */
    public static function daysLeft(array $license): ?int
    {
        if (empty($license['expires_at'])) {
            return null;
        }

        $diff = strtotime((string) $license['expires_at']) - time();
        return (int) floor($diff / 86400);
    }

    /**
     * Snapshot plan yang tersimpan di lisensi (JSON), atau null.
     *
     * @param array<string, mixed> $license
     * @return array<string, mixed>|null
     */
    public static function snapshotOf(array $license): ?array
    {
        $raw = $license['plan_snapshot'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Hak akses (entitlement) lisensi: dari snapshot plan saat
     * terbit; baris lama tanpa snapshot dibaca dari plan terhubung;
     * upaya terakhir dari kolom snapshot max_devices lisensi sendiri.
     *
     * @param array<string, mixed>      $license
     * @param array<string, mixed>|null $plan    Baris plan (cadangan).
     * @return array{plan_name: string, max_devices: int, max_events: int,
     *               drive_quota_gb: int, session_quota: int, retention_days: int,
     *               license_duration_days: int,
     *               features: array{payment_lock: bool, whatsapp_share: bool}}
     */
    public static function entitlements(array $license, ?array $plan = null): array
    {
        $source = self::snapshotOf($license) ?? $plan ?? [];

        $featuresRaw = $source['features'] ?? null;
        if (is_string($featuresRaw)) {
            $decoded = json_decode($featuresRaw, true);
            $featuresRaw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($featuresRaw)) {
            $featuresRaw = [];
        }

        $maxDevices = isset($source['max_devices'])
            ? (int) $source['max_devices']
            : (int) ($license['max_devices'] ?? 1);

        return [
            'plan_name' => (string) ($source['plan_name'] ?? $source['name'] ?? '—'),
            'max_devices' => $maxDevices,
            'max_events' => (int) ($source['max_events'] ?? 0),
            'drive_quota_gb' => (int) ($source['drive_quota_gb'] ?? 0),
            'session_quota' => (int) ($source['session_quota'] ?? 0),
            'retention_days' => (int) ($source['retention_days'] ?? 0),
            'license_duration_days' => (int) ($source['license_duration_days'] ?? 0),
            'features' => [
                'payment_lock' => !empty($featuresRaw['payment_lock']),
                'whatsapp_share' => !empty($featuresRaw['whatsapp_share']),
            ],
        ];
    }

    /** Susun snapshot dari baris plan (dipakai saat penerbitan). */
    public static function snapshotFromPlan(array $plan): array
    {
        return [
            'plan_id' => (int) $plan['id'],
            'plan_name' => (string) $plan['name'],
            'price' => (string) ($plan['price'] ?? '0'),
            'billing_period_days' => (int) ($plan['billing_period_days'] ?? 30),
            'license_duration_days' => (int) ($plan['license_duration_days'] ?? 30),
            'max_devices' => (int) ($plan['max_devices'] ?? 1),
            'max_events' => (int) ($plan['max_events'] ?? 0),
            'drive_quota_gb' => (int) ($plan['drive_quota_gb'] ?? 0),
            'session_quota' => (int) ($plan['session_quota'] ?? 0),
            'retention_days' => (int) ($plan['retention_days'] ?? 0),
            'features' => Plan::featuresOf($plan),
        ];
    }

    /* -----------------------------------------------------------------
     * Penerbitan & perpanjangan
     * --------------------------------------------------------------- */

    /**
     * Terbitkan lisensi baru untuk tenant dari sebuah plan.
     *
     * @param array<string, mixed> $plan Baris plan aktif.
     * @return array{license: array<string, mixed>, key: string}
     *         Baris lisensi + KUNCI MENTAH. Kunci disimpan sebagai
     *         hash (untuk API) DAN sebagai teks utuh di kolom
     *         `license_key` (untuk tampilan dashboard, v0.5.1);
     *         nilai kembalian ini dipakai untuk spanduk satu kali
     *         tepat setelah penerbitan.
     */
    public static function issue(PDO $pdo, int $tenantId, array $plan, ?int $createdBy = null): array
    {
        $duration = (int) ($plan['license_duration_days'] ?? 0);
        $now = new DateTimeImmutable('now');
        $startsAt = $now->format('Y-m-d H:i:s');
        $expiresAt = $duration > 0
            ? $now->modify('+' . $duration . ' days')->format('Y-m-d H:i:s')
            : null;

        $snapshot = self::snapshotFromPlan($plan);

        // Kunci unik: coba-ulang bila hash-nya kebetulan sudah ada.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $key = self::generateKey();
            $hash = self::keyHash($key);

            $check = $pdo->prepare('SELECT COUNT(*) FROM licenses WHERE license_key_hash = :hash');
            $check->execute(['hash' => $hash]);
            if ((int) $check->fetchColumn() === 0) {
                break;
            }
            $key = '';
        }
        if ($key === '') {
            throw new RuntimeException('Gagal membuat kunci lisensi unik.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO licenses
                (tenant_id, plan_id, license_key_hash, license_key_prefix, license_key,
                 status, max_devices, plan_snapshot, starts_at, expires_at,
                 source, created_by)
             VALUES
                (:tenant_id, :plan_id, :hash, :prefix, :full_key,
                 \'active\', :max_devices, :snapshot, :starts_at, :expires_at,
                 \'manual\', :created_by)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'plan_id' => (int) $plan['id'],
            'hash' => $hash,
            'prefix' => substr($key, 0, 12),
            'full_key' => $key,
            'max_devices' => (int) ($plan['max_devices'] ?? 1),
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        $license = self::find($pdo, (int) $pdo->lastInsertId());

        return ['license' => $license ?? [], 'key' => $key];
    }

    /**
     * Perpanjang lisensi: basis = max(sekarang, expires_at), ditambah
     * durasi plan dari snapshot. Lisensi tersuspensi ikut diaktifkan
     * kembali oleh pemanggil lewat status 'active' di sini.
     *
     * @param array<string, mixed> $license
     * @return string|null expires_at baru, atau null bila tidak bisa
     *                     diperpanjang (dicabut / tanpa kedaluwarsa).
     */
    public static function renew(PDO $pdo, array $license): ?string
    {
        if ((string) ($license['status'] ?? '') === 'revoked') {
            return null;
        }
        if (empty($license['expires_at'])) {
            return null; // Tanpa kedaluwarsa: tidak ada yang diperpanjang.
        }

        $ent = self::entitlements($license);
        $duration = $ent['license_duration_days'];
        if ($duration <= 0) {
            // Snapshot lama tanpa durasi: jatuh ke plan terhubung.
            $plan = Plan::find($pdo, (int) ($license['plan_id'] ?? 0));
            $duration = $plan !== null ? (int) ($plan['license_duration_days'] ?? 0) : 0;
        }
        if ($duration <= 0) {
            return null;
        }

        $baseTs = max(time(), (int) strtotime((string) $license['expires_at']));
        $newExpires = date('Y-m-d H:i:s', $baseTs + $duration * 86400);

        $stmt = $pdo->prepare(
            'UPDATE licenses SET expires_at = :expires_at, status = \'active\' WHERE id = :id'
        );
        $stmt->execute(['expires_at' => $newExpires, 'id' => (int) $license['id']]);

        return $newExpires;
    }

    /**
     * Model masa aktif (keputusan pemilik, v0.6.1): pembelian plan
     * oleh tenant memperpanjang lisensi yang SEDANG BERJALAN —
     * bukan menerbitkan lisensi baru. Dipakai aksi Super Admin
     * "Tambah Masa Aktif" dan kelak oleh checkout Midtrans.
     *
     * Pemilihan target:
     *   (a) lisensi yang dipakai aplikasi booth saat ini
     *       (currentForTenant()); bila tidak ada,
     *   (b) lisensi tenant paling baru yang status tersimpannya
     *       BUKAN 'revoked' — lisensi kedaluwarsa maupun
     *       tersuspensi dihidupkan kembali oleh perpanjangan ini.
     *   Bila keduanya kosong (tenant belum punya lisensi, atau
     *   semua lisensinya sudah dicabut) hasil null — pemanggil
     *   menerbitkan lisensi baru sebagai gantinya. Lisensi yang
     *   dicabut TIDAK pernah menjadi target: pencabutan permanen.
     *
     * Masa aktif baru:
     *   - Plan berdurasi 0 -> expires_at NULL (tanpa kedaluwarsa).
     *   - Target yang expires_at-nya NULL (sudah permanen) TETAP
     *     permanen walau plan barunya berdurasi: lisensi permanen
     *     tidak diam-diam berubah menjadi berbatas waktu. Untuk
     *     membatasinya, admin mencabut lisensi itu lalu menerbitkan
     *     lisensi baru.
     *   - Selain itu basis = expires_at target bila masih di masa
     *     depan, selain itu sekarang; hasil = basis + durasi plan.
     *
     * Baris target diperbarui mengikuti plan yang dipilih:
     * plan_id, snapshot plan (snapshotFromPlan), max_devices, dan
     * status kembali 'active' (perpanjangan menghidupkan lisensi
     * kedaluwarsa/tersuspensi). Kunci lisensi tidak berubah.
     *
     * @param array<string, mixed> $tenant Baris tenant.
     * @param array<string, mixed> $plan   Baris plan aktif yang dibeli.
     * @return array<string, mixed>|null Baris lisensi sesudah diperbarui.
     */
    public static function extendWithPlan(PDO $pdo, array $tenant, array $plan): ?array
    {
        $tenantId = (int) $tenant['id'];

        // Pemilihan target (a)/(b) di atas persis aturan
        // liveForTenant() — satu-satunya lisensi hidup tenant.
        $target = self::liveForTenant($pdo, $tenantId);
        if ($target === null) {
            return null;
        }

        $duration = (int) ($plan['license_duration_days'] ?? 0);

        if (empty($target['expires_at'])) {
            // Sudah permanen: tetap permanen (lihat docblock).
            $expiresAt = null;
        } elseif ($duration === 0) {
            $expiresAt = null;
        } else {
            $baseTs = max(time(), (int) strtotime((string) $target['expires_at']));
            $expiresAt = date('Y-m-d H:i:s', $baseTs + $duration * 86400);
        }

        $stmt = $pdo->prepare(
            'UPDATE licenses
                SET plan_id = :plan_id,
                    plan_snapshot = :snapshot,
                    max_devices = :max_devices,
                    expires_at = :expires_at,
                    status = \'active\'
              WHERE id = :id'
        );
        $stmt->execute([
            'plan_id' => (int) $plan['id'],
            'snapshot' => json_encode(
                self::snapshotFromPlan($plan),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'max_devices' => (int) ($plan['max_devices'] ?? 1),
            'expires_at' => $expiresAt,
            'id' => (int) $target['id'],
        ]);

        return self::find($pdo, (int) $target['id']);
    }

    public static function setStatus(PDO $pdo, int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'active', 'suspended', 'revoked', 'expired'], true)) {
            return;
        }

        $stmt = $pdo->prepare('UPDATE licenses SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /* -----------------------------------------------------------------
     * Perangkat (license_devices)
     * --------------------------------------------------------------- */

    /**
     * Tambah Perangkat (v0.6.2): naikkan batas perangkat lisensi
     * yang berjalan melampaui bawaan plan-nya — salah satu dari
     * hanya dua cara lisensi hidup bertumbuh (selain Tambah Masa
     * Aktif). Jumlah dibatasi 1..20 per aksi. Lisensi tanpa batas
     * (batas 0) tidak berubah dan hasilnya null; lisensi yang
     * sudah dicabut juga null — pemanggil membedakan keduanya dari
     * status barisnya. Batas efektif dibaca dari snapshot plan
     * (entitlements()), jadi snapshot DAN kolom max_devices samasama
     * diperbarui agar API aplikasi dan dashboard konsisten.
     *
     * @return int|null Batas perangkat baru, atau null bila tidak berubah.
     */
    public static function addDevices(PDO $pdo, int $licenseId, int $amount): ?int
    {
        $amount = max(1, min(20, $amount));

        $license = self::find($pdo, $licenseId);
        if ($license === null) {
            return null;
        }
        if ((string) ($license['status'] ?? '') === 'revoked') {
            return null;
        }

        $currentMax = self::entitlements($license)['max_devices'];
        if ($currentMax === 0) {
            return null; // Sudah tanpa batas: tidak ada yang ditambah.
        }

        $newMax = $currentMax + $amount;

        $snapshot = self::snapshotOf($license);
        $snapshotJson = null;
        if ($snapshot !== null) {
            $snapshot['max_devices'] = $newMax;
            $snapshotJson = json_encode(
                $snapshot,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        $stmt = $pdo->prepare(
            'UPDATE licenses SET max_devices = :max_devices, plan_snapshot = :snapshot WHERE id = :id'
        );
        $stmt->execute([
            'max_devices' => $newMax,
            'snapshot' => $snapshotJson,
            'id' => $licenseId,
        ]);

        return $newMax;
    }

    public static function deviceCount(PDO $pdo, int $licenseId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM license_devices WHERE license_id = :id AND is_active = 1'
        );
        $stmt->execute(['id' => $licenseId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public static function findDevice(PDO $pdo, int $licenseId, string $deviceIdHash): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM license_devices
              WHERE license_id = :license_id AND device_id_hash = :hash
              LIMIT 1'
        );
        $stmt->execute(['license_id' => $licenseId, 'hash' => $deviceIdHash]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Daftarkan perangkat baru atau segarkan yang sudah terdaftar. */
    public static function registerDevice(
        PDO $pdo,
        int $licenseId,
        string $deviceIdHash,
        ?string $deviceLabel,
        ?string $appVersion
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO license_devices
                (license_id, device_id_hash, device_label, app_version, is_active, first_seen_at, last_heartbeat_at)
             VALUES
                (:license_id, :hash, :label, :app_version, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                device_label = COALESCE(VALUES(device_label), device_label),
                app_version = COALESCE(VALUES(app_version), app_version),
                is_active = 1,
                last_heartbeat_at = NOW()'
        );
        $stmt->execute([
            'license_id' => $licenseId,
            'hash' => $deviceIdHash,
            'label' => $deviceLabel,
            'app_version' => $appVersion,
        ]);
    }

    /** Segarkan heartbeat perangkat yang sudah terdaftar (validasi API). */
    public static function touchDevice(PDO $pdo, int $deviceId, ?string $deviceLabel, ?string $appVersion): void
    {
        $stmt = $pdo->prepare(
            'UPDATE license_devices
                SET last_heartbeat_at = NOW(),
                    device_label = COALESCE(:label, device_label),
                    app_version = COALESCE(:app_version, app_version)
              WHERE id = :id'
        );
        $stmt->execute([
            'label' => $deviceLabel,
            'app_version' => $appVersion,
            'id' => $deviceId,
        ]);
    }

    /**
     * Semua baris perangkat milik satu lisensi, terbaru dulu —
     * untuk daftar "Perangkat Terhubung" di dashboard.
     * Kolom yang dibaca sengaja TIDAK memuat access_token_hash,
     * jadi daftar ini tetap bisa tampil sebelum migrasi M4c.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function devicesFor(PDO $pdo, int $licenseId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, device_label, is_active, first_seen_at, last_heartbeat_at
               FROM license_devices
              WHERE license_id = :license_id
              ORDER BY id DESC'
        );
        $stmt->execute(['license_id' => $licenseId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Cabut satu perangkat dari lisensi (dari dashboard tenant atau
     * Super Admin): baris dinonaktifkan dan token sesinya dihapus,
     * sehingga slot perangkat langsung bebas dan token lama mati.
     * Mengembalikan true bila ada baris yang berubah.
     *
     * Memakai kolom access_token_hash (migrasi M4c) — sebelum
     * migrasi, pemanggil menangkap PDOException-nya.
     */
    public static function revokeDevice(PDO $pdo, int $licenseId, int $deviceId): bool
    {
        $stmt = $pdo->prepare(
            'UPDATE license_devices
                SET is_active = 0, access_token_hash = NULL
              WHERE id = :id AND license_id = :license_id'
        );
        $stmt->execute(['id' => $deviceId, 'license_id' => $licenseId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Terbitkan token sesi perangkat untuk /api/app/login.
     * Token mentah (64 heksadesimal) hanya dikembalikan ke aplikasi
     * SATU KALI lewat respons login; server hanya menyimpan hash
     * SHA-256-nya di access_token_hash (migrasi M4c).
     */
    public static function issueDeviceToken(PDO $pdo, int $deviceRowId): string
    {
        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare(
            'UPDATE license_devices SET access_token_hash = :hash WHERE id = :id'
        );
        $stmt->execute(['hash' => hash('sha256', $token), 'id' => $deviceRowId]);

        return $token;
    }

    /**
     * Cari baris perangkat AKTIF dari token sesi mentahnya
     * (header Authorization: Bearer pada /api/app/*).
     *
     * @return array<string, mixed>|null
     */
    public static function findDeviceByToken(PDO $pdo, string $rawToken): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM license_devices
              WHERE access_token_hash = :hash AND is_active = 1
              LIMIT 1'
        );
        $stmt->execute(['hash' => hash('sha256', $rawToken)]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Akhiri sesi perangkat (/api/app/logout): nonaktif + token dihapus. */
    public static function deactivateDevice(PDO $pdo, int $deviceRowId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE license_devices
                SET is_active = 0, access_token_hash = NULL
              WHERE id = :id'
        );
        $stmt->execute(['id' => $deviceRowId]);
    }

    /**
     * Catat aktivitas API terakhir lisensi. Kolom last_seen_at hanya
     * ada setelah migrasi M4 — kegagalan di sini sengaja diabaikan
     * agar API tetap jalan sebelum migrasi.
     */
    public static function touchLastSeen(PDO $pdo, int $licenseId): void
    {
        try {
            $stmt = $pdo->prepare('UPDATE licenses SET last_seen_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $licenseId]);
        } catch (PDOException) {
            // Kolom belum ada (migrasi M4 belum jalan): abaikan.
        }
    }

    /** Tandai lisensi sudah diaktifkan aplikasi booth (sekali saja). */
    public static function markActivated(PDO $pdo, int $licenseId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE licenses
                SET activated_at = COALESCE(activated_at, NOW()),
                    status = CASE WHEN status = \'pending\' THEN \'active\' ELSE status END
              WHERE id = :id'
        );
        $stmt->execute(['id' => $licenseId]);
    }

    /* -----------------------------------------------------------------
     * Format
     * --------------------------------------------------------------- */

    /** Format tanggal gaya Indonesia: "5 November 2026"; null -> "—". */
    public static function formatDateId(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return '—';
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
}
