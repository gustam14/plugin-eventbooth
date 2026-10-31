<?php
/**
 * Daftar paket sesi milik tenant yang login (Milestone 5a).
 * Paket menempel pada event; daftar ini menggabungkan paket dari
 * semua event tenant.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $packages Paket tenant (+ event_name).
 */

$title = 'Paket Sesi';
$activeNav = 'packages';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Paket menentukan cara timer sesi berjalan di booth: per sesi,
    per durasi, atau gabungan — beserta harganya per event.
    <a class="btn btn-primary btn-small" style="margin-left: 10px;" href="/tenant/packages/create">+ Tambah Paket</a>
</p>

<?php if (($packages ?? []) === []): ?>
    <div class="panel-box">
        <p>Belum ada paket sesi. Klik <strong>Tambah Paket</strong> untuk membuat paket pertama pada salah satu eventmu.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Paket</th>
            <th>Event</th>
            <th>Mode Timer</th>
            <th>Sesi</th>
            <th>Durasi</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($packages as $package): ?>
            <?php $isActive = (int) ($package['is_active'] ?? 0) === 1; ?>
            <tr>
                <td><strong><?= e((string) $package['name']) ?></strong></td>
                <td><?= e((string) ($package['event_name'] ?? '')) ?></td>
                <td><?= e(Package::timerModeLabel((string) ($package['timer_mode'] ?? ''))) ?></td>
                <td><?= ($package['session_count'] ?? null) !== null ? number_format((int) $package['session_count'], 0, ',', '.') . ' sesi' : '—' ?></td>
                <td><?= ($package['duration_minutes'] ?? null) !== null ? number_format((int) $package['duration_minutes'], 0, ',', '.') . ' menit' : '—' ?></td>
                <td><?= e(Package::formatPrice($package['price'] ?? 0)) ?></td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="status-on">Aktif</span>
                    <?php else: ?>
                        <span class="status-off">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-small" href="/tenant/packages/<?= (int) $package['id'] ?>/edit">Ubah</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
