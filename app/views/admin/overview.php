<?php
/**
 * Ringkasan Super Admin.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var bool $dbReady False bila database belum disetel / tabel belum ada.
 * @var array{tenants: int, licenses: int, plans: int, events: int} $counts
 */

$title = 'Ringkasan Super Admin';
$activeNav = 'admin';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<h1>Ringkasan Super Admin</h1>
<p class="sub">Selamat datang, <?= e($user['name']) ?>. Ini pusat kendali platform Plugin Eventbooth.</p>

<?php if (!($dbReady ?? false)): ?>
    <div class="notice">
        <strong>Database belum disetel atau belum dapat dihubungi.</strong>
        Angka di bawah ini belum mencerminkan data sebenarnya. Isi
        kredensial database di <code>.env</code> dan impor
        <code>database/schema.sql</code> untuk mengaktifkan dashboard.
    </div>
<?php endif; ?>

<div class="stats">
    <div class="stat">
        <div class="num"><?= number_format((int) ($counts['tenants'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Tenant</div>
    </div>
    <div class="stat">
        <div class="num"><?= number_format((int) ($counts['licenses'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Lisensi</div>
    </div>
    <div class="stat">
        <div class="num"><?= number_format((int) ($counts['plans'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Plan</div>
    </div>
    <div class="stat">
        <div class="num"><?= number_format((int) ($counts['events'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Event</div>
    </div>
</div>

<div class="cards">
    <div class="card">
        <h2>Plan Lisensi</h2>
        <p>
            Atur harga, periode, batas perangkat/event, kuota, dan
            fitur tiap plan. Perubahan plan langsung berlaku untuk
            penerbitan lisensi berikutnya.
        </p>
        <p style="margin-top: 12px;"><a class="btn btn-primary btn-small" href="/admin/plans">Kelola Plan</a></p>
    </div>
    <div class="card soon">
        <span class="soon-tag">Segera hadir</span>
        <h2>Tenant &amp; Lisensi</h2>
        <p>
            Terbitkan, suspensi, dan cabut lisensi tenant; lepas ikatan
            perangkat; pantau heartbeat aplikasi booth dan audit log.
        </p>
    </div>
    <div class="card soon">
        <span class="soon-tag">Segera hadir</span>
        <h2>Google Drive Pusat</h2>
        <p>
            Kaitkan satu atau lebih akun Google Drive platform,
            tetapkan akun default, dan pantau pemakaian kuota per
            tenant. Penugasan Drive dilakukan per event.
        </p>
    </div>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
