<?php
/**
 * Album publik satu event — /e/{slug} (Milestone 5a, blueprint §5C).
 * Tanpa login; hanya metadata + thumbnail tersimpan yang dibaca.
 *
 * @var string $appName
 * @var string $appUrl
 * @var string $version
 * @var array<string, mixed> $event Event (+ vendor_name).
 * @var array<int, array<string, mixed>> $sessions Sesi satu halaman (+ media_count, cover_url).
 * @var int $page Halaman saat ini (1-based).
 * @var int $totalPages Total halaman.
 * @var int $totalSessions Total sesi pada event ini.
 */

$eventName = (string) ($event['name'] ?? '');
$vendorName = (string) ($event['vendor_name'] ?? '');
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
<title><?= e($eventName) ?> — Album Foto · <?= e($appName) ?></title>
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
        --font: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
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
        background: var(--gold-grad); box-shadow: 0 8px 20px rgba(201, 162, 75, .30);
    }
    .brandmark-text { font-size: 16.5px; font-weight: 800; letter-spacing: -.01em; color: var(--text); line-height: 1.15; }
    .brandmark-text .accent { background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .brandmark-sub { display: block; font-size: 10.5px; font-weight: 600; color: var(--muted-2); letter-spacing: .06em; text-transform: uppercase; }

    /* ---------- Kepala album ---------- */
    .album-head {
        padding: 56px 0 10px;
        background: radial-gradient(720px 380px at 85% -20%, rgba(201, 162, 75, .13), transparent 62%);
    }
    .album-head .kicker {
        font-size: 12.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gold-2);
    }
    .album-head h1 { font-size: clamp(30px, 4.4vw, 46px); font-weight: 800; letter-spacing: -.025em; line-height: 1.15; margin: 10px 0 8px; }
    .album-head .meta { color: var(--muted); font-size: 15.5px; }
    .album-head .meta strong { color: var(--text); font-weight: 600; }

    /* ---------- Kisi sesi ---------- */
    .grid {
        display: grid; gap: 18px; margin-top: 30px;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    }
    .session-card {
        display: block; background: linear-gradient(180deg, var(--panel-2), var(--panel));
        border: 1px solid var(--line); border-radius: 16px; overflow: hidden;
        transition: transform 160ms ease, border-color 160ms ease;
    }
    .session-card:hover { transform: translateY(-2px); border-color: rgba(201, 162, 75, .45); text-decoration: none; }
    .session-card .cover { aspect-ratio: 4 / 3; background: #0D1015; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .session-card .cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
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
    .pager a:hover { border-color: rgba(201, 162, 75, .55); text-decoration: none; }
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
        footer.site-foot .wrap { justify-content: center; text-align: center; }
    }
    @media (max-width: 520px) {
        .wrap { padding: 0 16px; }
        .site-head .wrap { height: 60px; }
        .grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        footer.site-foot { margin-top: 56px; }
    }
</style>
</head>
<body>

<header class="site-head">
    <div class="wrap">
        <div class="brandmark">
            <a href="/" aria-label="Plugin Eventbooth — ke halaman utama">
                <span class="logo-mark" aria-hidden="true">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                </span>
                <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span><span class="brandmark-sub">by EventBooth</span></span>
            </a>
        </div>
    </div>
</header>

<main>
    <section class="album-head">
        <div class="wrap">
            <div class="kicker">Album Event<?php if ($vendorName !== ''): ?> · <?= e($vendorName) ?><?php endif; ?></div>
            <h1><?= e($eventName) ?></h1>
            <p class="meta">
                <?php if ($eventDate !== '—'): ?><strong><?= e($eventDate) ?></strong><?php endif; ?>
                <?php if ($venue !== ''): ?> · <?= e($venue) ?><?php endif; ?>
                <?php if (($totalSessions ?? 0) > 0): ?> · <?= number_format((int) $totalSessions, 0, ',', '.') ?> sesi<?php endif; ?>
            </p>
        </div>
    </section>

    <div class="wrap">
        <?php if (($sessions ?? []) === []): ?>
            <div class="empty">Album masih kosong — foto akan muncul di sini setelah sesi pertama selesai.</div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $guestName = trim((string) ($session['guest_name'] ?? ''));
                    $coverUrl = trim((string) ($session['cover_url'] ?? ''));
                    $createdTs = isset($session['created_at']) ? strtotime((string) $session['created_at']) : false;
                    $createdLabel = $createdTs !== false
                        ? License::formatDateId((string) $session['created_at']) . ' · ' . date('H:i', $createdTs)
                        : '';
                    ?>
                    <a class="session-card" href="/s/<?= e((string) $session['code']) ?>">
                        <span class="cover">
                            <?php if ($coverUrl !== ''): ?>
                                <img src="<?= e($coverUrl) ?>" alt="Foto sesi <?= e((string) $session['code']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="placeholder">
                                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                                    Foto sesi
                                </span>
                            <?php endif; ?>
                        </span>
                        <span class="body">
                            <span class="guest"><?= e($guestName !== '' ? $guestName : 'Sesi ' . (string) $session['code']) ?></span>
                            <span class="detail" style="display: block;">
                                <?= e($createdLabel) ?> · <?= number_format((int) ($session['media_count'] ?? 0), 0, ',', '.') ?> foto
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (($totalPages ?? 1) > 1): ?>
                <nav class="pager" aria-label="Halaman album">
                    <?php if (($page ?? 1) > 1): ?>
                        <a href="/e/<?= e($slug) ?>?page=<?= (int) $page - 1 ?>">&larr; Sebelumnya</a>
                    <?php endif; ?>
                    <span>Halaman <?= (int) ($page ?? 1) ?> dari <?= (int) $totalPages ?></span>
                    <?php if (($page ?? 1) < ($totalPages ?? 1)): ?>
                        <a href="/e/<?= e($slug) ?>?page=<?= (int) $page + 1 ?>">Berikutnya &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<footer class="site-foot">
    <div class="wrap">
        <div class="brandmark">
            <span class="logo-mark" aria-hidden="true">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
            </span>
            <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span></span>
        </div>
        <p>&copy; <?= date('Y') ?> <?= e($appName) ?> · Bagian dari EventBooth · <a href="/privacy">Kebijakan Privasi</a> · <a href="/terms">Syarat Layanan</a> · <span title="Versi aplikasi">v<?= e($version) ?></span></p>
    </div>
</footer>

</body>
</html>
