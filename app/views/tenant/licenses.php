<?php
/**
 * Lisensi Saya (tenant, Milestone 4): daftar lisensi milik tenant
 * yang login, dengan kunci tampil PENUH (keputusan pemilik v0.5.1)
 * agar tenant bisa menyalinnya untuk aktivasi aplikasi booth.
 * Lisensi lama yang kuncinya tidak tersimpan (diterbitkan sebelum
 * kolom license_key ada) tampil terpotong dengan catatan.
 *
 * Sejak M4c aktivasi melekat pada akun: lisensi yang sedang
 * dipakai aplikasi ditandai "Dipakai Aplikasi", dan tiap lisensi
 * memuat daftar perangkat terhubung yang bisa dicabut tenant
 * sendiri untuk membebaskan slot perangkat.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $licenses Baris licenses + plan_name & device_count.
 * @var int|null $currentLicenseId ID lisensi yang dipakai aplikasi (currentForTenant).
 * @var array<int, array<int, array<string, mixed>>> $devicesByLicense Baris perangkat per ID lisensi.
 * @var array<int, array<string, mixed>> $checkoutPlans Plan berbayar aktif yang dapat dibeli (M6).
 * @var bool $midtransReady Midtrans platform sudah dikonfigurasi Super Admin (M6).
 * @var array<int, array<string, mixed>> $orders Riwayat pesanan lisensi tenant ini (M6).
 */

$title = 'Lisensi Saya';
$activeNav = 'licenses';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Daftar lisensi aplikasi booth milikmu. Aplikasi booth yang masuk
    dengan akun Anda otomatis memakai lisensi aktif dengan masa
    berlaku terpanjang. Kunci lisensi di bawah ini tetap tersedia
    sebagai jalur cadangan aktivasi di PC booth.
</p>

<?php if (($licenses ?? []) === []): ?>
    <div class="panel-box">
        <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 10px;">Belum ada lisensi</h2>
        <p class="hint">
            Tenant kamu belum memiliki lisensi. Lisensi diterbitkan oleh
            EventBooth setelah pembelian plan — hubungi kami bila kamu
            sudah membeli plan tetapi lisensinya belum muncul di sini.
        </p>
    </div>
<?php else: ?>
    <?php foreach ($licenses as $license): ?>
        <?php
        $effective = License::effectiveStatus($license);
        $ent = License::entitlements($license);
        $daysLeft = License::daysLeft($license);
        ?>
        <div class="panel-box" style="margin-bottom: 22px;">
            <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 14px;">
                <?= e((string) ($license['plan_name'] ?? $ent['plan_name'])) ?>
                <span class="<?= e(License::statusBadgeClass($effective)) ?>" style="margin-left: 8px;"><?= e(License::statusLabel($effective)) ?></span>
                <?php if (($currentLicenseId ?? null) !== null && (int) $license['id'] === (int) $currentLicenseId): ?>
                    <span class="status-on" style="margin-left: 6px;">Dipakai Aplikasi</span>
                <?php endif; ?>
                <?php if ($effective === 'active' && $daysLeft !== null && $daysLeft <= 30 && $daysLeft >= 0): ?>
                    <span class="status-warn" style="margin-left: 6px;"><?= $daysLeft ?> hari lagi</span>
                <?php endif; ?>
            </h2>
            <dl class="profile">
                <dt>Kunci Lisensi</dt>
                <dd>
                    <?php if (!empty($license['license_key'])): ?>
                        <span class="key-full" style="font-size: 15.5px;"><?= e((string) $license['license_key']) ?></span>
                    <?php else: ?>
                        <code><?= e(License::maskedKey($license)) ?></code><br>
                        <span class="hint">kunci tidak tersimpan (lisensi lama) — hubungi EventBooth bila kamu memerlukan kunci lengkapnya.</span>
                    <?php endif; ?>
                </dd>
                <dt>Berlaku</dt>
                <dd>
                    <?= e(License::formatDateId((string) ($license['starts_at'] ?? ''))) ?> —
                    <?= empty($license['expires_at']) ? 'Tanpa kedaluwarsa' : e(License::formatDateId((string) $license['expires_at'])) ?>
                </dd>
                <dt>Perangkat</dt>
                <dd>
                    <?= (int) ($license['device_count'] ?? 0) ?> /
                    <?= $ent['max_devices'] === 0 ? 'tanpa batas' : (string) $ent['max_devices'] ?> perangkat terdaftar
                </dd>
                <dt>Perangkat Terhubung</dt>
                <dd>
                    <?php $devices = $devicesByLicense[(int) $license['id']] ?? []; ?>
                    <?php if ($devices === []): ?>
                        <span class="hint">Belum ada perangkat yang terhubung ke lisensi ini.</span>
                    <?php else: ?>
                        <ul style="margin: 0; padding-left: 18px;">
                            <?php foreach ($devices as $device): ?>
                                <?php
                                $deviceRowId = (int) $device['id'];
                                $deviceLabel = trim((string) ($device['device_label'] ?? ''));
                                $deviceActive = (int) ($device['is_active'] ?? 0) === 1;
                                $firstSeen = License::formatDateId((string) ($device['first_seen_at'] ?? ''));
                                $lastActive = License::formatDateId((string) ($device['last_heartbeat_at'] ?? $device['first_seen_at'] ?? ''));
                                ?>
                                <li style="margin-bottom: 10px;">
                                    <strong><?= e($deviceLabel !== '' ? $deviceLabel : 'Perangkat') ?></strong>
                                    <?php if ($deviceActive): ?>
                                        <span class="status-on" style="margin-left: 6px;">Aktif</span>
                                    <?php else: ?>
                                        <span class="status-off" style="margin-left: 6px;">Dicabut</span>
                                    <?php endif; ?>
                                    <br>
                                    <span class="hint">Pertama terhubung: <?= e($firstSeen) ?> · Terakhir aktif: <?= e($lastActive) ?></span>
                                    <?php if ($deviceActive): ?>
                                        <form method="post"
                                              action="/tenant/licenses/<?= (int) $license['id'] ?>/devices/<?= $deviceRowId ?>/revoke"
                                              onsubmit="return confirm('Cabut perangkat ini? Aplikasi di perangkat itu akan terkunci sampai masuk lagi, dan slotnya bebas dipakai perangkat lain.');"
                                              style="display: inline-block; margin-left: 8px;">
                                            <?= Auth::csrfField() ?>
                                            <button class="btn btn-small btn-danger" type="submit">Cabut Perangkat</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="margin-top: 0;">Beli / Perpanjang Lisensi</h2>
    <?php if (!($midtransReady ?? false)): ?>
        <p class="hint">
            Pembayaran online belum diaktifkan oleh admin. Untuk
            perpanjangan, silakan hubungi EventBooth — masa aktifmu
            dapat ditambahkan secara manual.
        </p>
    <?php elseif (($checkoutPlans ?? []) === []): ?>
        <p class="hint">Belum ada plan berbayar yang aktif saat ini.</p>
    <?php else: ?>
        <p class="sub">
            Membeli plan akan <strong>menambah masa aktif</strong>
            lisensimu sesuai durasi plan (bukan membuat lisensi baru),
            dan batas/fitur lisensi mengikuti plan terakhir yang dibeli.
            Masa aktif bertambah otomatis setelah pembayaran dinyatakan
            lunas oleh Midtrans.
        </p>
        <?php foreach ($checkoutPlans as $plan): ?>
            <?php
            $duration = (int) ($plan['license_duration_days'] ?? 0);
            $durationLabel = $duration === 0
                ? 'tanpa kedaluwarsa'
                : 'masa aktif +' . $duration . ' hari';
            ?>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 10px 0; border-top: 1px solid var(--line, #e5e7eb);">
                <div>
                    <strong><?= e((string) $plan['name']) ?></strong><br>
                    <span class="hint">Rp <?= e(number_format((float) $plan['price'], 0, ',', '.')) ?> · <?= e($durationLabel) ?> · maks <?= (int) ($plan['max_devices'] ?? 1) ?> perangkat</span>
                </div>
                <form method="post" action="/tenant/licenses/checkout"
                      onsubmit="return confirm('Buat pesanan sebesar Rp <?= e(number_format((float) $plan['price'], 0, ',', '.')) ?> untuk plan ini?');">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                    <button class="btn btn-primary" type="submit">Beli Sekarang</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (($orders ?? []) !== []): ?>
<div class="panel-box">
    <h2 style="margin-top: 0;">Riwayat Pembayaran</h2>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Waktu</th>
            <th>Plan</th>
            <th>Order ID</th>
            <th>Jumlah</th>
            <th>Status</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <?php
            $planLabel = (string) ($order['plan_name'] ?? '');
            if ($planLabel === '' && !empty($order['plan_snapshot'])) {
                $snap = json_decode((string) $order['plan_snapshot'], true);
                $planLabel = is_array($snap) ? (string) ($snap['name'] ?? '') : '';
            }
            $canPay = (string) $order['status'] === 'pending' && !empty($order['snap_token']);
            ?>
            <tr>
                <td><?= e(License::formatDateId((string) $order['created_at'])) ?></td>
                <td><?= e($planLabel !== '' ? $planLabel : '—') ?></td>
                <td><code><?= e((string) $order['order_id']) ?></code></td>
                <td>Rp <?= e(number_format((float) $order['gross_amount'], 0, ',', '.')) ?></td>
                <td><?= e(Midtrans::statusLabel((string) $order['status'])) ?></td>
                <td>
                    <?php if ($canPay): ?>
                        <a class="btn btn-small btn-primary" href="/tenant/licenses/pay/<?= e((string) $order['order_id']) ?>">Bayar</a>
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
