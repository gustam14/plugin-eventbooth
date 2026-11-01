<?php
/**
 * Kelola event milik tenant (Super Admin, v0.20.0):
 * pengaturan status/tanggal/retensi/akun penyimpanan +
 * isi album (lihat, hapus berkas/sesi, unduh ZIP).
 *
 * @var string $appName
 * @var string $appUrl
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed> $event
 * @var array<string, mixed>|null $tenant
 * @var array<int, array{session: array<string, mixed>, media: array<int, array<string, mixed>>, media_total: int}> $sessionsData
 * @var array<int, array<string, mixed>> $driveAccounts Akun Drive berstatus connected.
 * @var int $retentionDays Retensi efektif event ini.
 * @var string $retentionPlan Nama plan tenant pemilik event.
 * @var bool $retentionOverridden True bila retensi diatur khusus per event.
 */

$title = 'Kelola Event';
$activeNav = 'admin-events';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$eventId = (int) ($event['id'] ?? 0);
$albumUrl = ($appUrl ?? '') . '/e/' . (string) ($event['slug'] ?? '');
$vendor = $tenant !== null ? Tenant::displayName($tenant) : '—';
$albumMediaTotal = 0;
foreach (($sessionsData ?? []) as $rowData) {
    $albumMediaTotal += (int) ($rowData['media_total'] ?? 0);
}
$currentAccount = isset($event['drive_account_id']) && $event['drive_account_id'] !== null
    ? (int) $event['drive_account_id']
    : 0;
$retOverrideVal = $event['retention_override_days'] ?? null;
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
    Event <strong><?= e((string) ($event['name'] ?? '')) ?></strong> milik tenant
    <a href="/admin/tenants/<?= (int) ($event['tenant_id'] ?? 0) ?>"><?= e($vendor) ?></a> —
    Anda mengelola event ini sebagai Super Admin; perubahan langsung berlaku untuk tenant.
    <a class="btn btn-small" style="margin-left: 10px;" href="/admin/events">← Semua Event</a>
    <a class="btn btn-small" href="<?= e($albumUrl) ?>" target="_blank" rel="noopener">Lihat Album Publik</a>
    <?php if ($albumMediaTotal > 0): ?>
        <a class="btn btn-small" href="/admin/events/<?= $eventId ?>/download">Unduh ZIP Album (<?= $albumMediaTotal ?> berkas)</a>
    <?php endif; ?>
</p>

<div class="panel-box">
    <h3 style="margin-top: 0;">Pengaturan Event</h3>
    <p class="hint" style="margin-bottom: 12px;">
        <strong>Status &amp; tanggal</strong> sama seperti yang dilihat tenant.
        <strong>Retensi album</strong>: kosongkan untuk mengikuti retensi plan tenant
        (<?= e((string) ($retentionPlan ?? '')) ?>); isi angka untuk mengatur khusus event ini
        (0 = tidak pernah dibersihkan). <strong>Akun penyimpanan</strong> hanya dapat dipindah dari sini.
    </p>
    <form method="post" action="/admin/events/<?= $eventId ?>/settings">
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
            <label style="display: block;">
                <span class="hint" style="display: block; margin-bottom: 4px;">Retensi album (hari, kosong = ikut plan)</span>
                <input type="number" name="retention_days" min="0" step="1" style="width: 130px;"
                       value="<?= $retOverrideVal !== null ? (int) $retOverrideVal : '' ?>"
                       placeholder="<?= (int) ($retentionDays ?? 0) ?>">
            </label>
            <label style="display: block;">
                <span class="hint" style="display: block; margin-bottom: 4px;">Akun penyimpanan</span>
                <select name="drive_account_id">
                    <option value="keep" selected>Tetap (<?= $currentAccount > 0 ? 'akun saat ini' : 'belum ditugaskan' ?>)</option>
                    <option value="auto">Otomatis — akun pertama dikaitkan</option>
                    <option value="" disabled>──────────</option>
                    <?php foreach (($driveAccounts ?? []) as $account): ?>
                        <?php
                        $accLabel = trim((string) ($account['label'] ?? '')) !== ''
                            ? (string) $account['label']
                            : (string) ($account['google_email'] ?? '');
                        ?>
                        <option value="<?= (int) $account['id'] ?>" <?= $currentAccount === (int) $account['id'] ? 'disabled' : '' ?>>
                            <?= e($accLabel) ?><?= $currentAccount === (int) $account['id'] ? ' (saat ini)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
        </div>
        <p class="hint" style="margin-top: 10px;">
            Retensi efektif event ini saat ini: <strong><?= (int) ($retentionDays ?? 0) ?> hari</strong>
            <?= ($retentionOverridden ?? false) ? '(diatur khusus untuk event ini)' : '(mengikuti plan tenant)' ?>.
        </p>
    </form>
</div>

<?php if ($sessionsData === []): ?>
    <div class="panel-box">
        <p>Belum ada sesi pada event ini.</p>
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
                <?php if ((int) $row['media_total'] > 0): ?>
                    <a class="btn btn-small" href="/admin/events/<?= $eventId ?>/sessions/<?= $sessionId ?>/download">Unduh ZIP</a>
                <?php endif; ?>
                <a class="btn btn-small" href="<?= e(($appUrl ?? '') . '/s/' . $code) ?>" target="_blank" rel="noopener">Lihat Publik</a>
                <form method="post" action="/admin/events/<?= $eventId ?>/album/sessions/<?= $sessionId ?>/delete"
                      onsubmit="return confirm('Hapus sesi <?= e($code) ?> beserta seluruh berkasnya dari penyimpanan?');">
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
                            <form method="post" action="/admin/events/<?= $eventId ?>/album/media/<?= $mediaId ?>/delete"
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

<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
