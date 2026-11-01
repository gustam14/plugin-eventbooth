<?php
/**
 * Kelola Album event (v0.14.0; pilihan massal + hapus event
 * v0.14.1): tenant menghapus media/sesi/event dan mengunggah
 * foto/GIF/boomerang manual ke galeri event.
 *
 * @var string $appName
 * @var string $appUrl
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed> $event
 * @var array<int, array{session: array<string, mixed>, media: array<int, array<string, mixed>>, media_total: int}> $sessionsData
 * @var int $retentionDays Retensi album plan tenant (0 = tanpa retensi) — kode warna v0.17.0.
 * @var string $retentionPlan Nama plan tenant untuk keterangan retensi.
 */

$title = 'Kelola Album';
$activeNav = 'events';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$eventId = (int) ($event['id'] ?? 0);
$albumUrl = ($appUrl ?? '') . '/e/' . (string) ($event['slug'] ?? '');
$albumMediaTotal = 0;
$oldestSessionAt = null;
foreach (($sessionsData ?? []) as $rowData) {
    $albumMediaTotal += (int) ($rowData['media_total'] ?? 0);
    $created = (string) ($rowData['session']['created_at'] ?? '');
    if ($created !== '' && ($oldestSessionAt === null || $created < $oldestSessionAt)) {
        $oldestSessionAt = $created;
    }
}
?>
<style>
    .album-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 12px; margin-top: 14px; }
    .album-media-card { border: 1px solid var(--line, #262B33); border-radius: 10px; overflow: hidden; background: rgba(255,255,255,.02); }
    .album-media-card .thumb { position: relative; }
    .album-media-card img { width: 100%; height: 104px; object-fit: cover; display: block; background: #11141A; }
    .album-media-card .pick { position: absolute; top: 6px; left: 6px; background: rgba(5,6,8,.72); border-radius: 7px; padding: 3px 6px; display: flex; align-items: center; }
    .album-media-card .pick input { width: 18px; height: 18px; accent-color: #C9A24B; cursor: pointer; margin: 0; }
    .album-media-card .meta { padding: 8px 10px 10px; }
    .album-media-card .kind { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--gold, #C9A24B); }
    .album-media-card .fname { font-size: 12px; color: var(--muted, #9AA3AF); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 2px 0 8px; }
    .album-session-head { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
    .album-session-head .info { color: var(--muted, #9AA3AF); font-size: 13px; }
    .album-actions { display: flex; gap: 8px; align-items: center; }
    .album-actions form { display: inline; }
    .album-pick { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted, #9AA3AF); cursor: pointer; }
    .album-pick input { width: 17px; height: 17px; accent-color: #C9A24B; cursor: pointer; margin: 0; }
    .bulk-bar { position: sticky; bottom: 12px; z-index: 6; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; box-shadow: 0 12px 32px rgba(0,0,0,.45); }
    .panel-box.ret-warn { border-left: 4px solid #D9A441; }
    .panel-box.ret-danger { border-left: 4px solid #D9534F; }
    .ret-badge { display: inline-block; margin-left: 8px; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 700; vertical-align: 2px; }
    .ret-badge.ret-warn { background: rgba(217,164,65,.18); color: #E8BC63; border: 1px solid rgba(217,164,65,.45); }
    .ret-badge.ret-danger { background: rgba(217,83,79,.16); color: #F0958F; border: 1px solid rgba(217,83,79,.45); }
    .ret-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin: 0 4px 0 10px; vertical-align: 0; }
    .ret-dot.warn { background: #D9A441; }
    .ret-dot.danger { background: #D9534F; }
</style>

<p class="sub">
    Kelola isi galeri event <strong><?= e((string) ($event['name'] ?? '')) ?></strong> —
    hapus foto/GIF yang tidak layak tampil, atau unggah berkas tambahan secara manual.
    Perubahan langsung terlihat di album publik.
    <a class="btn btn-small" style="margin-left: 10px;" href="/tenant/events">← Daftar Event</a>
    <a class="btn btn-small" href="<?= e($albumUrl) ?>" target="_blank" rel="noopener">Lihat Album Publik</a>
    <?php if ($albumMediaTotal > 0): ?>
        <a class="btn btn-small" href="/tenant/events/<?= $eventId ?>/album/download">Unduh ZIP Album (<?= $albumMediaTotal ?> berkas)</a>
    <?php endif; ?>
</p>

<div class="panel-box">
    <h3 style="margin-top: 0;">Pengaturan Event</h3>
    <p class="hint" style="margin-bottom: 12px;">
        <strong>Status</strong> menentukan kemunculan event di aplikasi booth: Draf &amp; Aktif tampil;
        Selesai &amp; Diarsipkan hilang dari aplikasi (sesi baru tidak bisa dimulai), tetapi galeri
        publik tetap dapat dilihat. <strong>Tanggal</strong> bersifat keterangan — tampil di daftar
        Event dan di aplikasi booth, tidak mengunci sesi.
    </p>
    <form method="post" action="/tenant/events/<?= $eventId ?>/album/event-settings">
        <?= Auth::csrfField() ?>
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: end;">
            <label style="display: block;">
                <span class="hint" style="display: block; margin-bottom: 4px;">Status</span>
                <select name="status">
                    <?php foreach (Event::STATUSES as $st): ?>
                        <option value="<?= e($st) ?>" <?= (string) ($event['status'] ?? 'draft') === $st ? 'selected' : '' ?>>
                            <?= e(Event::statusLabel($st)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="display: block;">
                <span class="hint" style="display: block; margin-bottom: 4px;">Tanggal &amp; jam mulai</span>
                <input type="datetime-local" name="starts_at"
                       value="<?= e(!empty($event['starts_at']) ? str_replace(' ', 'T', substr((string) $event['starts_at'], 0, 16)) : '') ?>">
            </label>
            <label style="display: block;">
                <span class="hint" style="display: block; margin-bottom: 4px;">Tanggal &amp; jam selesai</span>
                <input type="datetime-local" name="ends_at"
                       value="<?= e(!empty($event['ends_at']) ? str_replace(' ', 'T', substr((string) $event['ends_at'], 0, 16)) : '') ?>">
            </label>
            <button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
        </div>
    </form>
</div>

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

<?php if (($retentionDays ?? 0) > 0): ?>
<div class="panel-box">
    <h3 style="margin-top: 0;">Retensi Album — <?= ($retentionOverridden ?? false) ? 'Diatur Admin' : 'Plan ' . e((string) ($retentionPlan ?? '')) ?></h3>
    <p class="hint" style="margin: 0;">
        <?php if (($retentionOverridden ?? false)): ?>
            Admin mengatur <strong>retensi khusus <?= (int) $retentionDays ?> hari</strong> untuk event ini
            (mengabaikan retensi plan). Sesi dibersihkan otomatis dari galeri dan penyimpanan
            tanpa pemberitahuan setelah melewati masa itu.
        <?php else: ?>
            Sesi pada plan Anda disimpan <strong><?= (int) $retentionDays ?> hari</strong>, lalu dibersihkan
            otomatis dari galeri dan penyimpanan tanpa pemberitahuan.
        <?php endif; ?>
        <?php if ($oldestSessionAt !== null): ?>
            Sesi tertua event ini mulai dibersihkan pada
            <strong><?= e(License::formatDateId(date('Y-m-d H:i:s', strtotime((string) $oldestSessionAt) + (int) $retentionDays * 86400))) ?></strong>.
        <?php endif; ?>
        Pantau dari kode warna tiap sesi:
        <span class="ret-dot warn"></span>kuning = sisa retensi 14 hari atau kurang
        <span class="ret-dot danger"></span>merah = sisa 3 hari atau kurang / sudah lewat.
        Unduh ZIP sesi yang masih Anda perlukan sebelum masa simpannya habis.
    </p>
</div>
<?php endif; ?>

<?php if ($sessionsData === []): ?>
    <div class="panel-box">
        <p>Belum ada sesi pada event ini. Sesi muncul otomatis saat aplikasi booth dipakai tamu, atau unggah berkas pertama lewat formulir di atas untuk membuat sesi manual.</p>
    </div>
<?php endif; ?>

<?php if ($sessionsData !== []): ?>
    <form class="panel-box bulk-bar" id="bulkDeleteForm" method="post"
          action="/tenant/events/<?= $eventId ?>/album/bulk-delete"
          onsubmit="return ebConfirmBulk();">
        <?= Auth::csrfField() ?>
        <span class="hint" style="margin: 0;">
            Centang berkas atau sesi di bawah, lalu hapus sekaligus.
            <strong id="bulkCount" style="color: var(--ink, #EDEFF3);">0 terpilih</strong>
        </span>
        <span class="album-actions">
            <button class="btn btn-small" type="button" onclick="ebClearSelection();">Batalkan Pilihan</button>
            <button class="btn btn-danger btn-small" id="bulkSubmit" type="submit" disabled>Hapus yang Dipilih</button>
        </span>
    </form>
<?php endif; ?>

<?php foreach ($sessionsData as $row): ?>
    <?php
    $s = $row['session'];
    $sessionId = (int) $s['id'];
    $code = (string) ($s['code'] ?? '');
    $createdLabel = License::formatDateId((string) ($s['created_at'] ?? ''));
    $timeLabel = isset($s['created_at']) ? substr((string) $s['created_at'], 11, 5) : '';
    $guest = trim((string) ($s['guest_name'] ?? ''));
    $retClass = '';
    $retBadge = '';
    $retLeft = null;
    if (($retentionDays ?? 0) > 0 && !empty($s['created_at'])) {
        $ageDays = (int) floor((time() - strtotime((string) $s['created_at'])) / 86400);
        $retLeft = (int) $retentionDays - $ageDays;
        if ($retLeft < 0) {
            $retClass = 'ret-danger';
            $retBadge = 'Lewat masa retensi — menunggu pembersihan';
        } elseif ($retLeft <= 3) {
            $retClass = 'ret-danger';
            $retBadge = 'Sisa retensi ' . $retLeft . ' hari';
        } elseif ($retLeft <= 14) {
            $retClass = 'ret-warn';
            $retBadge = 'Sisa retensi ' . $retLeft . ' hari';
        }
    }
    ?>
    <div class="panel-box <?= $retClass ?>">
        <div class="album-session-head">
            <div>
                <label class="album-pick" title="Pilih sesi ini untuk dihapus massal">
                    <input type="checkbox" form="bulkDeleteForm" name="session_ids[]" value="<?= $sessionId ?>"
                           class="session-check" onchange="ebCount();">
                </label>
                <strong style="font-size: 16px;">Sesi <?= e($code) ?></strong>
                <?php if ($retBadge !== ''): ?>
                    <span class="ret-badge <?= $retClass ?>"><?= e($retBadge) ?></span>
                <?php endif; ?>
                <div class="info">
                    <?= e($createdLabel) ?><?= $timeLabel !== '' ? ' · ' . e($timeLabel) : '' ?>
                    · <?= (int) $row['media_total'] ?> berkas
                    <?= $guest !== '' ? '· Tamu: ' . e($guest) : '' ?>
                    <?= $retLeft !== null && $retBadge === '' ? '· Sisa retensi: ' . $retLeft . ' hari' : '' ?>
                </div>
            </div>
            <div class="album-actions">
                <label class="album-pick">
                    <input type="checkbox" class="select-all-session" data-session="<?= $sessionId ?>"
                           onchange="ebToggleSessionAll(this);">
                    Pilih semua berkas
                </label>
                <?php if ((int) $row['media_total'] > 0): ?>
                    <a class="btn btn-small" href="/tenant/events/<?= $eventId ?>/album/sessions/<?= $sessionId ?>/download">Unduh ZIP</a>
                <?php endif; ?>
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
                        <div class="thumb">
                            <img src="/media/<?= $mediaId ?>" alt="<?= e(Album::kindLabel($kind)) ?>" loading="lazy">
                            <span class="pick">
                                <input type="checkbox" form="bulkDeleteForm" name="media_ids[]" value="<?= $mediaId ?>"
                                       class="media-check" data-session="<?= $sessionId ?>" onchange="ebCount();"
                                       aria-label="Pilih berkas <?= e($fname) ?>">
                            </span>
                        </div>
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
            <strong>Kosongkan album:</strong> semua sesi dan berkas event ini dihapus permanen
            dari galeri dan penyimpanan. Event-nya sendiri tetap ada.
        </p>
        <form method="post" action="/tenant/events/<?= $eventId ?>/album/wipe"
              onsubmit="return confirm('Kosongkan SELURUH album event ini? Semua sesi dan berkas akan terhapus permanen.');" style="margin-bottom: 18px;">
            <?= Auth::csrfField() ?>
            <button class="btn btn-danger" type="submit">Hapus Seluruh Isi Album</button>
        </form>
        <p class="hint" style="margin-bottom: 12px;">
            <strong>Hapus event:</strong> event ini dihapus permanen beserta seluruh sesi,
            berkas di penyimpanan, paket sesi, dan pengaturannya. Riwayat pembayaran tamu
            tetap tersimpan sebagai catatan. Tindakan ini tidak dapat dibatalkan.
        </p>
        <form method="post" action="/tenant/events/<?= $eventId ?>/album/delete-event"
              onsubmit="return confirm('Hapus EVENT <?= e((string) ($event['name'] ?? '')) ?> secara permanen beserta semua sesi, berkas, dan paketnya?');">
            <?= Auth::csrfField() ?>
            <button class="btn btn-danger" type="submit">Hapus Event Ini</button>
        </form>
    </div>
<?php endif; ?>

<script>
function ebSelectedCount() {
    return document.querySelectorAll('.media-check:checked, .session-check:checked').length;
}
function ebCount() {
    var n = ebSelectedCount();
    var label = document.getElementById('bulkCount');
    if (label) { label.textContent = n + ' terpilih'; }
    var submit = document.getElementById('bulkSubmit');
    if (submit) { submit.disabled = n === 0; }
}
function ebToggleSessionAll(box) {
    document.querySelectorAll('.media-check[data-session="' + box.getAttribute('data-session') + '"]')
        .forEach(function (cb) { cb.checked = box.checked; });
    ebCount();
}
function ebClearSelection() {
    document.querySelectorAll('.media-check:checked, .session-check:checked, .select-all-session:checked')
        .forEach(function (cb) { cb.checked = false; });
    ebCount();
}
function ebConfirmBulk() {
    var n = ebSelectedCount();
    if (n === 0) { return false; }
    return confirm('Hapus ' + n + ' item terpilih beserta file-nya di penyimpanan? Tindakan ini tidak dapat dibatalkan.');
}
</script>

<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
