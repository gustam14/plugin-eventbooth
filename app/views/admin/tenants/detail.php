<?php
/**
 * Detail tenant (Super Admin, Milestone 4): profil lengkap, akun
 * login, dan pengelolaan lisensi.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed> $tenant Baris tenant.
 * @var array<int, array<string, mixed>> $tenantUsers Baris users milik tenant.
 * @var array<int, array<string, mixed>> $licenses Baris licenses + plan_name & device_count.
 * @var array<int, array<int, array<string, mixed>>> $devicesByLicense Baris perangkat per ID lisensi (M4c).
 * @var array<int, array<string, mixed>> $activePlans Plan aktif untuk formulir penerbitan.
 * @var string|null $newKey Kunci lisensi yang BARU diterbitkan (tampil satu kali).
 */

$tenantName = Tenant::displayName($tenant);
$title = 'Tenant: ' . $tenantName;
$activeNav = 'tenants';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$wa = Tenant::whatsappOf($tenant);
$tenantId = (int) $tenant['id'];
$isActive = (string) ($tenant['status'] ?? 'active') === 'active';

$roleLabel = static function (string $role): string {
    return match ($role) {
        'tenant_admin' => 'Admin Tenant',
        'tenant_staff' => 'Staf Tenant',
        default => $role,
    };
};
?>
<p class="sub">
    <a href="/admin/tenants">&larr; Kembali ke daftar tenant</a>
</p>

<?php if (($newKey ?? null) !== null): ?>
    <div class="notice">
        <strong>Lisensi berhasil diterbitkan — salin kuncinya sekarang.</strong><br>
        Kunci utuh hanya ditampilkan satu kali ini; sesudahnya yang
        tersimpan hanya awalan untuk identifikasi. Berikan kunci ini
        ke tenant untuk aktivasi aplikasi booth:<br>
        <code style="font-size: 17px; word-break: break-all;"><?= e((string) $newKey) ?></code>
    </div>
<?php endif; ?>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 14px;">
        Profil Tenant
        <?php if ($isActive): ?>
            <span class="status-on" style="margin-left: 8px;">Aktif</span>
        <?php else: ?>
            <span class="status-off" style="margin-left: 8px;">Nonaktif</span>
        <?php endif; ?>
    </h2>
    <dl class="profile">
        <dt>Nama Vendor</dt>
        <dd><strong><?= e($tenantName) ?></strong></dd>
        <dt>Penanggung Jawab</dt>
        <dd><?= e((string) ($tenant['contact_name'] ?? '')) !== '' ? e((string) $tenant['contact_name']) : '—' ?></dd>
        <dt>Email</dt>
        <dd><?= !empty($tenant['email']) ? e((string) $tenant['email']) : '—' ?></dd>
        <dt>WhatsApp</dt>
        <dd>
            <?php if ($wa !== null): ?>
                <?= e($wa) ?>
                <a class="btn btn-small" style="margin-left: 10px;" target="_blank" rel="noopener"
                   href="<?= e(Whatsapp::link($wa)) ?>">Chat WhatsApp</a>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>
        <dt>Alamat</dt>
        <dd><?= !empty($tenant['address']) ? nl2br(e((string) $tenant['address'])) : '—' ?></dd>
        <dt>Catatan</dt>
        <dd><?= !empty($tenant['notes']) ? nl2br(e((string) $tenant['notes'])) : '—' ?></dd>
        <dt>Terdaftar</dt>
        <dd><?= e(License::formatDateId((string) ($tenant['created_at'] ?? ''))) ?></dd>
    </dl>
    <div class="form-actions">
        <a class="btn" href="/admin/tenants/<?= $tenantId ?>/edit">Ubah Tenant</a>
    </div>
</div>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 14px;">Akun Login</h2>
    <?php if (($tenantUsers ?? []) === []): ?>
        <p class="hint" style="margin-bottom: 16px;">Belum ada akun login untuk tenant ini.</p>
    <?php else: ?>
        <div class="table-wrap" style="box-shadow: none; margin-bottom: 18px;">
        <table class="list" style="min-width: 0;">
            <thead>
            <tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th>Login Terakhir</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tenantUsers as $tenantUser): ?>
                <tr>
                    <td><strong><?= e((string) $tenantUser['name']) ?></strong></td>
                    <td><?= e((string) $tenantUser['email']) ?></td>
                    <td><?= e($roleLabel((string) $tenantUser['role'])) ?></td>
                    <td>
                        <?php if ((int) ($tenantUser['is_active'] ?? 0) === 1): ?>
                            <span class="status-on">Aktif</span>
                        <?php else: ?>
                            <span class="status-off">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($tenantUser['last_login_at']) ? e((string) $tenantUser['last_login_at']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/tenants/<?= $tenantId ?>/users">
        <?= Auth::csrfField() ?>
        <div class="form-grid">
            <div class="field">
                <label for="user_name">Nama Pengguna</label>
                <input type="text" id="user_name" name="user_name" maxlength="120" required>
            </div>
            <div class="field">
                <label for="user_email">Email Login</label>
                <input type="email" id="user_email" name="user_email" maxlength="190" required>
            </div>
            <div class="field">
                <label for="user_password">Kata Sandi Awal</label>
                <input type="password" id="user_password" name="user_password" autocomplete="new-password" required>
                <div class="hint">Minimal 8 karakter.</div>
            </div>
            <div class="field">
                <label for="user_role">Peran</label>
                <select id="user_role" name="user_role">
                    <option value="tenant_admin">Admin Tenant</option>
                    <option value="tenant_staff">Staf Tenant</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Tambah Akun Login</button>
        </div>
    </form>
</div>

<div class="panel-box">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 14px;">Lisensi</h2>

    <form method="post" action="/admin/tenants/<?= $tenantId ?>/licenses" style="margin-bottom: 20px;">
        <?= Auth::csrfField() ?>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
            <div class="field" style="flex: 1 1 260px; margin-bottom: 0;">
                <label for="plan_id">Plan untuk lisensi baru</label>
                <select id="plan_id" name="plan_id">
                    <?php foreach (($activePlans ?? []) as $plan): ?>
                        <option value="<?= (int) $plan['id'] ?>">
                            <?= e((string) $plan['name']) ?>
                            (<?= (int) ($plan['license_duration_days'] ?? 0) === 0
                                ? 'tanpa kedaluwarsa'
                                : (int) $plan['license_duration_days'] . ' hari' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit"
                <?= ($activePlans ?? []) === [] ? 'disabled' : '' ?>>Terbitkan Lisensi</button>
        </div>
        <?php if (($activePlans ?? []) === []): ?>
            <div class="hint" style="margin-top: 8px;">Tidak ada plan aktif. Aktifkan plan dulu di menu Plan Lisensi.</div>
        <?php endif; ?>
    </form>

    <?php if (($licenses ?? []) === []): ?>
        <p class="hint">Tenant ini belum memiliki lisensi.</p>
    <?php else: ?>
        <div class="table-wrap" style="box-shadow: none;">
        <table class="list" style="min-width: 0;">
            <thead>
            <tr>
                <th>Plan / Kunci</th>
                <th>Status</th>
                <th>Berlaku</th>
                <th>Perangkat</th>
                <th>Aktivitas Terakhir</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($licenses as $license): ?>
                <?php
                $effective = License::effectiveStatus($license);
                $ent = License::entitlements($license);
                $licenseId = (int) $license['id'];
                $daysLeft = License::daysLeft($license);
                ?>
                <tr>
                    <td>
                        <strong><?= e((string) ($license['plan_name'] ?? $ent['plan_name'])) ?></strong><br>
                        <?php if (!empty($license['license_key'])): ?>
                            <span class="key-full"><?= e((string) $license['license_key']) ?></span>
                        <?php else: ?>
                            <code><?= e(License::maskedKey($license)) ?></code><br>
                            <span class="hint">kunci tidak tersimpan (lisensi lama)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="<?= e(License::statusBadgeClass($effective)) ?>"><?= e(License::statusLabel($effective)) ?></span>
                        <?php if ($effective === 'active' && $daysLeft !== null && $daysLeft <= 30 && $daysLeft >= 0): ?>
                            <br><span class="status-warn" style="margin-top: 5px;"><?= $daysLeft ?> hari lagi</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e(License::formatDateId((string) ($license['starts_at'] ?? ''))) ?> –
                        <?= empty($license['expires_at']) ? 'Tanpa kedaluwarsa' : e(License::formatDateId((string) $license['expires_at'])) ?>
                    </td>
                    <td>
                        <?= (int) ($license['device_count'] ?? 0) ?> /
                        <?= $ent['max_devices'] === 0 ? '∞' : (string) $ent['max_devices'] ?>
                    </td>
                    <td><?= !empty($license['last_seen_at']) ? e((string) $license['last_seen_at']) : '—' ?></td>
                    <td>
                        <div class="row-actions">
                            <?php if (!empty($license['expires_at']) && $effective !== 'revoked'): ?>
                                <form method="post" action="/admin/licenses/<?= $licenseId ?>/renew">
                                    <?= Auth::csrfField() ?>
                                    <button class="btn btn-small" type="submit">Perpanjang</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($effective === 'active' || $effective === 'pending'): ?>
                                <form method="post" action="/admin/licenses/<?= $licenseId ?>/suspend"
                                      onsubmit="return confirm('Tangguhkan lisensi ini? Aplikasi booth tenant akan ditolak sampai diaktifkan lagi.');">
                                    <?= Auth::csrfField() ?>
                                    <button class="btn btn-small" type="submit">Tangguhkan</button>
                                </form>
                            <?php elseif ($effective === 'suspended'): ?>
                                <form method="post" action="/admin/licenses/<?= $licenseId ?>/suspend">
                                    <?= Auth::csrfField() ?>
                                    <button class="btn btn-small" type="submit">Aktifkan</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($effective !== 'revoked'): ?>
                                <form method="post" action="/admin/licenses/<?= $licenseId ?>/revoke"
                                      onsubmit="return confirm('Cabut lisensi ini secara permanen? Tindakan ini tidak dapat dibatalkan.');">
                                    <?= Auth::csrfField() ?>
                                    <button class="btn btn-small btn-danger" type="submit">Cabut</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php foreach ($licenses as $license): ?>
            <?php
            $devices = $devicesByLicense[(int) $license['id']] ?? [];
            if ($devices === []) {
                continue;
            }
            $ent = License::entitlements($license);
            ?>
            <div style="margin-top: 20px;">
                <h3 style="font-size: 14.5px; font-weight: 700; margin-bottom: 8px;">
                    Perangkat terhubung — <?= e((string) ($license['plan_name'] ?? $ent['plan_name'])) ?>
                    <span class="hint" style="font-weight: 400;">(<?= e(License::maskedKey($license)) ?>)</span>
                </h3>
                <ul style="margin: 0; padding-left: 18px;">
                    <?php foreach ($devices as $device): ?>
                        <?php
                        $deviceRowId = (int) $device['id'];
                        $deviceLabel = trim((string) ($device['device_label'] ?? ''));
                        $deviceActive = (int) ($device['is_active'] ?? 0) === 1;
                        $firstSeen = License::formatDateId((string) ($device['first_seen_at'] ?? ''));
                        $lastActive = License::formatDateId((string) ($device['last_heartbeat_at'] ?? $device['first_seen_at'] ?? ''));
                        ?>
                        <li style="margin-bottom: 8px;">
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
                                      action="/admin/licenses/<?= (int) $license['id'] ?>/devices/<?= $deviceRowId ?>/revoke"
                                      onsubmit="return confirm('Cabut perangkat ini dari lisensi? Slot perangkat akan bebas dan aplikasi di perangkat itu terkunci sampai masuk lagi.');"
                                      style="display: inline-block; margin-left: 8px;">
                                    <?= Auth::csrfField() ?>
                                    <button class="btn btn-small btn-danger" type="submit">Cabut</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
