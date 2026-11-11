<?php
/**
 * Daftar event milik tenant yang login (Milestone 5a).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $events Event tenant (+ session_count, first_session_at, media_count).
 * @var string $appUrl
 * @var int $retentionDays Retensi album plan tenant (0 = tanpa batas) — kolom Retensi v0.18.0.
 * @var string $retentionPlan Nama plan tenant.
 */

$title = 'Event';
$activeNav = 'events';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Event adalah satu acara dengan album publiknya sendiri.
    Setiap event menampung paket sesi dan hasil foto tamu.
    <a class="btn btn-primary btn-small" style="margin-left: 10px;" href="/tenant/events/create">+ Buat Event</a>
</p>

<?php if (($events ?? []) === []): ?>
    <div class="panel-box">
        <p>Belum ada event. Klik <strong>Buat Event</strong> untuk membuat event pertamamu.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list list-cards">
        <thead>
        <tr>
            <th>Event</th>
            <th>Tanggal</th>
            <th>Status</th>
            <th>Pembayaran Tamu</th>
            <th>Sesi</th>
            <th>Retensi Album</th>
            <th>Album</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <?php
            $status = (string) ($event['status'] ?? 'draft');
            $lockOn = (int) ($event['payment_lock_enabled'] ?? 1) === 1;
            $retOverride = $event['retention_override_days'] ?? null;
            $retDays = $retOverride !== null ? (int) $retOverride : (int) ($retentionDays ?? 0);
            $purgeTs = null;
            $leftDays = null;
            $retColor = 'var(--ink, #EDEFF3)';
            if ($retDays > 0 && !empty($event['first_session_at'])) {
                $purgeTs = strtotime((string) $event['first_session_at']) + $retDays * 86400;
                $leftDays = (int) floor(($purgeTs - time()) / 86400);
                $retColor = $leftDays <= 3 ? '#F0958F' : ($leftDays <= 14 ? '#E8BC63' : 'var(--ink, #EDEFF3)');
            }
            ?>
            <tr>
                <td class="cell-main">
                    <div class="ev-name"><?= e((string) $event['name']) ?></div>
                    <div class="ev-sub">/e/<?= e((string) $event['slug']) ?></div>
                    <?php if (!empty($event['client_name'])): ?>
                        <div class="ev-sub">Klien: <?= e((string) $event['client_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($event['client_package_name'])): ?>
                        <div class="ev-sub">Paket: <?= e((string) $event['client_package_name']) ?></div>
                    <?php endif; ?>
                    <?php $clientRemaining = Event::clientRemaining($event); ?>
                    <?php if ($clientRemaining !== null): ?>
                        <div class="ev-due">
                            <?php if ($clientRemaining > 0): ?>
                                Sisa klien: <strong><?= e(Package::formatPrice($clientRemaining)) ?></strong>
                            <?php else: ?>
                                <span class="status-on">Klien lunas</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($event['client_discount']) && (float) $event['client_discount'] > 0): ?>
                            <div class="ev-sub">termasuk diskon <?= e(Package::formatPrice($event['client_discount'])) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td data-label="Tanggal"><?= e(License::formatDateId(isset($event['starts_at']) ? (string) $event['starts_at'] : null)) ?></td>
                <td data-label="Status"><span class="<?= e(Event::statusBadgeClass($status)) ?>"><?= e(Event::statusLabel($status)) ?></span></td>
                <td data-label="Pembayaran Tamu">
                    <?php if ($lockOn): ?>
                        <span class="status-warn">Terkunci (bayar dulu)</span>
                    <?php else: ?>
                        <span class="status-on">Bebas (tanpa bayar)</span>
                    <?php endif; ?>
                </td>
                <td data-label="Sesi"><?= number_format((int) ($event['session_count'] ?? 0), 0, ',', '.') ?></td>
                <td data-label="Retensi Album">
                    <?php if ($retDays <= 0): ?>
                        <span style="color: var(--muted);">Tidak dibatasi</span>
                    <?php elseif ($purgeTs === null): ?>
                        <span style="color: var(--muted);">Belum ada sesi</span>
                    <?php else: ?>
                        <span style="color: <?= $retColor ?>; font-weight: 600;">Mulai <?= e(License::formatDateId(date('Y-m-d H:i:s', $purgeTs))) ?></span><br>
                        <span style="color: var(--muted); font-size: 13px;"><?= $leftDays < 0 ? 'sudah jatuh tempo' : 'sisa ' . $leftDays . ' hari' ?><?= $retOverride !== null ? ' · retensi khusus' : '' ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="Album">
                    <a href="<?= e(($appUrl ?? '') . '/e/' . (string) $event['slug']) ?>" target="_blank" rel="noopener">Lihat Album</a>
                </td>
                <td class="cell-actions">
                    <div class="row-actions">
                        <a class="btn btn-small" href="/tenant/events/<?= (int) $event['id'] ?>/album">Kelola Album</a>
                        <?php if ((int) ($event['media_count'] ?? 0) > 0): ?>
                            <a class="btn btn-small" href="/tenant/events/<?= (int) $event['id'] ?>/album/download">Unduh ZIP</a>
                        <?php endif; ?>
                        <a class="btn btn-small" href="/tenant/events/<?= (int) $event['id'] ?>/edit">Ubah</a>
                        <form method="post" action="/tenant/events/<?= (int) $event['id'] ?>/album/delete-event"
                              onsubmit="return confirm('Hapus EVENT <?= e((string) $event['name']) ?> secara permanen beserta semua sesi, berkas, dan paketnya?');">
                            <?= Auth::csrfField() ?>
                            <button class="btn btn-danger btn-small" type="submit">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
