<?php
/** @var string $appName */
/** @var string $appUrl */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0C0F">
<title>404 — Halaman Tidak Ditemukan · <?= e($appName) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230A0C0F'/%3E%3Cg fill='none' stroke='%23C9A24B' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 26h6l4-5h16l4 5h6a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V30a4 4 0 0 1 4-4z'/%3E%3Ccircle cx='32' cy='37' r='7'/%3E%3C/g%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background:
            radial-gradient(620px 400px at 50% -6%, rgba(201, 162, 75, .15), transparent 62%),
            #0A0C0F;
        color: #EDEFF3; line-height: 1.6;
        display: flex; min-height: 100vh; align-items: center; justify-content: center;
        padding: 32px 20px; text-align: center;
        -webkit-font-smoothing: antialiased;
    }
    ::selection { background: rgba(201, 162, 75, .35); color: #fff; }
    :focus-visible { outline: 2px solid #C9A24B; outline-offset: 2px; border-radius: 4px; }
    .logo-mark {
        display: inline-flex; align-items: center; justify-content: center;
        width: 54px; height: 54px; border-radius: 15px; margin-bottom: 26px;
        background: linear-gradient(135deg, #C9A24B, #E7C878);
        box-shadow: 0 10px 24px rgba(201, 162, 75, .32);
    }
    .code {
        font-size: clamp(84px, 16vw, 132px); font-weight: 800; line-height: 1; letter-spacing: -.04em;
        background: linear-gradient(135deg, #C9A24B, #E7C878);
        -webkit-background-clip: text; background-clip: text; color: transparent;
        margin-bottom: 14px;
    }
    h1 { font-size: 22px; font-weight: 800; letter-spacing: -.01em; margin-bottom: 8px; }
    p { color: #9AA3AF; margin-bottom: 28px; }
    a.btn {
        display: inline-flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #C9A24B, #E7C878); color: #1A1405;
        font-weight: 700; font-size: 15px; text-decoration: none;
        padding: 12px 26px; border-radius: 12px;
        box-shadow: 0 10px 26px rgba(201, 162, 75, .25);
        transition: filter 160ms ease, transform 160ms ease;
    }
    a.btn:hover { filter: brightness(1.05); }
    a.btn:active { transform: translateY(1px); }
</style>
</head>
<body>
<main>
    <span class="logo-mark" aria-hidden="true">
        <svg width="29" height="29" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
    </span>
    <div class="code">404</div>
    <h1>Halaman tidak ditemukan</h1>
    <p>Halaman yang kamu cari tidak ditemukan atau sudah dipindahkan.</p>
    <a class="btn" href="<?= e($appUrl) ?>/">Kembali ke Beranda</a>
</main>
</body>
</html>
