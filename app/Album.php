<?php
declare(strict_types=1);

/**
 * Album publik & sesi foto (Milestone 5a, blueprint §5C).
 *
 * Halaman publik /e/{slug} dan /s/{kode} TIDAK memerlukan login
 * dan hanya membaca metadata + tautan yang tersimpan di
 * `media_files` — byte foto tidak pernah lewat server ini
 * (blueprint §2: byte berhenti di PC booth / Google Drive).
 *
 * Kolom media yang dipakai (skema nyata): `web_link` (tautan
 * lihat di Drive) dan `thumbnail_link` (tautan thumbnail Drive).
 * Tautan unduh diturunkan dari `drive_file_id` yang tersimpan
 * (format unduh langsung Google Drive), bukan disimpan terpisah.
 *
 * Kelas ini juga dipakai API aplikasi booth untuk membuat sesi
 * baru (POST /api/app/sessions): kode sesi 8 karakter dari
 * alfabet yang sama seperti kunci lisensi (tanpa karakter ambigu).
 */
final class Album
{
    /** Alfabet kode sesi — sama seperti License (tanpa 0/O/1/I/L). */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Jumlah sesi per halaman album event. */
    public const PER_PAGE = 60;

    /* -----------------------------------------------------------------
     * Album event (/e/{slug})
     * --------------------------------------------------------------- */

    /**
     * Event dari slug publiknya + nama vendor pemiliknya untuk
     * kepala halaman album.
     *
     * @return array<string, mixed>|null
     */
    public static function eventBySlug(PDO $pdo, string $slug): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT e.*,
                    COALESCE(NULLIF(t.vendor_name, \'\'), t.name) AS vendor_name
               FROM events e
               JOIN tenants t ON t.id = e.tenant_id
              WHERE e.slug = :slug
              LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function countSessions(PDO $pdo, int $eventId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE event_id = :event_id');
        $stmt->execute(['event_id' => $eventId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Satu halaman sesi pada album event, terbaru dulu. Tiap baris
     * memuat 'media_count' dan 'cover_url' (thumbnail media
     * pertama sesi itu — final didahulukan — atau null).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sessionsPage(PDO $pdo, int $eventId, int $page, int $perPage = self::PER_PAGE): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM media_files m
                      WHERE m.session_id = s.id) AS media_count,
                    (SELECT m.thumbnail_link FROM media_files m
                      WHERE m.session_id = s.id
                        AND m.thumbnail_link IS NOT NULL
                        AND m.thumbnail_link <> \'\'
                      ORDER BY CASE m.kind
                                    WHEN \'final\' THEN 0
                                    WHEN \'thumb\' THEN 1
                                    ELSE 2
                               END,
                               m.id ASC
                      LIMIT 1) AS cover_url
               FROM sessions s
              WHERE s.event_id = :event_id
              ORDER BY s.created_at DESC, s.id DESC
              LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /* -----------------------------------------------------------------
     * Halaman sesi (/s/{kode})
     * --------------------------------------------------------------- */

    /**
     * Sesi dari kodenya + nama & slug event induknya (untuk
     * kepala halaman dan tautan kembali ke album).
     *
     * @return array<string, mixed>|null
     */
    public static function sessionByCode(PDO $pdo, string $code): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT s.*, e.name AS event_name, e.slug AS event_slug
               FROM sessions s
               JOIN events e ON e.id = s.event_id
              WHERE s.code = :code
              LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Media milik satu sesi: hasil final lebih dulu, lalu
     * original, gif, video, thumbnail (urutan jenis pada enum
     * skema), masing-masing menurut urutan masuk.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mediaForSession(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM media_files
              WHERE session_id = :session_id
              ORDER BY FIELD(kind, \'final\', \'original\', \'gif\', \'video\', \'thumb\'),
                       id ASC'
        );
        $stmt->execute(['session_id' => $sessionId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** Tautan lihat file: web_link tersimpan, atau tautan Drive dari file ID. */
    public static function viewUrl(array $media): ?string
    {
        $webLink = trim((string) ($media['web_link'] ?? ''));
        if ($webLink !== '') {
            return $webLink;
        }

        $fileId = trim((string) ($media['drive_file_id'] ?? ''));
        if ($fileId !== '') {
            return 'https://drive.google.com/file/d/' . $fileId . '/view';
        }

        return null;
    }

    /** Tautan unduh langsung, diturunkan dari drive_file_id tersimpan. */
    public static function downloadUrl(array $media): ?string
    {
        $fileId = trim((string) ($media['drive_file_id'] ?? ''));
        if ($fileId === '') {
            return null;
        }

        return 'https://drive.google.com/uc?export=download&id=' . $fileId;
    }

    /** Label Indonesia untuk jenis media. */
    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'original' => 'Original',
            'gif' => 'GIF',
            'video' => 'Video',
            'thumb' => 'Thumbnail',
            default => 'Final',
        };
    }

    /* -----------------------------------------------------------------
     * Pembuatan sesi (API aplikasi booth)
     * --------------------------------------------------------------- */

    /** Kode sesi baru: 8 karakter acak tanpa karakter ambigu. */
    public static function generateCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;

        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Buat baris sesi baru berstatus 'created' dengan kode unik
     * (coba-ulang bila kodenya kebetulan bentrok).
     *
     * @return array<string, mixed> Baris sesi yang baru dibuat.
     */
    public static function createSession(
        PDO $pdo,
        int $eventId,
        ?int $packageId,
        ?string $guestName,
        ?string $guestWhatsapp
    ): array {
        $code = '';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = self::generateCode();
            $check = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE code = :code');
            $check->execute(['code' => $candidate]);
            if ((int) $check->fetchColumn() === 0) {
                $code = $candidate;
                break;
            }
        }
        if ($code === '') {
            throw new RuntimeException('Gagal membuat kode sesi unik.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sessions
                (event_id, package_id, code, guest_name, guest_whatsapp, status)
             VALUES
                (:event_id, :package_id, :code, :guest_name, :guest_whatsapp, \'created\')'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'package_id' => $packageId,
            'code' => $code,
            'guest_name' => $guestName,
            'guest_whatsapp' => $guestWhatsapp,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $pdo->lastInsertId()]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    /* -----------------------------------------------------------------
     * Operasi Drive & registrasi media (API aplikasi, M5b)
     * --------------------------------------------------------------- */

    /**
     * Sesi dari ID-nya + kolom event induk yang dibutuhkan jalur
     * Drive: 'event_name', 'event_slug', 'event_tenant_id'
     * (penjaga kepemilikan), dan 'event_drive_account_id' (akun
     * Drive penampung event, terkunci sejak event dibuat).
     *
     * @return array<string, mixed>|null
     */
    public static function sessionWithEvent(PDO $pdo, int $sessionId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT s.*,
                    e.name AS event_name,
                    e.slug AS event_slug,
                    e.tenant_id AS event_tenant_id,
                    e.drive_account_id AS event_drive_account_id
               FROM sessions s
               JOIN events e ON e.id = s.event_id
              WHERE s.id = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $sessionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Simpan ID folder sesi di Drive (kolom sessions.drive_folder_id). */
    public static function saveSessionDriveFolder(PDO $pdo, int $sessionId, string $folderId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE sessions SET drive_folder_id = :folder WHERE id = :id'
        );
        $stmt->execute(['folder' => $folderId, 'id' => $sessionId]);
    }

    /**
     * Catat satu file yang byte-nya SUDAH selesai diunggah aplikasi
     * langsung ke Google Drive (POST /api/drive/complete). Server
     * hanya menyimpan metadata + tautan; `event_id` dan
     * `drive_account_id` didenormalisasi dari sesi/event agar album
     * tetap bisa dirangkai walau akun penampung berganti.
     *
     * @param array{session_id: int, event_id: int, drive_account_id: ?int,
     *              drive_file_id: string, drive_folder_id: ?string,
     *              kind: string, file_name: ?string, mime_type: ?string,
     *              size_bytes: ?int, web_link: ?string,
     *              thumbnail_link: ?string} $data
     * @return array<string, mixed> Baris media yang baru dicatat.
     */
    public static function registerMedia(PDO $pdo, array $data): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO media_files
                (session_id, event_id, drive_account_id, drive_file_id,
                 drive_folder_id, kind, file_name, mime_type, size_bytes,
                 web_link, thumbnail_link)
             VALUES
                (:session_id, :event_id, :drive_account_id, :drive_file_id,
                 :drive_folder_id, :kind, :file_name, :mime_type, :size_bytes,
                 :web_link, :thumbnail_link)'
        );
        $stmt->execute([
            'session_id' => $data['session_id'],
            'event_id' => $data['event_id'],
            'drive_account_id' => $data['drive_account_id'],
            'drive_file_id' => $data['drive_file_id'],
            'drive_folder_id' => $data['drive_folder_id'],
            'kind' => $data['kind'],
            'file_name' => $data['file_name'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
            'web_link' => $data['web_link'],
            'thumbnail_link' => $data['thumbnail_link'],
        ]);

        $stmt = $pdo->prepare('SELECT * FROM media_files WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $pdo->lastInsertId()]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }
}
