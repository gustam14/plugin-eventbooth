<?php
/**
 * Lisensi Saya (tenant, Milestone 4): daftar lisensi milik tenant
 * yang login, dengan kunci tampil PENUH (keputusan pemilik v0.5.1)
 * agar tenant bisa menyalinnya untuk aktivasi aplikasi booth.
 * Lisensi lama yang kuncinya tidak tersimpan (diterbitkan sebelum
 * kolom license_key ada) tampil terpotong dengan catatan.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $licenses Baris licenses + plan_name & device_count.
 */

$title = 'Lisensi Saya';
$activeNav = 'licenses';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Daftar lisensi aplikasi booth milikmu. Gunakan kunci lisensi di
    bawah ini untuk aktivasi aplikasi pendamping DSLRBooth di PC booth.
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
            </dl>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
