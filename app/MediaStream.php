<?php
declare(strict_types=1);

/**
 * Aliran video galeri dari server sendiri (v0.13.0, pilihan
 * pemilik meniru fotoshare): byte berkas video/GIF diteruskan
 * server ini dari Google Drive memakai access token akun
 * pusat, sehingga pemutar bawaan peramban menerima berkas
 * kelas-satu dari domain sendiri — Content-Type video,
 * Content-Disposition: inline, dan dukungan Range (206).
 *
 * Latar: rantai berbagi publik Drive (uc?export=download /
 * view) terbukti ditolak elemen <video> pada Chrome desktop
 * dan Android walau berkas H.264 dan headernya sehat; pemutar
 * pratinjau Drive (v0.12.5) bekerja tetapi membawa antarmuka
 * Google. Jalur ini hanya untuk PUTAR video (?s=video) —
 * foto tetap dialihkan 302 langsung ke CDN Google dan tidak
 * pernah lewat server, sesuai rancangan awal hemat hosting.
 *
 * Mengembalikan false (tanpa mengeluarkan apa pun) bila
 * aliran tidak dapat disiapkan, agar pemanggil dapat mundur
 * ke pengalihan biasa.
 */
final class MediaStream
{
    public static function tryServe(PDO $pdo, array $media): bool
    {
        $fileId = trim((string) ($media['drive_file_id'] ?? ''));
        if ($fileId === '') {
            return false;
        }

        try {
            $accessToken = self::accessToken($pdo, $media);
        } catch (Throwable) {
            return false;
        }
        if ($accessToken === null) {
            return false;
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: */*',
        ];
        $range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
        if ($range !== '' && preg_match('/^bytes=\d*-\d*(?:,\d*-\d*)*$/', $range) === 1) {
            $headers[] = 'Range: ' . $range;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'follow_location' => 1,
                'max_redirects' => 5,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $url = 'https://www.googleapis.com/drive/v3/files/'
            . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true';
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            return false;
        }

        // $http_response_header terisi oleh wrapper HTTP pada
        // lingkup lokal ini; blok TERAKHIR adalah respons final
        // setelah pengalihan.
        $blocks = $http_response_header ?? [];
        $status = 0;
        $upstreamType = null;
        $upstreamLength = null;
        $upstreamRange = null;
        foreach ($blocks as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                $upstreamType = null;
                $upstreamLength = null;
                $upstreamRange = null;
                continue;
            }
            if (stripos($line, 'Content-Type:') === 0) {
                $upstreamType = trim(substr($line, strlen('Content-Type:')));
            } elseif (stripos($line, 'Content-Length:') === 0) {
                $upstreamLength = trim(substr($line, strlen('Content-Length:')));
            } elseif (stripos($line, 'Content-Range:') === 0) {
                $upstreamRange = trim(substr($line, strlen('Content-Range:')));
            }
        }
        if ($status !== 200 && $status !== 206) {
            fclose($stream);
            return false;
        }

        $fileName = (string) ($media['file_name'] ?? 'video.mp4');
        $contentType = $upstreamType !== null && $upstreamType !== ''
            ? $upstreamType
            : (preg_match('/\.mov$/i', $fileName) === 1 ? 'video/quicktime' : 'video/mp4');

        @set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: inline; filename="'
            . str_replace(['"', '\\'], '', $fileName) . '"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=3600');
        if ($upstreamRange !== null && $upstreamRange !== '') {
            header('Content-Range: ' . $upstreamRange);
        }
        if ($upstreamLength !== null && $upstreamLength !== '') {
            header('Content-Length: ' . $upstreamLength);
        }

        while (!feof($stream)) {
            $chunk = fread($stream, 65536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            flush();
        }
        fclose($stream);

        return true;
    }

    /**
     * Access token segar untuk akun Drive pemilik media ini:
     * akun pada baris media (denormalisasi), atau akun event
     * lewat sesinya. Null bila belum diatur/tidak terhubung.
     */
    private static function accessToken(PDO $pdo, array $media): ?string
    {
        $accountId = isset($media['drive_account_id']) && $media['drive_account_id'] !== null
            ? (int) $media['drive_account_id']
            : 0;
        if ($accountId <= 0 && !empty($media['session_id'])) {
            $session = Album::sessionWithEvent($pdo, (int) $media['session_id']);
            $accountId = (int) ($session['event_drive_account_id'] ?? 0);
        }
        if ($accountId <= 0) {
            return null;
        }
        $account = GoogleAccount::find($pdo, $accountId);
        if ($account === null || (string) ($account['status'] ?? '') !== 'connected') {
            return null;
        }

        $clientId = trim((string) (Settings::get($pdo, 'google_client_id') ?? ''));
        $secretCipher = Settings::get($pdo, 'google_client_secret');
        $clientSecret = ($secretCipher !== null && trim($secretCipher) !== '')
            ? Crypto::decrypt($secretCipher)
            : null;
        $refreshToken = Crypto::decrypt((string) ($account['refresh_token_encrypted'] ?? ''));
        if ($clientId === '' || $clientSecret === null || $clientSecret === ''
            || $refreshToken === null || $refreshToken === '') {
            return null;
        }

        return GoogleDrive::accessTokenOrFail($clientId, $clientSecret, $refreshToken);
    }
}
