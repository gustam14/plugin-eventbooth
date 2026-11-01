<?php
declare(strict_types=1);

/**
 * Klien Google OAuth + Drive API minimalis (Milestone 3) — hanya
 * memakai ekstensi cURL bawaan PHP, tanpa composer/SDK Google.
 *
 * Ruang lingkup M3: menghubungkan akun Drive pusat milik platform
 * (OAuth consent pemilik), membaca info akun & kuota, dan memastikan
 * folder root "Plugin Eventbooth" ada.
 *
 * Milestone 5b menambah operasi Drive untuk endpoint /api/drive/*:
 * penyiapan folder tenant/event/sesi, inisiasi resumable upload
 * (byte file mengalir PC -> Google langsung, tidak lewat server
 * ini), pembacaan metadata file, dan pemberian izin lihat publik.
 * Metode-metode M5b adalah varian "OrFail": kegagalan dilempar
 * sebagai GoogleDriveException agar rute API bisa menjawab
 * 502 drive_error (metode M3 tetap menjawab null/false diam-diam).
 *
 * Kegagalan pada jalur M3 (jaringan, respons Google, cURL tidak
 * tersedia) menghasilkan null/false secara diam-diam: TIDAK ada
 * exception yang bocor ke view, dan TIDAK ada nilai token yang
 * dicatat ke log mana pun.
 */
final class GoogleDrive
{
    /** Scope: drive.file (hanya file/folder buatan aplikasi ini) + email pemilik akun. */
    private const SCOPES = 'https://www.googleapis.com/auth/drive.file'
        . ' https://www.googleapis.com/auth/userinfo.email';

    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';
    private const ABOUT_ENDPOINT = 'https://www.googleapis.com/drive/v3/about';
    private const FILES_ENDPOINT = 'https://www.googleapis.com/drive/v3/files';
    private const UPLOAD_ENDPOINT = 'https://www.googleapis.com/upload/drive/v3/files';

    private const TIMEOUT = 20;

    /**
     * URL persetujuan OAuth Google. prompt=consent + access_type=offline
     * memastikan Google selalu menerbitkan refresh token.
     */
    public static function authUrl(string $clientId, string $redirectUri, string $state): string
    {
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        return self::AUTH_ENDPOINT . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Tukar authorization code menjadi token.
     *
     * @return array<string, mixed>|null Array token mentah Google
     *         (access_token, refresh_token?, expires_in, ...) atau null.
     */
    public static function exchangeCode(
        string $clientId,
        string $clientSecret,
        string $code,
        string $redirectUri
    ): ?array {
        $result = self::request('POST', self::TOKEN_ENDPOINT, [], [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if ($result === null || !isset($result['access_token'])) {
            return null;
        }

        return $result;
    }

    /** Perbarui access token dari refresh token; null bila gagal. */
    public static function refreshAccessToken(
        string $clientId,
        string $clientSecret,
        string $refreshToken
    ): ?string {
        $result = self::request('POST', self::TOKEN_ENDPOINT, [], [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($result === null || !isset($result['access_token'])) {
            return null;
        }

        return (string) $result['access_token'];
    }

    /**
     * Info akun Drive: email pemilik + kuota penyimpanan.
     *
     * @return array{email: string, display_name: ?string, quota_total: ?int, quota_used: ?int}|null
     *         quota_total null = Google tidak melaporkan batas
     *         (mis. akun tanpa limit); quota_used dalam byte.
     */
    public static function about(string $accessToken): ?array
    {
        $url = self::ABOUT_ENDPOINT . '?fields='
            . urlencode('user(emailAddress,displayName),storageQuota(limit,usage)');

        $result = self::request('GET', $url, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        if ($result === null) {
            return null;
        }

        $email = $result['user']['emailAddress'] ?? null;
        if (!is_string($email) || $email === '') {
            return null;
        }

        $quota = isset($result['storageQuota']) && is_array($result['storageQuota'])
            ? $result['storageQuota']
            : [];

        return [
            'email' => $email,
            'display_name' => isset($result['user']['displayName'])
                ? (string) $result['user']['displayName']
                : null,
            'quota_total' => isset($quota['limit']) && is_numeric($quota['limit'])
                ? (int) $quota['limit']
                : null,
            'quota_used' => isset($quota['usage']) && is_numeric($quota['usage'])
                ? (int) $quota['usage']
                : null,
        ];
    }

    /**
     * Pastikan folder root aplikasi ada di Drive akun ini; kembalikan
     * ID foldernya. Dengan scope drive.file, pencarian hanya melihat
     * file/folder yang dibuat aplikasi ini — persis yang kita mau.
     *
     * @return string|null ID folder, atau null bila pencarian &
     *         pembuatan sama-sama gagal.
     */
    public static function ensureRootFolder(string $accessToken, string $folderName = 'Plugin Eventbooth'): ?string
    {
        $headers = ['Authorization: Bearer ' . $accessToken];

        $query = "name = '" . str_replace(['\\', "'"], ['\\\\', "\\'"], $folderName) . "'"
            . " and mimeType = 'application/vnd.google-apps.folder'"
            . ' and trashed = false';
        $url = self::FILES_ENDPOINT
            . '?q=' . urlencode($query)
            . '&fields=' . urlencode('files(id,name)')
            . '&spaces=drive&pageSize=1';

        $found = self::request('GET', $url, $headers);
        if ($found !== null) {
            $files = $found['files'] ?? null;
            if (is_array($files) && isset($files[0]['id'])) {
                return (string) $files[0]['id'];
            }
        }

        $created = self::request('POST', self::FILES_ENDPOINT . '?fields=id', $headers, null, [
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        if ($created !== null && isset($created['id'])) {
            return (string) $created['id'];
        }

        return null;
    }

    /* -----------------------------------------------------------------
     * Operasi Drive untuk API aplikasi (Milestone 5b)
     *
     * Semua metode di bagian ini melempar GoogleDriveException pada
     * kegagalan apa pun (transport, status non-2xx, respons tak
     * terbaca). Access token hanya hidup dalam variabel lokal satu
     * request — tidak disimpan, tidak dicatat.
     * --------------------------------------------------------------- */

    /**
     * Access token segar dari refresh token tersimpan, untuk satu
     * akun google_accounts. Pemanggil (rute API) yang memegang
     * kredensial OAuth + refresh token hasil dekripsi Crypto.
     *
     * @throws GoogleDriveException Bila Google menolak refresh.
     */
    public static function accessTokenOrFail(
        string $clientId,
        string $clientSecret,
        string $refreshToken
    ): string {
        $token = self::refreshAccessToken($clientId, $clientSecret, $refreshToken);
        if ($token === null || $token === '') {
            throw new GoogleDriveException('Google menolak permintaan access token baru.');
        }

        return $token;
    }

    /**
     * Cari folder bernama $name persis di bawah $parentId.
     * Mengembalikan null HANYA bila foldernya memang belum ada;
     * kegagalan request adalah exception.
     *
     * @throws GoogleDriveException
     */
    public static function findFolder(string $accessToken, string $name, string $parentId): ?string
    {
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);
        $query = "name = '" . $escaped . "'"
            . " and '" . $parentId . "' in parents"
            . " and mimeType = 'application/vnd.google-apps.folder'"
            . ' and trashed = false';
        $url = self::FILES_ENDPOINT
            . '?q=' . urlencode($query)
            . '&fields=' . urlencode('files(id,name)')
            . '&spaces=drive&pageSize=1';

        $result = self::requestOrFail('GET', $url, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $files = $result['files'] ?? null;
        if (is_array($files) && isset($files[0]['id'])) {
            return (string) $files[0]['id'];
        }

        return null;
    }

    /**
     * Buat folder baru bernama $name di bawah $parentId.
     *
     * @throws GoogleDriveException
     */
    public static function createFolder(string $accessToken, string $name, string $parentId): string
    {
        $created = self::requestOrFail(
            'POST',
            self::FILES_ENDPOINT . '?fields=id',
            ['Authorization: Bearer ' . $accessToken],
            null,
            [
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
            ]
        );

        if (!isset($created['id']) || !is_string($created['id']) || $created['id'] === '') {
            throw new GoogleDriveException('Google tidak mengembalikan ID folder baru.');
        }

        return $created['id'];
    }

    /**
     * Pastikan folder $name ada di bawah $parentId (cari dulu,
     * buat bila belum) dan kembalikan ID-nya.
     *
     * @throws GoogleDriveException
     */
    public static function ensureFolder(string $accessToken, string $name, string $parentId): string
    {
        $found = self::findFolder($accessToken, $name, $parentId);
        if ($found !== null) {
            return $found;
        }

        return self::createFolder($accessToken, $name, $parentId);
    }

    /**
     * Jelajahi/buat rantai folder dari folder root akun mengikuti
     * urutan segmen nama (mis. [NamaTenant, NamaEvent, KodeSesi]).
     *
     * @param array<int, string> $segments Nama segmen, dari luar ke dalam.
     * @return array<int, string> ID folder sejajar dengan $segments
     *         (indeks terakhir = folder terdalam).
     * @throws GoogleDriveException
     */
    public static function ensureFolderPath(string $accessToken, string $rootFolderId, array $segments): array
    {
        $ids = [];
        $parentId = $rootFolderId;
        foreach ($segments as $segment) {
            $parentId = self::ensureFolder($accessToken, (string) $segment, $parentId);
            $ids[] = $parentId;
        }

        return $ids;
    }

    /**
     * Inisiasi resumable upload: Google membalas sesi unggahan
     * berupa URL (header Location) yang dipakai aplikasi booth
     * untuk PUT byte file langsung ke Google. Server ini TIDAK
     * pernah menyentuh byte file.
     *
     * @throws GoogleDriveException Bila Google tidak menerbitkan URL sesi.
     */
    public static function initResumableUpload(
        string $accessToken,
        string $folderId,
        string $fileName,
        string $mimeType,
        ?int $sizeBytes
    ): string {
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: ' . $mimeType,
        ];
        if ($sizeBytes !== null) {
            $headers[] = 'X-Upload-Content-Length: ' . $sizeBytes;
        }

        $body = json_encode([
            'name' => $fileName,
            'mimeType' => $mimeType,
            'parents' => [$folderId],
        ]);
        if ($body === false) {
            throw new GoogleDriveException('Metadata unggahan tidak dapat disusun.');
        }

        [$raw, $status, $responseHeaders] = self::sendWithHeaders(
            'POST',
            self::UPLOAD_ENDPOINT . '?uploadType=resumable',
            $headers,
            $body
        );
        if ($raw === null || $status === null || $status < 200 || $status >= 300) {
            throw new GoogleDriveException('Google menolak inisiasi resumable upload.');
        }

        $location = $responseHeaders['location'] ?? '';
        if (!is_string($location) || $location === '') {
            throw new GoogleDriveException('Google tidak menerbitkan URL sesi unggahan.');
        }

        return $location;
    }

    /**
     * Metadata satu file hasil unggahan (dipakai registrasi media
     * setelah aplikasi selesai PUT byte ke Google).
     *
     * @return array{id: string, name: ?string, mime_type: ?string,
     *               size_bytes: ?int, web_link: ?string,
     *               thumbnail_link: ?string}
     * @throws GoogleDriveException
     */
    public static function fileMetadataOrFail(string $accessToken, string $fileId): array
    {
        $url = self::FILES_ENDPOINT . '/' . urlencode($fileId)
            . '?fields=' . urlencode('id,name,mimeType,size,webViewLink,thumbnailLink');

        $result = self::requestOrFail('GET', $url, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        if (!isset($result['id']) || !is_string($result['id']) || $result['id'] === '') {
            throw new GoogleDriveException('Metadata file tidak terbaca dari Google.');
        }

        return [
            'id' => $result['id'],
            'name' => isset($result['name']) && is_string($result['name']) ? $result['name'] : null,
            'mime_type' => isset($result['mimeType']) && is_string($result['mimeType'])
                ? $result['mimeType'] : null,
            'size_bytes' => isset($result['size']) && is_numeric($result['size'])
                ? (int) $result['size'] : null,
            'web_link' => isset($result['webViewLink']) && is_string($result['webViewLink'])
                ? $result['webViewLink'] : null,
            'thumbnail_link' => isset($result['thumbnailLink']) && is_string($result['thumbnailLink'])
                ? $result['thumbnailLink'] : null,
        ];
    }

    /**
     * Jadikan file bisa dilihat siapa pun yang memiliki tautannya
     * (role reader, type anyone) agar album publik & tamu bisa
     * membuka foto tanpa akun Google.
     *
     * @throws GoogleDriveException
     */
    public static function shareAnyoneReaderOrFail(string $accessToken, string $fileId): void
    {
        self::requestOrFail(
            'POST',
            self::FILES_ENDPOINT . '/' . urlencode($fileId) . '/permissions?fields=id',
            ['Authorization: Bearer ' . $accessToken],
            null,
            ['role' => 'reader', 'type' => 'anyone']
        );
    }

    /**
     * Hapus permanen satu file di Drive (dipakai Kelola Album
     * tenant, v0.14.0). File yang sudah tidak ada (404) dianggap
     * berhasil — tujuan akhirnya sama: file tidak lagi tersimpan.
     *
     * @throws GoogleDriveException
     */
    public static function deleteFileOrFail(string $accessToken, string $fileId): void
    {
        $status = null;
        $raw = self::send(
            'DELETE',
            self::FILES_ENDPOINT . '/' . urlencode($fileId) . '?supportsAllDrives=true',
            ['Authorization: Bearer ' . $accessToken],
            null,
            $status
        );
        if ($raw === null || $status === null) {
            throw new GoogleDriveException('Koneksi ke Google Drive gagal saat menghapus file.');
        }
        if ($status === 404 || ($status >= 200 && $status < 300)) {
            return;
        }

        throw new GoogleDriveException("Google menolak penghapusan file (HTTP {$status}).");
    }

    /**
     * Cabut token di sisi Google (usaha terbaik; hasil diabaikan
     * pemanggil — pemutusan lokal tetap berjalan apa pun hasilnya).
     */
    public static function revoke(string $token): bool
    {
        $result = self::requestRaw('POST', self::REVOKE_ENDPOINT, [], [
            'token' => $token,
        ]);

        return $result !== null && $result >= 200 && $result < 300;
    }

    /**
     * Request HTTP ber-body form ATAU JSON dengan respons JSON.
     *
     * @param array<int, string>      $headers Header tambahan (tanpa Content-Type).
     * @param array<string, string>|null $form Body form-urlencoded (POST).
     * @param array<string, mixed>|null  $json Body JSON (POST) — dipakai bila $form null.
     * @return array<string, mixed>|null Array hasil decode JSON, atau null bila gagal.
     */
    private static function request(
        string $method,
        string $url,
        array $headers = [],
        ?array $form = null,
        ?array $json = null
    ): ?array {
        $body = null;
        if ($form !== null) {
            $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif ($json !== null) {
            $encoded = json_encode($json);
            if ($encoded === false) {
                return null;
            }
            $body = $encoded;
            $headers[] = 'Content-Type: application/json';
        }

        $raw = self::send($method, $url, $headers, $body, $status);
        if ($raw === null || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Request yang hanya peduli kode status (mis. revoke).
     *
     * @param array<int, string> $headers
     * @param array<string, string>|null $form
     * @return int|null Kode status HTTP, atau null bila request gagal total.
     */
    private static function requestRaw(
        string $method,
        string $url,
        array $headers = [],
        ?array $form = null
    ): ?int {
        $body = null;
        if ($form !== null) {
            $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $status = null;
        $raw = self::send($method, $url, $headers, $body, $status);
        if ($raw === null) {
            return null;
        }

        return $status;
    }

    /**
     * Varian request() untuk jalur API (M5b): kegagalan apa pun
     * (transport, status non-2xx, JSON tak terbaca) menjadi
     * GoogleDriveException, bukan null.
     *
     * @param array<int, string>      $headers Header tambahan (tanpa Content-Type).
     * @param array<string, string>|null $form Body form-urlencoded (POST).
     * @param array<string, mixed>|null  $json Body JSON (POST) — dipakai bila $form null.
     * @return array<string, mixed> Array hasil decode JSON.
     * @throws GoogleDriveException
     */
    private static function requestOrFail(
        string $method,
        string $url,
        array $headers = [],
        ?array $form = null,
        ?array $json = null
    ): array {
        $body = null;
        if ($form !== null) {
            $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif ($json !== null) {
            $encoded = json_encode($json);
            if ($encoded === false) {
                throw new GoogleDriveException('Isi request tidak dapat disusun.');
            }
            $body = $encoded;
            $headers[] = 'Content-Type: application/json';
        }

        $raw = self::send($method, $url, $headers, $body, $status);
        if ($raw === null || $status === null || $status < 200 || $status >= 300) {
            throw new GoogleDriveException('Request ke Google Drive gagal.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new GoogleDriveException('Respons Google Drive tidak terbaca.');
        }

        return $decoded;
    }

    /**
     * Eksekusi cURL yang juga menangkap header respons (dipakai
     * inisiasi resumable upload yang membaca header Location).
     * Kunci header dinormalisasi ke huruf kecil.
     *
     * @param array<int, string> $headers
     * @return array{0: ?string, 1: ?int, 2: array<string, string>}
     *         [body (null bila transport gagal), status, header]
     */
    private static function sendWithHeaders(
        string $method,
        string $url,
        array $headers,
        ?string $body
    ): array {
        if (!function_exists('curl_init')) {
            return [null, null, []];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [null, null, []];
        }

        $responseHeaders = [];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        if ($raw === false) {
            curl_close($ch);
            return [null, null, $responseHeaders];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [is_string($raw) ? $raw : null, $status, $responseHeaders];
    }

    /**
     * Eksekusi cURL tingkat rendah. Mengembalikan body respons (string,
     * bisa kosong) atau null bila transport gagal; $status diisi kode
     * HTTP terakhir. Tidak mencatat apa pun.
     *
     * @param array<int, string> $headers
     */
    private static function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        ?int &$status
    ): ?string {
        $status = null;

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        if ($raw === false) {
            curl_close($ch);
            return null;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return is_string($raw) ? $raw : null;
    }
}
