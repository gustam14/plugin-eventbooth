<?php
declare(strict_types=1);

/**
 * Front controller — semua request masuk lewat berkas ini
 * (lihat public/.htaccess).
 *
 * Rute yang sudah hidup:
 *   GET  /            -> halaman landing                     (M1)
 *   GET  /api/health  -> status JSON                          (M1)
 *   GET|POST /login, POST /logout, GET /dashboard             (M2)
 *   GET  /admin, CRUD plan di /admin/plans                    (M2)
 *   GET  /tenant                                              (M2)
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
    View::render('landing', [
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
 * Area Super Admin (Milestone 2) — hanya role super_admin.
 * ------------------------------------------------------------------- */
$router->get('/admin', static function (): void {
    $user = Auth::requireRole('super_admin');

    $counts = ['tenants' => 0, 'licenses' => 0, 'plans' => 0, 'events' => 0];
    $dbReady = false;

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
    }

    View::render('admin/overview', [
        'appName' => Config::appName(),
        'user' => $user,
        'flashes' => Flash::pull(),
        'dbReady' => $dbReady,
        'counts' => $counts,
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
 * API — stub grup berikutnya (TODO, belum ada logika bisnis)
 * ------------------------------------------------------------------- */
$stub = static function (string $note): callable {
    return static function () use ($note): void {
        Json::notImplemented($note);
    };
};

// TODO(M2/M6): Lisensi aplikasi booth — aktivasi device &
// validasi/heartbeat hemat (blueprint §2 & §5A).
$router->post('/api/license/activate', $stub('Aktivasi lisensi — Milestone 2/6.'));
$router->post('/api/license/validate', $stub('Validasi/heartbeat lisensi — Milestone 2/6.'));

// TODO(M4): Checkout & perpanjangan lisensi via Midtrans platform
// (blueprint §5A). Server Key hanya di server, tidak di aplikasi PC.
$router->post('/api/plan/checkout', $stub('Checkout lisensi via Midtrans — Milestone 4.'));

// TODO(M4): Pembayaran tamu booth via Midtrans tenant + webhook
// notifikasi terverifikasi (blueprint §5B & §6).
$router->post('/api/payment/create', $stub('Buat pembayaran tamu — Milestone 4.'));
$router->post('/api/payment/notification', $stub('Webhook notifikasi Midtrans — Milestone 4.'));
$router->get('/api/payment/status', $stub('Polling status pembayaran — Milestone 4.'));

// TODO(M3/M5): Google Drive terpusat multi-akun (blueprint §2A & §3).
// Dipanggil n8n/aplikasi dengan token workflow; token Drive tidak
// pernah keluar dari server ini.
$router->post('/api/drive/folder', $stub('Buat folder Drive tenant/event/sesi — Milestone 3/5.'));
$router->post('/api/drive/init-upload', $stub('Inisiasi resumable upload Drive — Milestone 3/5.'));

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
