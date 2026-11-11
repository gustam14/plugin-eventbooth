<?php
/**
 * Album publik satu event — /e/{slug} (Milestone 5a, blueprint §5C).
 * Tanpa login; hanya metadata + thumbnail tersimpan yang dibaca.
 *
 * @var string $appName
 * @var string $appUrl
 * @var string $version
 * @var array<string, mixed> $event Event (+ vendor_name, tenant_slug).
 * @var array<int, array<string, mixed>> $sessions Sesi satu halaman (+ media_count, video_count, cover_url).
 * @var int $page Halaman saat ini (1-based).
 * @var int $totalPages Total halaman.
 * @var int $totalSessions Total sesi pada event ini.
 * @var string $albumBase Awalan URL halaman ini (/e/{slug} atau /gallery/{tenant}/{event}).
 */

$eventName = (string) ($event['name'] ?? '');
$vendorName = (string) ($event['vendor_name'] ?? '');
$brand = is_array($brand ?? null) ? $brand : Branding::defaults();
$brandCss = Branding::cssVars($brand);
$brandCustom = !empty($brand['has_custom']);
$brandLogo = (string) ($brand['logo_url'] ?? '');
$brandTagline = (string) ($brand['tagline'] ?? '');
$brandInk = (string) ($brand['ink'] ?? '#1A1405');
$bannerUrl = Branding::bannerUrl(isset($event['banner_image']) ? (string) $event['banner_image'] : null)
    ?? (string) ($brand['banner_url'] ?? '');
$eventDate = License::formatDateId(isset($event['starts_at']) ? (string) $event['starts_at'] : null);
$venue = trim((string) ($event['venue'] ?? ''));
$slug = (string) ($event['slug'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0C0F">
<title><?= e($eventName) ?> — Album Foto · <?= e($vendorName !== '' ? $vendorName : $appName) ?></title>
<meta name="description" content="Album foto <?= e($eventName) ?> oleh <?= e($vendorName) ?> — lihat dan unduh hasil sesi photobooth.">
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
        --gold-rgb: 201, 162, 75;
        --font: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: var(--font);
        /* Latar ambien (v0.15.0): cahaya emas halus tetap di
           tempat saat digulir, selaras dashboard. */
        background:
            radial-gradient(880px 480px at 88% -10%, rgba(var(--gold-rgb), .07), transparent 62%),
            radial-gradient(720px 460px at -12% 108%, rgba(var(--gold-rgb), .045), transparent 60%),
            var(--bg);
        background-attachment: fixed;
        color: var(--text);
        line-height: 1.65;
        font-size: 16px;
        -webkit-font-smoothing: antialiased;
        overflow-x: clip;
    }
    img, svg { max-width: 100%; }
    ::selection { background: rgba(var(--gold-rgb), .35); color: #fff; }
    :focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 4px; }
    a { color: var(--gold-2); text-decoration: none; }
    .wrap { max-width: 1160px; margin: 0 auto; padding: 0 24px; }

    /* ---------- Kepala situs (pola sama dengan landing) ---------- */
    .site-head {
        position: sticky; top: 0; z-index: 50;
        background: rgba(10, 12, 15, .82);
        -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
        border-bottom: 1px solid var(--line);
    }
    .site-head .wrap { display: flex; align-items: center; justify-content: space-between; gap: 20px; height: 68px; }
    .brandmark { display: flex; align-items: center; gap: 11px; }
    .brandmark > a { display: flex; align-items: center; gap: 11px; }
    .brandmark > a:hover { text-decoration: none; }
    .logo-mark {
        flex: none; display: inline-flex; align-items: center; justify-content: center;
        width: 38px; height: 38px; border-radius: 10px;
        background: var(--gold-grad); box-shadow: 0 8px 20px rgba(var(--gold-rgb), .30);
    }
    .brandmark-text { font-size: 16.5px; font-weight: 800; letter-spacing: -.01em; color: var(--text); line-height: 1.15; }
    .brandmark-text .accent { background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .brandmark-sub { display: block; font-size: 10.5px; font-weight: 600; color: var(--muted-2); letter-spacing: .06em; text-transform: uppercase; }
    .brand-logo-img { height: 40px; width: auto; max-width: 190px; object-fit: contain; display: block; }
    .brand-logo-img.small { height: 30px; max-width: 150px; }

    /* ---------- Kontak tenant di kepala album ---------- */
    .contact-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .contact-btn {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 16px; border-radius: 999px;
        border: 1px solid var(--line-strong); color: var(--text);
        font-size: 14px; font-weight: 700; background: rgba(255, 255, 255, .03);
    }
    .contact-btn:hover { border-color: rgba(var(--gold-rgb), .55); text-decoration: none; }
    .contact-btn.contact-wa { background: var(--gold-grad); color: var(--gold-ink); border-color: transparent; }

    /* ---------- Kepala album ---------- */
    .album-head {
        padding: 56px 0 10px;
        background: radial-gradient(720px 380px at 85% -20%, rgba(var(--gold-rgb), .17), transparent 62%);
    }
    .album-head .kicker {
        font-size: 12.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gold-2);
    }
    .album-head h1 { font-size: clamp(30px, 4.4vw, 46px); font-weight: 800; letter-spacing: -.025em; line-height: 1.15; margin: 10px 0 8px; }
    .album-head .meta { color: var(--muted); font-size: 15.5px; }
    .album-head .meta strong { color: var(--text); font-weight: 600; }

    /* ---------- Kepala album dengan banner (v0.25.0) ----------
       Potongan gambar DIJANGKAR KE ATAS (v0.25.1): banner pada
       umumnya membawa logo/teks di tepi atas dan ruang kosong
       di bawah untuk judul event — pemotongan dari tengah
       membuang justru bagian brandingnya. */
    .album-head.has-banner {
        position: relative; overflow: hidden;
        padding: 172px 0 40px; background: none;
    }
    .album-head.has-banner .hero-img {
        position: absolute; inset: 0; width: 100%; height: 100%;
        object-fit: cover; object-position: center top; display: block;
    }
    .album-head.has-banner .hero-shade {
        position: absolute; inset: 0;
        background: linear-gradient(180deg, rgba(10, 12, 15, .22) 0%, rgba(10, 12, 15, .48) 55%, rgba(10, 12, 15, .94) 100%);
    }
    .album-head.has-banner .wrap { position: relative; z-index: 1; }
    .album-head.has-banner h1 { text-shadow: 0 2px 20px rgba(0, 0, 0, .5); }
    .album-head.has-banner .meta { color: #C7CDD6; text-shadow: 0 1px 10px rgba(0, 0, 0, .55); }

    /* ---------- Kisi sesi ---------- */
    .grid {
        display: grid; gap: 18px; margin-top: 30px;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    }
    .session-card {
        display: block; background: linear-gradient(180deg, var(--panel-2), var(--panel));
        border: 1px solid var(--line); border-radius: 18px; overflow: hidden;
        box-shadow: 0 10px 26px rgba(0, 0, 0, .30);
        transition: transform 200ms ease, border-color 200ms ease, box-shadow 200ms ease;
    }
    .session-card:hover { transform: translateY(-4px); border-color: rgba(var(--gold-rgb), .45); box-shadow: 0 22px 44px rgba(0, 0, 0, .45); text-decoration: none; }
    .session-card .cover { position: relative; aspect-ratio: 4 / 5; background: #0D1015; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .session-card .cover img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 400ms ease; }
    .session-card:hover .cover img { transform: scale(1.045); }
    .session-card .badge {
        position: absolute; top: 10px; z-index: 2;
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 10px; border-radius: 999px;
        background: rgba(8, 10, 13, .72); color: #fff;
        border: 1px solid rgba(255, 255, 255, .14);
        font-size: 12px; font-weight: 700; letter-spacing: .02em;
        -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
    }
    .session-card .badge.count { left: 10px; }
    .session-card .badge.video { right: 10px; background: rgba(var(--gold-rgb), .92); color: var(--gold-ink); }
    .session-card .cover .time {
        position: absolute; left: 0; right: 0; bottom: 0; z-index: 2;
        padding: 22px 12px 9px; text-align: center;
        background: linear-gradient(180deg, transparent, rgba(5, 6, 8, .82));
        color: #fff; font-size: 15px; font-weight: 800; letter-spacing: .05em;
    }
    .session-card .cover .placeholder {
        display: flex; flex-direction: column; align-items: center; gap: 8px;
        color: var(--muted-2); font-size: 13px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
    }
    .session-card .body { padding: 14px 16px 16px; }
    .session-card .guest { font-weight: 700; font-size: 15.5px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .session-card .detail { color: var(--muted); font-size: 13.5px; margin-top: 2px; }

    .empty {
        margin-top: 34px; padding: 44px 28px; text-align: center;
        background: var(--panel); border: 1px dashed var(--line-strong); border-radius: 16px;
        color: var(--muted); font-size: 16px;
    }

    /* ---------- Paginasi ---------- */
    .pager { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 36px; }
    .pager a, .pager span {
        font-size: 14.5px; font-weight: 600; padding: 9px 18px; border-radius: 10px;
        border: 1px solid var(--line-strong); color: var(--text);
    }
    .pager a:hover { border-color: rgba(var(--gold-rgb), .55); background: rgba(var(--gold-rgb), .10); text-decoration: none; }
    .pager span { color: var(--muted); border-color: transparent; }

    /* ---------- Kaki (pola sama dengan landing) ---------- */
    footer.site-foot { border-top: 1px solid var(--line); margin-top: 72px; }
    footer.site-foot .wrap {
        display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        padding-top: 26px; padding-bottom: 30px;
    }
    footer.site-foot p { color: var(--muted-2); font-size: 13.5px; }
    footer.site-foot p a { color: var(--muted); }
    footer.site-foot p a:hover { color: var(--gold-2); }

    @media (max-width: 768px) {
        .album-head { padding: 42px 0 6px; }
        /* Di HP banner tampil UTUH sebagai blok 16:9 (v0.25.1):
           menimpanya dengan judul di layar sempit memotong logo
           dan teks di tepi banner. Judul, tanggal, dan tombol
           kontak pindah ke bawah banner di latar halaman. */
        .album-head.has-banner { padding: 0 0 6px; }
        .album-head.has-banner .hero-img {
            position: relative; inset: auto; height: auto; aspect-ratio: 16 / 9;
        }
        .album-head.has-banner .hero-shade { display: none; }
        .album-head.has-banner .wrap { padding-top: 20px; }
        .album-head.has-banner h1 { text-shadow: none; }
        .album-head.has-banner .meta { color: var(--muted); text-shadow: none; }
        footer.site-foot .wrap { justify-content: center; text-align: center; }
    }
    @media (max-width: 520px) {
        .wrap { padding: 0 16px; }
        .site-head .wrap { height: 60px; }
        .grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        footer.site-foot { margin-top: 56px; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { transition: none !important; }
        .session-card:hover { transform: none; }
        .session-card:hover .cover img { transform: none; }
    }
</style>
<?php if ($brandCss !== ''): ?><style><?= $brandCss ?></style><?php endif; ?>
</head>
<body>

<header class="site-head">
    <div class="wrap">
        <?php if ($brandCustom): ?>
            <div class="brandmark">
                <a href="<?= e((string) ($albumBase ?? ('/e/' . $slug))) ?>" aria-label="<?= e($vendorName) ?> — album event">
                    <?php if ($brandLogo !== ''): ?>
                        <img class="brand-logo-img" src="<?= e($brandLogo) ?>" alt="<?= e($vendorName) ?>">
                    <?php else: ?>
                        <span class="logo-mark" aria-hidden="true">
                            <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="<?= e($brandInk) ?>" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                        </span>
                    <?php endif; ?>
                    <span class="brandmark-text"><?= e($vendorName) ?><?php if ($brandTagline !== ''): ?><span class="brandmark-sub"><?= e($brandTagline) ?></span><?php endif; ?></span>
                </a>
            </div>
        <?php else: ?>
            <div class="brandmark">
                <a href="/" aria-label="Plugin Eventbooth — ke halaman utama">
                    <span class="logo-mark" aria-hidden="true">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                    </span>
                    <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span><span class="brandmark-sub">by EventBooth</span></span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</header>

<main>
    <section class="album-head<?= $bannerUrl !== '' ? ' has-banner' : '' ?>">
        <?php if ($bannerUrl !== ''): ?>
            <img class="hero-img" src="<?= e($bannerUrl) ?>" alt="">
            <span class="hero-shade" aria-hidden="true"></span>
        <?php endif; ?>
        <div class="wrap">
            <div class="kicker">Album Event<?php if (!$brandCustom && $vendorName !== ''): ?> · <?= e($vendorName) ?><?php endif; ?></div>
            <h1><?= e($eventName) ?></h1>
            <p class="meta">
                <?php if ($eventDate !== '—'): ?><strong><?= e($eventDate) ?></strong><?php endif; ?>
                <?php if ($venue !== ''): ?> · <?= e($venue) ?><?php endif; ?>
                <?php if (($totalSessions ?? 0) > 0): ?> · <?= number_format((int) $totalSessions, 0, ',', '.') ?> sesi<?php endif; ?>
            </p>
            <?php if (!empty($brand['whatsapp_url']) || !empty($brand['instagram_url'])): ?>
                <div class="contact-row">
                    <?php if (!empty($brand['whatsapp_url'])): ?>
                        <a class="contact-btn contact-wa" href="<?= e((string) $brand['whatsapp_url']) ?>" target="_blank" rel="noopener">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            Chat <?= e($vendorName !== '' ? $vendorName : 'Kami') ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($brand['instagram_url'])): ?>
                        <a class="contact-btn" href="<?= e((string) $brand['instagram_url']) ?>" target="_blank" rel="noopener">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="2.5" width="19" height="19" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.4" cy="6.6" r="1.1" fill="currentColor" stroke="none"/></svg>
                            @<?= e((string) $brand['instagram']) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="wrap">
        <?php if (($sessions ?? []) === []): ?>
            <?php if (!empty($retentionEmptiedAt)): ?>
                <?php
                $expiredLabel = License::formatDateId((string) $retentionEmptiedAt);
                $closureLabel = License::formatDateId(date('Y-m-d H:i:s', strtotime((string) $retentionEmptiedAt) + 30 * 86400));
                ?>
                <div class="empty"><strong>Album Expired</strong> — masa retensi album event ini telah berakhir pada <?= e($expiredLabel) ?>, sehingga seluruh foto di dalamnya telah dibersihkan dari penyimpanan. Halaman ini akan ditutup sepenuhnya pada <?= e($closureLabel) ?>.</div>
            <?php else: ?>
                <div class="empty">Album masih kosong — foto akan muncul di sini setelah sesi pertama selesai.</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $guestName = trim((string) ($session['guest_name'] ?? ''));
                    $coverUrl = trim((string) ($session['cover_url'] ?? ''));
                    $coverMediaId = (int) ($session['cover_media_id'] ?? 0);
                    $coverSrc = $coverMediaId > 0 ? '/media/' . $coverMediaId : $coverUrl;
                    $createdTs = isset($session['created_at']) ? strtotime((string) $session['created_at']) : false;
                    $createdLabel = $createdTs !== false
                        ? License::formatDateId((string) $session['created_at']) . ' · ' . date('H:i', $createdTs)
                        : '';
                    $timeLabel = $createdTs !== false ? date('H:i', $createdTs) : '';
                    ?>
                    <a class="session-card" href="/s/<?= e((string) $session['code']) ?>">
                        <span class="cover">
                            <?php if ($coverSrc !== ''): ?>
                                <img src="<?= e($coverSrc) ?>" alt="Foto sesi <?= e((string) $session['code']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="placeholder">
                                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                                    Foto sesi
                                </span>
                            <?php endif; ?>
                            <span class="badge count">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.4"/><path d="M21 16l-5-5-9 9"/></svg>
                                <?= number_format((int) ($session['media_count'] ?? 0), 0, ',', '.') ?> Item
                            </span>
                            <?php if ((int) ($session['video_count'] ?? 0) > 0): ?>
                                <span class="badge video">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M7 5.5v13l11-6.5z"/></svg>
                                    VIDEO
                                </span>
                            <?php endif; ?>
                            <?php if ($timeLabel !== ''): ?>
                                <span class="time"><?= e($timeLabel) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="body">
                            <span class="guest"><?= e($guestName !== '' ? $guestName : 'Sesi ' . (string) $session['code']) ?></span>
                            <span class="detail" style="display: block;">
                                <?= e($createdLabel) ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (($totalPages ?? 1) > 1): ?>
                <nav class="pager" aria-label="Halaman album">
                    <?php if (($page ?? 1) > 1): ?>
                        <a href="<?= e((string) ($albumBase ?? ('/e/' . $slug))) ?>?page=<?= (int) $page - 1 ?>">&larr; Sebelumnya</a>
                    <?php endif; ?>
                    <span>Halaman <?= (int) ($page ?? 1) ?> dari <?= (int) $totalPages ?></span>
                    <?php if (($page ?? 1) < ($totalPages ?? 1)): ?>
                        <a href="<?= e((string) ($albumBase ?? ('/e/' . $slug))) ?>?page=<?= (int) $page + 1 ?>">Berikutnya &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<footer class="site-foot">
    <div class="wrap">
        <?php if ($brandCustom): ?>
            <div class="brandmark">
                <?php if ($brandLogo !== ''): ?>
                    <img class="brand-logo-img small" src="<?= e($brandLogo) ?>" alt="<?= e($vendorName) ?>">
                <?php else: ?>
                    <span class="logo-mark" aria-hidden="true">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="<?= e($brandInk) ?>" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                    </span>
                    <span class="brandmark-text"><?= e($vendorName) ?></span>
                <?php endif; ?>
            </div>
            <p>&copy; <?= date('Y') ?> <?= e($vendorName) ?> · Didukung oleh <a href="/">Plugin Eventbooth</a> · <a href="/privacy">Kebijakan Privasi</a> · <a href="/terms">Syarat Layanan</a> · <span title="Versi aplikasi">v<?= e($version) ?></span></p>
        <?php else: ?>
            <div class="brandmark">
                <span class="logo-mark" aria-hidden="true">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                </span>
                <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span></span>
            </div>
            <p>&copy; <?= date('Y') ?> <?= e($appName) ?> · Bagian dari EventBooth · <a href="/privacy">Kebijakan Privasi</a> · <a href="/terms">Syarat Layanan</a> · <span title="Versi aplikasi">v<?= e($version) ?></span></p>
        <?php endif; ?>
    </div>
</footer>

</body>
</html>
