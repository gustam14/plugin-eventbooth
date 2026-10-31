<?php
/**
 * Daftar plan lisensi (Super Admin).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $plans
 */

$title = 'Plan Lisensi';
$activeNav = 'plans';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

/** Format angka gaya Indonesia; 0 pada kolom batas = tanpa batas. */
$fmtQuota = static function ($value): string {
    $n = (int) $value;
    return $n === 0 ? 'Tanpa batas' : number_format($n, 0, ',', '.');
};
?>
<p class="sub">
    Plan diatur dari halaman ini (tidak di-hardcode). Nilai 0 pada
    kolom batas berarti <strong>tanpa batas</strong>.
    <a class="btn btn-primary btn-small" style="margin-left: 10px;" href="/admin/plans/create">+ Tambah Plan</a>
</p>

<?php if (($plans ?? []) === []): ?>
    <div class="panel-box">
        <p>Belum ada plan. Klik <strong>Tambah Plan</strong> untuk membuat plan pertama.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Nama</th>
            <th>Harga</th>
            <th>Periode</th>
            <th>Perangkat</th>
            <th>Event</th>
            <th>Kuota Drive</th>
            <th>Kuota Sesi</th>
            <th>Retensi</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($plans as $plan): ?>
            <?php $isActive = (int) ($plan['is_active'] ?? 0) === 1; ?>
            <tr>
                <td>
                    <strong><?= e((string) $plan['name']) ?></strong><br>
                    <span style="color: var(--muted); font-size: 13px;"><?= e((string) $plan['slug']) ?></span>
                </td>
                <td>Rp <?= number_format((float) $plan['price'], 0, ',', '.') ?></td>
                <td><?= number_format((int) $plan['billing_period_days'], 0, ',', '.') ?> hari</td>
                <td><?= $fmtQuota($plan['max_devices']) ?></td>
                <td><?= $fmtQuota($plan['max_events']) ?></td>
                <td><?= (int) $plan['drive_quota_gb'] === 0 ? 'Tanpa batas' : number_format((int) $plan['drive_quota_gb'], 0, ',', '.') . ' GB' ?></td>
                <td><?= $fmtQuota($plan['session_quota']) ?></td>
                <td><?= number_format((int) $plan['retention_days'], 0, ',', '.') ?> hari</td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="status-on">Aktif</span>
                    <?php else: ?>
                        <span class="status-off">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-small" href="/admin/plans/<?= (int) $plan['id'] ?>/edit">Ubah</a>
                        <form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/toggle">
                            <?= Auth::csrfField() ?>
                            <button class="btn btn-small <?= $isActive ? 'btn-danger' : '' ?>" type="submit">
                                <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
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
