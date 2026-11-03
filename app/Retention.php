<?php
declare(strict_types=1);

/**
 * Retention (v0.20.0): retensi album efektif.
 *
 * Dasar = retensi plan dari snapshot lisensi tenant
 * (License::retentionOf). Super Admin dapat menimpanya
 * PER EVENT lewat events.retention_override_days
 * (NULL = ikut plan; 0 = tidak pernah dibersihkan).
 * Semua pembaca toleran terhadap kolom yang belum
 * dimigrasi (diperlakukan sebagai tanpa override).
 */
final class Retention
{
    /** Override dari baris event yang sudah memuat kolomnya. */
    public static function overrideOf(array $event): ?int
    {
        $v = $event['retention_override_days'] ?? null;

        return $v === null ? null : max(0, (int) $v);
    }

    /** Override dibaca langsung dari database (toleran migrasi). */
    public static function overrideByEventId(PDO $pdo, int $eventId): ?int
    {
        if ($eventId <= 0) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT retention_override_days FROM events WHERE id = :id');
            $stmt->execute(['id' => $eventId]);
            $v = $stmt->fetchColumn();

            return $v === false || $v === null ? null : max(0, (int) $v);
        } catch (PDOException) {
            return null; // Kolom belum dimigrasi.
        }
    }

    /**
     * Retensi efektif sebuah event: override per event
     * didahulukan, selain itu retensi plan tenantnya.
     *
     * @param array<string, mixed> $event Baris event/sessionWithEvent.
     * @return array{days: int, plan_name: string, overridden: bool}
     */
    public static function forEvent(PDO $pdo, array $event): array
    {
        $tenantId = (int) ($event['tenant_id'] ?? $event['event_tenant_id'] ?? 0);
        $base = License::retentionOf($pdo, $tenantId);
        // Baris sesi (Album::sessionWithEvent) memuat 'id' = ID SESI
        // dan 'event_id' = ID event — event_id WAJIB didahulukan.
        // (Bug v0.21.0: purge membaca override milik event lain
        // karena memakai ID sesi, lalu menolak dengan not_expired.)
        $eventId = (int) ($event['event_id'] ?? $event['id'] ?? 0);
        $override = array_key_exists('retention_override_days', $event)
            ? self::overrideOf($event)
            : self::overrideByEventId($pdo, $eventId);
        if ($override !== null) {
            return ['days' => $override, 'plan_name' => $base['plan_name'], 'overridden' => true];
        }

        return ['days' => $base['days'], 'plan_name' => $base['plan_name'], 'overridden' => false];
    }

    /** Tulis override (null = hapus override, kembali ikut plan). */
    public static function saveOverride(PDO $pdo, int $eventId, ?int $days): void
    {
        $stmt = $pdo->prepare('UPDATE events SET retention_override_days = :days WHERE id = :id');
        $stmt->execute(['days' => $days, 'id' => $eventId]);
    }

    /* -----------------------------------------------------------------
     * Album yang dikosongkan retensi (v0.22.0, keputusan pemilik
     * 3 Nov 2026): purge sesi terakhir TIDAK lagi menghapus event
     * seketika. Event ditandai lewat retention_emptied_at; halaman
     * publik albumnya menampilkan keterangan Expired, dan event
     * dibersihkan sepenuhnya 30 hari kemudian oleh sapuan pada
     * endpoint kandidat retensi. Semua pembaca/penulis di bawah
     * toleran terhadap kolom yang belum dimigrasi.
     * --------------------------------------------------------------- */

    /** Tandai album event baru saja dikosongkan retensi. */
    public static function markEmptied(PDO $pdo, int $eventId): bool
    {
        if ($eventId <= 0) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                'UPDATE events SET retention_emptied_at = NOW()
                  WHERE id = :id AND retention_emptied_at IS NULL'
            );
            $stmt->execute(['id' => $eventId]);

            return $stmt->rowCount() > 0 || self::emptiedAt($pdo, $eventId) !== null;
        } catch (PDOException) {
            return false; // Kolom belum dimigrasi.
        }
    }

    /** Kapan album event dikosongkan retensi (null = tidak ditandai). */
    public static function emptiedAt(PDO $pdo, int $eventId): ?string
    {
        if ($eventId <= 0) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT retention_emptied_at FROM events WHERE id = :id');
            $stmt->execute(['id' => $eventId]);
            $v = $stmt->fetchColumn();

            return $v === false || $v === null ? null : (string) $v;
        } catch (PDOException) {
            return null; // Kolom belum dimigrasi.
        }
    }

    /** Cabut penanda kosong-retensi (event dipakai lagi). */
    public static function clearEmptied(PDO $pdo, int $eventId): void
    {
        if ($eventId <= 0) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                'UPDATE events SET retention_emptied_at = NULL
                  WHERE id = :id AND retention_emptied_at IS NOT NULL'
            );
            $stmt->execute(['id' => $eventId]);
        } catch (PDOException) {
            // Kolom belum dimigrasi — tidak ada yang dibersihkan.
        }
    }

    /**
     * Event yang albumnya sudah kosong-retensi selama $days hari
     * atau lebih DAN masih tidak memiliki sesi — kandidat
     * dibersihkan sepenuhnya oleh sapuan harian.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function expiredEmptiedEvents(PDO $pdo, int $days = 30, int $limit = 3): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT e.id, e.name, e.tenant_id, e.drive_account_id, e.retention_emptied_at
                   FROM events e
                  WHERE e.retention_emptied_at IS NOT NULL
                    AND e.retention_emptied_at <= DATE_SUB(NOW(), INTERVAL :days DAY)
                    AND NOT EXISTS (SELECT 1 FROM sessions s WHERE s.event_id = e.id)
                  ORDER BY e.retention_emptied_at ASC
                  LIMIT :limit'
            );
            $stmt->bindValue('days', $days, PDO::PARAM_INT);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll() ?: [];
        } catch (PDOException) {
            return []; // Kolom belum dimigrasi.
        }
    }
}
