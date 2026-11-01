<?php
/**
 * Halaman publik satu sesi — /s/{kode} (Milestone 5a, blueprint §5C).
 * Tanpa login; tautan lihat/unduh berasal dari kolom tersimpan
 * media_files (web_link; unduh diturunkan dari drive_file_id).
 *
 * @var string $appName
 * @var string $appUrl
 * @var string $version
 * @var array<string, mixed> $session Sesi (+ event_name, event_slug).
 * @var array<int, array<string, mixed>> $media Media sesi (final lebih dulu).
 */

$eventName = (string) ($session['event_name'] ?? '');
$eventSlug = (string) ($session['event_slug'] ?? '');
$albumHref = Album::galleryUrl('', isset($session['tenant_slug']) ? (string) $session['tenant_slug'] : null, $eventSlug);
$code = (string) ($session['code'] ?? '');
$guestName = trim((string) ($session['guest_name'] ?? ''));
$createdLabel = isset($session['created_at'])
    ? License::formatDateId((string) $session['created_at'])
    : '';
$lbItems = [];
foreach (($media ?? []) as $item) {
    $lbItems[] = [
        'id' => (int) ($item['id'] ?? 0),
        'kind' => (string) ($item['kind'] ?? 'final'),
        'name' => trim((string) ($item['file_name'] ?? '')),
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0C0F">
<title><?= e($eventName) ?> — Sesi <?= e($code) ?> · <?= e($appName) ?></title>
<meta name="description" content="Hasil foto sesi <?= e($code) ?> pada event <?= e($eventName) ?> — lihat dan unduh fotomu.">
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
    .back-link { color: var(--muted); font-size: 14.5px; font-weight: 600; transition: color 150ms ease; white-space: nowrap; }
    .back-link:hover { color: var(--text); text-decoration: none; }

    /* ---------- Kepala sesi ---------- */
    .album-head {
        padding: 48px 0 6px;
        background: radial-gradient(720px 380px at 85% -20%, rgba(201, 162, 75, .13), transparent 62%);
    }
    .album-head .kicker {
        font-size: 12.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gold-2);
    }
    .album-head h1 { font-size: clamp(28px, 4vw, 40px); font-weight: 800; letter-spacing: -.025em; line-height: 1.15; margin: 10px 0 8px; }
    .album-head .meta { color: var(--muted); font-size: 15.5px; }
    .album-head .meta strong { color: var(--text); font-weight: 600; }

    /* ---------- Kisi media ---------- */
    .grid {
        display: grid; gap: 18px; margin-top: 30px;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    }
    .media-card {
        background: linear-gradient(180deg, var(--panel-2), var(--panel));
        border: 1px solid var(--line); border-radius: 16px; overflow: hidden;
    }
    .media-card .cover { aspect-ratio: 4 / 3; background: #0D1015; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .media-card .cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .media-card .cover .placeholder {
        display: flex; flex-direction: column; align-items: center; gap: 8px;
        color: var(--muted-2); font-size: 13px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
    }
    .media-card .body { padding: 13px 16px 15px; }
    .media-card .kind { font-weight: 700; font-size: 14.5px; }
    .media-card .file { color: var(--muted); font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .media-card .actions { display: flex; gap: 8px; margin-top: 11px; }
    .media-card .actions a {
        flex: 1; text-align: center; font-size: 13.5px; font-weight: 700;
        padding: 8px 10px; border-radius: 9px; border: 1px solid var(--line-strong); color: var(--text);
        transition: border-color 150ms ease, background 150ms ease;
    }
    .media-card .actions a:hover { border-color: rgba(201, 162, 75, .55); text-decoration: none; }
    .media-card .actions a.download { background: var(--gold-grad); color: var(--gold-ink); border-color: transparent; }
    .media-card .actions a.download:hover { filter: brightness(1.05); }

    .empty {
        margin-top: 34px; padding: 44px 28px; text-align: center;
        background: var(--panel); border: 1px dashed var(--line-strong); border-radius: 16px;
        color: var(--muted); font-size: 16px;
    }

    /* ---------- Penampil media (lightbox, di dalam situs) ---------- */
    .media-card .cover-btn { width: 100%; border: 0; padding: 0; cursor: zoom-in; position: relative; font: inherit; }
    .media-card .play-badge {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        color: #fff; background: rgba(5, 6, 8, .25);
    }
    .media-card .play-badge span {
        display: inline-flex; align-items: center; justify-content: center;
        width: 52px; height: 52px; border-radius: 50%;
        background: rgba(8, 10, 13, .72); border: 1px solid rgba(255, 255, 255, .35);
    }
    .media-card .actions button {
        flex: 1; text-align: center; font-size: 13.5px; font-weight: 700; font-family: inherit;
        padding: 8px 10px; border-radius: 9px; border: 1px solid var(--line-strong);
        color: var(--text); background: transparent; cursor: pointer;
        transition: border-color 150ms ease;
    }
    .media-card .actions button:hover { border-color: rgba(201, 162, 75, .55); }
    .lightbox {
        position: fixed; inset: 0; z-index: 200; display: none;
        background: rgba(4, 5, 7, .96); flex-direction: column;
    }
    .lightbox.open { display: flex; }
    .lb-top {
        display: flex; align-items: center; justify-content: space-between; gap: 14px;
        padding: 12px 16px; color: #fff;
    }
    .lb-counter { font-size: 14px; font-weight: 600; color: var(--muted); min-width: 64px; }
    .lb-actions { display: flex; align-items: center; gap: 8px; }
    .lb-actions a, .lb-actions button {
        display: inline-flex; align-items: center; justify-content: center;
        width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--line-strong);
        background: rgba(255, 255, 255, .04); color: #fff; cursor: pointer; font-family: inherit;
    }
    .lb-actions a:hover, .lb-actions button:hover { border-color: rgba(201, 162, 75, .6); text-decoration: none; }
    .lb-stage { flex: 1; display: flex; align-items: center; justify-content: center; min-height: 0; padding: 4px 12px; }
    .lb-stage img, .lb-stage video { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; }
    .lb-nav {
        position: absolute; top: 50%; transform: translateY(-50%);
        width: 46px; height: 46px; border-radius: 50%; border: 1px solid var(--line-strong);
        background: rgba(10, 12, 15, .65); color: #fff; font-size: 20px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .lb-nav.prev { left: 12px; }
    .lb-nav.next { right: 12px; }
    .lb-caption { text-align: center; color: var(--muted); font-size: 13.5px; padding: 8px 16px 4px; word-break: break-all; }
    .lb-strip {
        display: flex; gap: 8px; justify-content: flex-start; overflow-x: auto;
        padding: 10px 16px 16px; max-width: 100%;
    }
    .lb-strip button {
        flex: none; width: 62px; height: 62px; border-radius: 8px; overflow: hidden;
        border: 2px solid transparent; padding: 0; cursor: pointer; background: #0D1015;
    }
    .lb-strip button.active { border-color: var(--gold); }
    .lb-strip img { width: 100%; height: 100%; object-fit: cover; display: block; }

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
        .album-head { padding: 38px 0 4px; }
        footer.site-foot .wrap { justify-content: center; text-align: center; }
    }
    @media (max-width: 520px) {
        .wrap { padding: 0 16px; }
        .site-head .wrap { height: 60px; }
        .grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
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
        <a class="back-link" href="<?= e($albumHref) ?>">&larr; Album Event</a>
    </div>
</header>

<main>
    <section class="album-head">
        <div class="wrap">
            <div class="kicker">Hasil Sesi Foto</div>
            <h1><?= e($eventName) ?></h1>
            <p class="meta">
                Sesi <strong><?= e($code) ?></strong>
                <?php if ($guestName !== ''): ?> · <?= e($guestName) ?><?php endif; ?>
                <?php if ($createdLabel !== '' && $createdLabel !== '—'): ?> · <?= e($createdLabel) ?><?php endif; ?>
            </p>
        </div>
    </section>

    <div class="wrap">
        <?php if (($media ?? []) === []): ?>
            <div class="empty">Foto sesi ini belum tersedia — silakan periksa lagi sebentar lagi.</div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($media as $idx => $item): ?>
                    <?php
                    $mediaId = (int) ($item['id'] ?? 0);
                    $kind = (string) ($item['kind'] ?? 'final');
                    $hasImage = trim((string) ($item['thumbnail_link'] ?? '')) !== ''
                        || trim((string) ($item['drive_file_id'] ?? '')) !== '';
                    $fileName = trim((string) ($item['file_name'] ?? ''));
                    ?>
                    <figure class="media-card">
                        <button type="button" class="cover cover-btn" data-open="<?= (int) $idx ?>" aria-label="Lihat <?= e(Album::kindLabel($kind)) ?>">
                            <?php if ($hasImage): ?>
                                <img src="/media/<?= $mediaId ?>" alt="<?= e(Album::kindLabel($kind)) ?> sesi <?= e($code) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="placeholder">
                                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                                    <?= e(Album::kindLabel($kind)) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($kind === 'video'): ?>
                                <span class="play-badge"><span>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg>
                                </span></span>
                            <?php endif; ?>
                        </button>
                        <span class="body" style="display: block;">
                            <span class="kind"><?= e(Album::kindLabel($kind)) ?></span>
                            <?php if ($fileName !== ''): ?>
                                <span class="file" style="display: block;"><?= e($fileName) ?></span>
                            <?php endif; ?>
                            <span class="actions">
                                <button type="button" data-open="<?= (int) $idx ?>">Lihat</button>
                                <a class="download" href="/media/<?= $mediaId ?>/download">Unduh</a>
                            </span>
                        </span>
                    </figure>
                <?php endforeach; ?>
            </div>
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

<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Penampil media sesi">
    <div class="lb-top">
        <span class="lb-counter" id="lbCounter"></span>
        <span class="lb-actions">
            <a id="lbDownload" href="#" aria-label="Unduh berkas ini">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11m0 0l-4.5-4.5M12 15l4.5-4.5M4.5 20h15"/></svg>
            </a>
            <button type="button" id="lbClose" aria-label="Tutup penampil">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg>
            </button>
        </span>
    </div>
    <div class="lb-stage" id="lbStage"></div>
    <button type="button" class="lb-nav prev" id="lbPrev" aria-label="Sebelumnya">&lsaquo;</button>
    <button type="button" class="lb-nav next" id="lbNext" aria-label="Berikutnya">&rsaquo;</button>
    <div class="lb-caption" id="lbCaption"></div>
    <div class="lb-strip" id="lbStrip"></div>
</div>

<script>
(function () {
    var ITEMS = <?= json_encode($lbItems, JSON_UNESCAPED_SLASHES) ?>;
    if (!ITEMS.length) return;
    var box = document.getElementById('lightbox');
    var stage = document.getElementById('lbStage');
    var counter = document.getElementById('lbCounter');
    var caption = document.getElementById('lbCaption');
    var strip = document.getElementById('lbStrip');
    var download = document.getElementById('lbDownload');
    var current = 0;

    ITEMS.forEach(function (item, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-label', 'Item ' + (i + 1));
        var img = document.createElement('img');
        img.src = '/media/' + item.id;
        img.alt = '';
        img.loading = 'lazy';
        b.appendChild(img);
        b.addEventListener('click', function () { show(i); });
        strip.appendChild(b);
    });

    function show(i) {
        current = (i + ITEMS.length) % ITEMS.length;
        var item = ITEMS[current];
        stage.innerHTML = '';
        if (item.kind === 'video') {
            var video = document.createElement('video');
            video.src = '/media/' + item.id + '?s=video';
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            stage.appendChild(video);
        } else {
            var img = document.createElement('img');
            img.src = '/media/' + item.id + '?s=full';
            img.alt = item.name || '';
            stage.appendChild(img);
        }
        counter.textContent = (current + 1) + ' / ' + ITEMS.length;
        caption.textContent = item.name || '';
        download.href = '/media/' + item.id + '/download';
        Array.prototype.forEach.call(strip.children, function (el, j) {
            el.classList.toggle('active', j === current);
        });
    }

    function open(i) {
        box.classList.add('open');
        document.body.style.overflow = 'hidden';
        show(i);
    }

    function close() {
        box.classList.remove('open');
        document.body.style.overflow = '';
        stage.innerHTML = '';
    }

    document.querySelectorAll('[data-open]').forEach(function (el) {
        el.addEventListener('click', function () {
            open(parseInt(el.getAttribute('data-open'), 10) || 0);
        });
    });
    document.getElementById('lbClose').addEventListener('click', close);
    document.getElementById('lbPrev').addEventListener('click', function () { show(current - 1); });
    document.getElementById('lbNext').addEventListener('click', function () { show(current + 1); });
    box.addEventListener('click', function (ev) {
        if (ev.target === box || ev.target === stage) close();
    });
    document.addEventListener('keydown', function (ev) {
        if (!box.classList.contains('open')) return;
        if (ev.key === 'Escape') close();
        if (ev.key === 'ArrowLeft') show(current - 1);
        if (ev.key === 'ArrowRight') show(current + 1);
    });
})();
</script>

</body>
</html>
