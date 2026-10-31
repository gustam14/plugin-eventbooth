<?php
declare(strict_types=1);

/**
 * Autentikasi dashboard berbasis session (Milestone 2).
 *
 * Pengguna berasal dari tabel `users`:
 *   - role super_admin  -> tenant_id NULL, area /admin
 *   - role tenant_admin -> milik satu tenant, area /tenant
 *   - role tenant_staff -> milik satu tenant, area /tenant
 *
 * Kata sandi diverifikasi dengan password_verify() terhadap kolom
 * password_hash (dibuat dengan password_hash()). Session ID selalu
 * diperbarui (regenerate) saat login untuk mencegah session fixation.
 *
 * Bila database belum dikonfigurasi / tidak terhubung, semua method
 * di sini gagal secara anggun (mengembalikan null/false), tidak fatal
 * — sama seperti perilaku Database::pdo() di Milestone 1.
 */
final class Auth
{
    /**
     * Hash bcrypt boneka (bukan kredensial siapa pun) — dipakai untuk
     * menyamakan waktu respons saat email tidak ditemukan, agar login
     * tidak bisa dipakai menebak email yang terdaftar.
     */
    private const DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuvabcdefghijklmnopqrstuvwxyzabcde';

    private static bool $started = false;

    /** @var array{id: int, tenant_id: ?int, name: string, email: string, role: string}|null */
    private static ?array $currentUser = null;

    private static bool $userLoaded = false;

    /** Mulai session (idempoten) dengan cookie yang aman. */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::requestIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;
    }

    /** Deteksi HTTPS, termasuk di balik proxy Cloudflare. */
    private static function requestIsHttps(): bool
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (is_string($forwarded) && strtolower($forwarded) === 'https') {
            return true;
        }

        $https = $_SERVER['HTTPS'] ?? '';
        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    /**
     * Coba login dengan email + kata sandi.
     * Mengembalikan true bila berhasil (session sudah terisi).
     */
    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::pdo();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, tenant_id, name, email, password_hash, role, is_active
                   FROM users
                  WHERE email = :email
                  LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $row = $stmt->fetch();
        } catch (PDOException) {
            return false;
        }

        if ($row === false) {
            // Samakan waktu respons untuk email yang tidak dikenal.
            password_verify($password, self::DUMMY_HASH);
            return false;
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
            return false;
        }

        if ((int) $row['is_active'] !== 1) {
            return false;
        }

        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        self::$currentUser = null;
        self::$userLoaded = false;

        // Catat waktu login & perbarui hash bila algoritmanya usang.
        // Kegagalan di sini tidak menggagalkan login.
        try {
            $update = $pdo->prepare(
                'UPDATE users SET last_login_at = NOW() WHERE id = :id'
            );
            $update->execute(['id' => (int) $row['id']]);

            if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
                $rehash = $pdo->prepare(
                    'UPDATE users SET password_hash = :hash WHERE id = :id'
                );
                $rehash->execute([
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => (int) $row['id'],
                ]);
            }
        } catch (PDOException) {
            // Abaikan: login tetap dianggap berhasil.
        }

        return true;
    }

    /**
     * Pengguna yang sedang login (diambil ulang dari database pada
     * setiap request agar penonaktifan akun langsung berlaku), atau
     * null bila belum login / sesi tidak valid / DB tidak tersedia.
     *
     * @return array{id: int, tenant_id: ?int, name: string, email: string, role: string}|null
     */
    public static function user(): ?array
    {
        if (self::$userLoaded) {
            return self::$currentUser;
        }
        self::$userLoaded = true;
        self::$currentUser = null;

        self::start();
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id) || $id <= 0) {
            return null;
        }

        $pdo = Database::pdo();
        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, tenant_id, name, email, role, is_active
                   FROM users
                  WHERE id = :id
                  LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
        } catch (PDOException) {
            return null;
        }

        if ($row === false || (int) $row['is_active'] !== 1) {
            unset($_SESSION['user_id']);
            return null;
        }

        self::$currentUser = [
            'id' => (int) $row['id'],
            'tenant_id' => $row['tenant_id'] === null ? null : (int) $row['tenant_id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
        ];

        return self::$currentUser;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Akhiri sesi login sepenuhnya. */
    public static function logout(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }

        session_destroy();
        self::$started = false;
        self::$currentUser = null;
        self::$userLoaded = true;
    }

    /**
     * Wajib login: kembalikan pengguna, atau alihkan ke /login.
     *
     * @return array{id: int, tenant_id: ?int, name: string, email: string, role: string}
     */
    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }

    /**
     * Wajib login dengan salah satu role yang diizinkan.
     * Role yang salah mendapat halaman 403.
     *
     * @return array{id: int, tenant_id: ?int, name: string, email: string, role: string}
     */
    public static function requireRole(string ...$roles): array
    {
        $user = self::requireLogin();

        if (!in_array($user['role'], $roles, true)) {
            View::render('errors/403', [
                'appName' => Config::appName(),
                'appUrl' => Config::appUrl(),
                'message' => 'Kamu tidak punya akses ke halaman ini.',
            ], 403);
            exit;
        }

        return $user;
    }

    /* -----------------------------------------------------------------
     * CSRF — satu token per session, dipakai semua formulir POST.
     * --------------------------------------------------------------- */

    public static function csrfToken(): string
    {
        self::start();

        $token = $_SESSION['_csrf'] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['_csrf'] = $token;
        }

        return $token;
    }

    /** Input hidden siap sisip ke dalam <form method="post">. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();

        $known = $_SESSION['_csrf'] ?? null;
        if (!is_string($known) || $known === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($known, $token);
    }

    /** Hentikan request (403) bila token CSRF tidak sah. */
    public static function requireCsrf(): void
    {
        $token = $_POST['_csrf'] ?? null;

        if (!self::verifyCsrf(is_string($token) ? $token : null)) {
            View::render('errors/403', [
                'appName' => Config::appName(),
                'appUrl' => Config::appUrl(),
                'message' => 'Token keamanan formulir tidak sah atau sudah kedaluwarsa. Silakan muat ulang halaman dan coba lagi.',
            ], 403);
            exit;
        }
    }
}
