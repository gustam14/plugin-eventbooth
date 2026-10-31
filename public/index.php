<?php
declare(strict_types=1);

/**
 * Front controller — semua request masuk lewat berkas ini
 * (lihat public/.htaccess).
 *
 * Rute yang sudah hidup:
 *   GET  /            -> halaman landing                     (M1)
 *   GET  /privacy     -> kebijakan privasi                   (halaman legal)
 *   GET  /terms       -> syarat layanan                      (halaman legal)
 *   GET  /api/health  -> status JSON                          (M1)
 *   GET|POST /login, POST /logout, GET /dashboard             (M2)
 *   GET  /admin, CRUD plan di /admin/plans                    (M2)
 *   GET  /tenant                                              (M2)
 *   GET  /tenant/licenses -> lisensi milik tenant             (M4)
 *   GET|POST /account/password -> ganti kata sandi            (M2.1)
 *   GET  /admin/drive + OAuth Google Drive pusat              (M3)
 *   GET  /auth/google/callback -> callback OAuth Google       (M3)
 *   CRUD /admin/tenants + aksi lisensi /admin/licenses         (M4)
 *   POST /api/license/activate|validate -> API aplikasi booth  (M4)
 *   POST /api/app/login|me|logout -> sesi aplikasi via akun     (M4c)
 *   POST cabut perangkat: /tenant/licenses/... & /admin/...    (M4c)
 *   GET|POST /api/automation/* -> pengingat perpanjangan n8n   (M4)
 *
 * Grup API lain di bawah masih STUB 501 bertanda TODO; logika
 * bisnisnya (lisensi, Midtrans, Drive, sesi) dibangun bertahap
 * sesuai roadmap di README.md dan blueprint v4.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();

/* ---------------------------------------------------------------------
 * Halaman publik
 * ------------------------------------------------------------------- */
$router->get('/', static function (): void {
    // Bagian paket di landing membaca plan aktif dari dashboard
    // Super Admin. Bila database belum disetel ATAU query gagal
    // (mis. tabel/kolom belum ada sebelum migrasi), landing
    // menampilkan bagian paket statis contoh (lihat view).
    $publicPlans = [];
    $pdo = Database::pdo();
    if ($pdo !== null) {
        try {
            $publicPlans = Plan::activePublic($pdo);
        } catch (PDOException) {
            $publicPlans = [];
        }
    }

    View::render('landing', [
        'appName' => Config::appName(),
        'appUrl' => Config::appUrl(),
        'version' => Config::appVersion(),
        'plans' => $publicPlans,
    ]);
});

// Halaman legal publik — URL kebijakan privasi juga didaftarkan
// pada Google OAuth consent screen (Branding) proyek Cloud, jadi
// rutenya tidak boleh berubah tanpa memperbarui pendaftaran itu.
$router->get('/privacy', static function (): void {
    View::render('legal/privacy', [
        'appName' => Config::appName(),
        'appUrl' => Config::appUrl(),
        'version' => Config::appVersion(),
    ]);
});

// Halaman legal publik — URL syarat layanan juga didaftarkan
// pada Google OAuth branding screen (Branding) proyek Cloud, jadi
// rutenya tidak boleh berubah tanpa memperbarui pendaftaran itu.
$router->get('/terms', static function (): void {
    View::render('legal/terms', [
        'appName' => Config::appName(),
        'appUrl' => Config::appUrl(),
        'version' => Config::appVersion(),
    ]);
});

// TODO(M5): album publik per event & per sesi (blueprint §5C).
// $router->get('/e/{slug}', ...);
// $router->get('/s/{kode}', ...);

/* ---------------------------------------------------------------------
 * Autentikasi (Milestone 2)
 * ------------------------------------------------------------------- */
$router->get('/login', static function (): void {
    if (Auth::user() !== null) {
        redirect('/dashboard');
    }

    $old = Flash::pullInput();

    View::render('auth/login', [
        'appName' => Config::appName(),
        'appUrl' => Config::appUrl(),
        // Halaman tetap tampil anggun bila DB belum disetel:
        // formulir ditampilkan nonaktif + penjelasan (lihat view).
        'dbReady' => Database::pdo() !== null,
        'flashes' => Flash::pull(),
        'oldEmail' => (string) ($old['email'] ?? ''),
    ]);
});

$router->post('/login', static function (): void {
    Auth::requireCsrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (Database::pdo() === null) {
        Flash::error('Database belum disetel. Login belum dapat digunakan — hubungi administrator server.');
        Flash::keepInput(['email' => $email]);
        redirect('/login');
    }

    if ($email === '' || $password === '') {
        Flash::error('Email dan kata sandi wajib diisi.');
        Flash::keepInput(['email' => $email]);
        redirect('/login');
    }

    if (Auth::attempt($email, $password)) {
        redirect('/dashboard');
    }

    // Pesan sengaja umum: tidak membocorkan apakah email terdaftar.
    Flash::error('Email atau kata sandi salah, atau akun tidak aktif.');
    Flash::keepInput(['email' => $email]);
    redirect('/login');
});

// Keluar hanya lewat POST (dilindungi CSRF) — tidak ada GET /logout.
$router->post('/logout', static function (): void {
    Auth::requireCsrf();
    Auth::logout();
    redirect('/login');
});

// Gerbang dashboard: alihkan sesuai role pengguna.
$router->get('/dashboard', static function (): void {
    $user = Auth::requireLogin();
    redirect($user['role'] === 'super_admin' ? '/admin' : '/tenant');
});

/* ---------------------------------------------------------------------
 * Akun pengguna (Milestone 2.1) — ganti kata sandi, terbuka untuk
 * SEMUA role yang sudah login (super_admin, tenant_admin,
 * tenant_staff). Kata sandi tidak pernah dicatat ke log, disimpan
 * sebagai isian lama, atau ditampilkan kembali.
 * ------------------------------------------------------------------- */
$router->get('/account/password', static function (): void {
    $user = Auth::requireLogin();

    View::render('account/password', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'errors' => [],
    ]);
});

$router->post('/account/password', static function (): void {
    $user = Auth::requireLogin();
    Auth::requireCsrf();

    // Render ulang formulir dengan error per kolom (pola yang sama
    // seperti formulir plan: HTTP 422, tanpa redirect).
    $renderForm = static function (array $errors) use ($user): void {
        $flashes = Flash::pull();
        $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
        View::render('account/password', [
            'appName' => Config::appName(),
            'user' => $user,
            'flashes' => $flashes,
            'errors' => $errors,
        ], 422);
    };

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel. Kata sandi belum dapat diganti — hubungi administrator server.');
        redirect('/account/password');
    }

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');

    try {
        $stmt = $pdo->prepare(
            'SELECT password_hash FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $user['id']]);
        $row = $stmt->fetch();
    } catch (PDOException) {
        Flash::error('Kata sandi gagal diganti. Silakan coba lagi.');
        redirect('/account/password');
    }

    if ($row === false) {
        Flash::error('Kata sandi gagal diganti. Silakan coba lagi.');
        redirect('/account/password');
    }

    $currentHash = (string) $row['password_hash'];

    // Semua pesan error sengaja umum: tidak membocorkan detail
    // internal akun selain yang memang perlu diketahui pemiliknya.
    $errors = [];

    if ($currentPassword === '') {
        $errors['current_password'] = 'Kata sandi saat ini wajib diisi.';
    } elseif (!password_verify($currentPassword, $currentHash)) {
        $errors['current_password'] = 'Kata sandi saat ini salah.';
    }

    if ($newPassword === '') {
        $errors['new_password'] = 'Kata sandi baru wajib diisi.';
    } elseif (strlen($newPassword) < 10) {
        $errors['new_password'] = 'Kata sandi baru minimal 10 karakter.';
    } elseif ($errors === [] && $newPassword === $currentPassword) {
        // Hanya diperiksa setelah kata sandi saat ini terbukti benar.
        $errors['new_password'] = 'Kata sandi baru harus berbeda dari kata sandi saat ini.';
    }

    if ($confirmation === '') {
        $errors['new_password_confirmation'] = 'Konfirmasi kata sandi baru wajib diisi.';
    } elseif ($newPassword !== '' && $confirmation !== $newPassword) {
        $errors['new_password_confirmation'] = 'Konfirmasi tidak sama dengan kata sandi baru.';
    }

    if ($errors !== []) {
        $renderForm($errors);
        return;
    }

    try {
        $update = $pdo->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id'
        );
        $update->execute([
            'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $user['id'],
        ]);
    } catch (PDOException) {
        Flash::error('Kata sandi gagal diganti. Silakan coba lagi.');
        redirect('/account/password');
    }

    // Perbarui ID session setelah kredensial berubah — pola yang
    // sama seperti saat login (mencegah pemakaian ulang sesi lama).
    Auth::start();
    session_regenerate_id(true);

    Flash::success('Kata sandi berhasil diganti. Gunakan kata sandi baru pada login berikutnya.');
    redirect('/dashboard');
});

/* ---------------------------------------------------------------------
 * Area Super Admin (Milestone 2) — hanya role super_admin.
 * ------------------------------------------------------------------- */
$router->get('/admin', static function (): void {
    $user = Auth::requireRole('super_admin');

    $counts = ['tenants' => 0, 'licenses' => 0, 'plans' => 0, 'events' => 0];
    $dbReady = false;
    $expiring = [];

    $pdo = Database::pdo();
    if ($pdo !== null) {
        try {
            $counts['tenants'] = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
            $counts['licenses'] = (int) $pdo->query('SELECT COUNT(*) FROM licenses')->fetchColumn();
            $counts['plans'] = (int) $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn();
            $counts['events'] = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
            $dbReady = true;
        } catch (PDOException) {
            // Tabel belum diimpor: tampilkan nol + pemberitahuan.
            $dbReady = false;
        }

        // Kartu kedaluwarsa (M4): diam-diam kosong bila tabel belum
        // tersedia — ringkasan tidak boleh ikut rusak karenanya.
        try {
            $expiring = License::expiringWithin($pdo, 30);
        } catch (PDOException) {
            $expiring = [];
        }
    }

    View::render('admin/overview', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'dbReady' => $dbReady,
        'counts' => $counts,
        'expiring' => $expiring,
    ]);
});

$router->get('/admin/plans', static function (): void {
    $user = Auth::requireRole('super_admin');

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, daftar plan belum dapat ditampilkan.');
        redirect('/admin');
    }

    try {
        $plans = Plan::all($pdo);
    } catch (PDOException) {
        Flash::error('Tabel plan belum tersedia. Pastikan database/schema.sql sudah diimpor.');
        redirect('/admin');
    }

    View::render('admin/plans/index', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'plans' => $plans,
    ]);
});

$router->get('/admin/plans/create', static function (): void {
    $user = Auth::requireRole('super_admin');

    View::render('admin/plans/form', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'heading' => 'Tambah Plan',
        'action' => '/admin/plans',
        'values' => Plan::formValues(),
        'errors' => [],
    ]);
});

$router->post('/admin/plans', static function (): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, plan belum dapat disimpan.');
        redirect('/admin');
    }

    [$data, $errors] = Plan::validate($pdo, $_POST);
    if ($errors !== []) {
        $flashes = Flash::pull();
        $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
        View::render('admin/plans/form', [
            'appName' => Config::appName(),
            'user' => $user,
            'flashes' => $flashes,
            'heading' => 'Tambah Plan',
            'action' => '/admin/plans',
            'values' => Plan::formValues(null, $_POST),
            'errors' => $errors,
        ], 422);
        return;
    }

    try {
        Plan::create($pdo, $data);
    } catch (PDOException) {
        Flash::error('Plan gagal disimpan. Silakan coba lagi.');
        redirect('/admin/plans/create');
    }

    Flash::success('Plan "' . (string) $data['name'] . '" berhasil dibuat.');
    redirect('/admin/plans');
});

$router->get('/admin/plans/{id}/edit', static function (array $params): void {
    $user = Auth::requireRole('super_admin');

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, plan belum dapat diubah.');
        redirect('/admin');
    }

    $plan = Plan::find($pdo, (int) ($params['id'] ?? 0));
    if ($plan === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    View::render('admin/plans/form', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'heading' => 'Ubah Plan',
        'action' => '/admin/plans/' . (int) $plan['id'],
        'values' => Plan::formValues($plan),
        'errors' => [],
    ]);
});

$router->post('/admin/plans/{id}', static function (array $params): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, plan belum dapat disimpan.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);
    $plan = Plan::find($pdo, $id);
    if ($plan === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    [$data, $errors] = Plan::validate($pdo, $_POST, $plan);
    if ($errors !== []) {
        $flashes = Flash::pull();
        $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
        View::render('admin/plans/form', [
            'appName' => Config::appName(),
            'user' => $user,
            'flashes' => $flashes,
            'heading' => 'Ubah Plan',
            'action' => '/admin/plans/' . $id,
            'values' => Plan::formValues($plan, $_POST),
            'errors' => $errors,
        ], 422);
        return;
    }

    try {
        Plan::update($pdo, $id, $data);
    } catch (PDOException) {
        Flash::error('Plan gagal disimpan. Silakan coba lagi.');
        redirect('/admin/plans/' . $id . '/edit');
    }

    Flash::success('Plan "' . (string) $data['name'] . '" berhasil diperbarui.');
    redirect('/admin/plans');
});

$router->post('/admin/plans/{id}/toggle', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, status plan belum dapat diubah.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);
    $plan = Plan::find($pdo, $id);
    if ($plan === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $nowActive = (int) $plan['is_active'] !== 1;
    Plan::setActive($pdo, $id, $nowActive);

    Flash::success(
        'Plan "' . (string) $plan['name'] . '" sekarang '
        . ($nowActive ? 'aktif.' : 'nonaktif.')
    );
    redirect('/admin/plans');
});

/* ---------------------------------------------------------------------
 * Google Drive pusat (Milestone 3) — hanya role super_admin.
 *
 * Mengelola koneksi akun Drive MILIK PLATFORM (blueprint §2A):
 * kredensial OAuth disimpan di app_settings (Client Secret
 * terenkripsi Crypto), refresh token akun disimpan terenkripsi di
 * google_accounts. Token TIDAK PERNAH ditampilkan, dicatat ke log,
 * atau dimasukkan ke pesan flash.
 *
 * Endpoint /api/drive/* untuk n8n/aplikasi tetap stub 501 —
 * dibangun pada milestone berikutnya.
 * ------------------------------------------------------------------- */

/**
 * Baca kredensial OAuth Google dari app_settings.
 *
 * @return array{client_id: string, secret_cipher: ?string}
 */
$googleCredentials = static function (PDO $pdo): array {
    $clientId = '';
    $secretCipher = null;
    try {
        $clientId = trim((string) (Settings::get($pdo, 'google_client_id') ?? ''));
        $stored = Settings::get($pdo, 'google_client_secret');
        $secretCipher = ($stored !== null && trim($stored) !== '') ? $stored : null;
    } catch (PDOException) {
        // Tabel pengaturan belum ada: perlakukan sebagai belum diatur.
    }

    return ['client_id' => $clientId, 'secret_cipher' => $secretCipher];
};

$router->get('/admin/drive', static function () use ($googleCredentials): void {
    $user = Auth::requireRole('super_admin');

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, halaman Google Drive belum dapat ditampilkan.');
        redirect('/admin');
    }

    try {
        $creds = $googleCredentials($pdo);
        $accounts = GoogleAccount::all($pdo);
    } catch (PDOException) {
        Flash::error('Tabel Google Drive belum tersedia. Pastikan database/schema.sql sudah diimpor.');
        redirect('/admin');
    }

    $cryptoReady = Crypto::isConfigured();
    $secretSet = $creds['secret_cipher'] !== null;

    View::render('admin/drive/index', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'cryptoReady' => $cryptoReady,
        'clientId' => $creds['client_id'],
        'secretSet' => $secretSet,
        'credentialsReady' => $cryptoReady && $creds['client_id'] !== '' && $secretSet,
        'redirectUri' => Config::googleRedirectUri(),
        'accounts' => $accounts,
    ]);
});

$router->post('/admin/drive/settings', static function (): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, pengaturan belum dapat disimpan.');
        redirect('/admin');
    }

    $clientId = trim((string) ($_POST['google_client_id'] ?? ''));
    $secretInput = trim((string) ($_POST['google_client_secret'] ?? ''));

    try {
        Settings::set($pdo, 'google_client_id', $clientId, false, $user['id']);

        // Secret kosong = pertahankan yang lama. Secret baru wajib
        // bisa dienkripsi — tanpa APP_KEY, menolak menyimpannya
        // lebih aman daripada menyimpannya sebagai teks biasa.
        if ($secretInput !== '') {
            $cipher = Crypto::encrypt($secretInput);
            if ($cipher === null) {
                Flash::error('APP_KEY belum diatur di .env, Client Secret tidak dapat disimpan terenkripsi. Atur APP_KEY dulu, lalu coba lagi.');
                redirect('/admin/drive');
            }
            Settings::set($pdo, 'google_client_secret', $cipher, true, $user['id']);
        }
    } catch (PDOException) {
        Flash::error('Pengaturan gagal disimpan. Silakan coba lagi.');
        redirect('/admin/drive');
    }

    Flash::success('Pengaturan OAuth Google berhasil disimpan.');
    redirect('/admin/drive');
});

$router->get('/admin/drive/connect', static function () use ($googleCredentials): void {
    Auth::requireRole('super_admin');

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, akun Drive belum dapat dikaitkan.');
        redirect('/admin');
    }

    $creds = $googleCredentials($pdo);
    if (!Crypto::isConfigured() || $creds['client_id'] === '' || $creds['secret_cipher'] === null) {
        Flash::error('Lengkapi dulu Client ID & Client Secret (dan APP_KEY di .env) sebelum mengaitkan akun Drive.');
        redirect('/admin/drive');
    }

    // State acak satu kali pakai, diikat ke session admin yang
    // memulai proses — diverifikasi di /auth/google/callback.
    Auth::start();
    $state = bin2hex(random_bytes(24));
    $_SESSION['google_oauth_state'] = $state;

    redirect(GoogleDrive::authUrl($creds['client_id'], Config::googleRedirectUri(), $state));
});

/**
 * Callback OAuth Google — rute PUBLIK (browser diarahkan Google ke
 * sini). Keamanan bertumpu pada: (1) cookie session admin yang
 * memulai proses harus masih ada, (2) parameter state harus cocok
 * dengan yang disimpan di session. Tanpa keduanya, ditolak.
 */
$router->get('/auth/google/callback', static function () use ($googleCredentials): void {
    Auth::start();
    $expectedState = $_SESSION['google_oauth_state'] ?? null;
    unset($_SESSION['google_oauth_state']); // Sekali pakai.

    $user = Auth::user();
    if ($user === null) {
        Flash::error('Sesi login tidak ditemukan atau sudah berakhir. Masuk kembali, lalu ulangi pengaitan Drive.');
        redirect('/login');
    }
    if (($user['role'] ?? '') !== 'super_admin') {
        Flash::error('Hanya Super Admin yang dapat mengaitkan akun Google Drive.');
        redirect('/dashboard');
    }

    $state = $_GET['state'] ?? null;
    if (!is_string($expectedState) || !is_string($state) || !hash_equals($expectedState, $state)) {
        Flash::error('Verifikasi keamanan OAuth gagal (state tidak cocok). Silakan ulangi pengaitan dari halaman Google Drive.');
        redirect('/admin/drive');
    }

    if (isset($_GET['error'])) {
        Flash::error('Google membatalkan atau menolak proses pengaitan. Silakan coba lagi dan setujui akses yang diminta.');
        redirect('/admin/drive');
    }

    $code = $_GET['code'] ?? null;
    if (!is_string($code) || $code === '') {
        Flash::error('Kode otorisasi dari Google tidak diterima. Silakan ulangi pengaitan.');
        redirect('/admin/drive');
    }

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, akun Drive tidak dapat disimpan.');
        redirect('/admin/drive');
    }

    $creds = $googleCredentials($pdo);
    $clientSecret = $creds['secret_cipher'] !== null ? Crypto::decrypt($creds['secret_cipher']) : null;
    if ($creds['client_id'] === '' || $clientSecret === null) {
        Flash::error('Kredensial OAuth Google tidak lengkap atau tidak dapat dibaca (periksa APP_KEY). Perbarui pengaturan lalu ulangi pengaitan.');
        redirect('/admin/drive');
    }

    $tokens = GoogleDrive::exchangeCode(
        $creds['client_id'],
        $clientSecret,
        $code,
        Config::googleRedirectUri()
    );
    if ($tokens === null || !isset($tokens['access_token'])) {
        Flash::error('Pertukaran kode otorisasi dengan Google gagal. Silakan coba lagi.');
        redirect('/admin/drive');
    }

    $refreshToken = $tokens['refresh_token'] ?? null;
    if (!is_string($refreshToken) || $refreshToken === '') {
        // Hampir tidak terjadi karena prompt=consent, tetapi Google
        // bisa saja tidak menerbitkan refresh token bila aplikasi
        // pernah diberi akses tanpa pencabutan.
        Flash::error('Google tidak memberikan refresh token. Hapus akses aplikasi ini di myaccount.google.com/permissions pada akun tersebut, lalu kaitkan lagi.');
        redirect('/admin/drive');
    }

    $accessToken = (string) $tokens['access_token'];

    $about = GoogleDrive::about($accessToken);
    if ($about === null) {
        Flash::error('Akun Google berhasil diautentikasi, tetapi info akunnya tidak dapat dibaca. Silakan coba lagi.');
        redirect('/admin/drive');
    }

    $rootFolderId = GoogleDrive::ensureRootFolder($accessToken);

    $encryptedRefresh = Crypto::encrypt($refreshToken);
    if ($encryptedRefresh === null) {
        Flash::error('APP_KEY belum diatur di .env, refresh token tidak dapat disimpan terenkripsi.');
        redirect('/admin/drive');
    }

    try {
        $accountId = GoogleAccount::upsertByEmail($pdo, [
            'google_email' => $about['email'],
            'refresh_token_encrypted' => $encryptedRefresh,
            'root_folder_id' => $rootFolderId,
            'quota_total_bytes' => $about['quota_total'],
            'quota_used_bytes' => $about['quota_used'],
            'connected_by' => $user['id'],
            'label' => $about['display_name'],
        ]);

        // Akun pertama (atau bila belum ada default sama sekali)
        // otomatis menjadi akun default.
        if (GoogleAccount::defaultAccount($pdo) === null) {
            GoogleAccount::setDefault($pdo, $accountId);
        }
    } catch (PDOException) {
        Flash::error('Akun Google gagal disimpan ke database. Silakan coba lagi.');
        redirect('/admin/drive');
    }

    if ($rootFolderId === null) {
        Flash::info('Akun ' . $about['email'] . ' terkait, tetapi folder root "Plugin Eventbooth" belum berhasil dibuat — gunakan Tes Koneksi untuk memeriksa aksesnya.');
    }

    Flash::success('Akun Google ' . $about['email'] . ' berhasil dikaitkan sebagai Drive pusat.');
    redirect('/admin/drive');
});

$router->post('/admin/drive/{id}/default', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, akun default belum dapat diubah.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);
    $account = GoogleAccount::find($pdo, $id);
    if ($account === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    try {
        GoogleAccount::setDefault($pdo, $id);
    } catch (PDOException) {
        Flash::error('Akun default gagal diubah. Silakan coba lagi.');
        redirect('/admin/drive');
    }

    Flash::success('Akun ' . (string) $account['google_email'] . ' sekarang menjadi Drive default.');
    redirect('/admin/drive');
});

$router->post('/admin/drive/{id}/test', static function (array $params) use ($googleCredentials): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, koneksi belum dapat dites.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);
    $account = GoogleAccount::find($pdo, $id);
    if ($account === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $email = (string) $account['google_email'];

    $creds = $googleCredentials($pdo);
    $clientSecret = $creds['secret_cipher'] !== null ? Crypto::decrypt($creds['secret_cipher']) : null;
    $refreshToken = Crypto::decrypt((string) $account['refresh_token_encrypted']);

    if ($creds['client_id'] === '' || $clientSecret === null || $refreshToken === null) {
        try {
            GoogleAccount::setStatus($pdo, $id, 'error');
        } catch (PDOException) {
            // Status gagal ditulis; pesan ke pengguna tetap disampaikan.
        }
        Flash::error('Kredensial OAuth atau refresh token akun ' . $email . ' tidak dapat dibaca (periksa Client ID/Secret dan APP_KEY).');
        redirect('/admin/drive');
    }

    $accessToken = GoogleDrive::refreshAccessToken($creds['client_id'], $clientSecret, $refreshToken);
    $about = $accessToken !== null ? GoogleDrive::about($accessToken) : null;

    if ($about === null) {
        try {
            GoogleAccount::setStatus($pdo, $id, 'error');
        } catch (PDOException) {
            // Abaikan: pesan error utama sudah jelas.
        }
        Flash::error('Tes koneksi akun ' . $email . ' gagal — Google menolak tokennya. Coba kaitkan ulang akun ini.');
        redirect('/admin/drive');
    }

    try {
        GoogleAccount::updateQuota($pdo, $id, $about['quota_total'], $about['quota_used']);
        GoogleAccount::setStatus($pdo, $id, 'connected');

        // Folder root yang belum terbentuk dicoba dibuat lagi di sini.
        if (empty($account['root_folder_id']) && $accessToken !== null) {
            $folderId = GoogleDrive::ensureRootFolder($accessToken);
            if ($folderId !== null) {
                GoogleAccount::updateRootFolder($pdo, $id, $folderId);
            }
        }
    } catch (PDOException) {
        Flash::error('Hasil tes koneksi gagal disimpan. Silakan coba lagi.');
        redirect('/admin/drive');
    }

    Flash::success('Koneksi akun ' . $email . ' sehat — kuota Drive diperbarui.');
    redirect('/admin/drive');
});

$router->post('/admin/drive/{id}/disconnect', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, akun belum dapat diputuskan.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);
    $account = GoogleAccount::find($pdo, $id);
    if ($account === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $email = (string) $account['google_email'];
    $wasDefault = (int) ($account['is_default'] ?? 0) === 1;

    // Cabut akses di sisi Google (usaha terbaik — kegagalan di sini
    // tidak menghalangi pemutusan lokal).
    $refreshToken = Crypto::decrypt((string) $account['refresh_token_encrypted']);
    if ($refreshToken !== null) {
        GoogleDrive::revoke($refreshToken);
    }

    try {
        GoogleAccount::delete($pdo, $id);

        // Default yang hilang digantikan akun tertua yang tersisa.
        if ($wasDefault) {
            $remaining = GoogleAccount::all($pdo);
            if ($remaining !== []) {
                GoogleAccount::setDefault($pdo, (int) $remaining[0]['id']);
            }
        }
    } catch (PDOException) {
        Flash::error('Akun gagal diputuskan. Silakan coba lagi.');
        redirect('/admin/drive');
    }

    Flash::success('Akun ' . $email . ' sudah diputuskan dari Drive pusat.');
    redirect('/admin/drive');
});

/* ---------------------------------------------------------------------
 * Tenant & Lisensi (Milestone 4) — hanya role super_admin.
 *
 * Data tenant lengkap (vendor, penanggung jawab, email, WhatsApp,
 * alamat, catatan) karena kontaknya dipakai notifikasi perpanjangan.
 * Kunci lisensi disimpan sebagai hash SHA-256 + prefix; kunci utuh
 * ditampilkan SEKALI saat penerbitan lewat session.
 *
 * Halaman-halaman ini memakai kolom migrasi M4 (vendor_name,
 * whatsapp, address, plan_snapshot, last_seen_at) — sebelum migrasi
 * dijalankan, query melempar PDOException dan handler mengalihkan
 * dengan pesan untuk menjalankan migrasi.
 * ------------------------------------------------------------------- */
$router->get('/admin/tenants', static function (): void {
    $user = Auth::requireRole('super_admin');

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, daftar tenant belum dapat ditampilkan.');
        redirect('/admin');
    }

    $query = trim((string) ($_GET['q'] ?? ''));

    try {
        $tenants = Tenant::search($pdo, $query);
        $tokenSet = false;
        try {
            $storedToken = Settings::get($pdo, 'automation_token');
            $tokenSet = is_string($storedToken) && $storedToken !== '';
        } catch (PDOException) {
            $tokenSet = false;
        }
    } catch (PDOException) {
        Flash::error('Tabel tenant belum lengkap. Jalankan migrasi Milestone 4 (database/migrations/2026-10-31-m4-tenant-license.sql) di phpMyAdmin.');
        redirect('/admin');
    }

    // Token otomasi baru hanya ditampilkan satu kali (dari session).
    Auth::start();
    $tokenOnce = $_SESSION['automation_token_once'] ?? null;
    unset($_SESSION['automation_token_once']);

    View::render('admin/tenants/index', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'tenants' => $tenants,
        'query' => $query,
        'automationTokenSet' => $tokenSet,
        'automationTokenOnce' => is_string($tokenOnce) ? $tokenOnce : null,
    ]);
});

$router->post('/admin/automation/token/regenerate', static function (): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, token otomasi belum dapat dibuat.');
        redirect('/admin');
    }

    // 32 karakter heksadesimal dari 16 byte acak.
    $token = bin2hex(random_bytes(16));

    try {
        Settings::set($pdo, 'automation_token', $token, false, $user['id']);
    } catch (PDOException) {
        Flash::error('Token otomasi gagal disimpan. Silakan coba lagi.');
        redirect('/admin/tenants');
    }

    // Tampilkan sekali lewat session; sesudah itu token tidak
    // pernah dirender lagi dari nilai tersimpannya.
    Auth::start();
    $_SESSION['automation_token_once'] = $token;

    Flash::success('Token otomasi baru dibuat. Salin token di bawah ini ke kredensial workflow n8n.');
    redirect('/admin/tenants');
});

// PENTING: rute /create didaftarkan SEBELUM /{id} agar kata
// "create" tidak tertangkap sebagai parameter id.
$router->get('/admin/tenants/create', static function (): void {
    $user = Auth::requireRole('super_admin');

    View::render('admin/tenants/form', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'heading' => 'Tambah Tenant',
        'action' => '/admin/tenants',
        'values' => Tenant::formValues(),
        'errors' => [],
        'isEdit' => false,
    ]);
});

$router->post('/admin/tenants', static function (): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, tenant belum dapat disimpan.');
        redirect('/admin');
    }

    try {
        [$data, $errors] = Tenant::validate($pdo, $_POST);
        [$loginAccount, $loginErrors] = Tenant::validateLoginAccount(
            $pdo,
            $_POST,
            (string) ($data['contact_name'] ?? '')
        );
        $errors = array_merge($errors, $loginErrors);

        if ($errors !== []) {
            $flashes = Flash::pull();
            $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
            View::render('admin/tenants/form', [
                'appName' => Config::appName(),
                'user' => $user,
                'flashes' => $flashes,
                'heading' => 'Tambah Tenant',
                'action' => '/admin/tenants',
                'values' => Tenant::formValues(null, $_POST),
                'errors' => $errors,
                'isEdit' => false,
            ], 422);
            return;
        }

        // Tenant + akun login awal dibuat atomik.
        $pdo->beginTransaction();
        $tenantId = Tenant::create($pdo, $data);
        if ($loginAccount !== null) {
            Tenant::createUser(
                $pdo,
                $tenantId,
                $loginAccount['name'],
                $loginAccount['email'],
                $loginAccount['password'],
                'tenant_admin'
            );
        }
        $pdo->commit();
    } catch (PDOException) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Flash::error('Tenant gagal disimpan. Pastikan migrasi Milestone 4 sudah dijalankan, lalu coba lagi.');
        redirect('/admin/tenants/create');
    }

    Flash::success('Tenant "' . (string) $data['vendor_name'] . '" berhasil dibuat.');
    redirect('/admin/tenants/' . $tenantId);
});

$router->get('/admin/tenants/{id}', static function (array $params): void {
    $user = Auth::requireRole('super_admin');

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, detail tenant belum dapat ditampilkan.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);

    try {
        $tenant = Tenant::find($pdo, $id);
        if ($tenant === null) {
            View::render('errors/404', [
                'appName' => Config::appName(),
                'appUrl' => Config::appUrl(),
            ], 404);
            return;
        }

        $tenantUsers = Tenant::usersOf($pdo, $id);
        $licenses = License::forTenant($pdo, $id);
        $devicesByLicense = [];
        foreach ($licenses as $licenseRow) {
            try {
                $devicesByLicense[(int) $licenseRow['id']] =
                    License::devicesFor($pdo, (int) $licenseRow['id']);
            } catch (PDOException) {
                $devicesByLicense[(int) $licenseRow['id']] = [];
            }
        }
        $activePlans = array_values(array_filter(
            Plan::all($pdo),
            static fn (array $plan): bool => (int) ($plan['is_active'] ?? 0) === 1
        ));
    } catch (PDOException) {
        Flash::error('Data tenant belum dapat dibaca. Pastikan migrasi Milestone 4 (database/migrations/2026-10-31-m4-tenant-license.sql) sudah dijalankan.');
        redirect('/admin/tenants');
    }

    // Kunci lisensi yang baru diterbitkan tampil satu kali.
    Auth::start();
    $newKey = $_SESSION['new_license_key'] ?? null;
    unset($_SESSION['new_license_key']);

    View::render('admin/tenants/detail', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'tenant' => $tenant,
        'tenantUsers' => $tenantUsers,
        'licenses' => $licenses,
        'devicesByLicense' => $devicesByLicense,
        'activePlans' => $activePlans,
        'newKey' => is_string($newKey) ? $newKey : null,
    ]);
});

$router->get('/admin/tenants/{id}/edit', static function (array $params): void {
    $user = Auth::requireRole('super_admin');

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, tenant belum dapat diubah.');
        redirect('/admin');
    }

    try {
        $tenant = Tenant::find($pdo, (int) ($params['id'] ?? 0));
    } catch (PDOException) {
        Flash::error('Data tenant belum dapat dibaca. Pastikan migrasi Milestone 4 sudah dijalankan.');
        redirect('/admin/tenants');
    }

    if ($tenant === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    View::render('admin/tenants/form', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'heading' => 'Ubah Tenant',
        'action' => '/admin/tenants/' . (int) $tenant['id'],
        'values' => Tenant::formValues($tenant),
        'errors' => [],
        'isEdit' => true,
    ]);
});

$router->post('/admin/tenants/{id}', static function (array $params): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, tenant belum dapat disimpan.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);

    try {
        $tenant = Tenant::find($pdo, $id);
    } catch (PDOException) {
        Flash::error('Data tenant belum dapat dibaca. Pastikan migrasi Milestone 4 sudah dijalankan.');
        redirect('/admin/tenants');
    }

    if ($tenant === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    try {
        [$data, $errors] = Tenant::validate($pdo, $_POST, $tenant);

        if ($errors !== []) {
            $flashes = Flash::pull();
            $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
            View::render('admin/tenants/form', [
                'appName' => Config::appName(),
                'user' => $user,
                'flashes' => $flashes,
                'heading' => 'Ubah Tenant',
                'action' => '/admin/tenants/' . $id,
                'values' => Tenant::formValues($tenant, $_POST),
                'errors' => $errors,
                'isEdit' => true,
            ], 422);
            return;
        }

        Tenant::update($pdo, $id, $data);
    } catch (PDOException) {
        Flash::error('Tenant gagal disimpan. Pastikan migrasi Milestone 4 sudah dijalankan, lalu coba lagi.');
        redirect('/admin/tenants/' . $id . '/edit');
    }

    Flash::success('Tenant "' . (string) $data['vendor_name'] . '" berhasil diperbarui.');
    redirect('/admin/tenants/' . $id);
});

$router->post('/admin/tenants/{id}/users', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, akun login belum dapat dibuat.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);
    $tenant = Tenant::find($pdo, $id);
    if ($tenant === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $name = trim((string) ($_POST['user_name'] ?? ''));
    $email = trim((string) ($_POST['user_email'] ?? ''));
    $password = (string) ($_POST['user_password'] ?? '');
    $role = (string) ($_POST['user_role'] ?? 'tenant_admin');

    if ($name === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        Flash::error('Nama dan email akun login wajib diisi dengan format yang sah.');
        redirect('/admin/tenants/' . $id);
    }
    if (strlen($password) < 8) {
        Flash::error('Kata sandi awal akun login minimal 8 karakter.');
        redirect('/admin/tenants/' . $id);
    }
    if (Tenant::userEmailTaken($pdo, $email)) {
        Flash::error('Email login "' . $email . '" sudah terdaftar sebagai pengguna. Gunakan email lain.');
        redirect('/admin/tenants/' . $id);
    }

    try {
        Tenant::createUser($pdo, $id, $name, $email, $password, $role);
    } catch (PDOException) {
        Flash::error('Akun login gagal dibuat. Silakan coba lagi.');
        redirect('/admin/tenants/' . $id);
    }

    Flash::success('Akun login "' . $email . '" berhasil dibuat untuk tenant ini.');
    redirect('/admin/tenants/' . $id);
});

$router->post('/admin/tenants/{id}/licenses', static function (array $params): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, lisensi belum dapat diterbitkan.');
        redirect('/admin');
    }

    $id = (int) ($params['id'] ?? 0);
    $tenant = Tenant::find($pdo, $id);
    if ($tenant === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $plan = Plan::find($pdo, (int) ($_POST['plan_id'] ?? 0));
    if ($plan === null || (int) ($plan['is_active'] ?? 0) !== 1) {
        Flash::error('Plan yang dipilih tidak ditemukan atau sedang nonaktif.');
        redirect('/admin/tenants/' . $id);
    }

    try {
        $issued = License::issue($pdo, $id, $plan, (int) $user['id']);
    } catch (PDOException) {
        Flash::error('Lisensi gagal diterbitkan. Pastikan migrasi Milestone 4 (kolom plan_snapshot) sudah dijalankan.');
        redirect('/admin/tenants/' . $id);
    } catch (RuntimeException) {
        Flash::error('Lisensi gagal diterbitkan. Silakan coba lagi.');
        redirect('/admin/tenants/' . $id);
    }

    // Kunci utuh hanya disimpan di session untuk ditampilkan
    // SEKALI di halaman detail; database hanya menyimpan hash-nya.
    Auth::start();
    $_SESSION['new_license_key'] = $issued['key'];

    Flash::success('Lisensi plan "' . (string) $plan['name'] . '" berhasil diterbitkan untuk ' . Tenant::displayName($tenant) . '.');
    redirect('/admin/tenants/' . $id);
});

$router->post('/admin/licenses/{id}/renew', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, lisensi belum dapat diperpanjang.');
        redirect('/admin');
    }

    $license = License::find($pdo, (int) ($params['id'] ?? 0));
    if ($license === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $tenantId = (int) $license['tenant_id'];
    $wasSuspended = (string) $license['status'] === 'suspended';

    try {
        $newExpires = License::renew($pdo, $license);
    } catch (PDOException) {
        Flash::error('Perpanjangan gagal disimpan. Silakan coba lagi.');
        redirect('/admin/tenants/' . $tenantId);
    }

    if ($newExpires === null) {
        Flash::error('Lisensi ini tidak dapat diperpanjang (sudah dicabut atau tanpa kedaluwarsa).');
        redirect('/admin/tenants/' . $tenantId);
    }

    $message = 'Lisensi berhasil diperpanjang sampai ' . License::formatDateId($newExpires) . '.';
    if ($wasSuspended) {
        $message .= ' Lisensi yang ditangguhkan ikut diaktifkan kembali.';
    }
    Flash::success($message);
    redirect('/admin/tenants/' . $tenantId);
});

$router->post('/admin/licenses/{id}/suspend', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, status lisensi belum dapat diubah.');
        redirect('/admin');
    }

    $license = License::find($pdo, (int) ($params['id'] ?? 0));
    if ($license === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $tenantId = (int) $license['tenant_id'];
    $status = (string) $license['status'];

    if ($status === 'revoked') {
        Flash::error('Lisensi yang sudah dicabut tidak dapat diubah lagi.');
        redirect('/admin/tenants/' . $tenantId);
    }

    try {
        if ($status === 'suspended') {
            License::setStatus($pdo, (int) $license['id'], 'active');
            Flash::success('Lisensi berhasil diaktifkan kembali.');
        } else {
            License::setStatus($pdo, (int) $license['id'], 'suspended');
            Flash::success('Lisensi berhasil ditangguhkan. Aplikasi booth tenant akan ditolak sampai diaktifkan lagi.');
        }
    } catch (PDOException) {
        Flash::error('Status lisensi gagal diubah. Silakan coba lagi.');
    }

    redirect('/admin/tenants/' . $tenantId);
});

$router->post('/admin/licenses/{id}/revoke', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, lisensi belum dapat dicabut.');
        redirect('/admin');
    }

    $license = License::find($pdo, (int) ($params['id'] ?? 0));
    if ($license === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $tenantId = (int) $license['tenant_id'];

    if ((string) $license['status'] === 'revoked') {
        Flash::info('Lisensi ini memang sudah dicabut sebelumnya.');
        redirect('/admin/tenants/' . $tenantId);
    }

    try {
        License::setStatus($pdo, (int) $license['id'], 'revoked');
    } catch (PDOException) {
        Flash::error('Lisensi gagal dicabut. Silakan coba lagi.');
        redirect('/admin/tenants/' . $tenantId);
    }

    Flash::success('Lisensi berhasil dicabut secara permanen.');
    redirect('/admin/tenants/' . $tenantId);
});

/**
 * Cabut perangkat dari sebuah lisensi oleh Super Admin
 * (Milestone 4c, untuk keperluan dukungan): slot langsung bebas
 * dan token sesi perangkat itu mati.
 */
$router->post('/admin/licenses/{licenseId}/devices/{deviceId}/revoke', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, perangkat belum dapat dicabut.');
        redirect('/admin');
    }

    $licenseId = (int) ($params['licenseId'] ?? 0);
    $deviceRowId = (int) ($params['deviceId'] ?? 0);

    $license = License::find($pdo, $licenseId);
    if ($license === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $tenantId = (int) $license['tenant_id'];

    try {
        $revoked = License::revokeDevice($pdo, $licenseId, $deviceRowId);
    } catch (PDOException) {
        Flash::error('Perangkat gagal dicabut. Pastikan migrasi Milestone 4c (database/migrations/2026-10-31-m4c-device-token.sql) sudah dijalankan.');
        redirect('/admin/tenants/' . $tenantId);
    }

    if (!$revoked) {
        Flash::error('Perangkat tidak ditemukan pada lisensi ini.');
        redirect('/admin/tenants/' . $tenantId);
    }

    Flash::success('Perangkat dicabut dari lisensi ini — slot tersedia lagi.');
    redirect('/admin/tenants/' . $tenantId);
});

/* ---------------------------------------------------------------------
 * Area Tenant (Milestone 2) — role tenant_admin & tenant_staff.
 * Semua query dibatasi tenant_id milik pengguna yang login.
 * ------------------------------------------------------------------- */
$router->get('/tenant', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $tenant = null;
    $license = null;
    $counts = ['events' => 0, 'licenses' => 0, 'sessions' => 0];

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, name, slug, contact_name, email, phone, status, created_at
                   FROM tenants
                  WHERE id = :id
                  LIMIT 1'
            );
            $stmt->execute(['id' => $tenantId]);
            $row = $stmt->fetch();
            $tenant = $row === false ? null : $row;

            $countQueries = [
                'events' => 'SELECT COUNT(*) FROM events WHERE tenant_id = :id',
                'licenses' => 'SELECT COUNT(*) FROM licenses WHERE tenant_id = :id',
                'sessions' => 'SELECT COUNT(*) FROM sessions s
                                JOIN events ev ON ev.id = s.event_id
                               WHERE ev.tenant_id = :id',
            ];
            foreach ($countQueries as $key => $sql) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id' => $tenantId]);
                $counts[$key] = (int) $stmt->fetchColumn();
            }

            $stmt = $pdo->prepare(
                'SELECT p.name AS plan_name, l.status AS status, l.expires_at AS expires_at
                   FROM licenses l
                   JOIN plans p ON p.id = l.plan_id
                  WHERE l.tenant_id = :id
                  ORDER BY l.id DESC
                  LIMIT 1'
            );
            $stmt->execute(['id' => $tenantId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $license = [
                    'plan_name' => (string) $row['plan_name'],
                    'status' => (string) $row['status'],
                    'expires_at' => $row['expires_at'] === null ? null : (string) $row['expires_at'],
                ];
            }
        } catch (PDOException) {
            // Halaman tetap tampil dengan nilai bawaan.
        }
    }

    View::render('tenant/overview', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'tenant' => $tenant,
        'counts' => $counts,
        'license' => $license,
    ]);
});

/**
 * Lisensi milik tenant yang login (Milestone 4; kunci tampil penuh
 * sesuai keputusan pemilik v0.5.1). Query dibatasi tenant_id
 * pengguna — tenant tidak pernah melihat lisensi tenant lain.
 *
 * Sejak M4c halaman ini juga menandai lisensi yang sedang dipakai
 * aplikasi (currentForTenant) dan memuat daftar perangkat tiap
 * lisensi agar tenant bisa mencabut perangkatnya sendiri.
 */
$router->get('/tenant/licenses', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $licenses = [];
    $currentLicenseId = null;
    $devicesByLicense = [];
    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $licenses = License::forTenant($pdo, (int) $tenantId);
            $current = License::currentForTenant($pdo, (int) $tenantId);
            $currentLicenseId = $current !== null ? (int) $current['id'] : null;
            foreach ($licenses as $licenseRow) {
                try {
                    $devicesByLicense[(int) $licenseRow['id']] =
                        License::devicesFor($pdo, (int) $licenseRow['id']);
                } catch (PDOException) {
                    $devicesByLicense[(int) $licenseRow['id']] = [];
                }
            }
        } catch (PDOException) {
            // Halaman tetap tampil dengan keadaan kosong.
            $licenses = [];
            $currentLicenseId = null;
            $devicesByLicense = [];
        }
    }

    View::render('tenant/licenses', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'licenses' => $licenses,
        'currentLicenseId' => $currentLicenseId,
        'devicesByLicense' => $devicesByLicense,
    ]);
});

/**
 * Cabut perangkat dari dashboard tenant (Milestone 4c): slot
 * perangkat lisensi langsung bebas lagi. Lisensi WAJIB milik
 * tenant pengguna yang login dan perangkat wajib milik lisensi
 * itu — keduanya diverifikasi sebelum pencabutan.
 */
$router->post('/tenant/licenses/{licenseId}/devices/{deviceId}/revoke', static function (array $params): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, perangkat belum dapat dicabut.');
        redirect('/tenant/licenses');
    }

    $licenseId = (int) ($params['licenseId'] ?? 0);
    $deviceRowId = (int) ($params['deviceId'] ?? 0);
    $tenantId = $user['tenant_id'];

    try {
        $license = License::find($pdo, $licenseId);
        if ($license === null || $tenantId === null || (int) $license['tenant_id'] !== (int) $tenantId) {
            View::render('errors/404', [
                'appName' => Config::appName(),
                'appUrl' => Config::appUrl(),
            ], 404);
            return;
        }

        $revoked = License::revokeDevice($pdo, $licenseId, $deviceRowId);
    } catch (PDOException) {
        Flash::error('Perangkat gagal dicabut. Pastikan migrasi Milestone 4c (database/migrations/2026-10-31-m4c-device-token.sql) sudah dijalankan.');
        redirect('/tenant/licenses');
    }

    if (!$revoked) {
        Flash::error('Perangkat tidak ditemukan pada lisensi ini.');
        redirect('/tenant/licenses');
    }

    Flash::success('Perangkat dicabut — slot tersedia lagi.');
    redirect('/tenant/licenses');
});

/* ---------------------------------------------------------------------
 * API — health check
 * ------------------------------------------------------------------- */
$router->get('/api/health', static function (): void {
    Json::respond([
        'status' => 'ok',
        'app' => Config::appName(),
        'version' => Config::appVersion(),
        'milestone' => Config::milestone(),
        'time' => date('c'),
        'timezone' => date_default_timezone_get(),
        // Hanya status; tidak pernah memuat kredensial/error mentah.
        'db' => Database::healthStatus(),
    ]);
});

/* ---------------------------------------------------------------------
 * API — helper bersama (Milestone 4)
 * ------------------------------------------------------------------- */

/** Baca body JSON request; array kosong bila body bukan JSON objek. */
$readJsonBody = static function (): array {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
};

/**
 * Penjaga endpoint otomasi: header X-Automation-Token wajib cocok
 * (hash_equals) dengan Settings 'automation_token'. Mengembalikan
 * PDO yang siap pakai; kegagalan apa pun diakhiri respons JSON
 * (401/503) — tidak pernah halaman HTML.
 */
$automationGuard = static function (): PDO {
    $pdo = Database::pdo();
    if ($pdo === null) {
        Json::error('Server belum dikonfigurasi.', 503, 'server_not_configured');
    }

    $stored = null;
    try {
        $stored = Settings::get($pdo, 'automation_token');
    } catch (PDOException) {
        $stored = null;
    }

    $given = $_SERVER['HTTP_X_AUTOMATION_TOKEN'] ?? null;
    if (!is_string($stored) || $stored === '' || !is_string($given) || !hash_equals($stored, $given)) {
        Json::error('Token otomasi tidak sah.', 401, 'unauthorized');
    }

    return $pdo;
};

/**
 * Gerbang bersama API lisensi (aktivasi & validasi): memeriksa
 * konfigurasi server, keberadaan lisensi, status lisensi, dan
 * status tenant. Mengembalikan konteks yang dibutuhkan handler;
 * semua kegagalan diakhiri error JSON berkode stabil.
 *
 * @param array<string, mixed> $body
 * @return array{pdo: PDO, license: array<string, mixed>, tenant: array<string, mixed>,
 *               plan: array<string, mixed>|null, device_id: string,
 *               device_name: ?string, app_version: ?string}
 */
$licenseApiEntry = static function (array $body): array {
    if (!Crypto::isConfigured()) {
        Json::error('Server belum dikonfigurasi (APP_KEY belum diatur).', 503, 'server_not_configured');
    }

    $pdo = Database::pdo();
    if ($pdo === null) {
        Json::error('Server belum dikonfigurasi.', 503, 'server_not_configured');
    }

    $licenseKey = isset($body['license_key']) && is_string($body['license_key'])
        ? trim($body['license_key']) : '';
    $deviceId = isset($body['device_id']) && is_string($body['device_id'])
        ? trim($body['device_id']) : '';

    if ($licenseKey === '' || $deviceId === '') {
        Json::error('license_key dan device_id wajib diisi.', 422, 'invalid_request');
    }

    $cap = static function (?string $value, int $max): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $max, 'UTF-8')
            : substr($value, 0, $max);
    };

    $deviceName = $cap(isset($body['device_name']) && is_string($body['device_name']) ? $body['device_name'] : null, 120);
    $appVersion = $cap(isset($body['app_version']) && is_string($body['app_version']) ? $body['app_version'] : null, 32);

    try {
        $license = License::findByKey($pdo, $licenseKey);
        if ($license === null) {
            Json::error('Lisensi tidak ditemukan.', 404, 'not_found');
        }

        $tenant = Tenant::find($pdo, (int) $license['tenant_id']);
        if ($tenant === null) {
            Json::error('Lisensi tidak ditemukan.', 404, 'not_found');
        }

        $plan = Plan::find($pdo, (int) $license['plan_id']);
    } catch (PDOException) {
        Json::error('Layanan lisensi belum siap.', 503, 'server_not_configured');
    }

    // Urutan pemeriksaan: pencabutan & penangguhan lisensi, lalu
    // status tenant, lalu kedaluwarsa (dihitung dari expires_at).
    if ((string) $license['status'] === 'revoked') {
        Json::error('Lisensi sudah dicabut.', 403, 'revoked');
    }
    if ((string) $license['status'] === 'suspended') {
        Json::error('Lisensi sedang ditangguhkan. Hubungi EventBooth.', 403, 'suspended');
    }
    if ((string) ($tenant['status'] ?? 'active') !== 'active') {
        Json::error('Tenant sedang dinonaktifkan. Hubungi EventBooth.', 403, 'tenant_inactive');
    }
    if (License::effectiveStatus($license) === 'expired') {
        Json::error('Lisensi sudah kedaluwarsa. Silakan perpanjang.', 410, 'expired');
    }

    return [
        'pdo' => $pdo,
        'license' => $license,
        'tenant' => $tenant,
        'plan' => $plan,
        'device_id' => $deviceId,
        'device_name' => $deviceName,
        'app_version' => $appVersion,
    ];
};

/**
 * Payload keberhasilan API lisensi + tanda tangan HMAC-SHA256.
 * Kanonisasi tanda tangan: json_encode payload TANPA kunci
 * 'signature', urutan kunci persis seperti susunan di bawah —
 * aplikasi booth memverifikasi dengan cara yang sama memakai kunci
 * turunan APP_KEY yang sama (hash('sha256', APP_KEY)).
 *
 * @param array<string, mixed>      $license
 * @param array<string, mixed>|null $plan
 * @return array<string, mixed>
 */
$licenseApiPayload = static function (array $license, ?array $plan): array {
    $ent = License::entitlements($license, $plan);

    $payload = [
        'status' => 'active',
        'license' => [
            'plan' => $ent['plan_name'],
            'expires_at' => $license['expires_at'] ?? null,
            'issued_at' => $license['starts_at'] ?? null,
        ],
        'entitlements' => [
            'max_devices' => $ent['max_devices'],
            'max_events' => $ent['max_events'],
            'drive_quota_gb' => $ent['drive_quota_gb'],
            'session_quota' => $ent['session_quota'],
            'retention_days' => $ent['retention_days'],
            'features' => $ent['features'],
        ],
        'server_time' => date('c'),
    ];

    $payload['signature'] = hash_hmac(
        'sha256',
        (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        hash('sha256', (string) Config::appKey())
    );

    return $payload;
};

/* ---------------------------------------------------------------------
 * API — otomasi pengingat perpanjangan (Milestone 4).
 * Dipanggil workflow n8n (penjaga: header X-Automation-Token).
 * Pengiriman WhatsApp dilakukan n8n lewat MPWA; website hanya
 * menyediakan daftar jatuh tempo + pencatatan pengiriman.
 * ------------------------------------------------------------------- */
$router->get('/api/automation/expiring-licenses', static function () use ($automationGuard): void {
    $pdo = $automationGuard();

    try {
        $items = [];
        foreach (License::REMINDER_THRESHOLDS as $threshold) {
            $stmt = $pdo->prepare(
                "SELECT l.id AS license_id, l.license_key_prefix, l.expires_at,
                        p.name AS plan_name,
                        t.vendor_name, t.name AS tenant_name, t.contact_name,
                        t.whatsapp, t.phone
                   FROM licenses l
                   JOIN tenants t ON t.id = l.tenant_id
                   JOIN plans p ON p.id = l.plan_id
                  WHERE l.status IN ('active', 'pending')
                    AND t.status = 'active'
                    AND l.expires_at IS NOT NULL
                    AND l.expires_at >= NOW()
                    AND l.expires_at <= DATE_ADD(NOW(), INTERVAL {$threshold} DAY)
                    AND NOT EXISTS (
                        SELECT 1 FROM license_notifications n
                         WHERE n.license_id = l.id
                           AND n.threshold_days = :threshold
                           AND n.expires_at_snapshot = l.expires_at
                    )
                  ORDER BY l.expires_at ASC"
            );
            $stmt->execute(['threshold' => $threshold]);

            foreach ($stmt->fetchAll() as $row) {
                $daysLeft = (int) floor((strtotime((string) $row['expires_at']) - time()) / 86400);
                $vendor = trim((string) ($row['vendor_name'] ?? '')) !== ''
                    ? (string) $row['vendor_name']
                    : (string) ($row['tenant_name'] ?? '');
                $whatsapp = Whatsapp::normalize($row['whatsapp'] ?? null)
                    ?? Whatsapp::normalize($row['phone'] ?? null);
                $tanggal = License::formatDateId((string) $row['expires_at']);

                $items[] = [
                    'license_id' => (int) $row['license_id'],
                    // Kunci utuh tidak disimpan server (hanya hash),
                    // jadi yang dikirim adalah prefix identifikasinya.
                    'license_key' => (string) ($row['license_key_prefix'] ?? ''),
                    'vendor_name' => $vendor,
                    'contact_name' => $row['contact_name'] !== null ? (string) $row['contact_name'] : null,
                    'whatsapp' => $whatsapp,
                    'plan_name' => (string) ($row['plan_name'] ?? ''),
                    'expires_at' => (string) $row['expires_at'],
                    'days_left' => $daysLeft,
                    'threshold_days' => $threshold,
                    'message' => 'Halo ' . $vendor . ', lisensi Plugin Eventbooth Anda (plan '
                        . (string) ($row['plan_name'] ?? '') . ') akan berakhir pada ' . $tanggal
                        . ' (' . $daysLeft . ' hari lagi). Silakan hubungi EventBooth untuk '
                        . 'perpanjangan agar layanan booth Anda tidak terganggu. Terima kasih.',
                ];
            }
        }
    } catch (PDOException) {
        Json::error('Data pengingat belum dapat dibaca.', 503, 'server_not_configured');
    }

    Json::respond([
        'items' => $items,
        'generated_at' => date('c'),
    ]);
});

$router->post('/api/automation/mark-notified', static function () use ($automationGuard, $readJsonBody): void {
    $pdo = $automationGuard();
    $body = $readJsonBody();

    $licenseId = isset($body['license_id']) ? (int) $body['license_id'] : 0;
    $threshold = isset($body['threshold_days']) ? (int) $body['threshold_days'] : 0;

    if ($licenseId <= 0 || !in_array($threshold, License::REMINDER_THRESHOLDS, true)) {
        Json::error('license_id dan threshold_days (7, 3, atau 1) wajib diisi dengan benar.', 422, 'invalid_request');
    }

    try {
        $license = License::find($pdo, $licenseId);
        if ($license === null) {
            Json::error('Lisensi tidak ditemukan.', 404, 'not_found');
        }
        if (empty($license['expires_at'])) {
            Json::error('Lisensi ini tidak memiliki tanggal kedaluwarsa.', 422, 'invalid_request');
        }

        // Idempoten: pasangan unik (lisensi, ambang, expires_at)
        // membuat panggilan ulang hanya memperbarui baris yang sama.
        $stmt = $pdo->prepare(
            'INSERT INTO license_notifications
                (license_id, threshold_days, expires_at_snapshot, channel, status, sent_at)
             VALUES
                (:license_id, :threshold, :expires_at, \'whatsapp\', \'sent\', NOW())
             ON DUPLICATE KEY UPDATE status = \'sent\', sent_at = NOW()'
        );
        $stmt->execute([
            'license_id' => $licenseId,
            'threshold' => $threshold,
            'expires_at' => (string) $license['expires_at'],
        ]);
    } catch (PDOException) {
        Json::error('Pencatatan pengingat gagal.', 503, 'server_not_configured');
    }

    Json::respond(['ok' => true]);
});

/* ---------------------------------------------------------------------
 * API — lisensi aplikasi booth (Milestone 4, blueprint §2 & §5A).
 * Body JSON: {license_key, device_id, device_name?, app_version?}.
 * device_id mentah TIDAK pernah disimpan — hanya hash SHA-256-nya.
 * ------------------------------------------------------------------- */
$router->post('/api/license/activate', static function () use ($readJsonBody, $licenseApiEntry, $licenseApiPayload): void {
    $ctx = $licenseApiEntry($readJsonBody());
    $pdo = $ctx['pdo'];
    $license = $ctx['license'];
    $licenseId = (int) $license['id'];
    $deviceHash = hash('sha256', $ctx['device_id']);

    try {
        $device = License::findDevice($pdo, $licenseId, $deviceHash);
        $isNew = $device === null || (int) ($device['is_active'] ?? 0) !== 1;

        if ($isNew) {
            $ent = License::entitlements($license, $ctx['plan']);
            $maxDevices = $ent['max_devices'];
            if ($maxDevices > 0 && License::deviceCount($pdo, $licenseId) >= $maxDevices) {
                Json::error(
                    'Batas perangkat plan (' . $maxDevices . ') sudah tercapai. Lepaskan perangkat lama atau naikkan plan.',
                    409,
                    'device_limit'
                );
            }
        }

        License::registerDevice($pdo, $licenseId, $deviceHash, $ctx['device_name'], $ctx['app_version']);
        License::markActivated($pdo, $licenseId);
        License::touchLastSeen($pdo, $licenseId);
    } catch (PDOException) {
        Json::error('Aktivasi gagal diproses. Silakan coba lagi.', 500, 'server_error');
    }

    Json::respond($licenseApiPayload($license, $ctx['plan']));
});

$router->post('/api/license/validate', static function () use ($readJsonBody, $licenseApiEntry, $licenseApiPayload): void {
    $ctx = $licenseApiEntry($readJsonBody());
    $pdo = $ctx['pdo'];
    $license = $ctx['license'];
    $licenseId = (int) $license['id'];
    $deviceHash = hash('sha256', $ctx['device_id']);

    try {
        $device = License::findDevice($pdo, $licenseId, $deviceHash);
        if ($device === null || (int) ($device['is_active'] ?? 0) !== 1) {
            Json::error(
                'Perangkat ini belum terdaftar untuk lisensi tersebut. Lakukan aktivasi terlebih dahulu.',
                403,
                'device_not_registered'
            );
        }

        License::touchDevice($pdo, (int) $device['id'], $ctx['device_name'], $ctx['app_version']);
        License::touchLastSeen($pdo, $licenseId);
    } catch (PDOException) {
        Json::error('Validasi gagal diproses. Silakan coba lagi.', 500, 'server_error');
    }

    Json::respond($licenseApiPayload($license, $ctx['plan']));
});

/* ---------------------------------------------------------------------
 * API — sesi aplikasi booth berbasis AKUN (Milestone 4c,
 * keputusan pemilik: aktivasi melekat akun, meniru DSLRBooth).
 *
 * Aplikasi masuk dengan email + kata sandi akun tenant dan
 * otomatis memakai lisensi aktif dengan masa berlaku terpanjang
 * milik tenant itu (License::currentForTenant) — tanpa mengetik
 * kunci. Login menerbitkan token sesi perangkat
 * (Authorization: Bearer); server hanya menyimpan hash SHA-256
 * token di license_devices.access_token_hash (migrasi M4c).
 * Jalur kunci /api/license/* tetap hidup sebagai cadangan.
 *
 * Sebelum migrasi M4c dijalankan, kolom token belum ada dan
 * endpoint-endpoint ini menjawab 503 {error: "server_error"}
 * lewat tangkapan PDOException yang terarah — endpoint kunci
 * tidak ikut terpengaruh karena tidak menyentuh kolom itu.
 * ------------------------------------------------------------------- */

/** Ambil token dari header "Authorization: Bearer <token>". */
$bearerToken = static function (): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if (is_string($header) && preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) === 1) {
        $token = trim($matches[1]);
        return $token !== '' ? $token : null;
    }

    return null;
};

$router->post('/api/app/login', static function () use ($readJsonBody, $licenseApiPayload): void {
    if (!Crypto::isConfigured()) {
        Json::error('Server belum dikonfigurasi (APP_KEY belum diatur).', 503, 'server_not_configured');
    }

    $pdo = Database::pdo();
    if ($pdo === null) {
        Json::error('Server belum dikonfigurasi.', 503, 'server_not_configured');
    }

    $body = $readJsonBody();
    $email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : '';
    $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';
    $deviceId = isset($body['device_id']) && is_string($body['device_id']) ? trim($body['device_id']) : '';

    if ($email === '' || $password === '' || $deviceId === '') {
        Json::error('email, password, dan device_id wajib diisi.', 422, 'invalid_request');
    }

    $cap = static function (mixed $value, int $max): ?string {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $max, 'UTF-8')
            : substr($value, 0, $max);
    };
    $deviceName = $cap($body['device_name'] ?? null, 120);
    $appVersion = $cap($body['app_version'] ?? null, 32);

    try {
        $stmt = $pdo->prepare(
            'SELECT id, tenant_id, name, email, password_hash, role, is_active
               FROM users
              WHERE email = :email
              LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $account = $stmt->fetch();
    } catch (PDOException) {
        Json::error('Login gagal diproses. Silakan coba lagi.', 503, 'server_error');
    }

    // Pola yang sama seperti Auth::attempt: email tak dikenal tetap
    // menjalankan password_verify terhadap hash boneka agar waktu
    // respons tidak membocorkan email yang terdaftar. Semua
    // kegagalan kredensial menjawab pesan generik yang sama.
    $dummyHash = '$2y$10$abcdefghijklmnopqrstuvabcdefghijklmnopqrstuvwxyzabcde';
    if ($account === false) {
        password_verify($password, $dummyHash);
        Json::error('Email atau kata sandi salah.', 401, 'invalid_credentials');
    }
    if (!password_verify($password, (string) $account['password_hash'])) {
        Json::error('Email atau kata sandi salah.', 401, 'invalid_credentials');
    }
    if (
        !in_array((string) $account['role'], ['tenant_admin', 'tenant_staff'], true)
        || (int) $account['is_active'] !== 1
        || $account['tenant_id'] === null
    ) {
        Json::error('Email atau kata sandi salah.', 401, 'invalid_credentials');
    }

    $tenant = null;
    $license = null;
    $plan = null;
    $deviceToken = null;

    try {
        $tenant = Tenant::find($pdo, (int) $account['tenant_id']);
        if ($tenant === null || (string) ($tenant['status'] ?? 'active') !== 'active') {
            Json::error('Akun tenant sedang dinonaktifkan. Hubungi EventBooth.', 403, 'tenant_inactive');
        }

        // Lisensi yang dipakai = lisensi aktif akun ini; kedaluwarsa
        // dan ketiadaan lisensi sama-sama bermuara di sini karena
        // lisensi kedaluwarsa tidak berstatus efektif aktif.
        $license = License::currentForTenant($pdo, (int) $tenant['id']);
        if ($license === null) {
            Json::error(
                'Akun ini belum memiliki lisensi aktif. Periksa dashboard Anda atau hubungi EventBooth.',
                403,
                'no_active_license'
            );
        }

        $plan = Plan::find($pdo, (int) $license['plan_id']);
        $licenseId = (int) $license['id'];
        $deviceHash = hash('sha256', $deviceId);

        // Penegakan slot per lisensi — pola yang sama seperti
        // aktivasi kunci: perangkat baru ditolak bila kuota penuh.
        $device = License::findDevice($pdo, $licenseId, $deviceHash);
        $isNew = $device === null || (int) ($device['is_active'] ?? 0) !== 1;
        if ($isNew) {
            $ent = License::entitlements($license, $plan);
            $maxDevices = $ent['max_devices'];
            if ($maxDevices > 0 && License::deviceCount($pdo, $licenseId) >= $maxDevices) {
                Json::error(
                    'Batas perangkat plan (' . $maxDevices . ') sudah tercapai. Cabut perangkat lama dari dashboard Lisensi Saya, atau naikkan plan.',
                    409,
                    'device_limit'
                );
            }
        }

        License::registerDevice($pdo, $licenseId, $deviceHash, $deviceName, $appVersion);
        License::markActivated($pdo, $licenseId);
        License::touchLastSeen($pdo, $licenseId);

        $device = License::findDevice($pdo, $licenseId, $deviceHash);
        if ($device === null) {
            Json::error('Login gagal diproses. Silakan coba lagi.', 503, 'server_error');
        }

        // Baris ini yang menyentuh kolom migrasi M4c.
        $deviceToken = License::issueDeviceToken($pdo, (int) $device['id']);
    } catch (PDOException) {
        Json::error('Login gagal diproses. Silakan coba lagi.', 503, 'server_error');
    }

    $payload = $licenseApiPayload($license, $plan);
    $payload['device_token'] = $deviceToken;
    $payload['license_key'] = isset($license['license_key']) && is_string($license['license_key']) && $license['license_key'] !== ''
        ? $license['license_key']
        : null;
    $payload['tenant'] = ['vendor_name' => Tenant::displayName($tenant)];

    Json::respond($payload);
});

$router->post('/api/app/me', static function () use ($bearerToken, $licenseApiPayload): void {
    if (!Crypto::isConfigured()) {
        Json::error('Server belum dikonfigurasi (APP_KEY belum diatur).', 503, 'server_not_configured');
    }

    $pdo = Database::pdo();
    if ($pdo === null) {
        Json::error('Server belum dikonfigurasi.', 503, 'server_not_configured');
    }

    $token = $bearerToken();
    if ($token === null) {
        Json::error('Token perangkat tidak sah.', 401, 'invalid_token');
    }

    $license = null;
    $tenant = null;
    $plan = null;

    try {
        $device = License::findDeviceByToken($pdo, $token);
        if ($device === null) {
            // Token tidak dikenal — termasuk sesudah perangkat
            // dicabut tenant dari dashboardnya.
            Json::error('Token perangkat tidak sah.', 401, 'invalid_token');
        }

        $license = License::find($pdo, (int) $device['license_id']);
        if ($license === null) {
            Json::error('Token perangkat tidak sah.', 401, 'invalid_token');
        }

        $tenant = Tenant::find($pdo, (int) $license['tenant_id']);
        $plan = Plan::find($pdo, (int) $license['plan_id']);

        License::touchDevice($pdo, (int) $device['id'], null, null);
        License::touchLastSeen($pdo, (int) $license['id']);
    } catch (PDOException) {
        Json::error('Layanan belum siap. Silakan coba lagi.', 503, 'server_error');
    }

    // Status harus jujur: aplikasi mengunci diri dengan alasan ini
    // bila lisensinya tidak lagi aktif. Urutan mengikuti gerbang
    // API kunci: dicabut, ditangguhkan, tenant nonaktif, kedaluwarsa.
    $storedStatus = (string) ($license['status'] ?? '');
    if ($storedStatus === 'revoked') {
        $licenseStatus = 'revoked';
    } elseif ($storedStatus === 'suspended') {
        $licenseStatus = 'suspended';
    } elseif ($tenant === null || (string) ($tenant['status'] ?? 'active') !== 'active') {
        $licenseStatus = 'tenant_inactive';
    } elseif (License::effectiveStatus($license) === 'expired') {
        $licenseStatus = 'expired';
    } elseif (License::effectiveStatus($license) === 'active') {
        $licenseStatus = 'active';
    } else {
        $licenseStatus = 'suspended';
    }

    $payload = $licenseApiPayload($license, $plan);
    $payload['license_status'] = $licenseStatus;

    Json::respond($payload);
});

$router->post('/api/app/logout', static function () use ($bearerToken): void {
    $pdo = Database::pdo();
    if ($pdo === null) {
        Json::error('Server belum dikonfigurasi.', 503, 'server_not_configured');
    }

    $token = $bearerToken();
    if ($token === null) {
        Json::error('Token perangkat tidak sah.', 401, 'invalid_token');
    }

    try {
        $device = License::findDeviceByToken($pdo, $token);
        if ($device === null) {
            Json::error('Token perangkat tidak sah.', 401, 'invalid_token');
        }

        // Nonaktifkan baris perangkat: slot langsung bebas dipakai
        // perangkat lain dan token ini tidak berlaku lagi.
        License::deactivateDevice($pdo, (int) $device['id']);
    } catch (PDOException) {
        Json::error('Logout gagal diproses. Silakan coba lagi.', 503, 'server_error');
    }

    Json::respond(['status' => 'ok']);
});

/* ---------------------------------------------------------------------
 * API — stub grup berikutnya (TODO, belum ada logika bisnis)
 * ------------------------------------------------------------------- */
$stub = static function (string $note): callable {
    return static function () use ($note): void {
        Json::notImplemented($note);
    };
};

// TODO(M4): Checkout & perpanjangan lisensi via Midtrans platform
// (blueprint §5A). Server Key hanya di server, tidak di aplikasi PC.
$router->post('/api/plan/checkout', $stub('Checkout lisensi via Midtrans — Milestone 4.'));

// TODO(M4): Pembayaran tamu booth via Midtrans tenant + webhook
// notifikasi terverifikasi (blueprint §5B & §6).
$router->post('/api/payment/create', $stub('Buat pembayaran tamu — Milestone 4.'));
$router->post('/api/payment/notification', $stub('Webhook notifikasi Midtrans — Milestone 4.'));
$router->get('/api/payment/status', $stub('Polling status pembayaran — Milestone 4.'));

// TODO(M5): Endpoint Drive untuk n8n/aplikasi (blueprint §2A & §3).
// Manajemen koneksinya (OAuth, multi-akun, default) sudah hidup di
// /admin/drive sejak M3; endpoint di bawah dipanggil n8n/aplikasi
// dengan token workflow — token Drive tidak pernah keluar server ini.
$router->post('/api/drive/folder', $stub('Buat folder Drive tenant/event/sesi — Milestone 5.'));
$router->post('/api/drive/init-upload', $stub('Inisiasi resumable upload Drive — Milestone 5.'));

// TODO(M5): Registrasi metadata sesi selesai upload & berbagi
// WhatsApp lewat n8n/MPWA (blueprint §3 langkah 5 & §5C).
$router->post('/api/session/register', $stub('Registrasi metadata sesi — Milestone 5.'));
$router->post('/api/session/share-wa', $stub('Kirim tautan album via WhatsApp — Milestone 5.'));

/* ---------------------------------------------------------------------
 * Dispatch
 * ------------------------------------------------------------------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) && $path !== '' ? $path : '/';

// Normalisasi: buang trailing slash selain root.
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

if (!$router->dispatch($method, $path)) {
    if (str_starts_with($path, '/api/')) {
        Json::error('Endpoint tidak ditemukan.', 404, 'not_found');
    }

    View::render('errors/404', [
        'appName' => Config::appName(),
        'appUrl' => Config::appUrl(),
    ], 404);
}
