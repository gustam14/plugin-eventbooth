<?php
declare(strict_types=1);

/**
 * Buku Klien tenant (tabel `client_bookings`).
 *
 * v0.28.0 awalnya memuat data booking lengkap; atas arahan
 * pemilik (11 Nov 2026) v0.31.0 merampingkannya menjadi
 * daftar klien saja: NAMA, WHATSAPP, ALAMAT. Tanggal acara,
 * paket yang diambil, nilai/DP, dan status diisi pada EVENT
 * (events.client_id + kolom paket/pembayaran klien).
 *
 * Alur yang dikehendaki pemilik: klien mau booking -> catat
 * dulu di Buku Klien -> buat event-nya dari klien itu.
 */
final class ClientBook
{
    /* -----------------------------------------------------------------
     * Pembacaan
     * --------------------------------------------------------------- */

    /**
     * Daftar klien tenant + jumlah event yang terhubung ke
     * tiap klien. Pencarian pada nama, WhatsApp, dan alamat.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forTenant(PDO $pdo, int $tenantId, string $query = ''): array
    {
        $sql = 'SELECT cb.*,
                       (SELECT COUNT(*) FROM events ev
                         WHERE ev.client_id = cb.id) AS event_count
                  FROM client_bookings cb
                 WHERE cb.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($query !== '') {
            $sql .= ' AND (cb.client_name LIKE :q OR cb.whatsapp LIKE :q OR cb.address LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }
        $sql .= ' ORDER BY cb.client_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function findForTenant(PDO $pdo, int $tenantId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM client_bookings
              WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Event-event yang terhubung ke seorang klien (terbaru
     * dulu) — riwayat booking klien itu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function eventsForClient(PDO $pdo, int $clientId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, starts_at, status, client_package_name
               FROM events
              WHERE client_id = :client_id
              ORDER BY id DESC'
        );
        $stmt->execute(['client_id' => $clientId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
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

        $client = trim((string) ($input['client_name'] ?? ''));
        if ($client === '') {
            $errors['client_name'] = 'Nama klien wajib diisi.';
        } elseif (self::length($client) > 120) {
            $errors['client_name'] = 'Nama klien maksimal 120 karakter.';
        }
        $data['client_name'] = $client;

        $wa = trim((string) ($input['whatsapp'] ?? ''));
        if ($wa !== '' && preg_match('/^[0-9+\s\-()]{6,32}$/', $wa) !== 1) {
            $errors['whatsapp'] = 'Nomor WhatsApp tidak sah (6–32 karakter angka).';
        }
        $data['whatsapp'] = $wa !== '' ? $wa : null;

        $address = trim((string) ($input['address'] ?? ''));
        if (self::length($address) > 255) {
            $errors['address'] = 'Alamat maksimal 255 karakter.';
        }
        $data['address'] = $address !== '' ? $address : null;

        return [$data, $errors];
    }

    /** @param array<string, mixed> $data Hasil validate(). */
    public static function create(PDO $pdo, int $tenantId, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO client_bookings (tenant_id, client_name, whatsapp, address)
             VALUES (:tenant_id, :client_name, :whatsapp, :address)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'client_name' => $data['client_name'],
            'whatsapp' => $data['whatsapp'],
            'address' => $data['address'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data Hasil validate(). */
    public static function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE client_bookings
                SET client_name = :client_name, whatsapp = :whatsapp,
                    address = :address
              WHERE id = :id'
        );
        $stmt->execute([
            'client_name' => $data['client_name'],
            'whatsapp' => $data['whatsapp'],
            'address' => $data['address'],
            'id' => $id,
        ]);
    }

    public static function delete(PDO $pdo, int $tenantId, int $id): void
    {
        $stmt = $pdo->prepare(
            'DELETE FROM client_bookings WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * Nilai tampilan formulir buku klien.
     *
     * @param array<string, mixed>|null $client
     * @param array<string, mixed>|null $post
     * @return array<string, mixed>
     */
    public static function formValues(?array $client = null, ?array $post = null): array
    {
        $values = ['client_name' => '', 'whatsapp' => '', 'address' => ''];
        $source = $post ?? $client;
        if ($source !== null) {
            foreach (array_keys($values) as $field) {
                if (isset($source[$field]) && is_string($source[$field])) {
                    $values[$field] = $source[$field];
                }
            }
        }

        return $values;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
