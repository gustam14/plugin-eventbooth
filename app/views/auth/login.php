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
<title>Masuk · <?= e($appName) ?></title>
<style>
    :root {
        --bg: #0f1115; --panel: #171a21; --line: #262b36;
        --text: #eef1f6; --muted: #9aa4b2; --accent: #d4af37;
        --err-bg: #3a1b1b; --err-line: #a94442;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: var(--bg); color: var(--text); line-height: 1.55;
        min-height: 100vh; display: flex; align-items: center; justify-content: center;
        padding: 24px 16px;
    }
    .box {
        width: 100%; max-width: 400px;
        background: var(--panel); border: 1px solid var(--line);
        border-radius: 16px; padding: 30px 28px;
    }
    .brand { text-align: center; font-size: 22px; font-weight: 700; margin-bottom: 4px; }
    .brand .accent { color: var(--accent); }
    .sub { text-align: center; color: var(--muted); font-size: 14px; margin-bottom: 22px; }
    .flash { border-radius: 10px; padding: 11px 14px; margin-bottom: 14px; font-size: 14px; border: 1px solid var(--err-line); background: var(--err-bg); }
    .flash.success { background: #14301f; border-color: #2e7d4f; }
    .flash.info { background: #1b2a3a; border-color: #3d6ea5; }
    .notice {
        border: 1px dashed var(--err-line); background: var(--err-bg);
        border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; font-size: 14px;
    }
    .field { margin-bottom: 16px; }
    label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    input[type="email"], input[type="password"] {
        width: 100%; padding: 11px 12px; font-size: 15px;
        background: var(--bg); color: var(--text);
        border: 1px solid var(--line); border-radius: 10px;
    }
    input:focus { outline: 2px solid var(--accent); border-color: var(--accent); }
    button {
        width: 100%; padding: 12px; font-size: 15px; font-weight: 600; cursor: pointer;
        background: var(--accent); color: #171207;
        border: 1px solid var(--accent); border-radius: 10px;
    }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    .back { text-align: center; margin-top: 18px; font-size: 14px; }
    .back a { color: var(--muted); text-decoration: none; }
    .back a:hover { color: var(--accent); }
</style>
</head>
<body>
<main class="box">
    <div class="brand">Plugin <span class="accent">Eventbooth</span></div>
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
                   autocomplete="username"
                   value="<?= e($oldEmail ?? '') ?>"
                   <?= ($dbReady ?? false) ? '' : 'disabled' ?>>
        </div>
        <div class="field">
            <label for="password">Kata Sandi</label>
            <input type="password" id="password" name="password" required
                   autocomplete="current-password"
                   <?= ($dbReady ?? false) ? '' : 'disabled' ?>>
        </div>
        <button type="submit" <?= ($dbReady ?? false) ? '' : 'disabled' ?>>Masuk</button>
    </form>

    <p class="back"><a href="<?= e($appUrl) ?>/">&larr; Kembali ke Beranda</a></p>
</main>
</body>
</html>
