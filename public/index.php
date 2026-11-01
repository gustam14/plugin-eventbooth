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
 *   GET  /tenant/devices -> perangkat terhubung tenant        (M4c)
 *   CRUD /tenant/events & /tenant/packages (tenant)           (M5a)
 *   GET  /e/{slug} & /s/{kode} -> album publik                (M5a)
 *   GET  /api/app/events, POST /api/app/sessions              (M5a)
 *   POST /api/drive/session-folders|init-upload|complete      (M5b)
 *   GET|POST /account/password -> ganti kata sandi            (M2.1)
 *   GET  /admin/drive + OAuth Google Drive pusat              (M3)
 *   GET  /auth/google/callback -> callback OAuth Google       (M3)
 *   CRUD /admin/tenants + aksi lisensi /admin/licenses         (M4)
 *   POST /admin/tenants/{id}/extend -> tambah masa aktif       (M4)
 *   POST /api/license/activate|validate -> API aplikasi booth  (M4)
 *   POST /api/app/login|me|logout -> sesi aplikasi via akun     (M4c)
 *   POST cabut perangkat: /tenant/licenses/... & /admin/...    (M4c)
 *   POST /admin/licenses/{id}/devices/add -> tambah perangkat  (M4)
 *   GET|POST /api/automation/* -> pengingat perpanjangan n8n   (M4)
 *   POST /admin/automation/sender -> nomor pengirim MPWA       (M5)
 *   GET  /admin/payments + pengaturan Midtrans platform        (M6)
 *   POST /tenant/licenses/checkout + GET .../pay/{orderId}     (M6)
 *   POST /api/payments/midtrans/notify -> webhook Midtrans     (M6)
 *
 * Grup API lain di bawah masih STUB 501 bertanda TODO; logika
 * bisnisnya (Midtrans, registrasi sesi via n8n, berbagi WA)
 * dibangun bertahap sesuai roadmap di README.md dan blueprint v4.
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

/* ---------------------------------------------------------------------
 * Album publik (Milestone 5a, blueprint §5C) — TANPA login, hanya
 * membaca metadata + tautan tersimpan (byte foto tidak pernah
 * lewat server ini). Slug/kode tak dikenal -> 404 publik yang sama
 * seperti halaman lain. Album password (album_password_hash)
 * belum ditegakkan di milestone ini.
 * ------------------------------------------------------------------- */
/**
 * Render album event (daftar kartu sesi). Dipakai dua bentuk URL:
 * lama /e/{slug} dan galeri /gallery/{slug-tenant}/{slug-event};
 * $albumBase adalah awalan URL halaman ini untuk tautan paginasi.
 */
$renderEventAlbum = static function (?array $event, string $albumBase): void {
    if ($event === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $pdo = Database::pdo();
    $perPage = Album::PER_PAGE;
    $totalSessions = 0;
    $sessions = [];
    $totalPages = 1;
    $page = 1;
    if ($pdo !== null) {
        try {
            $totalSessions = Album::countSessions($pdo, (int) $event['id']);
            $totalPages = max(1, (int) ceil($totalSessions / $perPage));
            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $page = max(1, min($page, $totalPages));
            $sessions = Album::sessionsPage($pdo, (int) $event['id'], $page, $perPage);
        } catch (PDOException) {
            // Album tetap tampil dengan keadaan kosong.
            $totalPages = 1;
            $page = 1;
            $sessions = [];
        }
    }

    View::render('album/event', [
        'appName' => Config::appName(),
        'appUrl' => Config::appUrl(),
        'version' => Config::appVersion(),
        'event' => $event,
        'sessions' => $sessions,
        'page' => $page,
        'totalPages' => $totalPages,
        'totalSessions' => $totalSessions,
        'albumBase' => $albumBase,
    ]);
};

$router->get('/e/{slug}', static function (array $params) use ($renderEventAlbum): void {
    $pdo = Database::pdo();
    $event = null;
    if ($pdo !== null) {
        try {
            $event = Album::eventBySlug($pdo, (string) ($params['slug'] ?? ''));
        } catch (PDOException) {
            $event = null;
        }
    }

    $renderEventAlbum($event, '/e/' . (string) ($params['slug'] ?? ''));
});

$router->get('/gallery/{tenant}/{event}', static function (array $params) use ($renderEventAlbum): void {
    $pdo = Database::pdo();
    $event = null;
    if ($pdo !== null) {
        try {
            $event = Album::eventByTenantEventSlug(
                $pdo,
                (string) ($params['tenant'] ?? ''),
                (string) ($params['event'] ?? '')
            );
        } catch (PDOException) {
            $event = null;
        }
    }

    $renderEventAlbum(
        $event,
        '/gallery/' . (string) ($params['tenant'] ?? '') . '/' . (string) ($params['event'] ?? '')
    );
});

$router->get('/s/{code}', static function (array $params): void {
    $pdo = Database::pdo();
    $session = null;
    if ($pdo !== null) {
        try {
            $session = Album::sessionByCode($pdo, (string) ($params['code'] ?? ''));
        } catch (PDOException) {
            $session = null;
        }
    }

    if ($session === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $media = [];
    try {
        $media = Album::mediaForSession($pdo, (int) $session['id']);
    } catch (PDOException) {
        $media = [];
    }

    View::render('album/session', [
        'appName' => Config::appName(),
        'appUrl' => Config::appUrl(),
        'version' => Config::appVersion(),
        'session' => $session,
        'media' => $media,
    ]);
});

/* ---------------------------------------------------------------------
 * Media publik lewat domain sendiri — /media/{id} dan
 * /media/{id}/download. Galeri tampil sebagai website mandiri
 * (tidak ada tautan Google Drive pada halaman): rute ini hanya
 * MENGALIHKAN (302) ke berkas di CDN Google, jadi byte tidak
 * pernah mengalir lewat shared hosting. ?s=full meminta gambar
 * besar untuk penampil; ?s=video mengalihkan ke aliran video.
 * ------------------------------------------------------------------- */
$serveMedia = static function (array $params, bool $download): void {
    $id = isset($params['id']) && is_numeric($params['id']) ? (int) $params['id'] : 0;
    $pdo = Database::pdo();
    $media = null;
    if ($pdo !== null && $id > 0) {
        try {
            $media = Album::mediaById($pdo, $id);
        } catch (PDOException) {
            $media = null;
        }
    }
    if ($media === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    // Berkas berekstensi video diputar sebagai video walau
    // jenisnya "gif" (GIF DSLRBooth berupa .mp4) — tanpa ini
    // pemutar menerima URL gambar dan berhenti di layar hitam.
    $isVideoFile = (string) ($media['kind'] ?? '') === 'video'
        || (bool) preg_match('/\.(mp4|mov)$/i', (string) ($media['file_name'] ?? ''));
    if ($download) {
        $target = Album::downloadUrl($media);
    } elseif ($isVideoFile && ($_GET['s'] ?? '') === 'video') {
        $target = Album::downloadUrl($media);
    } else {
        $target = Album::sizedImageUrl($media, (($_GET['s'] ?? '') === 'full') ? 1920 : 1000);
    }
    if ($target === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    header('Cache-Control: public, max-age=3600');
    header('Location: ' . $target, true, 302);
};

$router->get('/media/{id}', static function (array $params) use ($serveMedia): void {
    $serveMedia($params, false);
});

$router->get('/media/{id}/download', static function (array $params) use ($serveMedia): void {
    $serveMedia($params, true);
});

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
 * Endpoint operasi /api/drive/* untuk aplikasi booth hidup
 * sejak M5b (lihat bagian "API — operasi Google Drive" di bawah).
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
        $mpwaSender = null;
        try {
            $storedToken = Settings::get($pdo, 'automation_token');
            $tokenSet = is_string($storedToken) && $storedToken !== '';
            $mpwaSender = Settings::get($pdo, 'mpwa_sender');
        } catch (PDOException) {
            $tokenSet = false;
            $mpwaSender = null;
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
        'automationSender' => is_string($mpwaSender) && $mpwaSender !== '' ? $mpwaSender : null,
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

// Nomor pengirim MPWA untuk pengingat kedaluwarsa (v0.7.2):
// disimpan sebagai pengaturan platform 'mpwa_sender' agar workflow
// n8n tidak meng-hardcode nomor — workflow membacanya dari field
// 'sender' pada respons /api/automation/expiring-licenses.
// Kosong = belum diatur (workflow tidak mengirim).
$router->post('/admin/automation/sender', static function (): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, nomor pengirim belum dapat disimpan.');
        redirect('/admin');
    }

    $raw = trim((string) ($_POST['mpwa_sender'] ?? ''));

    if ($raw === '') {
        try {
            Settings::set($pdo, 'mpwa_sender', null, false, $user['id']);
        } catch (PDOException) {
            Flash::error('Nomor pengirim gagal disimpan. Silakan coba lagi.');
            redirect('/admin/tenants');
        }

        Flash::success('Nomor pengirim dikosongkan.');
        redirect('/admin/tenants');
    }

    $sender = Whatsapp::normalize($raw);
    if ($sender === null) {
        Flash::error('Nomor pengirim tidak sah. Gunakan nomor Indonesia, mis. 0812 3456 7890.');
        redirect('/admin/tenants');
    }

    try {
        Settings::set($pdo, 'mpwa_sender', $sender, false, $user['id']);
    } catch (PDOException) {
        Flash::error('Nomor pengirim gagal disimpan. Silakan coba lagi.');
        redirect('/admin/tenants');
    }

    Flash::success('Nomor pengirim pengingat disimpan: ' . $sender . '.');
    redirect('/admin/tenants');
});

// ---------------------------------------------------------------------
// Milestone 6 — Pembayaran: pengaturan Midtrans platform + daftar
// pesanan lisensi (Super Admin). Kredensial platform hidup di
// app_settings (Server Key terenkripsi); URL notifikasi yang harus
// didaftarkan di dashboard Midtrans: /api/payments/midtrans/notify.
// ---------------------------------------------------------------------
$router->get('/admin/payments', static function (): void {
    $user = Auth::requireRole('super_admin');

    $midtrans = ['environment' => 'sandbox', 'client_key' => null, 'server_key' => null, 'configured' => false];
    $orders = [];
    $ordersReady = true;
    $mpwaApiKeyStored = false;
    $mpwaSender = null;
    $pdo = Database::pdo();
    if ($pdo !== null) {
        try {
            $midtrans = Midtrans::config($pdo);
            $mpwaApiKeyStored = Mpwa::apiKey($pdo) !== null;
            $mpwaSender = Whatsapp::normalize(Settings::get($pdo, 'mpwa_sender'));
        } catch (PDOException) {
            // Pengaturan belum terbaca; tampilkan keadaan kosong.
        }
        try {
            $stmt = $pdo->query(
                "SELECT p.*, t.vendor_name AS tenant_vendor, t.name AS tenant_name,
                        pl.name AS plan_name
                   FROM payments p
                   LEFT JOIN tenants t ON t.id = p.tenant_id
                   LEFT JOIN plans pl ON pl.id = p.plan_id
                  WHERE p.purpose = 'license'
                  ORDER BY p.id DESC
                  LIMIT 50"
            );
            $orders = $stmt->fetchAll();
        } catch (PDOException) {
            // Tabel payments belum siap (migrasi M6 belum jalan).
            $ordersReady = false;
        }
    }

    View::render('admin/payments/index', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'midtrans' => $midtrans,
        'serverKeyStored' => $midtrans['server_key'] !== null,
        'orders' => $orders,
        'ordersReady' => $ordersReady,
        'notifyUrl' => Midtrans::notifyUrl(),
        'mpwaApiKeyStored' => $mpwaApiKeyStored,
        'mpwaSender' => $mpwaSender,
    ]);
});

$router->post('/admin/payments/mpwa', static function (): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, API key MPWA belum dapat disimpan.');
        redirect('/admin');
    }

    $plain = trim((string) ($_POST['mpwa_api_key'] ?? ''));
    if ($plain === '') {
        Flash::error('API key MPWA kosong — tidak ada yang disimpan.');
        redirect('/admin/payments');
    }

    try {
        Mpwa::saveApiKey($pdo, $plain, $user['id']);
        Flash::success('API key MPWA disimpan. Notifikasi WhatsApp pembayaran lunas kini aktif.');
    } catch (MidtransException $e) {
        Flash::error($e->getMessage());
    } catch (PDOException) {
        Flash::error('API key MPWA gagal disimpan. Silakan coba lagi.');
    }

    redirect('/admin/payments');
});

$router->post('/admin/payments/settings', static function (): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, pengaturan Midtrans belum dapat disimpan.');
        redirect('/admin');
    }

    try {
        Midtrans::saveSettings(
            $pdo,
            (string) ($_POST['environment'] ?? 'sandbox'),
            (string) ($_POST['client_key'] ?? ''),
            array_key_exists('server_key', $_POST) ? (string) $_POST['server_key'] : null,
            $user['id']
        );
        Flash::success('Pengaturan Midtrans disimpan.');
    } catch (MidtransException $e) {
        Flash::error($e->getMessage());
    } catch (PDOException) {
        Flash::error('Pengaturan Midtrans gagal disimpan. Silakan coba lagi.');
    }

    redirect('/admin/payments');
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
        // Satu akun satu lisensi (v0.6.2; lisensi abadi v0.6.3):
        // formulir penerbitan hanya tampil bila tenant belum
        // pernah punya lisensi sama sekali.
        $liveLicense = License::liveForTenant($pdo, $id);
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
        'liveLicense' => $liveLicense,
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

    // Satu akun hanya memiliki satu lisensi (v0.6.2) dan lisensi
    // itu abadi (v0.6.3): selama tenant sudah pernah punya baris
    // lisensi — dalam keadaan apa pun, termasuk dicabut —
    // penerbitan lisensi tambahan ditolak; pembelian berikutnya
    // berjalan lewat Tambah Masa Aktif yang menghidupkan kembali
    // lisensi lama dengan kunci yang sama.
    try {
        $liveLicense = License::liveForTenant($pdo, $id);
    } catch (PDOException) {
        $liveLicense = null;
    }
    if ($liveLicense !== null) {
        Flash::error('Tenant ini sudah memiliki lisensi. Gunakan Tambah Masa Aktif — lisensi lama akan dipakai lagi, termasuk yang dicabut atau kedaluwarsa.');
        redirect('/admin/tenants/' . $id);
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

$router->post('/admin/tenants/{id}/extend', static function (array $params): void {
    $user = Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, masa aktif belum dapat ditambah.');
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

    $vendor = Tenant::displayName($tenant);

    try {
        // Model masa aktif + lisensi abadi (v0.6.3): pembelian plan
        // memperpanjang / menghidupkan kembali satu-satunya lisensi
        // tenant; lisensi baru hanya terbit bila tenant belum
        // pernah memiliki lisensi sama sekali. Keadaan target
        // SEBELUM perpanjangan dibaca lebih dulu untuk menentukan
        // bunyi pesannya (dicabut = diaktifkan kembali).
        $target = License::liveForTenant($pdo, $id);
        $wasRevoked = $target !== null
            && (string) ($target['status'] ?? '') === 'revoked';

        $extended = License::extendWithPlan($pdo, $tenant, $plan);
        if ($extended !== null) {
            $until = empty($extended['expires_at'])
                ? 'tanpa kedaluwarsa'
                : License::formatDateId((string) $extended['expires_at']);
            if ($wasRevoked) {
                Flash::success('Lisensi lama milik ' . $vendor . ' diaktifkan kembali dengan plan ' . (string) $plan['name'] . ' — berlaku hingga ' . $until . '.');
            } else {
                Flash::success('Masa aktif ' . $vendor . ' diperpanjang dengan plan ' . (string) $plan['name'] . ' — berlaku hingga ' . $until . '.');
            }
            redirect('/admin/tenants/' . $id);
        }

        $issued = License::issue($pdo, $id, $plan, (int) $user['id']);
    } catch (PDOException) {
        Flash::error('Masa aktif gagal ditambah. Pastikan migrasi Milestone 4 sudah dijalankan, lalu coba lagi.');
        redirect('/admin/tenants/' . $id);
    } catch (RuntimeException) {
        Flash::error('Masa aktif gagal ditambah. Silakan coba lagi.');
        redirect('/admin/tenants/' . $id);
    }

    // Sama seperti penerbitan biasa: kunci tampil sekali.
    Auth::start();
    $_SESSION['new_license_key'] = $issued['key'];

    Flash::success('Lisensi baru diterbitkan untuk ' . $vendor . ' dengan plan ' . (string) $plan['name'] . ' (tenant belum memiliki lisensi).');
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

/**
 * Tambah Perangkat (v0.6.2): naikkan batas perangkat lisensi yang
 * berjalan melampaui bawaan plan-nya, tanpa menerbitkan lisensi
 * baru dan tanpa mengubah masa aktifnya.
 */
$router->post('/admin/licenses/{id}/devices/add', static function (array $params): void {
    Auth::requireRole('super_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, batas perangkat belum dapat diubah.');
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
        Flash::error('Lisensi yang sudah dicabut tidak dapat diubah lagi.');
        redirect('/admin/tenants/' . $tenantId);
    }

    $amountRaw = $_POST['amount'] ?? null;
    $amount = is_numeric($amountRaw) ? (int) $amountRaw : 0;
    if ($amount < 1 || $amount > 20) {
        Flash::error('Jumlah perangkat tambahan harus antara 1 dan 20.');
        redirect('/admin/tenants/' . $tenantId);
    }

    try {
        $newMax = License::addDevices($pdo, (int) $license['id'], $amount);
    } catch (PDOException) {
        Flash::error('Batas perangkat gagal diubah. Pastikan migrasi Milestone 4 (kolom plan_snapshot) sudah dijalankan.');
        redirect('/admin/tenants/' . $tenantId);
    }

    if ($newMax === null) {
        // Status dicabut sudah disaring di atas, jadi null di sini
        // berarti lisensinya memang sudah tanpa batas perangkat.
        Flash::info('Lisensi ini sudah tanpa batas perangkat.');
        redirect('/admin/tenants/' . $tenantId);
    }

    $tenant = Tenant::find($pdo, $tenantId);
    $vendor = $tenant !== null ? Tenant::displayName($tenant) : 'tenant ini';
    Flash::success('Batas perangkat ditambah ' . $amount . ' — kini maksimal ' . $newMax . ' perangkat untuk ' . $vendor . '.');
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
/* ---------------------------------------------------------------------
 * Midtrans Tamu (tenant): akun Midtrans milik tenant sendiri
 * untuk pembayaran QRIS tamu booth — uang masuk langsung ke
 * tenant (keputusan 2026-11-01). Hanya Admin Tenant yang boleh
 * melihat/mengubah kunci pembayaran.
 * ------------------------------------------------------------------- */
$router->get('/tenant/midtrans', static function (): void {
    $user = Auth::requireRole('tenant_admin');

    $midtrans = null;
    $orders = [];
    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $midtrans = Midtrans::tenantConfig($pdo, (int) $tenantId);
        } catch (PDOException) {
            $midtrans = null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT p.*, s.code AS session_code
                   FROM payments p
                   LEFT JOIN sessions s ON s.id = p.session_id
                  WHERE p.purpose = 'guest' AND p.tenant_id = :tenant
                  ORDER BY p.id DESC
                  LIMIT 10"
            );
            $stmt->execute(['tenant' => (int) $tenantId]);
            $orders = $stmt->fetchAll();
        } catch (PDOException) {
            $orders = [];
        }
    }

    View::render('tenant/midtrans', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'midtrans' => $midtrans,
        'guestNotifyUrl' => Midtrans::guestNotifyUrl(),
        'orders' => $orders,
    ]);
});

$router->post('/tenant/midtrans', static function (): void {
    $user = Auth::requireRole('tenant_admin');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo === null || $tenantId === null) {
        Flash::error('Layanan belum siap. Silakan coba lagi.');
        redirect('/tenant/midtrans');
    }

    try {
        Midtrans::saveTenantSettings(
            $pdo,
            (int) $tenantId,
            isset($_POST['is_enabled']),
            (string) ($_POST['environment'] ?? 'sandbox'),
            (string) ($_POST['merchant_id'] ?? ''),
            (string) ($_POST['client_key'] ?? ''),
            isset($_POST['server_key']) ? (string) $_POST['server_key'] : null
        );
        Flash::success('Pengaturan Midtrans tamu tersimpan.');
    } catch (MidtransException $e) {
        Flash::error($e->getMessage());
    } catch (PDOException) {
        Flash::error('Pengaturan gagal disimpan. Silakan coba lagi.');
    }

    redirect('/tenant/midtrans');
});

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

    // Checkout lisensi Midtrans (M6): plan berbayar yang dapat
    // dibeli tenant + riwayat pesanannya. Best-effort — halaman
    // tetap tampil walau pembayaran belum siap/dikonfigurasi.
    $checkoutPlans = [];
    $midtransReady = false;
    $orders = [];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $midtransReady = Midtrans::config($pdo)['configured'];
            if ($midtransReady) {
                $checkoutPlans = array_values(array_filter(
                    Plan::all($pdo),
                    static fn (array $p): bool => (int) ($p['is_active'] ?? 0) === 1
                        && (float) ($p['price'] ?? 0) > 0
                ));
            }
        } catch (PDOException) {
            $checkoutPlans = [];
            $midtransReady = false;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT p.*, pl.name AS plan_name
                   FROM payments p
                   LEFT JOIN plans pl ON pl.id = p.plan_id
                  WHERE p.purpose = 'license' AND p.tenant_id = :tenant
                  ORDER BY p.id DESC
                  LIMIT 10"
            );
            $stmt->execute(['tenant' => (int) $tenantId]);
            $orders = $stmt->fetchAll();
        } catch (PDOException) {
            $orders = [];
        }
    }

    // Catatan hasil dari halaman bayar Snap (M6): callback Snap
    // mengarahkan kembali ke sini dengan ?pay=... — status resmi
    // pesanan tetap yang tercatat di tabel (dari webhook), teks
    // ini hanya penjelasan untuk tenant.
    $flashes = Flash::pull();
    $payNote = (string) ($_GET['pay'] ?? '');
    if ($payNote === 'success') {
        $flashes[] = ['type' => 'success', 'message' => 'Pembayaran diterima Midtrans. Masa aktifmu diperbarui otomatis begitu notifikasi terverifikasi — biasanya beberapa detik; muat ulang halaman ini bila belum berubah.'];
    } elseif ($payNote === 'pending') {
        $flashes[] = ['type' => 'info', 'message' => 'Pembayaranmu menunggu penyelesaian. Selesaikan sebelum kedaluwarsa; masa aktif bertambah otomatis setelah dinyatakan lunas.'];
    } elseif ($payNote === 'error') {
        $flashes[] = ['type' => 'error', 'message' => 'Pembayaran gagal diproses. Kamu dapat mencoba lagi dari riwayat pembayaran di bawah.'];
    }

    View::render('tenant/licenses', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => $flashes,
        'licenses' => $licenses,
        'currentLicenseId' => $currentLicenseId,
        'devicesByLicense' => $devicesByLicense,
        'checkoutPlans' => $checkoutPlans,
        'midtransReady' => $midtransReady,
        'orders' => $orders,
    ]);
});

// Checkout lisensi (M6): buat pesanan di tabel payments, minta
// token Snap ke Midtrans, lalu arahkan tenant ke halaman bayar.
// Masa aktif TIDAK berubah di sini — perpanjangan hanya terjadi
// saat webhook notifikasi terverifikasi menyatakan lunas.
$router->post('/tenant/licenses/checkout', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo === null || $tenantId === null) {
        Flash::error('Akun tenant tidak ditemukan, pesanan belum dapat dibuat.');
        redirect('/tenant/licenses');
    }

    try {
        $config = Midtrans::config($pdo);
        $plan = Plan::find($pdo, (int) ($_POST['plan_id'] ?? 0));
        $tenant = Tenant::find($pdo, (int) $tenantId);
    } catch (PDOException) {
        Flash::error('Data pembayaran belum dapat dibaca. Silakan coba lagi.');
        redirect('/tenant/licenses');
    }

    if (!$config['configured']) {
        Flash::error('Pembayaran online belum diaktifkan oleh admin. Silakan hubungi EventBooth untuk perpanjangan manual.');
        redirect('/tenant/licenses');
    }
    if ($plan === null || (int) ($plan['is_active'] ?? 0) !== 1 || (float) ($plan['price'] ?? 0) <= 0) {
        Flash::error('Plan tidak tersedia untuk dibeli.');
        redirect('/tenant/licenses');
    }
    if ($tenant === null) {
        Flash::error('Data tenant tidak ditemukan.');
        redirect('/tenant/licenses');
    }

    $orderId = 'EBL-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO payments
                (purpose, tenant_id, plan_id, plan_snapshot, order_id, gross_amount, status)
             VALUES ('license', :tenant, :plan, :snapshot, :order_id, :amount, 'pending')"
        );
        $stmt->execute([
            'tenant' => (int) $tenantId,
            'plan' => (int) $plan['id'],
            'snapshot' => json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'order_id' => $orderId,
            'amount' => (float) $plan['price'],
        ]);
    } catch (PDOException) {
        Flash::error('Fitur pembayaran belum aktif di server (migrasi M6 belum dijalankan).');
        redirect('/tenant/licenses');
    }

    try {
        $snap = Midtrans::createSnapToken(
            $pdo,
            ['order_id' => $orderId, 'gross_amount' => (float) $plan['price']],
            $plan,
            $tenant
        );
        $stmt = $pdo->prepare('UPDATE payments SET snap_token = :token WHERE order_id = :order_id');
        $stmt->execute(['token' => $snap['token'], 'order_id' => $orderId]);
    } catch (MidtransException $e) {
        try {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE order_id = :order_id");
            $stmt->execute(['order_id' => $orderId]);
        } catch (PDOException) {
            // Penandaan gagal best-effort; pesan error utama tetap diteruskan.
        }
        Flash::error('Midtrans menolak permintaan pembayaran: ' . $e->getMessage());
        redirect('/tenant/licenses');
    }

    redirect('/tenant/licenses/pay/' . $orderId);
});

// Halaman bayar (M6): membuka popup Snap untuk satu pesanan milik
// tenant yang login. Callback Snap di sini hanya soal tampilan —
// pelunasan resmi ditentukan webhook server.
$router->get('/tenant/licenses/pay/{orderId}', static function (array $params): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $order = null;
    $config = null;
    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $stmt = $pdo->prepare(
                "SELECT p.*, pl.name AS plan_name
                   FROM payments p
                   LEFT JOIN plans pl ON pl.id = p.plan_id
                  WHERE p.order_id = :order_id AND p.tenant_id = :tenant
                    AND p.purpose = 'license'
                  LIMIT 1"
            );
            $stmt->execute([
                'order_id' => (string) $params['orderId'],
                'tenant' => (int) $tenantId,
            ]);
            $row = $stmt->fetch();
            $order = $row === false ? null : $row;
            $config = Midtrans::config($pdo);
        } catch (PDOException) {
            $order = null;
        }
    }

    if ($order === null) {
        Flash::error('Pesanan tidak ditemukan.');
        redirect('/tenant/licenses');
    }
    if ((string) $order['status'] === 'paid') {
        Flash::success('Pesanan ini sudah lunas — masa aktif lisensimu sudah diperbarui.');
        redirect('/tenant/licenses');
    }
    if (empty($order['snap_token']) || $config === null || !$config['configured']) {
        Flash::error('Sesi pembayaran tidak tersedia lagi. Silakan buat pesanan baru.');
        redirect('/tenant/licenses');
    }

    View::render('tenant/pay', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'order' => $order,
        'snapJsUrl' => Midtrans::snapJsUrl((string) $config['environment']),
        'clientKey' => (string) $config['client_key'],
    ]);
});

/**
 * Perangkat terhubung milik tenant yang login (Milestone 4c;
 * halaman tersendiri sejak v0.6.3): daftar perangkat dari
 * satu-satunya lisensi tenant. Lisensi dipilih dengan aturan
 * liveForTenant (keadaan apa pun — termasuk dicabut/kedaluwarsa)
 * agar perangkatnya tetap terlihat. Pencabutan memakai rute
 * revoke yang sama dengan halaman Lisensi Saya, dengan field
 * 'return' = 'devices' agar pengguna kembali ke halaman ini.
 */
$router->get('/tenant/devices', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $license = null;
    $devices = [];
    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $license = License::liveForTenant($pdo, (int) $tenantId);
            if ($license !== null) {
                try {
                    $devices = License::devicesFor($pdo, (int) $license['id']);
                } catch (PDOException) {
                    $devices = [];
                }
            }
        } catch (PDOException) {
            // Halaman tetap tampil dengan keadaan kosong.
            $license = null;
            $devices = [];
        }
    }

    View::render('tenant/devices', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'license' => $license,
        'devices' => $devices,
    ]);
});

/**
 * Cabut perangkat dari dashboard tenant (Milestone 4c): slot
 * perangkat lisensi langsung bebas lagi. Lisensi WAJIB milik
 * tenant pengguna yang login dan perangkat wajib milik lisensi
 * itu — keduanya diverifikasi sebelum pencabutan. Pengguna
 * dikembalikan ke halaman asalnya: field POST 'return' =
 * 'devices' (dari halaman Perangkat, v0.6.3) menuju
 * /tenant/devices; selain itu ke /tenant/licenses.
 */
$router->post('/tenant/licenses/{licenseId}/devices/{deviceId}/revoke', static function (array $params): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');
    Auth::requireCsrf();

    $backUrl = (string) ($_POST['return'] ?? '') === 'devices'
        ? '/tenant/devices'
        : '/tenant/licenses';

    $pdo = Database::pdo();
    if ($pdo === null) {
        Flash::error('Database belum disetel, perangkat belum dapat dicabut.');
        redirect($backUrl);
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
        redirect($backUrl);
    }

    if (!$revoked) {
        Flash::error('Perangkat tidak ditemukan pada lisensi ini.');
        redirect($backUrl);
    }

    Flash::success('Perangkat dicabut — slot tersedia lagi.');
    redirect($backUrl);
});

/* ---------------------------------------------------------------------
 * Event & Paket Sesi milik tenant (Milestone 5a).
 *
 * Semua query dibatasi tenant_id pengguna yang login — tenant
 * tidak pernah melihat/mengubah event & paket tenant lain.
 * Paket menempel pada event (packages.event_id), jadi halaman
 * paket menggabungkan paket dari semua event tenant.
 * ------------------------------------------------------------------- */
$router->get('/tenant/events', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $events = [];
    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $events = Event::forTenant($pdo, (int) $tenantId);
        } catch (PDOException) {
            $events = [];
        }
    }

    View::render('tenant/events/index', [
        'appName' => Config::appName(),
        'appUrl' => Config::appUrl(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'events' => $events,
    ]);
});

$router->get('/tenant/events/create', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $driveAccounts = [];
    $pdo = Database::pdo();
    if ($pdo !== null) {
        try {
            $driveAccounts = Event::connectedAccounts($pdo);
        } catch (PDOException) {
            $driveAccounts = [];
        }
    }

    View::render('tenant/events/form', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'heading' => 'Buat Event',
        'action' => '/tenant/events',
        'values' => Event::formValues(),
        'errors' => [],
        'driveAccounts' => $driveAccounts,
    ]);
});

$router->post('/tenant/events', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo === null || $tenantId === null) {
        Flash::error('Database belum disetel, event belum dapat disimpan.');
        redirect('/tenant');
    }
    $tenantId = (int) $tenantId;

    [$data, $errors] = Event::validate($pdo, $_POST);
    if ($errors !== []) {
        $driveAccounts = [];
        try {
            $driveAccounts = Event::connectedAccounts($pdo);
        } catch (PDOException) {
            $driveAccounts = [];
        }
        $flashes = Flash::pull();
        $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
        View::render('tenant/events/form', [
            'appName' => Config::appName(),
            'user' => $user,
            'flashes' => $flashes,
            'heading' => 'Buat Event',
            'action' => '/tenant/events',
            'values' => Event::formValues(null, $_POST),
            'errors' => $errors,
            'driveAccounts' => $driveAccounts,
        ], 422);
        return;
    }

    // Batas event dari snapshot plan lisensi tenant (0 = tanpa
    // batas; tenant tanpa lisensi dianggap tanpa batas di sini —
    // penegakan komersialnya ada di lisensi itu sendiri).
    try {
        $license = License::liveForTenant($pdo, $tenantId);
        if ($license !== null) {
            $plan = Plan::find($pdo, (int) $license['plan_id']);
            $maxEvents = License::entitlements($license, $plan)['max_events'];
            if ($maxEvents > 0 && Event::countForTenant($pdo, $tenantId) >= $maxEvents) {
                Flash::error(
                    'Batas event plan Anda tercapai (maks ' . $maxEvents . '). Naikkan plan atau hubungi admin.'
                );
                redirect('/tenant/events');
            }
        }
    } catch (PDOException) {
        // Pemeriksaan batas gagal dibaca: pembuatan tetap dicoba.
    }

    try {
        Event::create($pdo, $tenantId, $data);
    } catch (PDOException) {
        Flash::error('Event gagal disimpan. Silakan coba lagi.');
        redirect('/tenant/events/create');
    }

    Flash::success('Event "' . (string) $data['name'] . '" berhasil dibuat.');
    if (!empty($data['drive_auto_missing'])) {
        Flash::info('Event dibuat tanpa akun penyimpanan — kaitkan akun di menu Google Drive (Super Admin) agar upload & album berjalan.');
    }
    redirect('/tenant/events');
});

$router->get('/tenant/events/{id}/edit', static function (array $params): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo === null || $tenantId === null) {
        Flash::error('Database belum disetel, event belum dapat diubah.');
        redirect('/tenant');
    }

    $event = Event::findForTenant($pdo, (int) $tenantId, (int) ($params['id'] ?? 0));
    if ($event === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $driveAccounts = [];
    try {
        $driveAccounts = Event::connectedAccounts($pdo);
    } catch (PDOException) {
        $driveAccounts = [];
    }
    // Akun yang sedang terkunci ke event ini tetap ditawarkan
    // walau statusnya sudah tidak 'connected', agar pilihan
    // tersimpan tidak hilang dari formulir.
    if (($event['drive_account_id'] ?? null) !== null) {
        $currentId = (int) $event['drive_account_id'];
        $listed = array_map(static fn (array $a): int => (int) $a['id'], $driveAccounts);
        if (!in_array($currentId, $listed, true)) {
            try {
                $current = GoogleAccount::find($pdo, $currentId);
                if ($current !== null) {
                    $driveAccounts[] = $current;
                }
            } catch (PDOException) {
                // Abaikan: daftar tetap tanpa akun lama.
            }
        }
    }

    View::render('tenant/events/form', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'heading' => 'Ubah Event',
        'action' => '/tenant/events/' . (int) $event['id'],
        'values' => Event::formValues($event),
        'errors' => [],
        'driveAccounts' => $driveAccounts,
    ]);
});

$router->post('/tenant/events/{id}', static function (array $params): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo === null || $tenantId === null) {
        Flash::error('Database belum disetel, event belum dapat disimpan.');
        redirect('/tenant');
    }

    $id = (int) ($params['id'] ?? 0);
    $event = Event::findForTenant($pdo, (int) $tenantId, $id);
    if ($event === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    [$data, $errors] = Event::validate($pdo, $_POST, $event);
    if ($errors !== []) {
        $driveAccounts = [];
        try {
            $driveAccounts = Event::connectedAccounts($pdo);
        } catch (PDOException) {
            $driveAccounts = [];
        }
        $flashes = Flash::pull();
        $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
        View::render('tenant/events/form', [
            'appName' => Config::appName(),
            'user' => $user,
            'flashes' => $flashes,
            'heading' => 'Ubah Event',
            'action' => '/tenant/events/' . $id,
            'values' => Event::formValues($event, $_POST),
            'errors' => $errors,
            'driveAccounts' => $driveAccounts,
        ], 422);
        return;
    }

    try {
        Event::update($pdo, $id, $data);
    } catch (PDOException) {
        Flash::error('Event gagal disimpan. Silakan coba lagi.');
        redirect('/tenant/events/' . $id . '/edit');
    }

    Flash::success('Event "' . (string) $data['name'] . '" berhasil diperbarui.');
    if (!empty($data['drive_auto_missing'])) {
        Flash::info('Event dibuat tanpa akun penyimpanan — kaitkan akun di menu Google Drive (Super Admin) agar upload & album berjalan.');
    }
    redirect('/tenant/events');
});

$router->get('/tenant/packages', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $packages = [];
    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $packages = Package::forTenant($pdo, (int) $tenantId);
        } catch (PDOException) {
            $packages = [];
        }
    }

    View::render('tenant/packages/index', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'packages' => $packages,
    ]);
});

$router->get('/tenant/packages/create', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    $tenantEvents = [];
    if ($pdo !== null && $tenantId !== null) {
        try {
            $tenantEvents = Event::forTenant($pdo, (int) $tenantId);
        } catch (PDOException) {
            $tenantEvents = [];
        }
    }

    if ($tenantEvents === []) {
        Flash::info('Buat event dulu sebelum menambahkan paket sesi.');
        redirect('/tenant/events');
    }

    View::render('tenant/packages/form', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'heading' => 'Tambah Paket',
        'action' => '/tenant/packages',
        'values' => Package::formValues(),
        'errors' => [],
        'events' => $tenantEvents,
    ]);
});

$router->post('/tenant/packages', static function (): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo === null || $tenantId === null) {
        Flash::error('Database belum disetel, paket belum dapat disimpan.');
        redirect('/tenant');
    }
    $tenantId = (int) $tenantId;

    [$data, $errors] = Package::validate($pdo, $tenantId, $_POST);
    if ($errors !== []) {
        $tenantEvents = [];
        try {
            $tenantEvents = Event::forTenant($pdo, $tenantId);
        } catch (PDOException) {
            $tenantEvents = [];
        }
        $flashes = Flash::pull();
        $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
        View::render('tenant/packages/form', [
            'appName' => Config::appName(),
            'user' => $user,
            'flashes' => $flashes,
            'heading' => 'Tambah Paket',
            'action' => '/tenant/packages',
            'values' => Package::formValues(null, $_POST),
            'errors' => $errors,
            'events' => $tenantEvents,
        ], 422);
        return;
    }

    try {
        Package::create($pdo, $data);
    } catch (PDOException) {
        Flash::error('Paket gagal disimpan. Silakan coba lagi.');
        redirect('/tenant/packages/create');
    }

    Flash::success('Paket "' . (string) $data['name'] . '" berhasil dibuat.');
    redirect('/tenant/packages');
});

$router->get('/tenant/packages/{id}/edit', static function (array $params): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo === null || $tenantId === null) {
        Flash::error('Database belum disetel, paket belum dapat diubah.');
        redirect('/tenant');
    }

    $package = Package::findForTenant($pdo, (int) $tenantId, (int) ($params['id'] ?? 0));
    if ($package === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    $tenantEvents = [];
    try {
        $tenantEvents = Event::forTenant($pdo, (int) $tenantId);
    } catch (PDOException) {
        $tenantEvents = [];
    }

    View::render('tenant/packages/form', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'heading' => 'Ubah Paket',
        'action' => '/tenant/packages/' . (int) $package['id'],
        'values' => Package::formValues($package),
        'errors' => [],
        'events' => $tenantEvents,
    ]);
});

$router->post('/tenant/packages/{id}', static function (array $params): void {
    $user = Auth::requireRole('tenant_admin', 'tenant_staff');
    Auth::requireCsrf();

    $pdo = Database::pdo();
    $tenantId = $user['tenant_id'];
    if ($pdo === null || $tenantId === null) {
        Flash::error('Database belum disetel, paket belum dapat disimpan.');
        redirect('/tenant');
    }
    $tenantId = (int) $tenantId;

    $id = (int) ($params['id'] ?? 0);
    $package = Package::findForTenant($pdo, $tenantId, $id);
    if ($package === null) {
        View::render('errors/404', [
            'appName' => Config::appName(),
            'appUrl' => Config::appUrl(),
        ], 404);
        return;
    }

    [$data, $errors] = Package::validate($pdo, $tenantId, $_POST, $package);
    if ($errors !== []) {
        $tenantEvents = [];
        try {
            $tenantEvents = Event::forTenant($pdo, $tenantId);
        } catch (PDOException) {
            $tenantEvents = [];
        }
        $flashes = Flash::pull();
        $flashes[] = ['type' => 'error', 'message' => 'Periksa kembali isian formulir di bawah ini.'];
        View::render('tenant/packages/form', [
            'appName' => Config::appName(),
            'user' => $user,
            'flashes' => $flashes,
            'heading' => 'Ubah Paket',
            'action' => '/tenant/packages/' . $id,
            'values' => Package::formValues($package, $_POST),
            'errors' => $errors,
            'events' => $tenantEvents,
        ], 422);
        return;
    }

    try {
        Package::update($pdo, $id, $data);
    } catch (PDOException) {
        Flash::error('Paket gagal disimpan. Silakan coba lagi.');
        redirect('/tenant/packages/' . $id . '/edit');
    }

    Flash::success('Paket "' . (string) $data['name'] . '" berhasil diperbarui.');
    redirect('/tenant/packages');
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
 * Respons expiring-licenses juga memuat 'sender' (pengaturan
 * 'mpwa_sender', v0.7.2): nomor perangkat MPWA yang harus dipakai
 * workflow untuk mengirim — diatur dari dashboard Super Admin,
 * tidak di-hardcode di workflow.
 * ------------------------------------------------------------------- */
// Webhook notifikasi Midtrans (M6): satu-satunya jalur yang boleh
// menyatakan pesanan lisensi lunas. Tanda tangan SHA-512 diverifikasi
// dengan Server Key platform; saat status menjadi 'paid' pertama
// kali, lisensi tenant dipenuhi dengan aturan yang sama seperti
// Tambah Masa Aktif manual (extendWithPlan; terbit baru hanya bila
// tenant belum pernah memiliki lisensi). Idempoten: notifikasi
// berulang tidak memperpanjang dua kali.
$router->post('/api/payments/midtrans/notify', static function () use ($readJsonBody): void {
    $pdo = Database::pdo();
    if ($pdo === null) {
        Json::error('Database belum disetel.', 503, 'server_not_configured');
    }

    $payload = $readJsonBody();
    if ($payload === [] || empty($payload['order_id'])) {
        Json::error('Payload notifikasi tidak valid.', 400, 'invalid_payload');
    }

    try {
        $config = Midtrans::config($pdo);
    } catch (PDOException) {
        Json::error('Pengaturan pembayaran belum dapat dibaca.', 503, 'server_not_configured');
    }
    if ($config['server_key'] === null) {
        Json::error('Midtrans platform belum dikonfigurasi.', 503, 'payment_not_configured');
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = :order_id LIMIT 1');
        $stmt->execute(['order_id' => (string) $payload['order_id']]);
        $payment = $stmt->fetch();
    } catch (PDOException) {
        Json::error('Data pembayaran belum dapat dibaca.', 503, 'server_not_configured');
    }
    if ($payment === false) {
        Json::error('Pesanan tidak ditemukan.', 404, 'order_not_found');
    }
    if (!Midtrans::verifySignature($payload, (string) $config['server_key'])) {
        Json::error('Tanda tangan notifikasi tidak sah.', 403, 'invalid_signature');
    }

    $wasPaid = (string) $payment['status'] === 'paid';
    $newStatus = Midtrans::statusFromNotification($payload);
    $finalStatus = $wasPaid ? 'paid' : $newStatus;

    try {
        $stmt = $pdo->prepare(
            'UPDATE payments
                SET status = :status, payment_type = :payment_type,
                    transaction_id = :transaction_id, status_code = :status_code,
                    raw_webhook = :raw
              WHERE id = :id'
        );
        $stmt->execute([
            'status' => $finalStatus,
            'payment_type' => isset($payload['payment_type']) ? (string) $payload['payment_type'] : null,
            'transaction_id' => isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            'status_code' => isset($payload['status_code']) ? (string) $payload['status_code'] : null,
            'raw' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'id' => (int) $payment['id'],
        ]);
    } catch (PDOException) {
        Json::error('Status pembayaran gagal dicatat.', 503, 'server_not_configured');
    }

    if ($finalStatus === 'paid' && !$wasPaid && (string) $payment['purpose'] === 'license') {
        $licenseId = $payment['license_id'] !== null ? (int) $payment['license_id'] : null;
        $waTenant = null;
        $waPlan = null;
        $waLicense = null;
        try {
            $tenant = $payment['tenant_id'] !== null
                ? Tenant::find($pdo, (int) $payment['tenant_id'])
                : null;
            $plan = null;
            if ($payment['plan_id'] !== null) {
                $plan = Plan::find($pdo, (int) $payment['plan_id']);
            }
            if ($plan === null && !empty($payment['plan_snapshot'])) {
                $snapshot = json_decode((string) $payment['plan_snapshot'], true);
                if (is_array($snapshot)) {
                    $plan = $snapshot;
                }
            }
            if ($tenant !== null && $plan !== null) {
                $license = License::extendWithPlan($pdo, $tenant, $plan);
                if ($license === null) {
                    $license = License::issue($pdo, (int) $tenant['id'], $plan);
                }
                $licenseId = (int) $license['id'];
                $waTenant = $tenant;
                $waPlan = $plan;
                $waLicense = $license;
            }
        } catch (Throwable) {
            // Pembayaran tetap tercatat lunas; perpanjangan yang
            // gagal di sini dapat diproses manual dari dashboard
            // admin (pesanan tampil sebagai Lunas).
        }

        $paidAt = isset($payload['settlement_time'])
            && strtotime((string) $payload['settlement_time']) !== false
            ? date('Y-m-d H:i:s', strtotime((string) $payload['settlement_time']))
            : date('Y-m-d H:i:s');
        try {
            $stmt = $pdo->prepare(
                'UPDATE payments SET paid_at = :paid_at, license_id = :license_id WHERE id = :id'
            );
            $stmt->execute([
                'paid_at' => $paidAt,
                'license_id' => $licenseId,
                'id' => (int) $payment['id'],
            ]);
        } catch (PDOException) {
            // Pencatatan waktu lunas best-effort; status sudah 'paid'.
        }

        // Notifikasi WhatsApp ke tenant (v0.8.2): dikirim langsung
        // lewat MPWA dari sini agar tiba seketika; best-effort,
        // kegagalannya tidak mengubah hasil webhook.
        Mpwa::notifyPaymentSuccess($pdo, $payment, $waTenant, $waPlan, $waLicense);
    }

    Json::respond(['ok' => true, 'status' => $finalStatus]);
});

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
                    // Teks pesan profesional (v0.7.3, disetujui
                    // pemilik): tanda *...* menjadi huruf tebal di
                    // WhatsApp.
                    'message' => 'Halo *' . $vendor . "*,\n\n"
                        . "Kami dari *EventBooth* mengingatkan bahwa lisensi Plugin Eventbooth Anda akan segera berakhir.\n\n"
                        . "*Detail Lisensi*\n"
                        . 'Plan: ' . (string) ($row['plan_name'] ?? '') . "\n"
                        . 'Berakhir: ' . $tanggal . "\n"
                        . 'Sisa waktu: ' . $daysLeft . " hari lagi\n\n"
                        . "Agar layanan booth Anda tidak terganggu, silakan lakukan perpanjangan sebelum tanggal tersebut — cukup balas pesan ini atau hubungi admin EventBooth.\n\n"
                        . "Terima kasih telah mempercayakan booth Anda kepada EventBooth.\n\n"
                        . "Salam,\n*Tim EventBooth*",
                ];
            }
        }
    } catch (PDOException) {
        Json::error('Data pengingat belum dapat dibaca.', 503, 'server_not_configured');
    }

    // Nomor pengirim untuk workflow: dibaca dari pengaturan
    // platform dan dinormalisasi ulang sebagai pengaman; null
    // bila belum diatur dari dashboard.
    $sender = null;
    try {
        $sender = Whatsapp::normalize(Settings::get($pdo, 'mpwa_sender'));
    } catch (PDOException) {
        $sender = null;
    }

    Json::respond([
        'items' => $items,
        'sender' => $sender,
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
 * API — event & sesi untuk aplikasi booth (Milestone 5a).
 * Penjaga yang sama seperti /api/app/me: token sesi perangkat
 * (Authorization: Bearer) dicari lewat License::findDeviceByToken;
 * tenant aplikasi SELALU adalah tenant pemilik lisensi perangkat
 * itu — aplikasi tidak bisa membaca/menyentuh data tenant lain.
 * ------------------------------------------------------------------- */

/**
 * Konteks perangkat untuk endpoint berbasis token: memeriksa
 * konfigurasi server lalu mewariskan [pdo, device, license,
 * tenant]. Semua kegagalan diakhiri error JSON berkode stabil
 * (server_not_configured/server_error 503, invalid_token 401).
 *
 * @return array{0: PDO, 1: array<string, mixed>, 2: array<string, mixed>, 3: array<string, mixed>}
 */
$appDeviceContext = static function () use ($bearerToken): array {
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

    $device = null;
    $license = null;
    $tenant = null;
    try {
        $device = License::findDeviceByToken($pdo, $token);
        if ($device === null) {
            Json::error('Token perangkat tidak sah.', 401, 'invalid_token');
        }

        $license = License::find($pdo, (int) $device['license_id']);
        if ($license === null) {
            Json::error('Token perangkat tidak sah.', 401, 'invalid_token');
        }

        $tenant = Tenant::find($pdo, (int) $license['tenant_id']);
        if ($tenant === null) {
            Json::error('Token perangkat tidak sah.', 401, 'invalid_token');
        }
    } catch (PDOException) {
        Json::error('Layanan belum siap. Silakan coba lagi.', 503, 'server_error');
    }

    return [$pdo, $device, $license, $tenant];
};

/**
 * Daftar event & paket tenant untuk layar pilihan di aplikasi
 * booth. Event: hanya status 'draft'/'active' (yang masuk akal
 * dijalankan booth). Paket: hanya yang aktif, beserta event_id
 * pasangannya; harga dibulatkan ke rupiah penuh.
 */
$router->get('/api/app/events', static function () use ($appDeviceContext): void {
    [$pdo, $device, $license, $tenant] = $appDeviceContext();
    $tenantId = (int) $tenant['id'];

    try {
        $eventRows = Event::forApi($pdo, $tenantId);
        $packageRows = Package::forApi($pdo, $tenantId);
    } catch (PDOException) {
        Json::error('Layanan belum siap. Silakan coba lagi.', 503, 'server_error');
    }

    $tenantSlug = isset($tenant['slug']) ? (string) $tenant['slug'] : null;
    $events = [];
    foreach ($eventRows as $row) {
        $events[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'album_url' => Album::galleryUrl(Config::appUrl(), $tenantSlug, (string) $row['slug']),
            'event_date' => !empty($row['starts_at'])
                ? date('c', (int) strtotime((string) $row['starts_at']))
                : null,
            'payment_lock' => (int) ($row['payment_lock_enabled'] ?? 1) === 1,
            'has_drive' => ($row['drive_account_id'] ?? null) !== null,
        ];
    }

    $packages = [];
    foreach ($packageRows as $row) {
        $packages[] = [
            'id' => (int) $row['id'],
            'event_id' => (int) $row['event_id'],
            'name' => (string) $row['name'],
            'timer_mode' => (string) $row['timer_mode'],
            'duration_minutes' => ($row['duration_minutes'] ?? null) !== null
                ? (int) $row['duration_minutes'] : null,
            'session_count' => ($row['session_count'] ?? null) !== null
                ? (int) $row['session_count'] : null,
            'price' => (int) round((float) ($row['price'] ?? 0)),
        ];
    }

    Json::respond([
        'status' => 'ok',
        'events' => $events,
        'packages' => $packages,
    ]);
});

/**
 * Buat sesi foto baru pada event milik tenant perangkat ini.
 * Body JSON: {event_id, package_id?, guest_name?, guest_whatsapp?}.
 * Event wajib milik tenant perangkat (selain itu 404 not_found —
 * keberadaan event tenant lain tidak dibocorkan). Sesi dibuat
 * berstatus 'created' dengan kode unik 8 karakter; unggahan &
 * registrasi media menyusul lewat endpoint Drive/sesi (M5b).
 */
$router->post('/api/app/sessions', static function () use ($appDeviceContext, $readJsonBody): void {
    [$pdo, $device, $license, $tenant] = $appDeviceContext();
    $tenantId = (int) $tenant['id'];

    $body = $readJsonBody();
    $eventId = isset($body['event_id']) && is_numeric($body['event_id'])
        ? (int) $body['event_id']
        : 0;
    if ($eventId <= 0) {
        Json::error('event_id wajib diisi.', 422, 'invalid_request');
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
    $guestName = $cap($body['guest_name'] ?? null, 120);
    // WhatsApp tamu disimpan ternormalisasi (62…); nomor yang
    // tidak terbaca normalisasinya disimpan kosong, bukan menggagalkan sesi.
    $guestWhatsapp = Whatsapp::normalize($cap($body['guest_whatsapp'] ?? null, 32));

    $session = null;
    $event = null;
    try {
        $event = Event::findForTenant($pdo, $tenantId, $eventId);
        if ($event === null) {
            Json::error('Event tidak ditemukan.', 404, 'not_found');
        }

        $packageId = null;
        if (isset($body['package_id']) && $body['package_id'] !== null && $body['package_id'] !== '') {
            if (!is_numeric($body['package_id'])) {
                Json::error('package_id tidak sah.', 422, 'invalid_request');
            }
            $packageId = (int) $body['package_id'];
            $package = Package::find($pdo, $packageId);
            if ($package === null || (int) $package['event_id'] !== $eventId) {
                Json::error('Paket tidak ditemukan pada event ini.', 422, 'invalid_request');
            }
        }

        $session = Album::createSession($pdo, $eventId, $packageId, $guestName, $guestWhatsapp);
        License::touchLastSeen($pdo, (int) $license['id']);
    } catch (PDOException) {
        Json::error('Sesi gagal dibuat. Silakan coba lagi.', 503, 'server_error');
    }

    Json::respond([
        'status' => 'ok',
        'session' => [
            'id' => (int) ($session['id'] ?? 0),
            'code' => (string) ($session['code'] ?? ''),
        ],
        'album_url' => Album::galleryUrl(
            Config::appUrl(),
            isset($tenant['slug']) ? (string) $tenant['slug'] : null,
            (string) $event['slug']
        ),
        'session_url' => Config::appUrl() . '/s/' . (string) ($session['code'] ?? ''),
    ]);
});

/* ---------------------------------------------------------------------
 * API — operasi Google Drive untuk aplikasi booth (Milestone 5b).
 *
 * Byte foto TIDAK PERNAH lewat server ini (blueprint §2): server
 * hanya menyiapkan folder, menerbitkan URL sesi resumable upload
 * (aplikasi PUT byte-nya langsung ke Google), dan mencatat
 * metadata sesudah unggahan selesai. Refresh token tetap
 * terenkripsi di server; access token hanya hidup selama satu
 * request dan tidak pernah masuk respons apa pun.
 *
 * Penjaga: token perangkat Bearer yang sama seperti /api/app/*
 * ($appDeviceContext) — hanya aplikasi PC yang memanggil
 * endpoint ini; n8n mengorkestrasi lewat aplikasi, BUKAN lewat
 * endpoint Drive ini. Sesi wajib milik tenant perangkat (selain
 * itu 404 not_found). Semua operasi memakai akun google_accounts
 * yang terkunci pada event sesi itu (events.drive_account_id):
 * event tanpa akun / akun terputus menjawab 503
 * drive_not_configured; kegagalan di sisi Google menjawab 502
 * drive_error.
 * ------------------------------------------------------------------- */

/** Batasi string dari body JSON: trim + potong maksimum; null bila kosong/bukan string. */
$driveCap = static function (mixed $value, int $max): ?string {
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

/** Jenis media yang sah (enum media_files.kind pada skema). */
$driveKinds = ['final', 'original', 'gif', 'video', 'thumb'];

/** Folder tujuan per jenis media: final, original, gif, dan video (boomerang) punya subfolder sendiri; thumb di folder sesi. */
$driveFolderForKind = static function (array $folders, string $kind): string {
    return match ($kind) {
        'final' => $folders['final'],
        'original' => $folders['originals'],
        'gif' => $folders['gif'] ?? $folders['session'],
        'video' => $folders['video'] ?? $folders['session'],
        default => $folders['session'],
    };
};

/**
 * Konteks operasi Drive: dari konteks perangkat + session_id pada
 * body, turunkan [pdo, device, license, tenant, session, account,
 * accessToken]. Session memuat kolom event hasil
 * Album::sessionWithEvent. Kegagalan diakhiri error JSON berkode
 * stabil: invalid_request 422, not_found 404,
 * drive_not_configured 503, drive_error 502, server_error 503.
 *
 * @param array<string, mixed> $body Body JSON hasil $readJsonBody.
 * @return array{0: PDO, 1: array<string, mixed>, 2: array<string, mixed>,
 *               3: array<string, mixed>, 4: array<string, mixed>,
 *               5: array<string, mixed>, 6: string}
 */
$driveContext = static function (array $body) use ($appDeviceContext, $googleCredentials): array {
    [$pdo, $device, $license, $tenant] = $appDeviceContext();

    $sessionId = isset($body['session_id']) && is_numeric($body['session_id'])
        ? (int) $body['session_id']
        : 0;
    if ($sessionId <= 0) {
        Json::error('session_id wajib diisi.', 422, 'invalid_request');
    }

    $session = null;
    $account = null;
    try {
        $session = Album::sessionWithEvent($pdo, $sessionId);
        if ($session === null || (int) $session['event_tenant_id'] !== (int) $tenant['id']) {
            Json::error('Sesi tidak ditemukan.', 404, 'not_found');
        }

        $accountId = $session['event_drive_account_id'] ?? null;
        if ($accountId === null) {
            Json::error(
                'Event ini belum memiliki akun penyimpanan. Minta admin menugaskan akun Drive pada event ini.',
                503,
                'drive_not_configured'
            );
        }

        $account = GoogleAccount::find($pdo, (int) $accountId);
        if ($account === null || (string) ($account['status'] ?? '') !== 'connected') {
            Json::error(
                'Akun penyimpanan event ini sedang tidak terhubung. Minta admin memeriksa menu Google Drive.',
                503,
                'drive_not_configured'
            );
        }

        License::touchLastSeen($pdo, (int) $license['id']);
    } catch (PDOException) {
        Json::error('Layanan belum siap. Silakan coba lagi.', 503, 'server_error');
    }

    // Kredensial OAuth + refresh token didekripsi hanya di sini,
    // di dalam server — tidak pernah keluar dalam respons apa pun.
    $creds = $googleCredentials($pdo);
    $clientSecret = $creds['secret_cipher'] !== null ? Crypto::decrypt($creds['secret_cipher']) : null;
    $refreshToken = Crypto::decrypt((string) ($account['refresh_token_encrypted'] ?? ''));
    if ($creds['client_id'] === '' || $clientSecret === null || $refreshToken === null || $refreshToken === '') {
        Json::error('Kredensial Google Drive belum lengkap di server.', 503, 'drive_not_configured');
    }

    try {
        $accessToken = GoogleDrive::accessTokenOrFail($creds['client_id'], $clientSecret, $refreshToken);
    } catch (GoogleDriveException) {
        // Pola yang sama seperti tes koneksi M3: token ditolak
        // Google -> tandai akun 'error' agar terlihat Super Admin.
        try {
            GoogleAccount::setStatus($pdo, (int) $account['id'], 'error');
        } catch (PDOException) {
            // Penandaan status bersifat tambahan; abaikan kegagalannya.
        }
        Json::error('Koneksi ke Google Drive gagal. Silakan coba lagi.', 502, 'drive_error');
    }

    return [$pdo, $device, $license, $tenant, $session, $account, $accessToken];
};

/**
 * Pastikan struktur folder PER EVENT ada pada akun Drive event:
 * "Plugin Eventbooth/{NamaTenant}/{NamaEvent}" lalu subfolder
 * per jenis `final`, `originals`, `gif`, `video`, dan di dalam
 * tiap subfolder jenis ada folder per kode sesi. Kembalikan
 * peta ID folder TUJUAN UNGGAH per jenis (folder kode sesi di
 * dalam subfolder jenisnya); kunci 'event'/'session' menunjuk
 * folder event sebagai induk bersama.
 * Folder root memakai google_accounts.root_folder_id; bila
 * kosong (akun tertaut sebelum folder root sempat dibuat),
 * folder root dibuat sekarang dan ID-nya disimpan kembali.
 * ID folder event disimpan ke sessions.drive_folder_id secara
 * best-effort (folder tetap ditemukan lewat nama pada panggilan
 * berikutnya; skema events memang tidak punya kolom folder).
 *
 * @param array<string, mixed> $tenant  Baris tenant pemilik sesi.
 * @param array<string, mixed> $session Baris sesi + kolom event (Album::sessionWithEvent).
 * @param array<string, mixed> $account Baris google_accounts penampung event.
 * @return array{event: string, session: string, final: string, originals: string, gif: string, video: string}
 * @throws GoogleDriveException
 */
$ensureSessionFolders = static function (
    PDO $pdo,
    array $tenant,
    array $session,
    array $account,
    string $accessToken
): array {
    $rootId = isset($account['root_folder_id']) && is_string($account['root_folder_id'])
        && $account['root_folder_id'] !== ''
        ? $account['root_folder_id']
        : null;

    if ($rootId === null) {
        $rootId = GoogleDrive::ensureRootFolder($accessToken);
        if ($rootId === null) {
            throw new GoogleDriveException('Folder root "Plugin Eventbooth" tidak dapat disiapkan.');
        }
        try {
            GoogleAccount::updateRootFolder($pdo, (int) $account['id'], $rootId);
        } catch (PDOException) {
            // Best-effort: folder root tetap ditemukan lewat pencarian nama.
        }
    }

    // Struktur PER EVENT (permintaan pemilik booth): folder
    // event menampung subfolder per jenis — final, originals,
    // gif, video — dan di dalam tiap subfolder jenis ada folder
    // per kode sesi. Satu folder "final" dengan demikian berisi
    // banyak sesi, bukan satu folder sesi berisi semua jenis.
    $ids = GoogleDrive::ensureFolderPath($accessToken, $rootId, [
        Tenant::displayName($tenant),
        (string) $session['event_name'],
    ]);
    $eventFolderId = $ids[1] ?? '';
    if ($eventFolderId === '') {
        throw new GoogleDriveException('Folder event tidak dapat disiapkan.');
    }

    $code = (string) $session['code'];
    $kindSessionIds = [];
    foreach (['final', 'originals', 'gif', 'video'] as $kindFolder) {
        $kindParentId = GoogleDrive::ensureFolder($accessToken, $kindFolder, $eventFolderId);
        $kindSessionIds[$kindFolder] = GoogleDrive::ensureFolder($accessToken, $code, $kindParentId);
    }

    if (empty($session['drive_folder_id'])) {
        try {
            Album::saveSessionDriveFolder($pdo, (int) $session['id'], $eventFolderId);
        } catch (PDOException) {
            // Best-effort: ID dicari lagi lewat nama pada panggilan berikutnya.
        }
    }

    return [
        'event' => $eventFolderId,
        'session' => $eventFolderId,
        'final' => $kindSessionIds['final'],
        'originals' => $kindSessionIds['originals'],
        'gif' => $kindSessionIds['gif'],
        'video' => $kindSessionIds['video'],
    ];
};

/**
 * Siapkan seluruh folder sesi di Drive (dipanggil aplikasi
 * sebelum mulai mengunggah foto sesi). Body: {session_id}.
 * Balasan: {status: "ok", folders: {event, session, final,
 * originals, gif, video}} — ID folder Drive tiap tingkat.
 */
$router->post('/api/drive/session-folders', static function () use ($readJsonBody, $driveContext, $ensureSessionFolders): void {
    $body = $readJsonBody();
    [$pdo, $device, $license, $tenant, $session, $account, $accessToken] = $driveContext($body);

    try {
        $folders = $ensureSessionFolders($pdo, $tenant, $session, $account, $accessToken);
    } catch (GoogleDriveException) {
        Json::error('Folder Drive gagal disiapkan. Silakan coba lagi.', 502, 'drive_error');
    }

    Json::respond(['status' => 'ok', 'folders' => $folders]);
});

/**
 * Inisiasi resumable upload satu file. Body: {session_id, kind,
 * file_name, mime_type?, size_bytes?}. Server meminta URL sesi
 * unggahan ke Google memakai token akun event; aplikasi lalu PUT
 * byte file langsung ke upload_url itu (dengan header
 * Content-Length sesuai ukuran file). Folder tujuan: final ->
 * subfolder final, original -> originals, gif/video/thumb ->
 * folder sesi.
 * Balasan: {status: "ok", upload_url, folder_id}.
 */
$router->post('/api/drive/init-upload', static function () use ($readJsonBody, $driveContext, $ensureSessionFolders, $driveCap, $driveKinds, $driveFolderForKind): void {
    $body = $readJsonBody();
    [$pdo, $device, $license, $tenant, $session, $account, $accessToken] = $driveContext($body);

    $kind = isset($body['kind']) && is_string($body['kind']) ? trim($body['kind']) : '';
    if (!in_array($kind, $driveKinds, true)) {
        Json::error('kind wajib salah satu dari: final, original, gif, video, thumb.', 422, 'invalid_request');
    }

    $fileName = $driveCap($body['file_name'] ?? null, 255);
    if ($fileName === null) {
        Json::error('file_name wajib diisi.', 422, 'invalid_request');
    }

    $mimeType = $driveCap($body['mime_type'] ?? null, 100) ?? 'application/octet-stream';

    $sizeBytes = null;
    if (isset($body['size_bytes']) && $body['size_bytes'] !== null && $body['size_bytes'] !== '') {
        if (!is_numeric($body['size_bytes']) || (int) $body['size_bytes'] < 0) {
            Json::error('size_bytes tidak sah.', 422, 'invalid_request');
        }
        $sizeBytes = (int) $body['size_bytes'];
    }

    $folders = [];
    $folderId = '';
    $uploadUrl = '';
    try {
        $folders = $ensureSessionFolders($pdo, $tenant, $session, $account, $accessToken);
        $folderId = $driveFolderForKind($folders, $kind);
        $uploadUrl = GoogleDrive::initResumableUpload($accessToken, $folderId, $fileName, $mimeType, $sizeBytes);
    } catch (GoogleDriveException) {
        Json::error('Inisiasi unggahan ke Google Drive gagal. Silakan coba lagi.', 502, 'drive_error');
    }

    Json::respond([
        'status' => 'ok',
        'upload_url' => $uploadUrl,
        'folder_id' => $folderId,
    ]);
});

/**
 * Selesaikan satu unggahan: aplikasi memanggil ini SETELAH PUT
 * byte ke upload_url selesai. Body: {session_id, kind, file_id,
 * file_name?}. Server membaca metadata file dari Google, memberi
 * izin lihat publik (anyone with link), dan mencatat baris
 * media_files agar foto muncul di album publik.
 * Balasan: {status: "ok", media: {id, web_link, thumbnail_link,
 * download_url}}.
 */
$router->post('/api/drive/complete', static function () use ($readJsonBody, $driveContext, $ensureSessionFolders, $driveCap, $driveKinds, $driveFolderForKind): void {
    $body = $readJsonBody();
    [$pdo, $device, $license, $tenant, $session, $account, $accessToken] = $driveContext($body);

    $kind = isset($body['kind']) && is_string($body['kind']) ? trim($body['kind']) : '';
    if (!in_array($kind, $driveKinds, true)) {
        Json::error('kind wajib salah satu dari: final, original, gif, video, thumb.', 422, 'invalid_request');
    }

    $fileId = isset($body['file_id']) && is_string($body['file_id']) ? trim($body['file_id']) : '';
    if ($fileId === '') {
        Json::error('file_id wajib diisi.', 422, 'invalid_request');
    }

    $fileNameInput = $driveCap($body['file_name'] ?? null, 255);

    $metadata = null;
    $folders = [];
    try {
        $metadata = GoogleDrive::fileMetadataOrFail($accessToken, $fileId);
        GoogleDrive::shareAnyoneReaderOrFail($accessToken, $metadata['id']);
        $folders = $ensureSessionFolders($pdo, $tenant, $session, $account, $accessToken);
    } catch (GoogleDriveException) {
        Json::error('Penyelesaian unggahan di Google Drive gagal. Silakan coba lagi.', 502, 'drive_error');
    }

    $media = null;
    try {
        $media = Album::registerMedia($pdo, [
            'session_id' => (int) $session['id'],
            'event_id' => (int) $session['event_id'],
            'drive_account_id' => (int) $account['id'],
            'drive_file_id' => $metadata['id'],
            'drive_folder_id' => $driveFolderForKind($folders, $kind),
            'kind' => $kind,
            'file_name' => $fileNameInput ?? $metadata['name'],
            'mime_type' => $metadata['mime_type'],
            'size_bytes' => $metadata['size_bytes'],
            'web_link' => $metadata['web_link'],
            'thumbnail_link' => $metadata['thumbnail_link'],
        ]);
    } catch (PDOException) {
        Json::error('Media gagal dicatat. Silakan coba lagi.', 503, 'server_error');
    }

    // Snapshot kuota akun digeser sebesar ukuran file ini
    // (best-effort; angka otoritatif dibaca ulang dari Google
    // saat tes koneksi/pengaitan ulang di dashboard Super Admin).
    if ($metadata['size_bytes'] !== null && ($account['quota_used_bytes'] ?? null) !== null) {
        try {
            GoogleAccount::updateQuota(
                $pdo,
                (int) $account['id'],
                ($account['quota_total_bytes'] ?? null) !== null
                    ? (int) $account['quota_total_bytes']
                    : null,
                (int) $account['quota_used_bytes'] + (int) $metadata['size_bytes']
            );
        } catch (PDOException) {
            // Best-effort: abaikan kegagalan pembaruan snapshot kuota.
        }
    }

    Json::respond([
        'status' => 'ok',
        'media' => [
            'id' => (int) ($media['id'] ?? 0),
            'web_link' => $metadata['web_link'],
            'thumbnail_link' => $metadata['thumbnail_link'],
            'download_url' => Album::downloadUrl(['drive_file_id' => $metadata['id']]),
        ],
    ]);
});

/* ---------------------------------------------------------------------
 * API — pembayaran tamu (live sejak v0.11.0) & stub berikutnya
 * ------------------------------------------------------------------- */
$stub = static function (string $note): callable {
    return static function () use ($note): void {
        Json::notImplemented($note);
    };
};

// TODO(M4): Checkout & perpanjangan lisensi via Midtrans platform
// (blueprint §5A). Server Key hanya di server, tidak di aplikasi PC.
$router->post('/api/plan/checkout', $stub('Checkout lisensi via Midtrans — Milestone 4.'));

/* ---------------------------------------------------------------------
 * Pembayaran tamu booth (QRIS) — Midtrans MILIK TENANT (keputusan
 * 2026-11-01: uang langsung ke tenant). Aplikasi membuat tagihan
 * QRIS untuk sesi (Core API akun tenant), tamu memindai dan
 * membayar dari ponselnya, aplikasi menanyakan status ke server
 * dan server memeriksa langsung ke Midtrans (jalur utama —
 * webhook bersifat pelengkap bagi tenant yang mengisi URL
 * notifikasinya). Status lunas HANYA dari respons Midtrans yang
 * tanda tangannya terverifikasi dengan Server Key tenant.
 * ------------------------------------------------------------------- */

/** Cari pembayaran tamu dari order_id (+ tenant bila diberikan). */
$guestPaymentFind = static function (PDO $pdo, string $orderId, ?int $tenantId): ?array {
    $sql = "SELECT * FROM payments WHERE purpose = 'guest' AND order_id = :order_id";
    $params = ['order_id' => $orderId];
    if ($tenantId !== null) {
        $sql .= ' AND tenant_id = :tenant';
        $params['tenant'] = $tenantId;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
};

/**
 * Terapkan payload status/notifikasi Midtrans ke baris payments:
 * petakan status, catat paid_at saat pertama lunas, simpan
 * payload mentah untuk audit. Mengembalikan baris terbaru.
 */
$guestPaymentApply = static function (PDO $pdo, array $payment, array $payload): array {
    $newStatus = Midtrans::statusFromNotification($payload);
    $paidAt = $payment['paid_at'] ?? null;
    if ($newStatus === 'paid' && ($paidAt === null || $paidAt === '')) {
        $paidAt = date('Y-m-d H:i:s');
    }
    $expiredAt = $payment['expired_at'] ?? null;
    if ($newStatus === 'expired' && ($expiredAt === null || $expiredAt === '')) {
        $expiredAt = date('Y-m-d H:i:s');
    }
    $stmt = $pdo->prepare(
        'UPDATE payments
            SET status = :status,
                status_code = :status_code,
                transaction_id = COALESCE(:transaction_id, transaction_id),
                payment_type = COALESCE(:payment_type, payment_type),
                raw_webhook = :raw,
                paid_at = :paid_at,
                expired_at = :expired_at
          WHERE id = :id'
    );
    $stmt->execute([
        'status' => $newStatus,
        'status_code' => isset($payload['status_code']) ? (string) $payload['status_code'] : ($payment['status_code'] ?? null),
        'transaction_id' => isset($payload['transaction_id']) && (string) $payload['transaction_id'] !== ''
            ? (string) $payload['transaction_id'] : null,
        'payment_type' => isset($payload['payment_type']) && (string) $payload['payment_type'] !== ''
            ? (string) $payload['payment_type'] : null,
        'raw' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        'paid_at' => $paidAt,
        'expired_at' => $expiredAt,
        'id' => (int) $payment['id'],
    ]);
    $payment['status'] = $newStatus;
    $payment['paid_at'] = $paidAt;

    return $payment;
};

/** Bentuk ringkas pembayaran tamu untuk aplikasi. */
$guestPaymentView = static function (array $payment, array $extra = []): array {
    return array_merge([
        'order_id' => (string) $payment['order_id'],
        'amount' => (int) round((float) $payment['gross_amount']),
        'state' => (string) $payment['status'],
        'paid' => (string) $payment['status'] === 'paid',
    ], $extra);
};

$router->post('/api/payment/create', static function () use ($appDeviceContext, $readJsonBody, $guestPaymentFind, $guestPaymentView): void {
    [$pdo, $device, $license, $tenant] = $appDeviceContext();
    $tenantId = (int) $tenant['id'];
    $body = $readJsonBody();

    $sessionId = isset($body['session_id']) && is_numeric($body['session_id']) ? (int) $body['session_id'] : 0;
    if ($sessionId <= 0) {
        Json::error('session_id wajib diisi.', 422, 'invalid_request');
    }

    try {
        $session = Album::sessionWithEvent($pdo, $sessionId);
        if ($session === null || (int) $session['event_tenant_id'] !== $tenantId) {
            Json::error('Sesi tidak ditemukan.', 404, 'not_found');
        }
        $event = Event::findForTenant($pdo, $tenantId, (int) $session['event_id']);
        if ($event === null) {
            Json::error('Event tidak ditemukan.', 404, 'not_found');
        }
        if ((int) ($event['payment_lock_enabled'] ?? 0) !== 1) {
            Json::error('Event ini tidak memakai pembayaran tamu.', 422, 'payment_not_required');
        }
        $package = null;
        if (!empty($session['package_id'])) {
            $package = Package::find($pdo, (int) $session['package_id']);
        }
        $amount = $package !== null ? (int) round((float) ($package['price'] ?? 0)) : 0;
        if ($amount <= 0) {
            Json::error('Paket sesi ini tidak memiliki harga yang dapat ditagih.', 422, 'no_billable_package');
        }

        // Satu tagihan menunggu per sesi: pembuatan ganda (ketuk
        // dua kali) mengembalikan tagihan yang sama. Tagihan
        // menunggu yang sudah lebih dari 15 menit dianggap
        // kedaluwarsa (QRIS tidak berlaku lagi / proses pembuatan
        // dulu terputus) — ditandai expired agar tagihan baru
        // dapat dibuat, tidak mengunci sesi selamanya.
        $stmt = $pdo->prepare(
            "SELECT * FROM payments
              WHERE purpose = 'guest' AND session_id = :session AND status = 'pending'
              ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['session' => $sessionId]);
        $existing = $stmt->fetch();
        if ($existing !== false) {
            $age = time() - (int) strtotime((string) ($existing['created_at'] ?? 'now'));
            if ($age <= 15 * 60) {
                Json::respond([
                    'status' => 'ok',
                    'payment' => $guestPaymentView($existing, ['existing' => true]),
                ]);
                return;
            }
            $expire = $pdo->prepare(
                "UPDATE payments SET status = 'expired', expired_at = NOW() WHERE id = :id"
            );
            $expire->execute(['id' => (int) $existing['id']]);
        }

        $cfg = Midtrans::tenantConfig($pdo, $tenantId);
    } catch (PDOException) {
        Json::error('Layanan belum siap. Silakan coba lagi.', 503, 'server_error');
        return;
    }
    if ($cfg === null || !$cfg['enabled'] || !$cfg['configured']) {
        Json::error(
            'Pembayaran tamu belum aktif. Tenant perlu mengisi kunci Midtrans miliknya di dashboard (menu Midtrans Tamu).',
            503,
            'tenant_midtrans_not_configured'
        );
    }

    $orderId = 'EBG-' . (string) $session['code'] . '-' . strtoupper(bin2hex(random_bytes(3)));
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO payments
                (purpose, tenant_id, event_id, session_id, order_id, gross_amount, status, payment_type)
             VALUES
                ('guest', :tenant, :event, :session, :order_id, :amount, 'pending', 'qris')"
        );
        $stmt->execute([
            'tenant' => $tenantId,
            'event' => (int) $session['event_id'],
            'session' => $sessionId,
            'order_id' => $orderId,
            'amount' => $amount,
        ]);
        $paymentId = (int) $pdo->lastInsertId();

        $charge = Midtrans::chargeQris(
            $cfg,
            $orderId,
            $amount,
            'Photobooth ' . (string) ($package['name'] ?? 'Sesi Foto')
        );
        if ($charge['transaction_id'] !== null) {
            $upd = $pdo->prepare('UPDATE payments SET transaction_id = :tid WHERE id = :id');
            $upd->execute(['tid' => $charge['transaction_id'], 'id' => $paymentId]);
        }
    } catch (MidtransException $e) {
        try {
            $fail = $pdo->prepare(
                "UPDATE payments SET status = 'failed', raw_webhook = :raw
                  WHERE order_id = :order_id AND purpose = 'guest'"
            );
            $fail->execute([
                'raw' => json_encode([
                    'error' => $e->getMessage(),
                    'midtrans' => $e->gatewayResponse,
                ], JSON_UNESCAPED_SLASHES),
                'order_id' => $orderId,
            ]);
        } catch (PDOException) {
            // Penandaan gagal bersifat tambahan.
        }
        Json::error('Gerbang pembayaran menolak permintaan: ' . $e->getMessage(), 502, 'gateway_error');
        return;
    } catch (PDOException) {
        Json::error('Pembayaran gagal dibuat. Silakan coba lagi.', 503, 'server_error');
        return;
    }

    Json::respond([
        'status' => 'ok',
        'payment' => [
            'order_id' => $orderId,
            'amount' => $amount,
            'state' => 'pending',
            'paid' => false,
            'qr_string' => $charge['qr_string'],
            'qr_url' => $charge['qr_url'],
            'expiry_time' => $charge['expiry_time'],
        ],
    ]);
});

$router->get('/api/payment/status', static function () use ($appDeviceContext, $guestPaymentFind, $guestPaymentApply, $guestPaymentView): void {
    [$pdo, $device, $license, $tenant] = $appDeviceContext();
    $tenantId = (int) $tenant['id'];

    $orderId = isset($_GET['order_id']) ? trim((string) $_GET['order_id']) : '';
    if ($orderId === '') {
        Json::error('order_id wajib diisi.', 422, 'invalid_request');
    }

    try {
        $payment = $guestPaymentFind($pdo, $orderId, $tenantId);
    } catch (PDOException) {
        Json::error('Layanan belum siap. Silakan coba lagi.', 503, 'server_error');
        return;
    }
    if ($payment === null) {
        Json::error('Pembayaran tidak ditemukan.', 404, 'not_found');
    }

    $terminal = ['paid', 'denied', 'expired', 'cancelled', 'failed', 'refunded'];
    if (in_array((string) $payment['status'], $terminal, true)) {
        Json::respond(['status' => 'ok', 'payment' => $guestPaymentView($payment)]);
        return;
    }

    $gatewayFailed = false;
    try {
        $cfg = Midtrans::tenantConfig($pdo, $tenantId);
        if ($cfg !== null && $cfg['configured']) {
            $statusPayload = Midtrans::fetchStatus($cfg, $orderId);
            $signatureOk = !isset($statusPayload['signature_key'])
                || Midtrans::verifySignature($statusPayload, (string) $cfg['server_key']);
            if ($signatureOk) {
                $payment = $guestPaymentApply($pdo, $payment, $statusPayload);
            } else {
                $gatewayFailed = true;
            }
        }
    } catch (MidtransException | PDOException) {
        // Status terakhir yang tercatat tetap dilaporkan; aplikasi
        // akan menanyakan lagi pada putaran berikutnya.
        $gatewayFailed = true;
    }

    Json::respond([
        'status' => 'ok',
        'payment' => $guestPaymentView($payment, ['gateway_check_failed' => $gatewayFailed]),
    ]);
});

$router->post('/api/payment/notification', static function () use ($readJsonBody, $guestPaymentFind, $guestPaymentApply): void {
    // Endpoint ini SELALU menjawab 200 untuk notifikasi yang
    // tidak dapat diproses, dan hanya MENERAPKAN notifikasi yang
    // order-nya dikenal + tanda tangannya terverifikasi. Alasan:
    // (1) tombol "Test notification URL" di dashboard Midtrans
    // mengirim order contoh yang tidak ada di tabel kita — jawaban
    // non-200 membuat Midtrans melaporkan "Test failed"; (2) akun
    // Midtrans tenant dapat dipakai bersama sistem tenant yang
    // lain, jadi notifikasi milik transaksi asing sah-sah saja
    // mampir ke sini dan harus diakui tanpa diterapkan. Status
    // lunas utama tetap dipastikan lewat /api/payment/status.
    $payload = $readJsonBody();
    $orderId = isset($payload['order_id']) ? trim((string) $payload['order_id']) : '';
    $pdo = Database::pdo();
    if ($orderId === '' || $pdo === null) {
        Json::respond(['status' => 'ok', 'applied' => false]);
        return;
    }

    try {
        $payment = $guestPaymentFind($pdo, $orderId, null);
        if ($payment === null) {
            Json::respond(['status' => 'ok', 'applied' => false]);
            return;
        }
        $cfg = Midtrans::tenantConfig($pdo, (int) $payment['tenant_id']);
        if ($cfg === null
            || ($cfg['server_key'] ?? null) === null
            || !Midtrans::verifySignature($payload, (string) $cfg['server_key'])
        ) {
            Json::respond(['status' => 'ok', 'applied' => false]);
            return;
        }
        $guestPaymentApply($pdo, $payment, $payload);
    } catch (PDOException) {
        Json::respond(['status' => 'ok', 'applied' => false]);
        return;
    }

    Json::respond(['status' => 'ok', 'applied' => true]);
});

// CATATAN(M5b): Endpoint operasi Drive (/api/drive/session-folders,
// /api/drive/init-upload, /api/drive/complete) SUDAH HIDUP di bagian
// "API — operasi Google Drive" di atas — manajemen koneksinya
// (OAuth, multi-akun, default) hidup di /admin/drive sejak M3 dan
// token Drive tidak pernah keluar server ini.

// TODO(M5): Registrasi metadata sesi selesai upload & berbagi
// WhatsApp lewat n8n/MPWA (blueprint §3 langkah 5 & §5C).
$router->post('/api/session/register', $stub('Registrasi metadata sesi — Milestone 5.'));
/**
 * Kirim tautan galeri sesi ke WhatsApp tamu (fitur plan
 * whatsapp_share) lewat gateway MPWA platform. Dipanggil
 * aplikasi dari layar selesai kios setelah tamu mengisi
 * nomornya. Pengiriman tercatat sebagai upaya terbaik gateway
 * platform — nomor pengirim & API key diatur Super Admin di
 * /admin/payments, sama seperti notifikasi lunas lisensi.
 */
$router->post('/api/session/share-wa', static function () use ($appDeviceContext, $readJsonBody): void {
    [$pdo, $device, $license, $tenant] = $appDeviceContext();
    $tenantId = (int) $tenant['id'];
    $body = $readJsonBody();

    $entitlements = License::entitlements($license);
    if (empty($entitlements['features']['whatsapp_share'])) {
        Json::error('Fitur berbagi via WhatsApp tidak termasuk dalam plan lisensimu.', 403, 'feature_not_included');
    }

    $sessionId = isset($body['session_id']) && is_numeric($body['session_id']) ? (int) $body['session_id'] : 0;
    $number = Whatsapp::normalize(isset($body['whatsapp']) ? (string) $body['whatsapp'] : null);
    if ($sessionId <= 0) {
        Json::error('session_id wajib diisi.', 422, 'invalid_request');
    }
    if ($number === null) {
        Json::error('Nomor WhatsApp tidak sah. Gunakan format 08… atau 628…', 422, 'invalid_whatsapp');
    }

    try {
        $session = Album::sessionWithEvent($pdo, $sessionId);
        if ($session === null || (int) $session['event_tenant_id'] !== $tenantId) {
            Json::error('Sesi tidak ditemukan.', 404, 'not_found');
        }
    } catch (PDOException) {
        Json::error('Layanan belum siap. Silakan coba lagi.', 503, 'server_error');
        return;
    }

    // URL sesi kanonis — sama seperti session_url pada pembuatan
    // sesi (POST /api/app/sessions).
    $sessionUrl = Config::appUrl() . '/s/' . (string) $session['code'];
    $vendor = (string) ($tenant['vendor_name'] ?? 'Photobooth');
    $message = "Halo! Foto sesi {$session['code']} di event {$session['event_name']} sudah dapat dilihat & diunduh di sini:\n"
        . $sessionUrl . "\n\nTerima kasih sudah berfoto bersama {$vendor}!";

    if (!Mpwa::sendText($pdo, $number, $message)) {
        Json::error(
            'Pesan WhatsApp belum terkirim (gateway platform belum diatur atau sedang gangguan). Tautan tetap dapat dibagikan lewat QR di layar.',
            502,
            'whatsapp_send_failed'
        );
    }

    Json::respond(['status' => 'ok', 'sent' => true, 'session_url' => $sessionUrl]);
});

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
