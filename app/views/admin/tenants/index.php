<?php
/**
 * Daftar tenant (Super Admin, Milestone 4).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $tenants Baris tenants + license_plan_name & license_expires_at.
 * @var string $query Kata pencarian aktif.
 * @var bool $automationTokenSet Token otomasi pengingat sudah diatur.
 * @var string|null $automationTokenOnce Token BARU yang ditampilkan satu kali (baru dibuat ulang).
 */

$title = 'Tenant & Lisensi';
$activeNav = 'tenants';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Tenant adalah pembeli/pemakai lisensi aplikasi booth. Kontak
    (email &amp; WhatsApp) dipakai untuk notifikasi perpanjangan
    otomatis — pastikan selalu mutakhir.
    <a class="btn btn-primary btn-small" style="margin-left: 10px;" href="/admin/tenants/create">+ Tambah Tenant</a>
</p>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 6px;">Otomasi Pengingat Perpanjangan</h2>
    <p class="hint" style="margin-bottom: 14px;">
        Workflow n8n membaca lisensi yang akan berakhir lewat endpoint
        <code>/api/automation/expiring-licenses</code> dengan header
        <code>X-Automation-Token</code>, mengirim WhatsApp lewat MPWA,
        lalu mencatat pengiriman ke
        <code>/api/automation/mark-notified</code>. Ambang pengingat:
        7, 3, dan 1 hari sebelum kedaluwarsa.
    </p>
    <?php if (($automationTokenOnce ?? null) !== null): ?>
        <div class="notice" style="margin-bottom: 14px;">
            <strong>Token otomasi baru — salin sekarang.</strong>
            Token hanya ditampilkan satu kali ini dan tidak akan
            ditampilkan lagi. Tempel ke kredensial workflow n8n:<br>
            <code style="font-size: 15px; word-break: break-all;"><?= e((string) $automationTokenOnce) ?></code>
        </div>
    <?php elseif ($automationTokenSet ?? false): ?>
        <p style="margin-bottom: 14px;">
            Status: <span class="status-on">Token sudah diatur</span>
            <span class="hint">— token tersimpan tidak ditampilkan lagi; buat ulang bila hilang.</span>
        </p>
    <?php else: ?>
        <p style="margin-bottom: 14px;">
            Status: <span class="status-off">Token belum diatur</span>
            <span class="hint">— endpoint otomasi menolak semua panggilan sampai token dibuat.</span>
        </p>
    <?php endif; ?>
    <form method="post" action="/admin/automation/token/regenerate"
          onsubmit="return confirm('Membuat token baru akan menonaktifkan token lama. Lanjutkan?');">
        <?= Auth::csrfField() ?>
        <button class="btn btn-small" type="submit"><?= ($automationTokenSet ?? false) ? 'Buat Ulang Token' : 'Buat Token Otomasi' ?></button>
    </form>
</div>

<form method="get" action="/admin/tenants" style="margin-bottom: 18px;">
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <input type="text" name="q" value="<?= e((string) ($query ?? '')) ?>"
               placeholder="Cari vendor, penanggung jawab, email, atau WhatsApp…"
               style="flex: 1 1 280px; padding: 11px 13px; font-size: 15px; font-family: inherit; background: var(--bg); color: var(--text); border: 1px solid var(--line-strong); border-radius: var(--r-md);">
        <button class="btn btn-primary" type="submit">Cari</button>
        <?php if (($query ?? '') !== ''): ?>
            <a class="btn" href="/admin/tenants">Atur Ulang</a>
        <?php endif; ?>
    </div>
</form>

<?php if (($tenants ?? []) === []): ?>
    <div class="panel-box">
        <?php if (($query ?? '') !== ''): ?>
            <p>Tidak ada tenant yang cocok dengan pencarian
                “<?= e((string) $query) ?>”.</p>
        <?php else: ?>
            <p>Belum ada tenant. Klik <strong>Tambah Tenant</strong> untuk
                mendaftarkan pembeli lisensi pertama.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Vendor</th>
            <th>Kontak</th>
            <th>Lisensi Aktif</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tenants as $tenant): ?>
            <?php
            $wa = Tenant::whatsappOf($tenant);
            $expiresAt = $tenant['license_expires_at'] ?? null;
            $daysLeft = $expiresAt !== null
                ? (int) floor((strtotime((string) $expiresAt) - time()) / 86400)
                : null;
            $isActive = (string) ($tenant['status'] ?? 'active') === 'active';
            ?>
            <tr>
                <td>
                    <strong><?= e(Tenant::displayName($tenant)) ?></strong>
                    <?php if (!empty($tenant['contact_name'])): ?>
                        <br><span style="color: var(--muted); font-size: 13px;"><?= e((string) $tenant['contact_name']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($tenant['email'])): ?>
                        <?= e((string) $tenant['email']) ?><br>
                    <?php endif; ?>
                    <?php if ($wa !== null): ?>
                        <a href="<?= e(Whatsapp::link($wa)) ?>" target="_blank" rel="noopener">WA <?= e($wa) ?></a>
                    <?php else: ?>
                        <span style="color: var(--muted);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($tenant['license_plan_name'])): ?>
                        <strong><?= e((string) $tenant['license_plan_name']) ?></strong><br>
                        <?php if ($expiresAt !== null): ?>
                            <span style="color: var(--muted); font-size: 13px;">s.d. <?= e(License::formatDateId((string) $expiresAt)) ?></span>
                            <?php if ($daysLeft !== null && $daysLeft < 0): ?>
                                <span class="status-off" style="margin-left: 6px;">Kedaluwarsa</span>
                            <?php elseif ($daysLeft !== null && $daysLeft <= 30): ?>
                                <span class="status-warn" style="margin-left: 6px;"><?= $daysLeft ?> hari lagi</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: var(--muted); font-size: 13px;">Tanpa kedaluwarsa</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: var(--muted);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="status-on">Aktif</span>
                    <?php else: ?>
                        <span class="status-off">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="btn btn-small" href="/admin/tenants/<?= (int) $tenant['id'] ?>">Detail</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
