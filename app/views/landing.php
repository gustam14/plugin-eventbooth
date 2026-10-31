<?php
/** @var string $appName */
/** @var string $appUrl */
/** @var string $version */
/** @var array<int, array<string, mixed>> $plans Plan aktif dari dashboard; kosong = tampilkan paket statis contoh. */
$plans = $plans ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0C0F">
<title><?= e($appName) ?> — Pendamping DSLRBooth untuk Self-Booth Mandiri</title>
<meta name="description" content="Plugin Eventbooth: platform pendamping DSLRBooth — manajemen lisensi aplikasi booth, album event online, dan pembayaran sesi mandiri.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230A0C0F'/%3E%3Cg fill='none' stroke='%23C9A24B' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 26h6l4-5h16l4 5h6a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V30a4 4 0 0 1 4-4z'/%3E%3Ccircle cx='32' cy='37' r='7'/%3E%3C/g%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --bg: #0A0C0F;
        --panel: #12151B;
        --panel-2: #171B23;
        --line: rgba(255, 255, 255, .08);
        --line-strong: rgba(255, 255, 255, .14);
        --text: #EDEFF3;
        --muted: #9AA3AF;
        --muted-2: #6E7684;
        --gold: #C9A24B;
        --gold-2: #E7C878;
        --gold-grad: linear-gradient(135deg, #C9A24B 0%, #E7C878 100%);
        --gold-ink: #1A1405;
        --gold-soft: rgba(201, 162, 75, .12);
        --success: #34C77B;
        --font: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
        font-family: var(--font);
        background: var(--bg);
        color: var(--text);
        line-height: 1.65;
        font-size: 16px;
        -webkit-font-smoothing: antialiased;
        overflow-x: clip;
    }
    img, svg { max-width: 100%; }
    ::selection { background: rgba(201, 162, 75, .35); color: #fff; }
    :focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 4px; }
    a { color: var(--gold-2); text-decoration: none; }
    .wrap { max-width: 1160px; margin: 0 auto; padding: 0 24px; }

    /* ---------- Tombol ---------- */
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        font-size: 15px; font-weight: 700; line-height: 1.3;
        padding: 13px 26px; border-radius: 12px; border: 1px solid transparent;
        transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease, background 160ms ease, filter 160ms ease;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary {
        background: var(--gold-grad); color: var(--gold-ink);
        box-shadow: 0 10px 28px rgba(201, 162, 75, .25);
    }
    .btn-primary:hover { filter: brightness(1.05); box-shadow: 0 14px 34px rgba(201, 162, 75, .34); color: var(--gold-ink); }
    .btn-outline { background: rgba(255, 255, 255, .02); border-color: var(--line-strong); color: var(--text); }
    .btn-outline:hover { border-color: rgba(201, 162, 75, .55); color: var(--text); }
    .btn-sm { padding: 9px 20px; font-size: 14px; border-radius: 10px; }

    /* ---------- Kepala situs ---------- */
    .site-head {
        position: sticky; top: 0; z-index: 50;
        background: rgba(10, 12, 15, .82);
        -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
        border-bottom: 1px solid var(--line);
    }
    .site-head .wrap { display: flex; align-items: center; justify-content: space-between; gap: 20px; height: 68px; }
    .brandmark { display: flex; align-items: center; gap: 11px; }
    .logo-mark {
        flex: none; display: inline-flex; align-items: center; justify-content: center;
        width: 38px; height: 38px; border-radius: 10px;
        background: var(--gold-grad); box-shadow: 0 8px 20px rgba(201, 162, 75, .30);
    }
    .brandmark-text { font-size: 16.5px; font-weight: 800; letter-spacing: -.01em; color: var(--text); line-height: 1.15; }
    .brandmark-text .accent { background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .brandmark-sub { display: block; font-size: 10.5px; font-weight: 600; color: var(--muted-2); letter-spacing: .06em; text-transform: uppercase; }
    .site-nav { display: flex; align-items: center; gap: 30px; }
    .site-nav a.nav-link { color: var(--muted); font-size: 14.5px; font-weight: 600; transition: color 150ms ease; }
    .site-nav a.nav-link:hover { color: var(--text); }

    /* ---------- Hero ---------- */
    .hero {
        position: relative;
        padding: 84px 0 72px;
        background:
            radial-gradient(760px 460px at 82% -10%, rgba(201, 162, 75, .15), transparent 62%),
            radial-gradient(560px 380px at 4% 12%, rgba(94, 168, 232, .07), transparent 60%);
    }
    .hero-grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 56px; align-items: center; }
    .eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 14px; border: 1px solid rgba(201, 162, 75, .42); border-radius: 999px;
        background: var(--gold-soft); color: var(--gold-2);
        font-size: 12.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    }
    .eyebrow::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--gold-2); }
    .hero h1 {
        font-size: clamp(36px, 4.6vw, 54px); font-weight: 800;
        line-height: 1.12; letter-spacing: -.025em; margin: 22px 0 18px;
    }
    .hero h1 .accent { background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .hero .lead { color: var(--muted); font-size: clamp(16px, 1.6vw, 18.5px); max-width: 540px; }
    .hero-cta { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 30px; }
    .hero-note { margin-top: 16px; font-size: 13.5px; color: var(--muted-2); }

    /* ---------- Mock sesi photobooth (murni CSS/SVG) ---------- */
    .booth-card {
        background: linear-gradient(180deg, var(--panel-2), var(--panel));
        border: 1px solid var(--line-strong); border-radius: 22px;
        padding: 22px; box-shadow: 0 30px 70px rgba(0, 0, 0, .55);
        transform: rotate(1.2deg);
    }
    .booth-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
    .booth-title { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 700; }
    .booth-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--success); box-shadow: 0 0 0 4px rgba(52, 199, 123, .16); }
    .pill {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12px; font-weight: 700; border-radius: 999px; padding: 4px 12px; white-space: nowrap;
    }
    .pill-ok { color: var(--success); background: rgba(52, 199, 123, .12); border: 1px solid rgba(52, 199, 123, .38); }
    .booth-stage {
        display: flex; align-items: center; gap: 18px;
        background: rgba(255, 255, 255, .03); border: 1px solid var(--line);
        border-radius: 16px; padding: 16px 18px; margin-bottom: 16px;
    }
    .ring { position: relative; flex: none; width: 74px; height: 74px; }
    .ring svg { transform: rotate(-90deg); }
    .ring .ring-num {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        font-size: 26px; font-weight: 800; letter-spacing: -.02em;
    }
    .booth-stage .stage-text strong { display: block; font-size: 15px; }
    .booth-stage .stage-text span { font-size: 13px; color: var(--muted); }
    .photo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
    .photo {
        position: relative; aspect-ratio: 4 / 3; border-radius: 12px; overflow: hidden;
        border: 1px solid var(--line);
    }
    .photo::after {
        content: attr(data-label); position: absolute; left: 10px; bottom: 8px;
        font-size: 11px; font-weight: 700; letter-spacing: .05em; color: rgba(255, 255, 255, .85);
        text-shadow: 0 1px 6px rgba(0, 0, 0, .45);
    }
    .ph-1 { background: linear-gradient(140deg, #3D4E6B, #8FA8C8 55%, #D8C49A); }
    .ph-2 { background: linear-gradient(140deg, #6B3D54, #C88FA8 55%, #E8D8B0); }
    .ph-3 { background: linear-gradient(140deg, #2F5D50, #7FBfa8 55%, #D8D0A0); }
    .ph-4 { background: linear-gradient(140deg, #54432A, #B08D57 55%, #EFE0BC); }
    .booth-foot { display: flex; align-items: center; gap: 14px; }
    .qr-tile {
        flex: none; width: 62px; height: 62px; border-radius: 10px;
        background: #F4F1E8; display: flex; align-items: center; justify-content: center;
    }
    .booth-foot .foot-text strong { display: block; font-size: 14px; }
    .booth-foot .foot-text span { font-size: 12.5px; color: var(--muted); }

    /* ---------- Strip kepercayaan ---------- */
    .trust { border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); background: rgba(255, 255, 255, .015); }
    .trust .wrap {
        display: flex; align-items: center; justify-content: center; flex-wrap: wrap;
        gap: 12px 34px; padding-top: 20px; padding-bottom: 20px;
    }
    .trust span { font-size: 13.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); display: inline-flex; align-items: center; gap: 10px; }
    .trust span::before { content: ""; width: 5px; height: 5px; border-radius: 50%; background: var(--gold); }

    /* ---------- Bagian umum ---------- */
    section.block { padding: 84px 0; }
    .sec-head { text-align: center; max-width: 640px; margin: 0 auto 48px; }
    .sec-head .kicker {
        font-size: 12.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gold-2);
    }
    .sec-head h2 { font-size: clamp(27px, 3.4vw, 36px); font-weight: 800; letter-spacing: -.02em; line-height: 1.2; margin: 10px 0 12px; }
    .sec-head p { color: var(--muted); font-size: 16px; }

    .features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .feature {
        background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 26px 24px;
        transition: border-color 180ms ease, transform 180ms ease;
    }
    .feature:hover { border-color: rgba(201, 162, 75, .4); transform: translateY(-3px); }
    .feature .icon {
        width: 44px; height: 44px; border-radius: 12px; margin-bottom: 16px;
        display: flex; align-items: center; justify-content: center;
        background: var(--gold-soft); color: var(--gold-2);
    }
    .feature h3 { font-size: 17px; font-weight: 700; letter-spacing: -.01em; margin-bottom: 8px; }
    .feature p { color: var(--muted); font-size: 14.5px; }

    .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; counter-reset: step; }
    .step { position: relative; background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 28px 24px; }
    .step .step-num {
        font-size: 44px; font-weight: 800; line-height: 1; letter-spacing: -.03em;
        color: transparent; -webkit-text-stroke: 1.5px rgba(201, 162, 75, .55);
        margin-bottom: 16px;
    }
    .step h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
    .step p { color: var(--muted); font-size: 14.5px; }

    /* ---------- Paket ---------- */
    #paket { background: rgba(255, 255, 255, .015); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
    .pricing { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; align-items: stretch; }
    .price-card {
        position: relative; display: flex; flex-direction: column;
        background: var(--panel); border: 1px solid var(--line); border-radius: 20px; padding: 30px 26px;
    }
    .price-card.featured { border-color: rgba(201, 162, 75, .6); background: linear-gradient(180deg, rgba(201, 162, 75, .07), var(--panel) 55%); box-shadow: 0 22px 50px rgba(0, 0, 0, .4); }
    .price-badge {
        position: absolute; top: -13px; left: 50%; transform: translateX(-50%);
        background: var(--gold-grad); color: var(--gold-ink);
        font-size: 11.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
        border-radius: 999px; padding: 4px 14px; white-space: nowrap;
    }
    .price-card .plan-name { font-size: 15px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); }
    .price-card.featured .plan-name { color: var(--gold-2); }
    .price { display: flex; align-items: baseline; gap: 6px; margin: 14px 0 4px; }
    .price .amount { font-size: 38px; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
    .price .per { color: var(--muted); font-size: 14px; }
    .price-card .plan-desc { color: var(--muted); font-size: 14px; margin-bottom: 20px; }
    .price-card ul { list-style: none; display: flex; flex-direction: column; gap: 10px; margin-bottom: 26px; }
    .price-card li { display: flex; gap: 10px; font-size: 14.5px; color: var(--text); }
    .price-card li svg { flex: none; margin-top: 3px; color: var(--gold-2); }
    .price-card .btn { margin-top: auto; }
    .pricing-note { text-align: center; color: var(--muted-2); font-size: 13px; margin-top: 22px; }

    /* ---------- CTA & kaki ---------- */
    .cta-band {
        border-radius: 22px; padding: 46px 40px; text-align: center;
        background: linear-gradient(140deg, rgba(201, 162, 75, .16), rgba(201, 162, 75, .05) 55%, var(--panel));
        border: 1px solid rgba(201, 162, 75, .35);
    }
    .cta-band h2 { font-size: clamp(24px, 3vw, 32px); font-weight: 800; letter-spacing: -.02em; margin-bottom: 10px; }
    .cta-band p { color: var(--muted); max-width: 520px; margin: 0 auto 24px; }
    footer.site-foot { border-top: 1px solid var(--line); margin-top: 84px; }
    footer.site-foot .wrap {
        display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        padding-top: 26px; padding-bottom: 30px;
    }
    footer.site-foot p { color: var(--muted-2); font-size: 13.5px; }

    @media (max-width: 1024px) {
        .hero-grid { grid-template-columns: 1fr; gap: 44px; }
        .hero .lead { max-width: 640px; }
        .booth-card { width: 100%; max-width: 520px; transform: none; }
        .features, .steps, .pricing { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 768px) {
        .site-nav a.nav-link { display: none; }
        .hero { padding: 56px 0 52px; }
        section.block { padding: 60px 0; }
        .features, .steps, .pricing { grid-template-columns: 1fr; }
        .price-card .btn { width: 100%; }
        .cta-band { padding: 36px 24px; }
        footer.site-foot .wrap { justify-content: center; text-align: center; }
    }
    @media (max-width: 520px) {
        .wrap { padding: 0 16px; }
        .site-head .wrap { height: 60px; }
        .hero { padding: 44px 0 40px; }
        .hero h1 { font-size: clamp(29px, 8.8vw, 36px); }
        .hero-cta .btn { flex: 1 1 100%; }
        .eyebrow { font-size: 11px; padding: 5px 11px; }
        section.block { padding: 48px 0; }
        .sec-head { margin-bottom: 36px; }
        .trust .wrap { gap: 10px 22px; padding-top: 16px; padding-bottom: 16px; }
        .booth-card { padding: 16px; border-radius: 18px; }
        .booth-stage { gap: 12px; padding: 14px; }
        .booth-foot .foot-text span { font-size: 12px; }
        .price-card { padding: 26px 20px; }
        .price .amount { font-size: 32px; }
        .cta-band { padding: 32px 20px; border-radius: 18px; }
        footer.site-foot { margin-top: 60px; }
    }
</style>
</head>
<body>

<header class="site-head">
    <div class="wrap">
        <div class="brandmark">
            <span class="logo-mark" aria-hidden="true">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
            </span>
            <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span><span class="brandmark-sub">by EventBooth</span></span>
        </div>
        <nav class="site-nav" aria-label="Navigasi utama">
            <a class="nav-link" href="#fitur">Fitur</a>
            <a class="nav-link" href="#cara-kerja">Cara Kerja</a>
            <a class="nav-link" href="#paket">Paket</a>
            <a class="btn btn-primary btn-sm" href="/login">Masuk</a>
        </nav>
    </div>
</header>

<main>
    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow">Pendamping DSLRBooth untuk Self-Booth Mandiri</span>
                <h1>Booth foto yang <span class="accent">bekerja sendiri</span>, album yang tersusun rapi.</h1>
                <p class="lead">
                    Plugin Eventbooth menghubungkan DSLRBooth dengan lisensi, pembayaran,
                    dan album online. Tamu membayar, berfoto, dan menerima hasilnya —
                    kamu cukup memantau semuanya dari satu dashboard.
                </p>
                <div class="hero-cta">
                    <a class="btn btn-primary" href="/login">Masuk Dashboard</a>
                    <a class="btn btn-outline" href="#fitur">Lihat Fitur</a>
                </div>
                <p class="hero-note">Mendukung DSLRBooth v7 &amp; v8 · Tanpa mengubah alur DSLRBooth yang sudah kamu kenal.</p>
            </div>

            <div class="booth-card" aria-hidden="true">
                <div class="booth-head">
                    <span class="booth-title"><span class="booth-dot"></span> Sesi Foto · Paket Print</span>
                    <span class="pill pill-ok">Sesi Selesai</span>
                </div>
                <div class="booth-stage">
                    <div class="ring">
                        <svg width="74" height="74" viewBox="0 0 74 74">
                            <circle cx="37" cy="37" r="32" fill="none" stroke="rgba(255,255,255,.09)" stroke-width="6"/>
                            <circle cx="37" cy="37" r="32" fill="none" stroke="#C9A24B" stroke-width="6" stroke-linecap="round" stroke-dasharray="201" stroke-dashoffset="52"/>
                        </svg>
                        <span class="ring-num">3</span>
                    </div>
                    <div class="stage-text">
                        <strong>Hitung mundur sesi</strong>
                        <span>Template 2×2 · 4 pose · Cetak + file digital</span>
                    </div>
                </div>
                <div class="photo-grid">
                    <div class="photo ph-1" data-label="POSE 1"></div>
                    <div class="photo ph-2" data-label="POSE 2"></div>
                    <div class="photo ph-3" data-label="POSE 3"></div>
                    <div class="photo ph-4" data-label="POSE 4"></div>
                </div>
                <div class="booth-foot">
                    <span class="qr-tile">
                        <svg width="44" height="44" viewBox="0 0 44 44" fill="#14161A"><rect x="2" y="2" width="12" height="12" rx="2" fill="none" stroke="#14161A" stroke-width="3"/><rect x="7" y="7" width="2.5" height="2.5"/><rect x="30" y="2" width="12" height="12" rx="2" fill="none" stroke="#14161A" stroke-width="3"/><rect x="35" y="7" width="2.5" height="2.5"/><rect x="2" y="30" width="12" height="12" rx="2" fill="none" stroke="#14161A" stroke-width="3"/><rect x="7" y="35" width="2.5" height="2.5"/><rect x="20" y="4" width="4" height="4"/><rect x="26" y="10" width="4" height="4"/><rect x="20" y="18" width="4" height="4"/><rect x="4" y="20" width="4" height="4"/><rect x="12" y="24" width="4" height="4"/><rect x="22" y="26" width="4" height="4"/><rect x="30" y="20" width="4" height="4"/><rect x="36" y="26" width="4" height="4"/><rect x="28" y="34" width="4" height="4"/><rect x="36" y="36" width="5" height="5"/></svg>
                    </span>
                    <div class="foot-text">
                        <strong>Album sesi otomatis terbit</strong>
                        <span>Pindai QR atau terima via WhatsApp — foto langsung tampil di album online.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="trust">
        <div class="wrap">
            <span>DSLRBooth v7 / v8</span>
            <span>Midtrans · QRIS</span>
            <span>Album Online</span>
            <span>WhatsApp</span>
        </div>
    </div>

    <section class="block" id="fitur">
        <div class="wrap">
            <div class="sec-head">
                <span class="kicker">Fitur Utama</span>
                <h2>Semua kebutuhan booth mandiri, dalam satu platform</h2>
                <p>Website ini adalah pusat kendali Plugin Eventbooth: lisensi aplikasi, event, album, dan pembayaran sesi.</p>
            </div>
            <div class="features">
                <article class="feature">
                    <span class="icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4.2"/><path d="M11.2 11.8 20 3M16.5 6.5l2.8 2.8M13.8 9.2l2.3 2.3"/></svg></span>
                    <h3>Manajemen Lisensi</h3>
                    <p>Aktivasi aplikasi booth per perangkat dengan plan yang diatur dari dashboard Super Admin — harga, periode, batas perangkat, dan fitur bisa diubah tanpa rilis ulang.</p>
                </article>
                <article class="feature">
                    <span class="icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.7"/><path d="M4.5 18.5 10 13.5l3.5 3 2.5-2.2 3.5 3.2"/></svg></span>
                    <h3>Album Event Online</h3>
                    <p>Setiap event memiliki halaman album sendiri. Tamu membuka hasil fotonya dari tautan atau kode QR — rapi per sesi, tanpa perlu mencari-cari file.</p>
                </article>
                <article class="feature">
                    <span class="icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><path d="M14 14h3v3h-3zM20.5 17.5v3h-3M17.5 20.5h.01"/></svg></span>
                    <h3>Pembayaran QRIS / Midtrans</h3>
                    <p>Tamu membayar paket sesi lewat QRIS Midtrans dan sesi dimulai otomatis setelah pembayaran terverifikasi. Paket fleksibel: per sesi, per durasi, atau gabungan.</p>
                </article>
                <article class="feature">
                    <span class="icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 3.5h10A2.3 2.3 0 0 1 20.8 5.8v9.7"/><rect x="3.2" y="7.2" width="14" height="13.3" rx="2.5"/><circle cx="7.4" cy="11.2" r="1.4"/><path d="M4.8 18.6 8.6 15.1l2.7 2.4 2-1.7 2.9 2.5"/></svg></span>
                    <h3>Upload Otomatis</h3>
                    <p>Hasil foto otomatis tampil di album online per event. Tamu cukup memindai kode QR untuk melihat dan mengunduh fotonya — tanpa perlu memasang aplikasi.</p>
                </article>
                <article class="feature">
                    <span class="icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10" width="15" height="10" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 7.8-1.3"/><circle cx="12" cy="15" r="1.8"/></svg></span>
                    <h3>Kunci Payment Fleksibel</h3>
                    <p>Untuk event seperti wedding, kunci pembayaran bisa dimatikan dari dashboard: booth langsung berjalan tanpa pembayaran tamu, dengan batas jam event atau kuota prabayar.</p>
                </article>
                <article class="feature">
                    <span class="icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a8 8 0 0 1-11.6 7.1L4 20l.9-4.3A8 8 0 1 1 20 12z"/><path d="M8.8 9.2c.4 2.3 3.7 5.6 6 6l1.3-1.3 2.4 1.2c-.3 1.5-1.4 2.3-2.8 2.1-3.9-.5-8.4-5-8.9-8.9-.2-1.4.6-2.5 2.1-2.8L10.1 8 8.8 9.2z" stroke-width="1.3"/></svg></span>
                    <h3>Bagikan via WhatsApp</h3>
                    <p>Selain kode QR di lokasi, tautan album sesi dapat dikirim ke nomor WhatsApp tamu — hasil foto sampai tanpa antre di booth.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="block" id="cara-kerja" style="padding-top: 0;">
        <div class="wrap">
            <div class="sec-head">
                <span class="kicker">Cara Kerja</span>
                <h2>Dari aktivasi sampai tamu menerima foto</h2>
                <p>Tiga langkah sederhana — aplikasi di PC booth menangani sesi, website menangani kendali dan album.</p>
            </div>
            <div class="steps">
                <article class="step">
                    <div class="step-num">01</div>
                    <h3>Aktifkan Lisensi</h3>
                    <p>Pasang aplikasi pendamping di PC booth, masukkan license key, dan hubungkan ke DSLRBooth. Pilih paket dan atur timer dari dashboard tenant.</p>
                </article>
                <article class="step">
                    <div class="step-num">02</div>
                    <h3>Tamu Berfoto Mandiri</h3>
                    <p>Tamu memilih paket, membayar via QRIS bila kunci payment aktif, lalu sesi berjalan dengan hitung mundur di DSLRBooth seperti biasa.</p>
                </article>
                <article class="step">
                    <div class="step-num">03</div>
                    <h3>Foto Terbit di Album</h3>
                    <p>Hasil sesi terunggah otomatis dan langsung muncul di album event online. Tamu menerimanya lewat kode QR atau WhatsApp.</p>
                </article>
            </div>
        </div>
    </section>

    <?php if ($plans !== []): ?>
    <?php
    // Bagian paket DINAMIS — dirender dari plan aktif yang diatur
    // Super Admin. Teks publik hanya bicara soal album online.
    $checkSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>';

    // Harga ringkas gaya tampilan statis: Rp149rb / Rp349rb / Rp0.
    $fmtPublicPrice = static function (float $price): string {
        if ($price >= 1000 && fmod($price, 1000.0) === 0.0) {
            return 'Rp' . number_format($price / 1000, 0, ',', '.') . 'rb';
        }
        return 'Rp' . number_format($price, 0, ',', '.');
    };

    // Pemetaan jumlah hari -> label periode tampilan.
    $periodLabel = static function (int $days): string {
        return match ($days) {
            365, 360 => '/ tahun',
            180 => '/ 6 bulan',
            90 => '/ 3 bulan',
            30 => '/ bulan',
            7 => '/ minggu',
            1 => '/ hari',
            default => '/ ' . number_format($days, 0, ',', '.') . ' hari',
        };
    };

    // Akhiran periode di samping harga mengikuti MASA AKTIF LISENSI
    // (yang dibeli pelanggan adalah waktu lisensi), bukan periode
    // tagihan. Masa aktif 0 -> pakai periode tagihan dengan pemetaan
    // yang sama; keduanya 0 -> tanpa kedaluwarsa.
    $publicSuffix = static function (array $plan) use ($periodLabel): string {
        $duration = (int) ($plan['license_duration_days'] ?? 0);
        if ($duration > 0) {
            return $periodLabel($duration);
        }
        $billing = (int) ($plan['billing_period_days'] ?? 0);
        if ($billing > 0) {
            return $periodLabel($billing);
        }
        return '/ tanpa kedaluwarsa';
    };

    $publicDesc = static function (array $plan): string {
        $desc = trim((string) ($plan['description'] ?? ''));
        // Deskripsi bawaan seed bersifat catatan internal ("Nilai
        // contoh — ubah dari dashboard...") sehingga tidak layak
        // tampil publik; pakai kalimat umum sampai Super Admin
        // menulis deskripsi publiknya sendiri.
        if ($desc !== '' && stripos($desc, 'nilai contoh') === false) {
            return $desc;
        }
        return (float) ($plan['price'] ?? 0) > 0
            ? 'Untuk booth reguler dengan pembayaran mandiri.'
            : 'Untuk mencoba alur self-booth di event pertamamu.';
    };

    /** @return array<int, string> */
    $publicBullets = static function (array $plan): array {
        $bullets = [];

        $maxDevices = (int) ($plan['max_devices'] ?? 0);
        $bullets[] = $maxDevices > 0
            ? number_format($maxDevices, 0, ',', '.') . ' perangkat PC booth'
            : 'Perangkat PC booth tanpa batas';

        $maxEvents = (int) ($plan['max_events'] ?? 0);
        $bullets[] = $maxEvents > 0
            ? 'Maks. ' . number_format($maxEvents, 0, ',', '.') . ' event aktif'
            : 'Event tanpa batas';

        $retention = (int) ($plan['retention_days'] ?? 0);
        $bullets[] = $retention > 0
            ? 'Retensi album hingga ' . number_format($retention, 0, ',', '.') . ' hari'
            : 'Retensi album tanpa batas';

        $sessionQuota = (int) ($plan['session_quota'] ?? 0);
        if ($sessionQuota > 0) {
            $bullets[] = 'Kuota ' . number_format($sessionQuota, 0, ',', '.') . ' sesi per periode';
        }

        $albumQuotaGb = (int) ($plan['drive_quota_gb'] ?? 0);
        if ($albumQuotaGb > 0) {
            $bullets[] = 'Kuota album ' . number_format($albumQuotaGb, 0, ',', '.') . ' GB';
        }

        $features = Plan::featuresOf($plan);
        if (!empty($features['payment_lock'])) {
            $bullets[] = 'Kunci pembayaran QRIS';
        }
        if (!empty($features['whatsapp_share'])) {
            $bullets[] = 'Berbagi via WhatsApp';
        }

        return array_slice($bullets, 0, 6);
    };

    // Sorotan emas: plan aktif berharga tertinggi (> 0).
    $featuredPlanId = null;
    $highestPrice = 0.0;
    foreach ($plans as $planRow) {
        $rowPrice = (float) ($planRow['price'] ?? 0);
        if ($rowPrice > $highestPrice) {
            $highestPrice = $rowPrice;
            $featuredPlanId = (int) ($planRow['id'] ?? 0);
        }
    }
    ?>
    <section class="block" id="paket">
        <div class="wrap">
            <div class="sec-head">
                <span class="kicker">Paket Lisensi</span>
                <h2>Pilih plan sesuai skala booth kamu</h2>
                <p>Plan dan harga resmi yang berlaku saat ini, dikelola langsung dari dashboard Super Admin.</p>
            </div>
            <div class="pricing">
                <?php foreach ($plans as $plan): ?>
                    <?php
                    $price = (float) ($plan['price'] ?? 0);
                    $isFeatured = $featuredPlanId !== null && (int) ($plan['id'] ?? 0) === $featuredPlanId;
                    ?>
                    <article class="price-card<?= $isFeatured ? ' featured' : '' ?>">
                        <?php if ($isFeatured): ?>
                            <span class="price-badge">Paling Lengkap</span>
                        <?php endif; ?>
                        <span class="plan-name"><?= e((string) ($plan['name'] ?? '')) ?></span>
                        <div class="price"><span class="amount"><?= e($fmtPublicPrice($price)) ?></span><span class="per"><?= e($publicSuffix($plan)) ?></span></div>
                        <p class="plan-desc"><?= e($publicDesc($plan)) ?></p>
                        <ul>
                            <?php foreach ($publicBullets($plan) as $bullet): ?>
                                <li><?= $checkSvg ?><?= e($bullet) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="btn <?= $isFeatured ? 'btn-primary' : 'btn-outline' ?>" href="/login"><?= $price <= 0 ? 'Mulai ' : 'Pilih ' ?><?= e((string) ($plan['name'] ?? '')) ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php else: ?>
    <section class="block" id="paket">
        <div class="wrap">
            <div class="sec-head">
                <span class="kicker">Paket Lisensi</span>
                <h2>Pilih plan sesuai skala booth kamu</h2>
                <p>Berikut contoh paket yang tersedia. Plan resmi beserta harga final dikelola langsung dari dashboard Super Admin.</p>
            </div>
            <div class="pricing">
                <article class="price-card">
                    <span class="plan-name">Trial</span>
                    <div class="price"><span class="amount">Rp0</span><span class="per">/ 14 hari</span></div>
                    <p class="plan-desc">Untuk mencoba alur self-booth di event pertamamu.</p>
                    <ul>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>1 perangkat PC booth</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Album event online</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Upload otomatis ke album online</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Berlaku 14 hari</li>
                    </ul>
                    <a class="btn btn-outline" href="/login">Mulai Trial</a>
                </article>
                <article class="price-card">
                    <span class="plan-name">Basic</span>
                    <div class="price"><span class="amount">Rp149rb</span><span class="per">/ bulan</span></div>
                    <p class="plan-desc">Untuk booth reguler dengan pembayaran mandiri.</p>
                    <ul>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>1 perangkat PC booth</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Album event + QR galeri</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Pembayaran QRIS via Midtrans</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Kirim album via WhatsApp</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Kunci payment bisa dimatikan</li>
                    </ul>
                    <a class="btn btn-outline" href="/login">Pilih Basic</a>
                </article>
                <article class="price-card featured">
                    <span class="price-badge">Paling Lengkap</span>
                    <span class="plan-name">Pro</span>
                    <div class="price"><span class="amount">Rp349rb</span><span class="per">/ bulan</span></div>
                    <p class="plan-desc">Untuk operator multi-booth dan event berskala besar.</p>
                    <ul>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>3 perangkat PC booth</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Semua fitur Basic</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Retensi album hingga 365 hari</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Kuota sesi &amp; event lebih besar</li>
                        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5 10 18 19.5 6.5"/></svg>Prioritas dukungan</li>
                    </ul>
                    <a class="btn btn-primary" href="/login">Pilih Pro</a>
                </article>
            </div>
            <p class="pricing-note">Harga di atas adalah contoh tampilan. Plan dan harga resmi diatur dari dashboard Super Admin.</p>
        </div>
    </section>
    <?php endif; ?>

    <section class="block">
        <div class="wrap">
            <div class="cta-band">
                <h2>Sudah punya akun tenant?</h2>
                <p>Masuk ke dashboard untuk mengelola event, paket sesi, dan lisensi aplikasi booth kamu.</p>
                <a class="btn btn-primary" href="/login">Masuk Dashboard</a>
            </div>
        </div>
    </section>
</main>

<footer class="site-foot">
    <div class="wrap">
        <div class="brandmark">
            <span class="logo-mark" aria-hidden="true">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
            </span>
            <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span></span>
        </div>
        <p>&copy; <?= date('Y') ?> <?= e($appName) ?> · Bagian dari EventBooth · <span title="Versi aplikasi">v<?= e($version) ?></span></p>
    </div>
</footer>

</body>
</html>
