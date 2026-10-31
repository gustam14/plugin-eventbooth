<?php
/** @var string $appName */
/** @var string $appUrl */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 — Halaman Tidak Ditemukan · <?= e($appName) ?></title>
<style>
    body {
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: #0f1115; color: #eef1f6; line-height: 1.6;
        display: flex; min-height: 100vh; align-items: center; justify-content: center;
        margin: 0; padding: 20px; text-align: center;
    }
    h1 { font-size: 44px; margin-bottom: 8px; }
    p { color: #9aa4b2; margin-bottom: 20px; }
    a { color: #d4af37; text-decoration: none; border: 1px solid #d4af37; padding: 8px 18px; border-radius: 999px; }
    a:hover { text-decoration: underline; }
</style>
</head>
<body>
<main>
    <h1>404</h1>
    <p>Halaman yang kamu cari tidak ditemukan.</p>
    <a href="<?= e($appUrl) ?>/">Kembali ke Beranda</a>
</main>
</body>
</html>
