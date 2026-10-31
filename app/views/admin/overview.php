<?php
/**
 * Ringkasan Super Admin.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var bool $dbReady False bila database belum disetel / tabel belum ada.
 * @var array{tenants: int, licenses: int, plans: int, events: int} $counts
 * @var array<int, array<string, mixed>> $expiring Lisensi aktif yang berakhir <= 30 hari (M4).
 */

$title = 'Ringkasan Super Admin';
$activeNav = 'admin';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">Selamat datang, <?= e($user['name']) ?>. Ini pusat kendali platform Plugin Eventbooth.</p>

<?php if (!($dbReady ?? false)): ?>
    <div class="notice">
        <strong>Database belum disetel atau belum dapat dihubungi.</strong>
        Angka di bawah ini belum mencerminkan data sebenarnya. Isi
        kredensial database di <code>.env</code> dan impor
        <code>database/schema.sql</code> untuk mengaktifkan dashboard.
    </div>
<?php endif; ?>

<div class="stats">
    <div class="stat">
        <span class="icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5.5A1.5 1.5 0 0 1 5.5 4h8A1.5 1.5 0 0 1 15 5.5V21"/><path d="M15 9.5h3.5A1.5 1.5 0 0 1 20 11v10"/><path d="M2.5 21h19"/><path d="M8 8h3M8 12h3M8 16h3"/></svg></span>
        <div class="num"><?= number_format((int) ($counts['tenants'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Tenant</div>
    </div>
    <div class="stat">
        <span class="icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4.2"/><path d="M11.2 11.8 20 3M16.5 6.5l2.8 2.8M13.8 9.2l2.3 2.3"/></svg></span>
        <div class="num"><?= number_format((int) ($counts['licenses'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Lisensi</div>
    </div>
    <div class="stat">
        <span class="icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 4.8-9 4.8-9-4.8L12 3z"/><path d="M3.6 12.7 12 17.2l8.4-4.5"/><path d="M3.6 16.7 12 21.2l8.4-4.5"/></svg></span>
        <div class="num"><?= number_format((int) ($counts['plans'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Plan</div>
    </div>
    <div class="stat">
        <span class="icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 9.8h18M8 3v4M16 3v4"/></svg></span>
        <div class="num"><?= number_format((int) ($counts['events'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Event</div>
    </div>
</div>

<div class="cards">
    <div class="card">
        <h2>Plan Lisensi</h2>
        <p>
            Atur harga, periode, batas perangkat/event, kuota, dan
            fitur tiap plan. Perubahan plan langsung berlaku untuk
            penerbitan lisensi berikutnya.
        </p>
        <p style="margin-top: 12px;"><a class="btn btn-primary btn-small" href="/admin/plans">Kelola Plan</a></p>
    </div>
    <div class="card">
        <h2>Tenant &amp; Lisensi</h2>
        <p>
            Daftarkan tenant beserta kontaknya, terbitkan dan
            perpanjang lisensi, tangguhkan atau cabut lisensi, dan
            pantau kedaluwarsa untuk pengingat perpanjangan.
        </p>
        <p style="margin-top: 12px;"><a class="btn btn-primary btn-small" href="/admin/tenants">Kelola Tenant</a></p>
    </div>
    <div class="card soon">
        <span class="soon-tag">Segera hadir</span>
        <h2>Google Drive Pusat</h2>
        <p>
            Kaitkan satu atau lebih akun Google Drive platform,
            tetapkan akun default, dan pantau pemakaian kuota per
            tenant. Penugasan Drive dilakukan per event.
        </p>
    </div>
</div>

<?php if (($expiring ?? []) !== []): ?>
<div class="panel-box" style="margin-top: 22px;">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 6px;">Lisensi Akan Berakhir (30 hari ke depan)</h2>
    <p class="hint" style="margin-bottom: 16px;">
        Hubungi tenant lebih awal agar booth mereka tidak berhenti di
        tengah event. Pengingat WhatsApp otomatis berjalan lewat
        workflow n8n pada ambang 7, 3, dan 1 hari.
    </p>
    <div class="table-wrap" style="box-shadow: none;">
    <table class="list" style="min-width: 0;">
        <thead>
        <tr><th>Tenant</th><th>Plan</th><th>Berakhir</th><th>Sisa</th><th>Kontak</th></tr>
        </thead>
        <tbody>
        <?php foreach ($expiring as $row): ?>
            <?php
            $wa = Whatsapp::normalize($row['tenant_whatsapp'] ?? null)
                ?? Whatsapp::normalize($row['tenant_phone'] ?? null);
            $left = License::daysLeft($row);
            $vendorLabel = trim((string) ($row['tenant_vendor_name'] ?? '')) !== ''
                ? (string) $row['tenant_vendor_name']
                : (string) ($row['tenant_name'] ?? '');
            ?>
            <tr>
                <td>
                    <a href="/admin/tenants/<?= (int) $row['tenant_id'] ?>"><strong><?= e($vendorLabel) ?></strong></a>
                </td>
                <td><?= e((string) ($row['plan_name'] ?? '—')) ?></td>
                <td><?= e(License::formatDateId((string) ($row['expires_at'] ?? ''))) ?></td>
                <td>
                    <?php if ($left !== null && $left <= 7): ?>
                        <span class="status-off"><?= $left ?> hari lagi</span>
                    <?php elseif ($left !== null): ?>
                        <span class="status-warn"><?= $left ?> hari lagi</span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($wa !== null): ?>
                        <a href="<?= e(Whatsapp::link($wa)) ?>" target="_blank" rel="noopener">Chat WhatsApp</a>
                    <?php elseif (!empty($row['tenant_email'])): ?>
                        <?= e((string) $row['tenant_email']) ?>
                    <?php else: ?>
                        —
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
