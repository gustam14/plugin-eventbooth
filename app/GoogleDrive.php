<?php
declare(strict_types=1);

/**
 * Klien Google OAuth + Drive API minimalis (Milestone 3) — hanya
 * memakai ekstensi cURL bawaan PHP, tanpa composer/SDK Google.
 *
 * Ruang lingkup M3: menghubungkan akun Drive pusat milik platform
 * (OAuth consent pemilik), membaca info akun & kuota, dan memastikan
 * folder root "Plugin Eventbooth" ada. Endpoint /api/drive/* untuk
 * n8n/aplikasi dibangun pada milestone berikutnya.
 *
 * Semua kegagalan (jaringan, respons Google, cURL tidak tersedia)
 * menghasilkan null/false secara diam-diam: TIDAK ada exception yang
 * bocor ke view, dan TIDAK ada nilai token yang dicatat ke log mana pun.
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
