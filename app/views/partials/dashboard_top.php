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
 * @var string $activeNav  Kunci menu aktif: admin | plans | tenant | account.
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
        ['key' => 'account', 'label' => 'Ganti Kata Sandi', 'href' => '/account/password'],
    ];
    $navSoon = ['Tenant & Lisensi', 'Google Drive', 'Pengaturan Platform'];
} else {
    $navItems = [
        ['key' => 'tenant', 'label' => 'Ringkasan', 'href' => '/tenant'],
        ['key' => 'account', 'label' => 'Ganti Kata Sandi', 'href' => '/account/password'],
    ];
    $navSoon = ['Event', 'Paket', 'Lisensi', 'Midtrans', 'Statistik'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($appName) ?></title>
<style>
    :root {
        --bg: #0f1115;
        --panel: #171a21;
        --panel-2: #1d212b;
        --line: #262b36;
        --text: #eef1f6;
        --muted: #9aa4b2;
        --accent: #d4af37;
        --ok-bg: #14301f;
        --ok-line: #2e7d4f;
        --err-bg: #3a1b1b;
        --err-line: #a94442;
        --info-bg: #1b2a3a;
        --info-line: #3d6ea5;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.55;
    }
    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }

    .topbar {
        display: flex; align-items: center; justify-content: space-between;
        gap: 16px; flex-wrap: wrap;
        padding: 14px 24px;
        background: var(--panel);
        border-bottom: 1px solid var(--line);
    }
    .brand { font-size: 19px; font-weight: 700; color: var(--text); }
    .brand .accent { color: var(--accent); }
    .who { display: flex; align-items: center; gap: 14px; font-size: 14px; color: var(--muted); }
    .who .name { color: var(--text); font-weight: 600; }
    .role-badge {
        border: 1px solid var(--accent); color: var(--accent);
        border-radius: 999px; padding: 1px 10px; font-size: 12px;
        letter-spacing: 0.04em; text-transform: uppercase;
    }
    .logout-form { display: inline; }
    .btn {
        display: inline-block; cursor: pointer;
        background: var(--panel-2); color: var(--text);
        border: 1px solid var(--line); border-radius: 10px;
        padding: 8px 16px; font-size: 14px; font-family: inherit;
    }
    .btn:hover { border-color: var(--accent); text-decoration: none; }
    .btn-primary { background: var(--accent); border-color: var(--accent); color: #171207; font-weight: 600; }
    .btn-danger { border-color: var(--err-line); color: #f0b3b1; }
    .btn-small { padding: 5px 12px; font-size: 13px; border-radius: 8px; }

    .navbar {
        display: flex; gap: 6px; flex-wrap: wrap;
        padding: 10px 24px;
        background: var(--panel);
        border-bottom: 1px solid var(--line);
    }
    .navbar a, .navbar .soon {
        padding: 6px 14px; border-radius: 999px; font-size: 14px; color: var(--muted);
    }
    .navbar a:hover { color: var(--text); text-decoration: none; }
    .navbar a.active { background: var(--panel-2); color: var(--accent); border: 1px solid var(--line); }
    .navbar .soon { opacity: 0.55; cursor: default; }
    .navbar .soon em { font-style: normal; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; }

    .content { max-width: 1080px; margin: 0 auto; padding: 28px 20px 48px; }
    .content h1 { font-size: 26px; margin-bottom: 6px; }
    .content .sub { color: var(--muted); margin-bottom: 22px; }

    .flash { border-radius: 10px; padding: 12px 16px; margin-bottom: 14px; font-size: 15px; border: 1px solid var(--line); background: var(--panel-2); }
    .flash.success { background: var(--ok-bg); border-color: var(--ok-line); }
    .flash.error { background: var(--err-bg); border-color: var(--err-line); }
    .flash.info { background: var(--info-bg); border-color: var(--info-line); }

    .notice {
        border: 1px dashed var(--err-line); background: var(--err-bg);
        border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; font-size: 15px;
    }

    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 26px; }
    .stat {
        background: var(--panel); border: 1px solid var(--line);
        border-radius: 14px; padding: 18px;
    }
    .stat .num { font-size: 30px; font-weight: 700; color: var(--accent); }
    .stat .lbl { color: var(--muted); font-size: 14px; }

    .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
    .card {
        background: var(--panel); border: 1px solid var(--line);
        border-radius: 14px; padding: 20px;
    }
    .card h2 { font-size: 17px; margin-bottom: 8px; }
    .card p { color: var(--muted); font-size: 14px; }
    .card.soon { opacity: 0.6; }
    .soon-tag {
        display: inline-block; font-size: 11px; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--accent);
        border: 1px solid var(--accent); border-radius: 999px; padding: 1px 9px;
        margin-bottom: 10px;
    }

    table.list { width: 100%; border-collapse: collapse; background: var(--panel); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
    table.list th, table.list td { padding: 11px 14px; text-align: left; font-size: 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
    table.list th { color: var(--muted); font-weight: 600; background: var(--panel-2); }
    table.list tr:last-child td { border-bottom: none; }
    .status-on { color: #7bd79b; font-weight: 600; }
    .status-off { color: #f0b3b1; font-weight: 600; }
    .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .row-actions form { display: inline; }

    form .field { margin-bottom: 16px; }
    form label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    form .hint { font-size: 13px; color: var(--muted); font-weight: 400; }
    form input[type="text"], form input[type="email"], form input[type="password"],
    form input[type="number"], form textarea {
        width: 100%; padding: 10px 12px; font-size: 15px; font-family: inherit;
        background: var(--panel); color: var(--text);
        border: 1px solid var(--line); border-radius: 10px;
    }
    form textarea { min-height: 72px; resize: vertical; }
    form input:focus, form textarea:focus { outline: 2px solid var(--accent); outline-offset: 0; border-color: var(--accent); }
    .field-error { color: #f0b3b1; font-size: 13px; margin-top: 5px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; }
    .check { display: flex; align-items: center; gap: 9px; font-size: 15px; margin-bottom: 10px; }
    .check input { width: 17px; height: 17px; }
    fieldset { border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px 6px; margin-bottom: 16px; }
    legend { font-size: 14px; font-weight: 600; padding: 0 6px; }
    .form-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
    .panel-box { background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 22px; }
    dl.profile { display: grid; grid-template-columns: 170px 1fr; gap: 8px 16px; font-size: 15px; }
    dl.profile dt { color: var(--muted); }
    dl.profile dd { color: var(--text); }

    footer.dash {
        border-top: 1px solid var(--line); color: var(--muted);
        font-size: 13px; text-align: center; padding: 20px 16px 30px;
    }

    @media (max-width: 860px) {
        .stats { grid-template-columns: repeat(2, 1fr); }
        .cards { grid-template-columns: 1fr; }
        .form-grid { grid-template-columns: 1fr; }
        table.list { display: block; overflow-x: auto; }
        dl.profile { grid-template-columns: 1fr; gap: 2px 0; }
        dl.profile dd { margin-bottom: 8px; }
    }
</style>
</head>
<body>
<header class="topbar">
    <div class="brand">Plugin <span class="accent">Eventbooth</span></div>
    <div class="who">
        <span class="role-badge"><?= e($roleLabel) ?></span>
        <span><span class="name"><?= e($user['name'] ?? '') ?></span> · <?= e($user['email'] ?? '') ?></span>
        <form class="logout-form" method="post" action="/logout">
            <?= Auth::csrfField() ?>
            <button class="btn btn-small" type="submit">Keluar</button>
        </form>
    </div>
</header>
<nav class="navbar">
    <?php foreach ($navItems as $item): ?>
        <a href="<?= e($item['href']) ?>"<?= ($activeNav ?? '') === $item['key'] ? ' class="active"' : '' ?>><?= e($item['label']) ?></a>
    <?php endforeach; ?>
    <?php foreach ($navSoon as $label): ?>
        <span class="soon"><?= e($label) ?> · <em>segera hadir</em></span>
    <?php endforeach; ?>
</nav>
<main class="content">
<?php foreach (($flashes ?? []) as $flash): ?>
    <div class="flash <?= e((string) ($flash['type'] ?? 'info')) ?>"><?= e((string) ($flash['message'] ?? '')) ?></div>
<?php endforeach; ?>
