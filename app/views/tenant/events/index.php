<?php
/**
 * Daftar event milik tenant yang login (Milestone 5a).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $events Event tenant (+ session_count).
 * @var string $appUrl
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
    <table class="list">
        <thead>
        <tr>
            <th>Event</th>
            <th>Tanggal</th>
            <th>Status</th>
            <th>Pembayaran Tamu</th>
            <th>Sesi</th>
            <th>Album</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <?php
            $status = (string) ($event['status'] ?? 'draft');
            $lockOn = (int) ($event['payment_lock_enabled'] ?? 1) === 1;
            ?>
            <tr>
                <td>
                    <strong><?= e((string) $event['name']) ?></strong><br>
                    <span style="color: var(--muted); font-size: 13px;">/e/<?= e((string) $event['slug']) ?></span>
                </td>
                <td><?= e(License::formatDateId(isset($event['starts_at']) ? (string) $event['starts_at'] : null)) ?></td>
                <td><span class="<?= e(Event::statusBadgeClass($status)) ?>"><?= e(Event::statusLabel($status)) ?></span></td>
                <td>
                    <?php if ($lockOn): ?>
                        <span class="status-warn">Terkunci (bayar dulu)</span>
                    <?php else: ?>
                        <span class="status-on">Bebas (tanpa bayar)</span>
                    <?php endif; ?>
                </td>
                <td><?= number_format((int) ($event['session_count'] ?? 0), 0, ',', '.') ?></td>
                <td>
                    <a href="<?= e(($appUrl ?? '') . '/e/' . (string) $event['slug']) ?>" target="_blank" rel="noopener">Lihat Album</a>
                </td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-small" href="/tenant/events/<?= (int) $event['id'] ?>/album">Kelola Album</a>
                        <a class="btn btn-small" href="/tenant/events/<?= (int) $event['id'] ?>/edit">Ubah</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
