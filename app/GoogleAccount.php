<?php
declare(strict_types=1);

/**
 * Repository akun Google Drive pusat (tabel `google_accounts`,
 * blueprint §2A: multi-akun, satu akun default, penugasan per event).
 *
 * Kolom skema yang dipakai (lihat database/schema.sql):
 *   id, label, google_email (unik), refresh_token_encrypted,
 *   root_folder_id, status ('connected'|'disconnected'|'error'),
 *   is_default, quota_total_bytes, quota_used_bytes,
 *   quota_checked_at, connected_by, created_at, updated_at.
 *
 * Refresh token HANYA disimpan dalam bentuk terenkripsi Crypto —
 * kelas ini menerima token yang SUDAH dienkripsi dari pemanggil dan
 * tidak pernah mendekripsinya.
 */
final class GoogleAccount
{
    private const COLUMNS = 'id, label, google_email, refresh_token_encrypted,
        root_folder_id, status, is_default, quota_total_bytes,
        quota_used_bytes, quota_checked_at, connected_by,
        created_at, updated_at';

    /** @return array<int, array<string, mixed>> Semua akun, tertua dulu. */
    public static function all(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT ' . self::COLUMNS . '
               FROM google_accounts
              ORDER BY id ASC'
        );

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT ' . self::COLUMNS . '
               FROM google_accounts
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public static function findByEmail(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT ' . self::COLUMNS . '
               FROM google_accounts
              WHERE google_email = :email
              LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Akun default saat ini (is_default = 1), bila ada. */
    public static function defaultAccount(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            'SELECT ' . self::COLUMNS . '
               FROM google_accounts
              WHERE is_default = 1
              ORDER BY id ASC
              LIMIT 1'
        );
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Sisip akun baru atau perbarui akun dengan email yang sama
     * (kaitkan ulang): refresh token, folder root, status, dan kuota
     * selalu ditimpa dengan nilai terbaru dari Google.
     *
     * @param array{google_email: string, refresh_token_encrypted: string,
     *              root_folder_id: ?string, quota_total_bytes: ?int,
     *              quota_used_bytes: ?int, connected_by: ?int,
     *              label: ?string} $data
     * @return int ID baris (baru maupun yang diperbarui).
     */
    public static function upsertByEmail(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO google_accounts
                (label, google_email, refresh_token_encrypted, root_folder_id,
                 status, quota_total_bytes, quota_used_bytes, quota_checked_at,
                 connected_by)
             VALUES
                (:label, :google_email, :refresh_token_encrypted, :root_folder_id,
                 \'connected\', :quota_total_bytes, :quota_used_bytes, NOW(),
                 :connected_by)
             ON DUPLICATE KEY UPDATE
                label = COALESCE(VALUES(label), label),
                refresh_token_encrypted = VALUES(refresh_token_encrypted),
                root_folder_id = VALUES(root_folder_id),
                status = \'connected\',
                quota_total_bytes = VALUES(quota_total_bytes),
                quota_used_bytes = VALUES(quota_used_bytes),
                quota_checked_at = NOW(),
                connected_by = VALUES(connected_by)'
        );
        $stmt->execute([
            'label' => $data['label'] ?? null,
            'google_email' => $data['google_email'],
            'refresh_token_encrypted' => $data['refresh_token_encrypted'],
            'root_folder_id' => $data['root_folder_id'] ?? null,
            'quota_total_bytes' => $data['quota_total_bytes'] ?? null,
            'quota_used_bytes' => $data['quota_used_bytes'] ?? null,
            'connected_by' => $data['connected_by'] ?? null,
        ]);

        $existing = self::findByEmail($pdo, (string) $data['google_email']);
        return $existing !== null ? (int) $existing['id'] : (int) $pdo->lastInsertId();
    }

    /**
     * Jadikan satu akun sebagai default — dalam satu transaksi:
     * semua akun lain diturunkan dulu, baru akun ini dinaikkan,
     * sehingga tidak pernah ada dua default sekaligus.
     */
    public static function setDefault(PDO $pdo, int $id): void
    {
        $pdo->beginTransaction();
        try {
            $clear = $pdo->prepare('UPDATE google_accounts SET is_default = 0 WHERE is_default = 1');
            $clear->execute();

            $set = $pdo->prepare('UPDATE google_accounts SET is_default = 1 WHERE id = :id');
            $set->execute(['id' => $id]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Perbarui snapshot kuota + waktu pemeriksaan terakhir. */
    public static function updateQuota(PDO $pdo, int $id, ?int $totalBytes, ?int $usedBytes): void
    {
        $stmt = $pdo->prepare(
            'UPDATE google_accounts
                SET quota_total_bytes = :total,
                    quota_used_bytes = :used,
                    quota_checked_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            'total' => $totalBytes,
            'used' => $usedBytes,
            'id' => $id,
        ]);
    }

    /** @param string $status 'connected' | 'disconnected' | 'error' */
    public static function setStatus(PDO $pdo, int $id, string $status): void
    {
        $stmt = $pdo->prepare(
            'UPDATE google_accounts SET status = :status WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function updateRootFolder(PDO $pdo, int $id, ?string $folderId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE google_accounts SET root_folder_id = :folder WHERE id = :id'
        );
        $stmt->execute(['folder' => $folderId, 'id' => $id]);
    }

    /** Hapus baris akun (dipakai aksi Putuskan setelah revoke best-effort). */
    public static function delete(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM google_accounts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
