<?php
declare(strict_types=1);

/**
 * Buku Klien tenant (tabel `client_bookings`, v0.28.0):
 * catatan klien & booking bisnis tenant — siapa kliennya,
 * kapan & di mana acaranya, paket apa yang diambil, berapa
 * nilainya, sudah DP berapa, dan sisa pembayarannya. Booking
 * dapat dikaitkan ke event galeri bila event-nya sudah dibuat.
 */
final class ClientBook
{
    /** @var array<string, string> Status -> label Indonesia. */
    public const STATUSES = [
        'inquiry' => 'Baru / Tanya-tanya',
        'booked' => 'Booking',
        'done' => 'Selesai',
        'cancelled' => 'Batal',
    ];

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    /* -----------------------------------------------------------------
     * Pembacaan
     * --------------------------------------------------------------- */

    /**
     * Daftar booking tenant dengan pencarian nama/klien/venue
     * dan saringan status. Urutan: tanggal acara terdekat dulu
     * (yang belum bertanggal di bawah), lalu terbaru dibuat.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forTenant(PDO $pdo, int $tenantId, string $query = '', string $status = ''): array
    {
        $sql = 'SELECT cb.*, e.name AS event_name
                  FROM client_bookings cb
                  LEFT JOIN events e ON e.id = cb.event_id
                 WHERE cb.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($query !== '') {
            $sql .= ' AND (cb.client_name LIKE :q OR cb.contact_name LIKE :q OR cb.venue LIKE :q OR cb.package_name LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }
        if ($status !== '' && isset(self::STATUSES[$status])) {
            $sql .= ' AND cb.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY (cb.event_date IS NULL) ASC, cb.event_date ASC, cb.id DESC';

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
     * Ringkasan buku klien untuk strip statistik halaman daftar:
     * jumlah booking berjalan (booked), total nilai booking
     * berjalan, dan total sisa pembayaran yang belum lunas.
     *
     * @return array{active_count: int, active_total: float, outstanding: float}
     */
    public static function stats(PDO $pdo, int $tenantId): array
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c,
                    COALESCE(SUM(total_price), 0) AS total,
                    COALESCE(SUM(GREATEST(total_price - dp_paid, 0)), 0) AS sisa
               FROM client_bookings
              WHERE tenant_id = :tenant_id AND status = 'booked'"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return [
            'active_count' => (int) ($row['c'] ?? 0),
            'active_total' => (float) ($row['total'] ?? 0),
            'outstanding' => (float) ($row['sisa'] ?? 0),
        ];
    }

    /** Sisa pembayaran satu booking (tidak pernah negatif). */
    public static function remaining(array $booking): float
    {
        return max(0.0, (float) ($booking['total_price'] ?? 0) - (float) ($booking['dp_paid'] ?? 0));
    }

    /* -----------------------------------------------------------------
     * Validasi & simpan
     * --------------------------------------------------------------- */

    /**
     * @param array<string, mixed> $input Isi $_POST.
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function validate(PDO $pdo, int $tenantId, array $input): array
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

        $contact = trim((string) ($input['contact_name'] ?? ''));
        if (self::length($contact) > 120) {
            $errors['contact_name'] = 'Nama kontak maksimal 120 karakter.';
        }
        $data['contact_name'] = $contact !== '' ? $contact : null;

        $wa = trim((string) ($input['whatsapp'] ?? ''));
        if ($wa !== '' && preg_match('/^[0-9+\s\-()]{6,32}$/', $wa) !== 1) {
            $errors['whatsapp'] = 'Nomor WhatsApp tidak sah (6–32 karakter angka).';
        }
        $data['whatsapp'] = $wa !== '' ? $wa : null;

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Format email tidak sah.';
        }
        $data['email'] = $email !== '' ? $email : null;

        $date = trim((string) ($input['event_date'] ?? ''));
        if ($date === '') {
            $data['event_date'] = null;
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            $data['event_date'] = $date;
        } else {
            $errors['event_date'] = 'Tanggal acara tidak sah.';
            $data['event_date'] = null;
        }

        $venue = trim((string) ($input['venue'] ?? ''));
        if (self::length($venue) > 160) {
            $errors['venue'] = 'Lokasi maksimal 160 karakter.';
        }
        $data['venue'] = $venue !== '' ? $venue : null;

        $package = trim((string) ($input['package_name'] ?? ''));
        if (self::length($package) > 120) {
            $errors['package_name'] = 'Nama paket maksimal 120 karakter.';
        }
        $data['package_name'] = $package !== '' ? $package : null;

        [$total, $totalError] = PackageTemplate::parseMoney($input['total_price'] ?? null);
        if ($totalError !== null) {
            $errors['total_price'] = 'Nilai paket: ' . $totalError;
        }
        $data['total_price'] = $total ?? '0.00';

        $dpRaw = trim((string) ($input['dp_paid'] ?? ''));
        if ($dpRaw === '') {
            $data['dp_paid'] = '0.00';
        } else {
            [$dp, $dpError] = PackageTemplate::parseMoney($dpRaw);
            if ($dpError !== null) {
                $errors['dp_paid'] = 'DP: ' . $dpError;
            }
            $data['dp_paid'] = $dp ?? '0.00';
        }
        if (!isset($errors['total_price']) && !isset($errors['dp_paid'])
            && (float) $data['dp_paid'] > (float) $data['total_price']) {
            $errors['dp_paid'] = 'DP tidak boleh melebihi nilai paket.';
        }

        $status = (string) ($input['status'] ?? 'inquiry');
        if (!isset(self::STATUSES[$status])) {
            $errors['status'] = 'Status tidak sah.';
            $status = 'inquiry';
        }
        $data['status'] = $status;

        $eventId = isset($input['event_id']) && is_numeric($input['event_id'])
            ? (int) $input['event_id'] : 0;
        if ($eventId > 0 && Event::findForTenant($pdo, $tenantId, $eventId) === null) {
            $errors['event_id'] = 'Event terkait tidak ditemukan pada akunmu.';
            $eventId = 0;
        }
        $data['event_id'] = $eventId > 0 ? $eventId : null;

        $notes = trim((string) ($input['notes'] ?? ''));
        $data['notes'] = $notes !== '' ? $notes : null;

        return [$data, $errors];
    }

    /** @param array<string, mixed> $data Hasil validate(). */
    public static function create(PDO $pdo, int $tenantId, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO client_bookings
                (tenant_id, client_name, contact_name, whatsapp, email,
                 event_date, venue, package_name, total_price, dp_paid,
                 status, event_id, notes)
             VALUES
                (:tenant_id, :client_name, :contact_name, :whatsapp, :email,
                 :event_date, :venue, :package_name, :total_price, :dp_paid,
                 :status, :event_id, :notes)'
        );
        $stmt->execute(self::params($tenantId, $data));

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data Hasil validate(). */
    public static function update(PDO $pdo, int $id, array $data): void
    {
        $stmt = $pdo->prepare(
            'UPDATE client_bookings
                SET client_name = :client_name, contact_name = :contact_name,
                    whatsapp = :whatsapp, email = :email,
                    event_date = :event_date, venue = :venue,
                    package_name = :package_name, total_price = :total_price,
                    dp_paid = :dp_paid, status = :status,
                    event_id = :event_id, notes = :notes
              WHERE id = :id'
        );
        $params = self::params(0, $data);
        unset($params['tenant_id']);
        $params['id'] = $id;
        $stmt->execute($params);
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
     * @param array<string, mixed>|null $booking
     * @param array<string, mixed>|null $post
     * @return array<string, mixed>
     */
    public static function formValues(?array $booking = null, ?array $post = null): array
    {
        $values = [
            'client_name' => '', 'contact_name' => '', 'whatsapp' => '',
            'email' => '', 'event_date' => '', 'venue' => '',
            'package_name' => '', 'total_price' => '', 'dp_paid' => '',
            'status' => 'inquiry', 'event_id' => '', 'notes' => '',
        ];
        $source = $post ?? $booking;
        if ($source !== null) {
            foreach (array_keys($values) as $field) {
                if (!isset($source[$field]) || $source[$field] === null) {
                    continue;
                }
                $value = $source[$field];
                if (($field === 'total_price' || $field === 'dp_paid') && is_numeric($value)) {
                    $text = (string) $value;
                    if (str_contains($text, '.')) {
                        $text = rtrim(rtrim($text, '0'), '.');
                    }
                    $values[$field] = $text === '' ? '0' : $text;
                } elseif ($field === 'event_id') {
                    $values[$field] = (string) (int) $value;
                } elseif (is_string($value)) {
                    $values[$field] = $value;
                }
            }
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private static function params(int $tenantId, array $data): array
    {
        return [
            'tenant_id' => $tenantId,
            'client_name' => $data['client_name'],
            'contact_name' => $data['contact_name'],
            'whatsapp' => $data['whatsapp'],
            'email' => $data['email'],
            'event_date' => $data['event_date'],
            'venue' => $data['venue'],
            'package_name' => $data['package_name'],
            'total_price' => $data['total_price'],
            'dp_paid' => $data['dp_paid'],
            'status' => $data['status'],
            'event_id' => $data['event_id'],
            'notes' => $data['notes'],
        ];
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
