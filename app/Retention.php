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
}
