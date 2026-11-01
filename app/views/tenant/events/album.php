<?php
/**
 * Kelola Album event (v0.14.0): tenant menghapus media/sesi dan
 * mengunggah foto/GIF/boomerang manual ke galeri event.
 *
 * @var string $appName
 * @var string $appUrl
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed> $event
 * @var array<int, array{session: array<string, mixed>, media: array<int, array<string, mixed>>, media_total: int}> $sessionsData
 */

$title = 'Kelola Album';
$activeNav = 'events';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$eventId = (int) ($event['id'] ?? 0);
$albumUrl = ($appUrl ?? '') . '/e/' . (string) ($event['slug'] ?? '');
?>
<style>
    .album-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 12px; margin-top: 14px; }
    .album-media-card { border: 1px solid var(--line, #262B33); border-radius: 10px; overflow: hidden; background: rgba(255,255,255,.02); }
    .album-media-card img { width: 100%; height: 104px; object-fit: cover; display: block; background: #11141A; }
    .album-media-card .meta { padding: 8px 10px 10px; }
    .album-media-card .kind { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--gold, #C9A24B); }
    .album-media-card .fname { font-size: 12px; color: var(--muted, #9AA3AF); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 2px 0 8px; }
    .album-session-head { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
    .album-session-head .info { color: var(--muted, #9AA3AF); font-size: 13px; }
    .album-actions { display: flex; gap: 8px; align-items: center; }
    .album-actions form { display: inline; }
</style>

<p class="sub">
    Kelola isi galeri event <strong><?= e((string) ($event['name'] ?? '')) ?></strong> —
    hapus foto/GIF yang tidak layak tampil, atau unggah berkas tambahan secara manual.
    Perubahan langsung terlihat di album publik.
    <a class="btn btn-small" style="margin-left: 10px;" href="/tenant/events">← Daftar Event</a>
    <a class="btn btn-small" href="<?= e($albumUrl) ?>" target="_blank" rel="noopener">Lihat Album Publik</a>
</p>

<div class="panel-box">
    <h3 style="margin-top: 0;">Unggah Berkas ke Galeri</h3>
    <p class="hint" style="margin-bottom: 12px;">
        Foto (JPG/PNG), GIF, atau boomerang/video (MP4/MOV), maks 25 MB per berkas.
        Berkas tersimpan ke penyimpanan event dan langsung tampil di galeri tujuan.
        Untuk boomerang .mp4 yang ingin tampil sebagai GIF berputar, pilih jenis
        <strong>GIF / Boomerang</strong>.
    </p>
    <form method="post" enctype="multipart/form-data" action="/tenant/events/<?= $eventId ?>/album/upload">
        <?= Auth::csrfField() ?>
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: end;">
            <label style="display: block;">
                <span class="hint" style="display: block; margin-bottom: 4px;">Sesi tujuan</span>
                <select name="session_id">
                    <option value="new">Sesi baru (upload manual)</option>
                    <?php foreach ($sessionsData as $row): ?>
                        <?php $s = $row['session']; ?>
                        <option value="<?= (int) $s['id'] ?>">
                            <?= e((string) $s['code']) ?> — <?= e(License::formatDateId((string) ($s['created_at'] ?? ''))) ?> (<?= (int) $row['media_total'] ?> berkas)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="display: block;">
                <span class="hint" style="display: block; margin-bottom: 4px;">Jenis berkas</span>
                <select name="kind">
                    <option value="auto">Otomatis dari ekstensi</option>
                    <option value="final">Final / Poster</option>
                    <option value="original">Original</option>
                    <option value="gif">GIF / Boomerang</option>
                    <option value="video">Video</option>
                </select>
            </label>
            <label style="display: block;">
                <span class="hint" style="display: block; margin-bottom: 4px;">Berkas</span>
                <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.gif,.mp4,.mov" required>
            </label>
            <button class="btn btn-primary" type="submit">Unggah</button>
        </div>
    </form>
</div>

<?php if ($sessionsData === []): ?>
    <div class="panel-box">
        <p>Belum ada sesi pada event ini. Sesi muncul otomatis saat aplikasi booth dipakai tamu, atau unggah berkas pertama lewat formulir di atas untuk membuat sesi manual.</p>
    </div>
<?php endif; ?>

<?php foreach ($sessionsData as $row): ?>
    <?php
    $s = $row['session'];
    $sessionId = (int) $s['id'];
    $code = (string) ($s['code'] ?? '');
    $createdLabel = License::formatDateId((string) ($s['created_at'] ?? ''));
    $timeLabel = isset($s['created_at']) ? substr((string) $s['created_at'], 11, 5) : '';
    $guest = trim((string) ($s['guest_name'] ?? ''));
    ?>
    <div class="panel-box">
        <div class="album-session-head">
            <div>
                <strong style="font-size: 16px;">Sesi <?= e($code) ?></strong>
                <div class="info">
                    <?= e($createdLabel) ?><?= $timeLabel !== '' ? ' · ' . e($timeLabel) : '' ?>
                    · <?= (int) $row['media_total'] ?> berkas
                    <?= $guest !== '' ? '· Tamu: ' . e($guest) : '' ?>
                </div>
            </div>
            <div class="album-actions">
                <a class="btn btn-small" href="<?= e(($appUrl ?? '') . '/s/' . $code) ?>" target="_blank" rel="noopener">Lihat Publik</a>
                <form method="post" action="/tenant/events/<?= $eventId ?>/album/sessions/<?= $sessionId ?>/delete"
                      onsubmit="return confirm('Hapus sesi <?= e($code) ?> beserta seluruh berkasnya? Tindakan ini tidak dapat dibatalkan.');">
                    <?= Auth::csrfField() ?>
                    <button class="btn btn-danger btn-small" type="submit">Hapus Sesi</button>
                </form>
            </div>
        </div>

        <?php if ($row['media'] === []): ?>
            <p class="hint" style="margin-top: 10px;">Sesi ini belum memiliki berkas.</p>
        <?php else: ?>
            <div class="album-media-grid">
                <?php foreach ($row['media'] as $m): ?>
                    <?php
                    $mediaId = (int) ($m['id'] ?? 0);
                    $kind = (string) ($m['kind'] ?? 'final');
                    $fname = (string) ($m['file_name'] ?? '');
                    ?>
                    <div class="album-media-card">
                        <img src="/media/<?= $mediaId ?>" alt="<?= e(Album::kindLabel($kind)) ?>" loading="lazy">
                        <div class="meta">
                            <div class="kind"><?= e(Album::kindLabel($kind)) ?></div>
                            <div class="fname" title="<?= e($fname) ?>"><?= e($fname) ?></div>
                            <form method="post" action="/tenant/events/<?= $eventId ?>/album/media/<?= $mediaId ?>/delete"
                                  onsubmit="return confirm('Hapus berkas <?= e($fname) ?> dari galeri dan penyimpanan?');">
                                <?= Auth::csrfField() ?>
                                <button class="btn btn-danger btn-small" type="submit">Hapus</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ((int) $row['media_total'] > count($row['media'])): ?>
                <p class="hint" style="margin-top: 10px;">Menampilkan <?= count($row['media']) ?> dari <?= (int) $row['media_total'] ?> berkas pada sesi ini.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if ($sessionsData !== []): ?>
    <div class="panel-box" style="border-color: var(--err-line, #5A2B2B);">
        <h3 style="margin-top: 0;">Zona Berbahaya</h3>
        <p class="hint" style="margin-bottom: 12px;">
            Kosongkan seluruh album event ini: semua sesi dan berkasnya dihapus permanen
            dari galeri dan penyimpanan. Event-nya sendiri tetap ada.
        </p>
        <form method="post" action="/tenant/events/<?= $eventId ?>/album/wipe"
              onsubmit="return confirm('Kosongkan SELURUH album event ini? Semua sesi dan berkas akan terhapus permanen.');">
            <?= Auth::csrfField() ?>
            <button class="btn btn-danger" type="submit">Hapus Seluruh Isi Album</button>
        </form>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
