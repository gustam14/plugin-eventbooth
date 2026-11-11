<?php
/**
 * Partial layout dashboard — bagian atas (dibuka) untuk semua
 * halaman /admin dan /tenant. Ditutup oleh partials/dashboard_bottom.php.
 *
 * Variabel yang diharapkan dari view pemanggil:
 * @var string $appName
 * @var string $title      Judul halaman.
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $activeNav  Kunci menu aktif: admin | plans | tenants | drive | tenant | licenses | devices | events | packages | catalog | crew | clients | midtrans | branding | account.
 */

$roleLabel = match ($user['role'] ?? '') {
    'super_admin' => 'Super Admin',
    'tenant_admin' => 'Admin Tenant',
    'tenant_staff' => 'Staf Tenant',
    default => 'Pengguna',
};

$navItems = [];
if (($user['role'] ?? '') === 'super_admin') {
    $navItems = [
        ['key' => 'admin', 'label' => 'Ringkasan', 'href' => '/admin'],
        ['key' => 'plans', 'label' => 'Plan Lisensi', 'href' => '/admin/plans'],
        ['key' => 'tenants', 'label' => 'Tenant & Lisensi', 'href' => '/admin/tenants'],
        ['key' => 'admin-events', 'label' => 'Event', 'href' => '/admin/events'],
        ['key' => 'drive', 'label' => 'Google Drive', 'href' => '/admin/drive'],
        ['key' => 'payments', 'label' => 'Pembayaran', 'href' => '/admin/payments'],
        ['key' => 'account', 'label' => 'Ganti Kata Sandi', 'href' => '/account/password'],
    ];
    $navSoon = ['Pengaturan Platform'];
} else {
    // Menu tenant dikelompokkan (v0.30.0, permintaan pemilik
    // 11 Nov 2026 — menu terlalu banyak bila dijejer rata):
    // Beranda berdiri sendiri; sisanya empat grup lipat.
    $navItems = [
        ['key' => 'tenant', 'label' => 'Beranda', 'href' => '/tenant'],
        ['label' => 'Event & Galeri', 'children' => [
            ['key' => 'events', 'label' => 'Event', 'href' => '/tenant/events'],
            ['key' => 'branding', 'label' => 'Branding Galeri', 'href' => '/tenant/branding'],
        ]],
        ['label' => 'Paket & Pembayaran', 'children' => [
            ['key' => 'packages', 'label' => 'Paket Sesi', 'href' => '/tenant/packages'],
            ['key' => 'midtrans', 'label' => 'Midtrans Tamu', 'href' => '/tenant/midtrans'],
        ]],
        ['label' => 'Operasional', 'children' => [
            ['key' => 'catalog', 'label' => 'Daftar Paket', 'href' => '/tenant/package-catalog'],
            ['key' => 'crew', 'label' => 'Kru', 'href' => '/tenant/crew'],
            ['key' => 'clients', 'label' => 'Buku Klien', 'href' => '/tenant/clients'],
            ['key' => 'notify', 'label' => 'Notifikasi', 'href' => '/tenant/notifications'],
        ]],
        ['label' => 'Akun & Lisensi', 'children' => [
            ['key' => 'licenses', 'label' => 'Lisensi Saya', 'href' => '/tenant/licenses'],
            ['key' => 'devices', 'label' => 'Perangkat', 'href' => '/tenant/devices'],
            ['key' => 'account', 'label' => 'Ganti Kata Sandi', 'href' => '/account/password'],
        ]],
    ];
    $navSoon = ['Statistik'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0C0F">
<title><?= e($title) ?> · <?= e($appName) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230A0C0F'/%3E%3Cg fill='none' stroke='%23C9A24B' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 26h6l4-5h16l4 5h6a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V30a4 4 0 0 1 4-4z'/%3E%3Ccircle cx='32' cy='37' r='7'/%3E%3C/g%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
<?php require BASE_PATH . '/app/views/partials/app.css.php'; ?>
</style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brandmark">
            <span class="logo-mark" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
            </span>
            <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span><span class="brandmark-sub">Dashboard</span></span>
        </div>
        <nav class="side-nav" aria-label="Menu dashboard">
            <span class="side-heading">Menu</span>
            <?php foreach ($navItems as $item): ?>
                <?php if (isset($item['children'])): ?>
                    <?php
                    $groupActive = false;
                    foreach ($item['children'] as $childItem) {
                        if (($activeNav ?? '') === $childItem['key']) {
                            $groupActive = true;
                            break;
                        }
                    }
                    ?>
                    <details class="side-group"<?= $groupActive ? ' open' : '' ?>>
                        <summary class="side-group-title<?= $groupActive ? ' active' : '' ?>"><?= e($item['label']) ?></summary>
                        <?php foreach ($item['children'] as $childItem): ?>
                            <a class="side-link side-sub<?= ($activeNav ?? '') === $childItem['key'] ? ' active' : '' ?>" href="<?= e($childItem['href']) ?>"><?= e($childItem['label']) ?></a>
                        <?php endforeach; ?>
                    </details>
                <?php else: ?>
                    <a class="side-link<?= ($activeNav ?? '') === $item['key'] ? ' active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($navSoon as $label): ?>
                <span class="side-soon"><?= e($label) ?><em>Segera hadir</em></span>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <div class="user-row">
                <span class="avatar" aria-hidden="true"><?= e(strtoupper(substr((string) ($user['name'] ?? 'P'), 0, 1))) ?></span>
                <span class="user-meta">
                    <span class="name"><?= e($user['name'] ?? '') ?></span>
                    <span class="email"><?= e($user['email'] ?? '') ?></span>
                </span>
            </div>
            <form class="logout-form" method="post" action="/logout">
                <?= Auth::csrfField() ?>
                <button class="btn btn-ghost btn-small btn-block" type="submit">Keluar</button>
            </form>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <h1 class="page-title"><?= e($title) ?></h1>
            <span class="role-badge"><?= e($roleLabel) ?></span>
        </header>
        <main class="content">
<?php foreach (($flashes ?? []) as $flash): ?>
    <div class="flash <?= e((string) ($flash['type'] ?? 'info')) ?>"><?= e((string) ($flash['message'] ?? '')) ?></div>
<?php endforeach; ?>
