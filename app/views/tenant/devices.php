<?php
/**
 * Perangkat (tenant, Milestone 4c; halaman tersendiri sejak
 * v0.6.3): daftar perangkat yang terhubung ke satu-satunya
 * lisensi tenant, supaya tenant melihat akunnya sedang dipakai
 * di perangkat mana saja dan bisa mencabut perangkat langsung
 * dari sini. Daftar tertanam yang sama tetap ada di halaman
 * Lisensi Saya; pencabutan di halaman ini memakai rute yang
 * sama dengan field return=devices agar kembali ke sini.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed>|null $license Satu-satunya lisensi tenant (liveForTenant), bila ada.
 * @var array<int, array<string, mixed>> $devices Baris perangkat milik lisensi itu.
 */

$title = 'Perangkat';
$activeNav = 'devices';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Perangkat yang masuk dengan akunmu untuk memakai aplikasi
    booth. Cabut perangkat yang tidak kamu kenali — aplikasi di
    perangkat itu akan terkunci sampai masuk lagi, dan slotnya
    bebas dipakai perangkat lain.
</p>

<?php if (($license ?? null) === null): ?>
    <div class="panel-box">
        <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 10px;">Belum ada lisensi</h2>
        <p class="hint">
            Akun Anda belum memiliki lisensi. Hubungi admin untuk mengaktifkannya.
        </p>
    </div>
<?php elseif (($devices ?? []) === []): ?>
    <div class="panel-box">
        <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 10px;">Belum ada perangkat terhubung</h2>
        <p class="hint">
            Belum ada perangkat terhubung. Perangkat akan muncul di sini setelah aplikasi booth masuk dengan akun Anda.
        </p>
    </div>
<?php else: ?>
    <?php
    $effective = License::effectiveStatus($license);
    $ent = License::entitlements($license);
    $licenseId = (int) $license['id'];
    ?>
    <div class="panel-box">
        <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 14px;">
            <?= e((string) ($license['plan_name'] ?? $ent['plan_name'])) ?>
            <span class="<?= e(License::statusBadgeClass($effective)) ?>" style="margin-left: 8px;"><?= e(License::statusLabel($effective)) ?></span>
            <span class="hint" style="font-weight: 400; margin-left: 6px;">(<?= e(License::maskedKey($license)) ?>)</span>
        </h2>
        <div class="table-wrap" style="box-shadow: none;">
        <table class="list" style="min-width: 0;">
            <thead>
            <tr>
                <th>Perangkat</th>
                <th>Status</th>
                <th>Pertama Terhubung</th>
                <th>Terakhir Aktif</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($devices as $device): ?>
                <?php
                $deviceRowId = (int) $device['id'];
                $deviceLabel = trim((string) ($device['device_label'] ?? ''));
                $deviceActive = (int) ($device['is_active'] ?? 0) === 1;
                $firstSeen = License::formatDateId((string) ($device['first_seen_at'] ?? ''));
                $lastActive = License::formatDateId((string) ($device['last_heartbeat_at'] ?? $device['first_seen_at'] ?? ''));
                ?>
                <tr>
                    <td><strong><?= e($deviceLabel !== '' ? $deviceLabel : 'Perangkat') ?></strong></td>
                    <td>
                        <?php if ($deviceActive): ?>
                            <span class="status-on">Aktif</span>
                        <?php else: ?>
                            <span class="status-off">Dicabut</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($firstSeen) ?></td>
                    <td><?= e($lastActive) ?></td>
                    <td>
                        <?php if ($deviceActive): ?>
                            <form method="post"
                                  action="/tenant/licenses/<?= $licenseId ?>/devices/<?= $deviceRowId ?>/revoke"
                                  onsubmit="return confirm('Cabut perangkat ini? Aplikasi di perangkat itu akan terkunci sampai masuk lagi, dan slotnya bebas dipakai perangkat lain.');">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="return" value="devices">
                                <button class="btn btn-small btn-danger" type="submit">Cabut Perangkat</button>
                            </form>
                        <?php else: ?>
                            <span class="hint">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
