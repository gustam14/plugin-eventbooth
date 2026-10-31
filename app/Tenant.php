<?php
declare(strict_types=1);

/**
 * Repository + validasi tenant (tabel `tenants`) — Milestone 4.
 *
 * Data tenant sengaja LENGKAP (nama vendor, penanggung jawab, email,
 * WhatsApp, alamat, catatan) karena kontaknya dipakai untuk
 * notifikasi perpanjangan lisensi otomatis.
 *
 * Keputusan pemetaan kolom (skema nyata):
 *   - `vendor_name` (M4) adalah nama vendor/bisnis kanonis. Kolom
 *     lama `name` TETAP diisi dengan nilai yang sama saat menyimpan
 *     (sinkron), karena pembaca lama (mis. ringkasan tenant) memakai
 *     `name`. Pembacaan memakai vendor_name bila terisi, selain itu
 *     jatuh ke `name`.
 *   - `whatsapp` (M4) adalah nomor kanonis ternormalisasi (62…).
 *     Kolom lama `phone` disinkronkan dengan nilai yang sama.
 *   - Status memakai enum skema: 'active' (Aktif) / 'suspended'
 *     (Nonaktif). Menonaktifkan tenant membuat API lisensi menolak
 *     semua lisensinya (kode tenant_inactive) tanpa mengubah baris
 *     lisensi itu sendiri.
 */
final class Tenant
{
    /**
     * Daftar tenant + ringkasan lisensi aktif terakhir per tenant.
     * Kolom vendor_name/whatsapp/address hanya ada setelah migrasi
     * M4 — pemanggil membungkus dengan try/catch PDOException.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(PDO $pdo, string $query = ''): array
    {
        $sql = 'SELECT t.*,
                       al.expires_at AS license_expires_at,
                       ap.name AS license_plan_name
                  FROM tenants t
                  LEFT JOIN (
                        SELECT tenant_id, plan_id, expires_at
                          FROM licenses
                         WHERE id IN (
                               SELECT MAX(id) FROM licenses
                                WHERE status IN (\'active\', \'pending\')
                                GROUP BY tenant_id
                         )
                  ) al ON al.tenant_id = t.id
                  LEFT JOIN plans ap ON ap.id = al.plan_id';

        $params = [];
        $query = trim($query);
        if ($query !== '') {
            // Placeholder bernama tidak boleh diulang pada prepared
            // statement natif PDO — satu nama per kolom yang dicari.
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $sql .= ' WHERE t.vendor_name LIKE :q1
                         OR t.name LIKE :q2
                         OR t.contact_name LIKE :q3
                         OR t.email LIKE :q4
                         OR t.whatsapp LIKE :q5
                         OR t.phone LIKE :q6';
            $params = [
                'q1' => $like, 'q2' => $like, 'q3' => $like,
                'q4' => $like, 'q5' => $like, 'q6' => $like,
            ];
        }

        $sql .= ' ORDER BY COALESCE(NULLIF(t.vendor_name, \'\'), t.name) ASC, t.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Akun login (users) milik tenant.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function usersOf(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, email, role, is_active, last_login_at, created_at
               FROM users
              WHERE tenant_id = :tenant_id
              ORDER BY id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** Nama tampilan tenant: vendor_name bila ada, selain itu name. */
    public static function displayName(array $tenant): string
    {
        $vendor = trim((string) ($tenant['vendor_name'] ?? ''));
        return $vendor !== '' ? $vendor : (string) ($tenant['name'] ?? '');
    }

    /** WhatsApp ternormalisasi tenant (kolom whatsapp, cadangan phone). */
    public static function whatsappOf(array $tenant): ?string
    {
        return Whatsapp::normalize($tenant['whatsapp'] ?? null)
            ?? Whatsapp::normalize($tenant['phone'] ?? null);
    }

    public static function emailTaken(PDO $pdo, string $email, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE email = :email');
            $stmt->execute(['email' => $email]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE email = :email AND id <> :id');
            $stmt->execute(['email' => $email, 'id' => $exceptId]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function userEmailTaken(PDO $pdo, string $email): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Buat slug URL-aman dari teks. */
    public static function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    /** Slug unik untuk tenants (tambah akhiran -2, -3, … bila bentrok). */
    public static function uniqueSlug(PDO $pdo, string $base, ?int $exceptId = null): string
    {
        $base = $base !== '' ? $base : 'tenant';
        $slug = $base;
        $suffix = 2;

        while (true) {
            if ($exceptId === null) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE slug = :slug');
                $stmt->execute(['slug' => $slug]);
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE slug = :slug AND id <> :id');
                $stmt->execute(['slug' => $slug, 'id' => $exceptId]);
            }
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    /**
     * Validasi + normalisasi isian formulir tenant.
     *
     * @param array<string, mixed>      $input    Isi $_POST.
     * @param array<string, mixed>|null $existing Baris tenant (mode ubah) atau null (mode buat).
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function validate(PDO $pdo, array $input, ?array $existing = null): array
    {
        $errors = [];
        $data = [];

        // --- Nama vendor -------------------------------------------
        $vendor = trim((string) ($input['vendor_name'] ?? ''));
        if ($vendor === '') {
            $errors['vendor_name'] = 'Nama vendor wajib diisi.';
        } elseif (self::length($vendor) > 160) {
            $errors['vendor_name'] = 'Nama vendor maksimal 160 karakter.';
        }
        $data['vendor_name'] = $vendor;

        // --- Penanggung jawab ---------------------------------------
        $contact = trim((string) ($input['contact_name'] ?? ''));
        if ($contact === '') {
            $errors['contact_name'] = 'Nama penanggung jawab wajib diisi.';
        } elseif (self::length($contact) > 120) {
            $errors['contact_name'] = 'Nama penanggung jawab maksimal 120 karakter.';
        }
        $data['contact_name'] = $contact;

        // --- Email (unik antar tenant) -------------------------------
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = 'Email wajib diisi.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Format email tidak sah.';
        } elseif (self::emailTaken($pdo, $email, $existing !== null ? (int) $existing['id'] : null)) {
            $errors['email'] = 'Email ini sudah dipakai tenant lain.';
        }
        $data['email'] = $email;

        // --- WhatsApp (wajib, ternormalisasi) ------------------------
        $whatsappRaw = trim((string) ($input['whatsapp'] ?? ''));
        $whatsapp = Whatsapp::normalize($whatsappRaw);
        if ($whatsappRaw === '') {
            $errors['whatsapp'] = 'Nomor WhatsApp wajib diisi (untuk notifikasi perpanjangan).';
        } elseif ($whatsapp === null) {
            $errors['whatsapp'] = 'Nomor WhatsApp tidak sah. Gunakan nomor Indonesia, mis. 0812 3456 7890.';
        }
        $data['whatsapp'] = $whatsapp ?? '';

        // --- Alamat & catatan (opsional) ------------------------------
        $address = trim((string) ($input['address'] ?? ''));
        if (self::length($address) > 1000) {
            $errors['address'] = 'Alamat maksimal 1000 karakter.';
        }
        $data['address'] = $address === '' ? null : $address;

        $notes = trim((string) ($input['notes'] ?? ''));
        if (self::length($notes) > 2000) {
            $errors['notes'] = 'Catatan maksimal 2000 karakter.';
        }
        $data['notes'] = $notes === '' ? null : $notes;

        // --- Status (hanya mode ubah; buat selalu aktif) --------------
        if ($existing !== null) {
            $status = (string) ($input['status'] ?? $existing['status'] ?? 'active');
            $data['status'] = in_array($status, ['active', 'suspended'], true) ? $status : 'active';
        } else {
            $data['status'] = 'active';
        }

        return [$data, $errors];
    }

    /**
     * Validasi seksi opsional "Akun login tenant" pada formulir buat.
     * Seksi dianggap dipakai bila salah satu kolomnya diisi.
     *
     * @param array<string, mixed> $input Isi $_POST.
     * @return array{0: array{name: string, email: string, password: string}|null, 1: array<string, string>}
     *         [data akun atau null bila seksi kosong, peta error]
     */
    public static function validateLoginAccount(PDO $pdo, array $input, string $defaultName = ''): array
    {
        $name = trim((string) ($input['login_name'] ?? ''));
        $email = trim((string) ($input['login_email'] ?? ''));
        $password = (string) ($input['login_password'] ?? '');

        if ($name === '' && $email === '' && $password === '') {
            return [null, []];
        }

        $errors = [];

        if ($email === '') {
            $errors['login_email'] = 'Email login wajib diisi bila membuat akun login.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['login_email'] = 'Format email login tidak sah.';
        } elseif (self::userEmailTaken($pdo, $email)) {
            $errors['login_email'] = 'Email login ini sudah terdaftar sebagai pengguna. Gunakan email lain.';
        }

        if ($password === '') {
            $errors['login_password'] = 'Kata sandi awal wajib diisi bila membuat akun login.';
        } elseif (strlen($password) < 8) {
            $errors['login_password'] = 'Kata sandi awal minimal 8 karakter.';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [[
            'name' => $name !== '' ? $name : ($defaultName !== '' ? $defaultName : $email),
            'email' => $email,
            'password' => $password,
        ], []];
    }

    /**
     * @param array<string, mixed> $data Hasil Tenant::validate().
     * @return int ID tenant baru.
     */
    public static function create(PDO $pdo, array $data): int
    {
        $slug = self::uniqueSlug($pdo, self::slugify((string) $data['vendor_name']));

        $stmt = $pdo->prepare(
            'INSERT INTO tenants
                (name, vendor_name, slug, contact_name, email, phone, whatsapp, address, status, notes)
             VALUES
                (:name, :vendor_name, :slug, :contact_name, :email, :phone, :whatsapp, :address, :status, :notes)'
        );
        $stmt->execute([
            // name disinkronkan dengan vendor_name (label tampilan).
            'name' => $data['vendor_name'],
            'vendor_name' => $data['vendor_name'],
            'slug' => $slug,
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            // phone disinkronkan dengan whatsapp ternormalisasi.
            'phone' => $data['whatsapp'],
            'whatsapp' => $data['whatsapp'],
            'address' => $data['address'],
            'status' => $data['status'],
            'notes' => $data['notes'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data Hasil Tenant::validate(). */
    public static function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE tenants
                SET name = :name,
                    vendor_name = :vendor_name,
                    contact_name = :contact_name,
                    email = :email,
                    phone = :phone,
                    whatsapp = :whatsapp,
                    address = :address,
                    status = :status,
                    notes = :notes
              WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['vendor_name'],
            'vendor_name' => $data['vendor_name'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['whatsapp'],
            'whatsapp' => $data['whatsapp'],
            'address' => $data['address'],
            'status' => $data['status'],
            'notes' => $data['notes'],
            'id' => $id,
        ]);
    }

    /** Buat akun login (users) untuk tenant. Kata sandi disimpan sebagai hash. */
    public static function createUser(
        PDO $pdo,
        int $tenantId,
        string $name,
        string $email,
        string $password,
        string $role = 'tenant_admin'
    ): int {
        if (!in_array($role, ['tenant_admin', 'tenant_staff'], true)) {
            $role = 'tenant_admin';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (tenant_id, name, email, password_hash, role, is_active)
             VALUES (:tenant_id, :name, :email, :hash, :role, 1)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Nilai tampilan formulir tenant.
     *
     * @param array<string, mixed>|null $tenant
     * @param array<string, mixed>|null $post
     * @return array<string, string>
     */
    public static function formValues(?array $tenant = null, ?array $post = null): array
    {
        $values = [
            'vendor_name' => '',
            'contact_name' => '',
            'email' => '',
            'whatsapp' => '',
            'address' => '',
            'notes' => '',
            'status' => 'active',
            'login_name' => '',
            'login_email' => '',
        ];

        if ($post !== null) {
            foreach (array_keys($values) as $field) {
                if (isset($post[$field]) && is_string($post[$field])) {
                    $values[$field] = $post[$field];
                }
            }
            return $values;
        }

        if ($tenant !== null) {
            $values['vendor_name'] = self::displayName($tenant);
            $values['contact_name'] = (string) ($tenant['contact_name'] ?? '');
            $values['email'] = (string) ($tenant['email'] ?? '');
            $values['whatsapp'] = (string) (($tenant['whatsapp'] ?? '') !== '' ? $tenant['whatsapp'] : ($tenant['phone'] ?? ''));
            $values['address'] = (string) ($tenant['address'] ?? '');
            $values['notes'] = (string) ($tenant['notes'] ?? '');
            $values['status'] = (string) ($tenant['status'] ?? 'active');
        }

        return $values;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
