<?php
/** @var string $appName */
/** @var string $appUrl */
/** @var string $version */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($appName) ?> — Pendamping DSLRBooth untuk Self-Booth Mandiri</title>
<meta name="description" content="Plugin Eventbooth: platform pendamping DSLRBooth — manajemen lisensi aplikasi booth, album event online, dan pembayaran sesi mandiri.">
<style>
    :root {
        --bg: #0f1115;
        --panel: #171a21;
        --line: #262b36;
        --text: #eef1f6;
        --muted: #9aa4b2;
        --accent: #d4af37;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.6;
    }
    .wrap { max-width: 960px; margin: 0 auto; padding: 0 20px; }
    header { padding: 72px 0 40px; text-align: center; }
    .badge {
        display: inline-block;
        padding: 4px 14px;
        border: 1px solid var(--accent);
        border-radius: 999px;
        color: var(--accent);
        font-size: 13px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    h1 { font-size: clamp(34px, 6vw, 52px); line-height: 1.15; margin: 22px 0 14px; }
    h1 .accent { color: var(--accent); }
    .lead { color: var(--muted); font-size: clamp(16px, 2.4vw, 19px); max-width: 640px; margin: 0 auto; }
    .cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        padding: 24px 0 8px;
    }
    .card {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 24px 22px;
    }
    .card h2 { font-size: 18px; margin-bottom: 8px; }
    .card p { color: var(--muted); font-size: 15px; }
    section.how { padding: 40px 0 8px; }
    section.how h2 { font-size: 22px; margin-bottom: 10px; text-align: center; }
    section.how p { color: var(--muted); text-align: center; max-width: 680px; margin: 0 auto; }
    footer {
        margin-top: 56px;
        padding: 24px 0 36px;
        border-top: 1px solid var(--line);
        color: var(--muted);
        font-size: 14px;
        text-align: center;
    }
    @media (max-width: 720px) {
        .cards { grid-template-columns: 1fr; }
        header { padding-top: 48px; }
    }
</style>
</head>
<body>
<div class="wrap">
    <header>
        <span class="badge">Segera Hadir</span>
        <h1>Plugin <span class="accent">Eventbooth</span></h1>
        <p class="lead">
            Platform pendamping DSLRBooth yang mengubah booth foto menjadi
            self-booth mandiri: tamu membayar dan memulai sesi sendiri,
            hasil foto otomatis tampil di album online.
        </p>
    </header>

    <main>
        <div class="cards">
            <div class="card">
                <h2>Manajemen Lisensi</h2>
                <p>
                    Aktivasi aplikasi booth per perangkat, plan fleksibel,
                    dan perpanjangan langsung dari dashboard. Booth tetap
                    bisa berjalan untuk event tanpa pembayaran tamu,
                    misalnya wedding.
                </p>
            </div>
            <div class="card">
                <h2>Album Event Online</h2>
                <p>
                    Setiap event punya halaman album sendiri. Foto tersimpan
                    aman di Google Drive dan dibagikan ke tamu lewat kode QR
                    dan WhatsApp — tanpa membebani server website.
                </p>
            </div>
            <div class="card">
                <h2>Pembayaran &amp; Timer Sesi</h2>
                <p>
                    Pembayaran QRIS lewat Midtrans dengan paket sesi yang
                    fleksibel: per sesi, per durasi, atau gabungan —
                    mengikuti kebutuhan setiap event.
                </p>
            </div>
        </div>

        <section class="how">
            <h2>Sedang dibangun</h2>
            <p>
                Website ini adalah pusat kendali Plugin Eventbooth:
                lisensi, event, album, dan pembayaran. Akses dashboard
                untuk Super Admin dan tenant akan dibuka bertahap.
            </p>
        </section>
    </main>

    <footer>
        &copy; <?= date('Y') ?> <?= e($appName) ?> &middot; Bagian dari EventBooth
        &middot; <span title="Versi aplikasi">v<?= e($version) ?></span>
    </footer>
</div>
</body>
</html>
