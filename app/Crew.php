<?php
declare(strict_types=1);

/**
 * Manajemen kru tenant (tabel `crew_members` + `event_crew`,
 * v0.28.0): daftar orang yang bekerja untuk tenant (operator,
 * fotografer, asisten) dan penugasannya pada tiap event —
 * menjawab kebutuhan "kru bertugas" yang selama ini kosong.
 */
final class Crew
{
    public const STATUSES = [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
    ];

    /* -----------------------------------------------------------------
     * Anggota kru
     * --------------------------------------------------------------- */

    /** @return array<int, array<string, mixed>> */
    public static function forTenant(PDO $pdo, int $tenantId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM crew_members WHERE tenant_id = :tenant_id';
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= ' ORDER BY status ASC, name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function findForTenant(PDO $pdo, int $tenantId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM crew_members
              WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

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
            $errors['name'] = 'Nama kru wajib diisi.';
        } elseif (self::length($name) > 120) {
            $errors['name'] = 'Nama kru maksimal 120 karakter.';
        }
        $data['name'] = $name;

        $role = trim((string) ($input['role'] ?? ''));
        if (self::length($role) > 60) {
            $errors['role'] = 'Peran maksimal 60 karakter.';
        }
        $data['role'] = $role !== '' ? $role : null;

        $phone = trim((string) ($input['phone'] ?? ''));
        if ($phone !== '' && preg_match('/^[0-9+\s\-()]{6,32}$/', $phone) !== 1) {
            $errors['phone'] = 'Nomor WhatsApp tidak sah (6–32 karakter angka).';
        }
        $data['phone'] = $phone !== '' ? $phone : null;

        $status = (string) ($input['status'] ?? 'active');
        if (!isset(self::STATUSES[$status])) {
            $status = 'active';
        }
        $data['status'] = $status;

        $notes = trim((string) ($input['notes'] ?? ''));
        if (self::length($notes) > 255) {
            $errors['notes'] = 'Catatan maksimal 255 karakter.';
        }
        $data['notes'] = $notes !== '' ? $notes : null;

        return [$data, $errors];
    }

    /** @param array<string, mixed> $data Hasil validate(). */
    public static function create(PDO $pdo, int $tenantId, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO crew_members (tenant_id, name, role, phone, status, notes)
             VALUES (:tenant_id, :name, :role, :phone, :status, :notes)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'role' => $data['role'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'notes' => $data['notes'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data Hasil validate(). */
    public static function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE crew_members
                SET name = :name, role = :role, phone = :phone,
                    status = :status, notes = :notes
              WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['name'],
            'role' => $data['role'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'notes' => $data['notes'],
            'id' => $id,
        ]);
    }

    public static function delete(PDO $pdo, int $tenantId, int $id): void
    {
        $stmt = $pdo->prepare(
            'DELETE FROM crew_members WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * Nilai tampilan formulir kru.
     *
     * @param array<string, mixed>|null $member
     * @param array<string, mixed>|null $post
     * @return array<string, mixed>
     */
    public static function formValues(?array $member = null, ?array $post = null): array
    {
        $values = [
            'name' => '', 'role' => '', 'phone' => '',
            'status' => 'active', 'notes' => '',
        ];
        $source = $post ?? $member;
        if ($source !== null) {
            foreach (array_keys($values) as $field) {
                if (isset($source[$field]) && is_string($source[$field])) {
                    $values[$field] = $source[$field];
                }
            }
        }

        return $values;
    }

    /* -----------------------------------------------------------------
     * Penugasan per event
     * --------------------------------------------------------------- */

    /**
     * Kru yang ditugaskan pada satu event (milik tenant ini),
     * beserta peran pada event itu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forEvent(PDO $pdo, int $eventId): array
    {
        $stmt = $pdo->prepare(
            'SELECT cm.*, ec.role AS event_role
               FROM event_crew ec
               JOIN crew_members cm ON cm.id = ec.crew_member_id
              WHERE ec.event_id = :event_id
              ORDER BY cm.name ASC'
        );
        $stmt->execute(['event_id' => $eventId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Ganti seluruh penugasan kru sebuah event (dari centang
     * formulir). ID anggota divalidasi milik tenant ini.
     *
     * @param array<int, int> $memberIds
     */
    public static function setAssignments(PDO $pdo, int $tenantId, int $eventId, array $memberIds): void
    {
        $valid = [];
        foreach (self::forTenant($pdo, $tenantId) as $member) {
            $valid[(int) $member['id']] = true;
        }
        $memberIds = array_values(array_filter(
            array_map('intval', $memberIds),
            static fn (int $id): bool => isset($valid[$id])
        ));

        $delete = $pdo->prepare('DELETE FROM event_crew WHERE event_id = :event_id');
        $delete->execute(['event_id' => $eventId]);

        if ($memberIds !== []) {
            $insert = $pdo->prepare(
                'INSERT INTO event_crew (event_id, crew_member_id) VALUES (:event_id, :member_id)'
            );
            foreach ($memberIds as $memberId) {
                $insert->execute(['event_id' => $eventId, 'member_id' => $memberId]);
            }
        }
    }

    /** Nomor telepon -> tautan wa.me (digit saja, awalan 0 -> 62). */
    public static function waLink(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return 'https://wa.me/' . $digits;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
