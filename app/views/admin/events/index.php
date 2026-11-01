<?php
/**
 * Daftar SEMUA event lintas tenant (Super Admin, v0.20.0).
 *
 * @var string $appName
 * @var string $appUrl
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $events Event semua tenant (+ vendor_name, session_count, first_session_at, media_count).
 * @var array<int, array{days: int, plan_name: string}> $retentionByTenant Retensi plan per tenant.
 */

$title = 'Event Semua Tenant';
$activeNav = 'admin-events';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Seluruh event dari semua tenant. Klik <strong>Kelola</strong> untuk mengatur status,
    tanggal, retensi per event, akun penyimpanan, dan isi albumnya.
</p>

<?php if (($events ?? []) === []): ?>
    <div class="panel-box">
        <p>Belum ada event dari tenant mana pun.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Event</th>
            <th>Tenant</th>
            <th>Tanggal</th>
            <th>Status</th>
            <th>Sesi</th>
            <th>Retensi Album</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <?php
            $status = (string) ($event['status'] ?? 'draft');
            $tenantId = (int) ($event['tenant_id'] ?? 0);
            $vendor = trim((string) ($event['vendor_name'] ?? '')) !== ''
                ? (string) $event['vendor_name']
                : (string) ($event['tenant_name'] ?? '');
            $retOverride = $event['retention_override_days'] ?? null;
            $retPlan = $retentionByTenant[$tenantId] ?? ['days' => 0, 'plan_name' => ''];
            $retDays = $retOverride !== null ? (int) $retOverride : (int) $retPlan['days'];
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
                <td>
                    <strong><?= e((string) $event['name']) ?></strong><br>
                    <span style="color: var(--muted); font-size: 13px;">/e/<?= e((string) $event['slug']) ?></span>
                </td>
                <td>
                    <a href="/admin/tenants/<?= $tenantId ?>"><?= e($vendor) ?></a>
                </td>
                <td><?= e(License::formatDateId(isset($event['starts_at']) ? (string) $event['starts_at'] : null)) ?></td>
                <td><span class="<?= e(Event::statusBadgeClass($status)) ?>"><?= e(Event::statusLabel($status)) ?></span></td>
                <td><?= number_format((int) ($event['session_count'] ?? 0), 0, ',', '.') ?></td>
                <td>
                    <?php if ($retDays <= 0): ?>
                        <span style="color: var(--muted);">Tidak dibatasi</span>
                    <?php elseif ($purgeTs === null): ?>
                        <span style="color: var(--muted);">Belum ada sesi</span>
                    <?php else: ?>
                        <span style="color: <?= $retColor ?>; font-weight: 600;">Mulai <?= e(License::formatDateId(date('Y-m-d H:i:s', $purgeTs))) ?></span><br>
                        <span style="color: var(--muted); font-size: 13px;"><?= $leftDays < 0 ? 'sudah jatuh tempo' : 'sisa ' . $leftDays . ' hari' ?><?= $retOverride !== null ? ' · khusus' : ' · plan ' . e((string) $retPlan['plan_name']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-small" href="/admin/events/<?= (int) $event['id'] ?>">Kelola</a>
                        <a class="btn btn-small" href="<?= e(($appUrl ?? '') . '/e/' . (string) $event['slug']) ?>" target="_blank" rel="noopener">Lihat Album</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
