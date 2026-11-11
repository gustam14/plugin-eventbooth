<?php
declare(strict_types=1);

/**
 * Daftar Paket — paket LAYANAN untuk klien event/wedding
 * (tabel `package_templates`, v0.28.0; bentuk disesuaikan
 * v0.29.0 atas penegasan pemilik 11 Nov 2026).
 *
 * Paket ini BERBEDA dari Paket Sesi (tabel `packages`):
 *   - Daftar Paket = penawaran jasa ke klien acara — nama
 *     paket, durasi layanan (jam), foto/cetakan termasuk,
 *     isi/fasilitas, dan harga total untuk klien.
 *   - Paket Sesi = paket yang dipilih tamu pada booth
 *     self-photo / photobox mandiri (mode timer & harga
 *     per sesi).
 * Nama paket pada Buku Klien dapat dipilih dari daftar ini
 * (tetap disimpan sebagai teks pada catatan booking, agar
 * perubahan katalog tidak mengubah kesepakatan lama).
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

    /**
     * Nama-nama paket aktif tenant — pilihan pada formulir
     * Buku Klien.
     *
     * @return array<int, string>
     */
    public static function names(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            'SELECT name FROM package_templates
              WHERE tenant_id = :tenant_id AND is_active = 1
              ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $names = [];
        foreach ($stmt->fetchAll() as $row) {
            $names[] = (string) $row['name'];
        }

        return $names;
    }

    /* -----------------------------------------------------------------
     * Validasi & simpan
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

        // Durasi layanan dalam jam (boleh pecahan: 2,5 jam).
        $durationRaw = trim((string) ($input['duration_hours'] ?? ''));
        $durationRaw = str_replace(',', '.', $durationRaw);
        if ($durationRaw === '') {
            $data['duration_hours'] = null;
        } elseif (is_numeric($durationRaw) && (float) $durationRaw > 0 && (float) $durationRaw <= 999.9) {
            $data['duration_hours'] = number_format((float) $durationRaw, 1, '.', '');
        } else {
            $errors['duration_hours'] = 'Durasi harus berupa angka jam lebih dari 0 (mis. 3 atau 2,5).';
            $data['duration_hours'] = null;
        }

        // Jumlah foto/cetakan termasuk (kosong = unlimited).
        $printsRaw = trim((string) ($input['prints_included'] ?? ''));
        if ($printsRaw === '') {
            $data['prints_included'] = null;
        } elseif (preg_match('/^\d+$/', $printsRaw) === 1 && (int) $printsRaw >= 1) {
            $data['prints_included'] = (int) $printsRaw;
        } else {
            $errors['prints_included'] = 'Jumlah foto harus berupa bilangan bulat 1 atau lebih, atau kosongkan untuk unlimited.';
            $data['prints_included'] = null;
        }

        $description = trim((string) ($input['description'] ?? ''));
        $data['description'] = $description !== '' ? $description : null;

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
                (tenant_id, name, duration_hours, prints_included,
                 description, price, is_active, sort_order)
             VALUES
                (:tenant_id, :name, :duration_hours, :prints_included,
                 :description, :price, :is_active, :sort_order)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'duration_hours' => $data['duration_hours'],
            'prints_included' => $data['prints_included'],
            'description' => $data['description'],
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
                SET name = :name, duration_hours = :duration_hours,
                    prints_included = :prints_included,
                    description = :description, price = :price,
                    is_active = :is_active, sort_order = :sort_order
              WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['name'],
            'duration_hours' => $data['duration_hours'],
            'prints_included' => $data['prints_included'],
            'description' => $data['description'],
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

    /* -----------------------------------------------------------------
     * Tampilan
     * --------------------------------------------------------------- */

    /** Durasi layanan -> "3 jam" / "2,5 jam" / "Tanpa batas jam". */
    public static function formatDuration(mixed $hours): string
    {
        if ($hours === null || $hours === '') {
            return 'Tanpa batas jam';
        }
        $text = number_format((float) $hours, 1, ',', '.');
        $text = rtrim(rtrim($text, '0'), ',');

        return $text . ' jam';
    }

    /** Foto termasuk -> "400 foto" / "Unlimited". */
    public static function formatPrints(mixed $prints): string
    {
        if ($prints === null || $prints === '') {
            return 'Unlimited';
        }

        return number_format((int) $prints, 0, ',', '.') . ' foto';
    }

    /**
     * Nilai tampilan formulir daftar paket.
     *
     * @param array<string, mixed>|null $template
     * @param array<string, mixed>|null $post
     * @return array<string, mixed>
     */
    public static function formValues(?array $template = null, ?array $post = null): array
    {
        $values = [
            'name' => '',
            'duration_hours' => '',
            'prints_included' => '',
            'description' => '',
            'price' => '',
            'sort_order' => '0',
            'is_active' => true,
        ];

        if ($post !== null) {
            foreach (['name', 'duration_hours', 'prints_included', 'description', 'price', 'sort_order'] as $field) {
                if (isset($post[$field]) && is_string($post[$field])) {
                    $values[$field] = $post[$field];
                }
            }
            $values['is_active'] = isset($post['is_active']);
            return $values;
        }

        if ($template !== null) {
            $values['name'] = (string) ($template['name'] ?? '');
            if (($template['duration_hours'] ?? null) !== null) {
                $duration = number_format((float) $template['duration_hours'], 1, ',', '.');
                $values['duration_hours'] = rtrim(rtrim($duration, '0'), ',');
            }
            $values['prints_included'] = ($template['prints_included'] ?? null) !== null
                ? (string) (int) $template['prints_included'] : '';
            $values['description'] = (string) ($template['description'] ?? '');
            $price = (string) ($template['price'] ?? '');
            if ($price !== '' && str_contains($price, '.')) {
                $price = rtrim(rtrim($price, '0'), '.');
                if ($price === '') {
                    $price = '0';
                }
            }
            $values['price'] = $price;
            $values['sort_order'] = (string) (int) ($template['sort_order'] ?? 0);
            $values['is_active'] = (int) ($template['is_active'] ?? 0) === 1;
        }

        return $values;
    }

    /**
     * Uang dari isian formulir: terima "150000", "150.000",
     * "Rp 150.000" (titik = ribuan, koma = desimal).
     * Dipakai juga oleh ClientBook.
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
