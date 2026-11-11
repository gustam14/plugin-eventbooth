<?php
declare(strict_types=1);

/**
 * Katalog paket tingkat tenant (tabel `package_templates`,
 * v0.28.0) — "Daftar Paket": cetakan paket (nama, mode timer,
 * durasi/sesi, harga) yang dipakai berulang lintas event.
 * Menyalin katalog ke sebuah event membuat baris `packages`
 * biasa pada event itu (yang dibaca aplikasi booth), sehingga
 * perubahan katalog sesudahnya tidak mengubah paket event
 * yang sudah berjalan.
 */
final class PackageTemplate
{
    /* -----------------------------------------------------------------
     * Pembacaan
     * --------------------------------------------------------------- */

    /** @return array<int, array<string, mixed>> */
    public static function forTenant(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM package_templates
              WHERE tenant_id = :tenant_id
              ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function findForTenant(PDO $pdo, int $tenantId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM package_templates
              WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /* -----------------------------------------------------------------
     * Validasi & simpan — pola yang sama dengan Package::validate()
     * dikurangi event induk.
     * --------------------------------------------------------------- */

    /**
     * @param array<string, mixed> $input Isi $_POST.
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function validate(array $input): array
    {
        $errors = [];
        $data = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Nama paket wajib diisi.';
        } elseif (self::length($name) > 120) {
            $errors['name'] = 'Nama paket maksimal 120 karakter.';
        }
        $data['name'] = $name;

        $mode = (string) ($input['timer_mode'] ?? 'per_session');
        if (!isset(Package::TIMER_MODES[$mode])) {
            $errors['timer_mode'] = 'Mode timer tidak sah.';
            $mode = 'per_session';
        }
        $data['timer_mode'] = $mode;

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
            && !isset($errors['session_count'])) {
            $errors['session_count'] = 'Jumlah sesi wajib diisi (minimal 1) untuk mode ini.';
        }
        if (($mode === 'per_duration' || $mode === 'combined')
            && ($duration === null || $duration < 1)
            && !isset($errors['duration_minutes'])) {
            $errors['duration_minutes'] = 'Durasi wajib diisi (minimal 1 menit) untuk mode ini.';
        }
        $data['session_count'] = $sessionCount;
        $data['duration_minutes'] = $duration;

        [$price, $priceError] = self::parseMoney($input['price'] ?? null);
        if ($priceError !== null) {
            $errors['price'] = $priceError;
        }
        $data['price'] = $price ?? '0.00';

        $sortRaw = trim((string) ($input['sort_order'] ?? ''));
        if ($sortRaw === '') {
            $data['sort_order'] = 0;
        } elseif (preg_match('/^-?\d+$/', $sortRaw) === 1) {
            $data['sort_order'] = (int) $sortRaw;
        } else {
            $errors['sort_order'] = 'Urutan tampil harus berupa bilangan bulat.';
            $data['sort_order'] = 0;
        }

        $data['is_active'] = isset($input['is_active']) ? 1 : 0;

        return [$data, $errors];
    }

    /** @param array<string, mixed> $data Hasil validate(). */
    public static function create(PDO $pdo, int $tenantId, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO package_templates
                (tenant_id, name, timer_mode, session_count,
                 duration_minutes, price, is_active, sort_order)
             VALUES
                (:tenant_id, :name, :timer_mode, :session_count,
                 :duration_minutes, :price, :is_active, :sort_order)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
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

    /** @param array<string, mixed> $data Hasil validate(). */
    public static function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE package_templates
                SET name = :name, timer_mode = :timer_mode,
                    session_count = :session_count,
                    duration_minutes = :duration_minutes,
                    price = :price, is_active = :is_active,
                    sort_order = :sort_order
              WHERE id = :id'
        );
        $stmt->execute([
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

    public static function delete(PDO $pdo, int $tenantId, int $id): void
    {
        $stmt = $pdo->prepare(
            'DELETE FROM package_templates WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * Salin katalog ini menjadi paket sungguhan pada sebuah
     * event milik tenant yang sama.
     *
     * @param array<string, mixed> $template Baris package_templates.
     * @return int ID paket baru pada event.
     */
    public static function applyToEvent(PDO $pdo, array $template, int $eventId): int
    {
        return Package::create($pdo, [
            'event_id' => $eventId,
            'name' => (string) $template['name'],
            'timer_mode' => (string) $template['timer_mode'],
            'session_count' => ($template['session_count'] ?? null) !== null
                ? (int) $template['session_count'] : null,
            'duration_minutes' => ($template['duration_minutes'] ?? null) !== null
                ? (int) $template['duration_minutes'] : null,
            'price' => (string) ($template['price'] ?? '0.00'),
            'is_active' => (int) ($template['is_active'] ?? 1),
            'sort_order' => (int) ($template['sort_order'] ?? 0),
        ]);
    }

    /**
     * Nilai tampilan formulir katalog.
     *
     * @param array<string, mixed>|null $template
     * @param array<string, mixed>|null $post
     * @return array<string, mixed>
     */
    public static function formValues(?array $template = null, ?array $post = null): array
    {
        // Bentuk nilainya sama dengan formulir paket event.
        return Package::formValues($template, $post);
    }

    /* -----------------------------------------------------------------
     * Helper internal (pola sama seperti Package)
     * --------------------------------------------------------------- */

    /** @return array{0: ?int, 1: ?string} */
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

    /**
     * Uang dari isian formulir: terima "150000", "150.000",
     * "Rp 150.000" (titik = ribuan, koma = desimal).
     *
     * @return array{0: ?string, 1: ?string} [nilai DECIMAL, pesan error]
     */
    public static function parseMoney(mixed $raw): array
    {
        $value = trim((string) ($raw ?? ''));
        $value = preg_replace('/^rp\s*/i', '', $value) ?? $value;
        $value = str_replace([' ', '.'], '', $value);
        $value = str_replace(',', '.', $value);
        if ($value === '' || !is_numeric($value) || (float) $value < 0) {
            return [null, 'Jumlah harus berupa angka 0 atau lebih (mis. 250000).'];
        }

        return [number_format((float) $value, 2, '.', ''), null];
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
