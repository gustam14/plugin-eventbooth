<?php
declare(strict_types=1);

/**
 * Repository + validasi paket sesi (tabel `packages`) — Milestone 5a.
 *
 * Keputusan pemetaan kolom (skema nyata, database/schema.sql):
 *   - Paket MENEMPEL pada event (`event_id` NOT NULL, FK ke
 *     events): tidak ada paket tingkat tenant. Kepemilikan tenant
 *     selalu diverifikasi lewat event induknya — semua query
 *     tenant di kelas ini JOIN ke `events`.
 *   - Mode timer memakai enum skema: 'per_session' (jumlah sesi),
 *     'per_duration' (durasi menit), 'combined' (keduanya) —
 *     keputusan blueprint #3 (model timer fleksibel per paket).
 *   - `price` DECIMAL rupiah; tampilan & API membulatkannya ke
 *     rupiah penuh (int).
 *   - Tidak ada kolom deskripsi pada paket.
 */
final class Package
{
    /** @var array<string, string> Mode timer sah -> label Indonesia. */
    public const TIMER_MODES = [
        'per_session' => 'Per Sesi',
        'per_duration' => 'Per Durasi',
        'combined' => 'Gabungan (Sesi + Durasi)',
    ];

    /* -----------------------------------------------------------------
     * Pembacaan
     * --------------------------------------------------------------- */

    /**
     * Semua paket milik tenant (lewat event induknya), lengkap
     * dengan nama & slug event — untuk halaman daftar paket.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forTenant(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            'SELECT p.*, e.name AS event_name, e.slug AS event_slug
               FROM packages p
               JOIN events e ON e.id = p.event_id
              WHERE e.tenant_id = :tenant_id
              ORDER BY e.id DESC, p.sort_order ASC, p.id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM packages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Paket hanya bila event induknya milik tenant ini. */
    public static function findForTenant(PDO $pdo, int $tenantId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT p.*, e.name AS event_name, e.slug AS event_slug
               FROM packages p
               JOIN events e ON e.id = p.event_id
              WHERE p.id = :id AND e.tenant_id = :tenant_id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Paket pada satu event (urut tampilan).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forEvent(PDO $pdo, int $eventId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM packages WHERE event_id = :event_id';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Paket aktif milik tenant untuk API aplikasi booth (beserta
     * event_id agar aplikasi memasangkan paket ke event-nya).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forApi(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.event_id, p.name, p.timer_mode,
                    p.session_count, p.duration_minutes, p.price
               FROM packages p
               JOIN events e ON e.id = p.event_id
              WHERE e.tenant_id = :tenant_id
                AND p.is_active = 1
              ORDER BY p.event_id ASC, p.sort_order ASC, p.id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /* -----------------------------------------------------------------
     * Validasi & simpan
     * --------------------------------------------------------------- */

    /**
     * Validasi + normalisasi isian formulir paket. Event induk
     * WAJIB milik tenant yang sedang login.
     *
     * @param array<string, mixed>      $input    Isi $_POST.
     * @param array<string, mixed>|null $existing Baris paket (mode ubah) atau null (mode buat).
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function validate(PDO $pdo, int $tenantId, array $input, ?array $existing = null): array
    {
        $errors = [];
        $data = [];

        // --- Event induk (wajib milik tenant ini) --------------------
        $eventId = isset($input['event_id']) && is_numeric($input['event_id'])
            ? (int) $input['event_id']
            : 0;
        $event = $eventId > 0 ? Event::findForTenant($pdo, $tenantId, $eventId) : null;
        if ($event === null) {
            $errors['event_id'] = 'Pilih event yang valid untuk paket ini.';
        }
        $data['event_id'] = $eventId;

        // --- Nama ------------------------------------------------------
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Nama paket wajib diisi.';
        } elseif (self::length($name) > 120) {
            $errors['name'] = 'Nama paket maksimal 120 karakter.';
        }
        $data['name'] = $name;

        // --- Mode timer --------------------------------------------------
        $mode = (string) ($input['timer_mode'] ?? 'per_session');
        if (!isset(self::TIMER_MODES[$mode])) {
            $errors['timer_mode'] = 'Mode timer tidak sah.';
            $mode = 'per_session';
        }
        $data['timer_mode'] = $mode;

        // --- Jumlah sesi & durasi (wajib sesuai mode) ---------------------
        [$sessionCount, $sessionError] = self::nullableInt($input['session_count'] ?? null);
        if ($sessionError !== null) {
            $errors['session_count'] = 'Jumlah sesi ' . $sessionError;
        }
        [$duration, $durationError] = self::nullableInt($input['duration_minutes'] ?? null);
        if ($durationError !== null) {
            $errors['duration_minutes'] = 'Durasi ' . $durationError;
        }

        if (($mode === 'per_session' || $mode === 'combined')
            && ($sessionCount === null || $sessionCount < 1)
            && !isset($errors['session_count'])
        ) {
            $errors['session_count'] = 'Jumlah sesi wajib diisi (minimal 1) untuk mode ini.';
        }
        if (($mode === 'per_duration' || $mode === 'combined')
            && ($duration === null || $duration < 1)
            && !isset($errors['duration_minutes'])
        ) {
            $errors['duration_minutes'] = 'Durasi wajib diisi (minimal 1 menit) untuk mode ini.';
        }
        $data['session_count'] = $sessionCount;
        $data['duration_minutes'] = $duration;

        // --- Harga ---------------------------------------------------------
        // Pola yang sama seperti harga plan: terima "150000",
        // "150.000", "Rp 150.000" (titik = ribuan, koma = desimal).
        $priceRaw = trim((string) ($input['price'] ?? ''));
        $priceRaw = preg_replace('/^rp\s*/i', '', $priceRaw) ?? $priceRaw;
        $priceRaw = str_replace([' ', '.'], '', $priceRaw);
        $priceRaw = str_replace(',', '.', $priceRaw);
        if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
            $errors['price'] = 'Harga harus berupa angka 0 atau lebih (mis. 25000).';
            $data['price'] = '0.00';
        } else {
            $data['price'] = number_format((float) $priceRaw, 2, '.', '');
        }

        // --- Urutan tampil ---------------------------------------------------
        $sortRaw = trim((string) ($input['sort_order'] ?? ''));
        if ($sortRaw === '') {
            $data['sort_order'] = 0;
        } elseif (preg_match('/^-?\d+$/', $sortRaw) === 1) {
            $data['sort_order'] = (int) $sortRaw;
        } else {
            $errors['sort_order'] = 'Urutan tampil harus berupa bilangan bulat.';
            $data['sort_order'] = 0;
        }

        // --- Status aktif ------------------------------------------------------
        $data['is_active'] = isset($input['is_active']) ? 1 : 0;

        return [$data, $errors];
    }

    /**
     * @param array<string, mixed> $data Hasil Package::validate().
     * @return int ID paket baru.
     */
    public static function create(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO packages
                (event_id, name, timer_mode, session_count,
                 duration_minutes, price, is_active, sort_order)
             VALUES
                (:event_id, :name, :timer_mode, :session_count,
                 :duration_minutes, :price, :is_active, :sort_order)'
        );
        $stmt->execute([
            'event_id' => $data['event_id'],
            'name' => $data['name'],
            'timer_mode' => $data['timer_mode'],
            'session_count' => $data['session_count'],
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data Hasil Package::validate(). */
    public static function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE packages
                SET event_id = :event_id,
                    name = :name,
                    timer_mode = :timer_mode,
                    session_count = :session_count,
                    duration_minutes = :duration_minutes,
                    price = :price,
                    is_active = :is_active,
                    sort_order = :sort_order
              WHERE id = :id'
        );
        $stmt->execute([
            'event_id' => $data['event_id'],
            'name' => $data['name'],
            'timer_mode' => $data['timer_mode'],
            'session_count' => $data['session_count'],
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
            'id' => $id,
        ]);
    }

    /* -----------------------------------------------------------------
     * Tampilan
     * --------------------------------------------------------------- */

    /** Label Indonesia untuk mode timer. */
    public static function timerModeLabel(string $mode): string
    {
        return self::TIMER_MODES[$mode] ?? $mode;
    }

    /** Harga DECIMAL -> "Rp 150.000" (rupiah penuh). */
    public static function formatPrice(mixed $price): string
    {
        return 'Rp ' . number_format((float) ($price ?? 0), 0, ',', '.');
    }

    /**
     * Nilai tampilan formulir paket.
     *
     * @param array<string, mixed>|null $package
     * @param array<string, mixed>|null $post
     * @return array<string, mixed>
     */
    public static function formValues(?array $package = null, ?array $post = null): array
    {
        $values = [
            'event_id' => '',
            'name' => '',
            'timer_mode' => 'per_session',
            'session_count' => '',
            'duration_minutes' => '',
            'price' => '',
            'sort_order' => '0',
            'is_active' => true,
        ];

        if ($post !== null) {
            foreach (['event_id', 'name', 'timer_mode', 'session_count', 'duration_minutes', 'price', 'sort_order'] as $field) {
                if (isset($post[$field]) && is_string($post[$field])) {
                    $values[$field] = $post[$field];
                }
            }
            $values['is_active'] = isset($post['is_active']);
            return $values;
        }

        if ($package !== null) {
            $values['event_id'] = (string) (int) ($package['event_id'] ?? 0);
            $values['name'] = (string) ($package['name'] ?? '');
            $values['timer_mode'] = (string) ($package['timer_mode'] ?? 'per_session');
            $values['session_count'] = ($package['session_count'] ?? null) !== null
                ? (string) (int) $package['session_count'] : '';
            $values['duration_minutes'] = ($package['duration_minutes'] ?? null) !== null
                ? (string) (int) $package['duration_minutes'] : '';
            $price = (string) ($package['price'] ?? '');
            if ($price !== '' && str_contains($price, '.')) {
                // Tampilkan harga tanpa ,00 agar mudah dibaca/diedit.
                $price = rtrim(rtrim($price, '0'), '.');
                if ($price === '') {
                    $price = '0';
                }
            }
            $values['price'] = $price;
            $values['sort_order'] = (string) (int) ($package['sort_order'] ?? 0);
            $values['is_active'] = (int) ($package['is_active'] ?? 0) === 1;
        }

        return $values;
    }

    /* -----------------------------------------------------------------
     * Helper internal
     * --------------------------------------------------------------- */

    /**
     * Bilangan bulat nullable dari isian formulir: string kosong ->
     * [null, null]; bukan bilangan >= 0 -> [null, pesan error].
     *
     * @return array{0: ?int, 1: ?string}
     */
    private static function nullableInt(mixed $raw): array
    {
        if ($raw === null) {
            return [null, null];
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [null, null];
        }
        if (preg_match('/^\d+$/', $raw) !== 1) {
            return [null, 'harus berupa bilangan bulat 0 atau lebih.'];
        }

        return [(int) $raw, null];
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
