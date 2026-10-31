<?php
declare(strict_types=1);

/**
 * Repository + validasi untuk plan lisensi (tabel `plans`).
 *
 * Plan sepenuhnya diatur dari dashboard Super Admin (keputusan
 * blueprint yang dikunci: plan TIDAK di-hardcode di kode; baris seed
 * di schema.sql hanya contoh awal).
 *
 * Konvensi skema: nilai 0 pada kolom batas (max_events,
 * drive_quota_gb, session_quota) berarti "tanpa batas".
 */
final class Plan
{
    /**
     * Flag fitur plan — HANYA kemampuan yang dikendalikan aplikasi
     * pendamping ini, disimpan sebagai JSON di kolom `features`.
     * GIF/Boomerang/Video adalah kemampuan DSLRBooth sendiri dan
     * BUKAN flag plan. JSON yang tersimpan selalu berisi tepat
     * kunci-kunci ini (flag asing dibuang saat plan disimpan).
     */
    public const FEATURE_FLAGS = [
        'payment_lock' => 'Kunci Pembayaran',
        'whatsapp_share' => 'Berbagi via WhatsApp',
    ];

    /** @return array<int, array<string, mixed>> Semua plan, urut tampilan. */
    public static function all(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, name, slug, description, price, billing_period_days,
                    max_devices, max_events, drive_quota_gb, session_quota,
                    retention_days, features, is_active, sort_order,
                    created_at, updated_at
               FROM plans
              ORDER BY sort_order ASC, id ASC'
        );

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, slug, description, price, billing_period_days,
                    max_devices, max_events, drive_quota_gb, session_quota,
                    retention_days, features, is_active, sort_order,
                    created_at, updated_at
               FROM plans
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data Hasil Plan::validate().
     * @return int ID plan baru.
     */
    public static function create(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO plans
                (name, slug, description, price, billing_period_days,
                 max_devices, max_events, drive_quota_gb, session_quota,
                 retention_days, features, is_active, sort_order)
             VALUES
                (:name, :slug, :description, :price, :billing_period_days,
                 :max_devices, :max_events, :drive_quota_gb, :session_quota,
                 :retention_days, :features, :is_active, :sort_order)'
        );
        $stmt->execute(self::bindable($data));

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data Hasil Plan::validate(). */
    public static function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE plans
                SET name = :name,
                    slug = :slug,
                    description = :description,
                    price = :price,
                    billing_period_days = :billing_period_days,
                    max_devices = :max_devices,
                    max_events = :max_events,
                    drive_quota_gb = :drive_quota_gb,
                    session_quota = :session_quota,
                    retention_days = :retention_days,
                    features = :features,
                    is_active = :is_active,
                    sort_order = :sort_order
              WHERE id = :id'
        );
        $params = self::bindable($data);
        $params['id'] = $id;
        $stmt->execute($params);
    }

    public static function setActive(PDO $pdo, int $id, bool $active): void
    {
        $stmt = $pdo->prepare('UPDATE plans SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public static function slugTaken(PDO $pdo, string $slug, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM plans WHERE slug = :slug');
            $stmt->execute(['slug' => $slug]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM plans WHERE slug = :slug AND id <> :id'
            );
            $stmt->execute(['slug' => $slug, 'id' => $exceptId]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Decode kolom features (JSON) menjadi array asosiatif flag.
     *
     * @param array<string, mixed> $plan Baris plan dari database.
     * @return array<string, mixed>
     */
    public static function featuresOf(array $plan): array
    {
        $raw = $plan['features'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Buat slug URL-aman dari nama plan. */
    public static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    /**
     * Validasi + normalisasi isian formulir plan.
     *
     * @param array<string, mixed>      $input    Isi $_POST.
     * @param array<string, mixed>|null $existing Baris plan (mode ubah) atau null (mode buat).
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     *         [data ternormalisasi, peta error per kolom]
     */
    public static function validate(PDO $pdo, array $input, ?array $existing = null): array
    {
        $errors = [];
        $data = [];

        // --- Nama ---------------------------------------------------
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Nama plan wajib diisi.';
        } elseif (self::length($name) > 60) {
            $errors['name'] = 'Nama plan maksimal 60 karakter.';
        }
        $data['name'] = $name;

        // --- Slug (otomatis dari nama bila dikosongkan) -------------
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        if ($slug === '') {
            $slug = self::slugify($name);
        }
        if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $errors['slug'] = 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.';
        } elseif (self::slugTaken($pdo, $slug, $existing !== null ? (int) $existing['id'] : null)) {
            $errors['slug'] = 'Slug ini sudah dipakai plan lain.';
        }
        $data['slug'] = $slug;

        // --- Deskripsi ----------------------------------------------
        $description = trim((string) ($input['description'] ?? ''));
        if (self::length($description) > 255) {
            $errors['description'] = 'Deskripsi maksimal 255 karakter.';
        }
        $data['description'] = $description === '' ? null : $description;

        // --- Harga ----------------------------------------------------
        // Terima format Indonesia: "149000", "149.000", "Rp 149.000".
        // Titik diperlakukan sebagai pemisah ribuan, koma sebagai
        // pemisah desimal.
        $priceRaw = trim((string) ($input['price'] ?? ''));
        $priceRaw = preg_replace('/^rp\s*/i', '', $priceRaw) ?? $priceRaw;
        $priceRaw = str_replace([' ', '.'], '', $priceRaw);
        $priceRaw = str_replace(',', '.', $priceRaw);
        if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
            $errors['price'] = 'Harga harus berupa angka 0 atau lebih (mis. 149000).';
            $data['price'] = '0.00';
        } else {
            $data['price'] = number_format((float) $priceRaw, 2, '.', '');
        }

        // --- Kolom bilangan bulat ------------------------------------
        $intFields = [
            'billing_period_days' => ['label' => 'Periode tagihan (hari)', 'min' => 1, 'default' => 30],
            'max_devices' => ['label' => 'Maks. perangkat', 'min' => 0, 'default' => 1],
            'max_events' => ['label' => 'Maks. event', 'min' => 0, 'default' => 0],
            'drive_quota_gb' => ['label' => 'Kuota Drive (GB)', 'min' => 0, 'default' => 0],
            'session_quota' => ['label' => 'Kuota sesi per periode', 'min' => 0, 'default' => 0],
            'retention_days' => ['label' => 'Retensi album (hari)', 'min' => 0, 'default' => 90],
            'sort_order' => ['label' => 'Urutan tampil', 'min' => null, 'default' => 0],
        ];

        foreach ($intFields as $field => $rule) {
            $raw = trim((string) ($input[$field] ?? ''));
            if ($raw === '') {
                $data[$field] = $rule['default'];
                continue;
            }
            if (preg_match('/^-?\d+$/', $raw) !== 1) {
                $errors[$field] = $rule['label'] . ' harus berupa bilangan bulat.';
                $data[$field] = $rule['default'];
                continue;
            }
            $value = (int) $raw;
            if ($rule['min'] !== null && $value < $rule['min']) {
                $errors[$field] = $rule['label'] . ' minimal ' . $rule['min'] . '.';
            }
            $data[$field] = $value;
        }

        // --- Fitur (checkbox) — tepat flag FEATURE_FLAGS saja --------
        $features = [];
        $postedFeatures = $input['features'] ?? [];
        if (!is_array($postedFeatures)) {
            $postedFeatures = [];
        }
        foreach (self::FEATURE_FLAGS as $key => $label) {
            $features[$key] = isset($postedFeatures[$key]);
        }
        $data['features'] = $features;

        // --- Status aktif ---------------------------------------------
        $data['is_active'] = isset($input['is_active']) ? 1 : 0;

        return [$data, $errors];
    }

    /**
     * Nilai tampilan formulir: dari baris database (mode ubah), dari
     * isian mentah $_POST (setelah validasi gagal), atau nilai bawaan
     * (mode buat).
     *
     * @param array<string, mixed>|null $plan
     * @param array<string, mixed>|null $post
     * @return array<string, mixed>
     */
    public static function formValues(?array $plan = null, ?array $post = null): array
    {
        $values = [
            'name' => '',
            'slug' => '',
            'description' => '',
            'price' => '',
            'billing_period_days' => '30',
            'max_devices' => '1',
            'max_events' => '0',
            'drive_quota_gb' => '0',
            'session_quota' => '0',
            'retention_days' => '90',
            'sort_order' => '0',
            'is_active' => true,
            'features' => array_fill_keys(array_keys(self::FEATURE_FLAGS), false),
        ];

        $stringFields = [
            'name', 'slug', 'description', 'price', 'billing_period_days',
            'max_devices', 'max_events', 'drive_quota_gb', 'session_quota',
            'retention_days', 'sort_order',
        ];

        if ($post !== null) {
            foreach ($stringFields as $field) {
                if (isset($post[$field]) && is_string($post[$field])) {
                    $values[$field] = $post[$field];
                }
            }
            $values['is_active'] = isset($post['is_active']);
            $postedFeatures = $post['features'] ?? [];
            if (is_array($postedFeatures)) {
                foreach (self::FEATURE_FLAGS as $key => $label) {
                    $values['features'][$key] = isset($postedFeatures[$key]);
                }
            }
            return $values;
        }

        if ($plan !== null) {
            foreach ($stringFields as $field) {
                if (isset($plan[$field]) && $plan[$field] !== null) {
                    $values[$field] = (string) $plan[$field];
                }
            }
            if (is_string($values['price']) && str_contains($values['price'], '.')) {
                // Tampilkan harga tanpa ,00 agar mudah dibaca/diedit.
                $values['price'] = rtrim(rtrim($values['price'], '0'), '.');
                if ($values['price'] === '') {
                    $values['price'] = '0';
                }
            }
            $values['is_active'] = (int) ($plan['is_active'] ?? 0) === 1;
            $decoded = self::featuresOf($plan);
            foreach (self::FEATURE_FLAGS as $key => $label) {
                $values['features'][$key] = !empty($decoded[$key]);
            }
        }

        return $values;
    }

    /**
     * Siapkan data tervalidasi untuk execute() prepared statement.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function bindable(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'price' => $data['price'],
            'billing_period_days' => $data['billing_period_days'],
            'max_devices' => $data['max_devices'],
            'max_events' => $data['max_events'],
            'drive_quota_gb' => $data['drive_quota_gb'],
            'session_quota' => $data['session_quota'],
            'retention_days' => $data['retention_days'],
            'features' => json_encode($data['features'], JSON_UNESCAPED_SLASHES),
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ];
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
