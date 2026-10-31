<?php
/**
 * @var string $appName
 * @var string $appUrl
 * @var bool   $dbReady  False bila database belum disetel/di hubungi.
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $oldEmail Email dari percobaan login sebelumnya.
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0C0F">
<title>Masuk · <?= e($appName) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230A0C0F'/%3E%3Cg fill='none' stroke='%23C9A24B' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 26h6l4-5h16l4 5h6a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V30a4 4 0 0 1 4-4z'/%3E%3Ccircle cx='32' cy='37' r='7'/%3E%3C/g%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
<?php require BASE_PATH . '/app/views/partials/app.css.php'; ?>
</style>
</head>
<body class="auth">
<div class="auth-shell">
    <div class="auth-logo">
        <span class="logo-mark lg" aria-hidden="true">
            <svg width="29" height="29" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
        </span>
        <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span></span>
    </div>

    <main class="auth-card">
        <h1 class="card-title">Selamat datang kembali</h1>
        <p class="sub">Masuk ke dashboard Super Admin / tenant.</p>

        <?php foreach (($flashes ?? []) as $flash): ?>
            <div class="flash <?= e((string) ($flash['type'] ?? 'error')) ?>"><?= e((string) ($flash['message'] ?? '')) ?></div>
        <?php endforeach; ?>

        <?php if (!($dbReady ?? false)): ?>
            <div class="notice">
                <strong>Database belum disetel.</strong><br>
                Login belum dapat digunakan. Selesaikan dulu konfigurasi
                <code>.env</code> (kredensial database) dan impor
                <code>database/schema.sql</code> pada server, lalu muat
                ulang halaman ini.
            </div>
        <?php endif; ?>

        <form method="post" action="/login">
            <?= Auth::csrfField() ?>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       autocomplete="username" placeholder="nama@email.com"
                       value="<?= e($oldEmail ?? '') ?>"
                       <?= ($dbReady ?? false) ? '' : 'disabled' ?>>
            </div>
            <div class="field">
                <label for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password" placeholder="Kata sandi kamu"
                       <?= ($dbReady ?? false) ? '' : 'disabled' ?>>
            </div>
            <button class="btn btn-primary btn-block" type="submit" <?= ($dbReady ?? false) ? '' : 'disabled' ?>>Masuk ke Dashboard</button>
        </form>
    </main>

    <p class="auth-foot"><a href="<?= e($appUrl) ?>/">&larr; Kembali ke Beranda</a></p>
    <p class="auth-version"><?= e($appName) ?> · Bagian dari EventBooth</p>
</div>
</body>
</html>
