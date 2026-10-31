<?php
/**
 * Pembayaran (Super Admin, Milestone 6): pengaturan Midtrans
 * platform untuk checkout lisensi + daftar pesanan lisensi terbaru.
 *
 * Server Key TIDAK PERNAH ditampilkan — hanya ditandai sudah
 * tersimpan atau belum. Mengosongkan kolom Server Key saat
 * menyimpan berarti mempertahankan nilai lama.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array{environment: string, client_key: ?string, server_key: ?string, configured: bool} $midtrans
 * @var bool $serverKeyStored
 * @var array<int, array<string, mixed>> $orders
 * @var bool $ordersReady
 * @var string $notifyUrl
 */

$title = 'Pembayaran';
$activeNav = 'payments';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$fmtRupiah = static fn ($amount): string => 'Rp ' . number_format((float) $amount, 0, ',', '.');
?>

<div class="panel-box form-panel" style="margin-bottom: 22px;">
    <h2 style="margin-top: 0;">Midtrans Platform — Checkout Lisensi</h2>
    <p class="sub">
        Kredensial Midtrans milik EventBooth untuk penjualan lisensi.
        Tenant membayar dari halaman Lisensi Saya; masa aktif mereka
        bertambah otomatis begitu notifikasi Midtrans terverifikasi.
    </p>
    <p class="hint" style="margin-bottom: 14px;">
        Status saat ini:
        <?php if ($midtrans['configured']): ?>
            <span class="status-on">Aktif (<?= e($midtrans['environment']) ?>)</span>
        <?php else: ?>
            <span class="status-off">Belum lengkap — checkout tenant nonaktif</span>
        <?php endif; ?>
    </p>
    <form method="post" action="/admin/payments/settings">
        <?= Auth::csrfField() ?>
        <div class="field">
            <label for="environment">Environment</label>
            <select id="environment" name="environment">
                <option value="sandbox" <?= $midtrans['environment'] === 'sandbox' ? 'selected' : '' ?>>Sandbox (uji coba)</option>
                <option value="production" <?= $midtrans['environment'] === 'production' ? 'selected' : '' ?>>Production (uang sungguhan)</option>
            </select>
            <p class="hint">Harus sama dengan jenis kunci di bawah: kunci Sandbox hanya bekerja di environment Sandbox.</p>
        </div>
        <div class="field">
            <label for="client_key">Client Key</label>
            <input id="client_key" name="client_key" type="text" autocomplete="off"
                   value="<?= e($midtrans['client_key'] ?? '') ?>"
                   placeholder="mis. SB-Mid-client-...">
        </div>
        <div class="field">
            <label for="server_key">Server Key</label>
            <input id="server_key" name="server_key" type="password" autocomplete="new-password"
                   placeholder="<?= $serverKeyStored ? 'Sudah tersimpan — kosongkan bila tidak diganti' : 'mis. SB-Mid-server-...' ?>">
            <p class="hint">
                <?= $serverKeyStored
                    ? 'Server Key tersimpan terenkripsi di server dan tidak ditampilkan.'
                    : 'Server Key disimpan terenkripsi di server dan tidak pernah ditampilkan lagi.' ?>
            </p>
        </div>
        <button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
    </form>
</div>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="margin-top: 0;">URL Notifikasi Midtrans</h2>
    <p class="sub">
        Daftarkan alamat ini di dashboard Midtrans (Settings →
        Configuration → <em>Payment Notification URL</em>) agar
        pelunasan tercatat dan lisensi tenant terpenuhi otomatis:
    </p>
    <p><code><?= e($notifyUrl) ?></code></p>
    <p class="hint">
        URL halaman selesai (finish) boleh diarahkan ke
        <code>/tenant/licenses</code>. Notifikasi hanya diterima dari
        Midtrans dan selalu diverifikasi tanda tangannya di server.
    </p>
</div>

<div class="panel-box">
    <h2 style="margin-top: 0;">Pesanan Lisensi Terbaru</h2>
    <?php if (!$ordersReady): ?>
        <p class="hint">Tabel pembayaran belum siap — jalankan migrasi M6
            (<code>database/migrations/2026-11-01-m6-payments.sql</code>) di phpMyAdmin.</p>
    <?php elseif ($orders === []): ?>
        <p class="hint">Belum ada pesanan lisensi.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th>Waktu</th>
                <th>Tenant</th>
                <th>Plan</th>
                <th>Order ID</th>
                <th>Jumlah</th>
                <th>Metode</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <?php
                $tenantLabel = trim((string) ($order['tenant_vendor'] ?? '')) !== ''
                    ? (string) $order['tenant_vendor']
                    : (string) ($order['tenant_name'] ?? '—');
                $planLabel = (string) ($order['plan_name'] ?? '');
                if ($planLabel === '' && !empty($order['plan_snapshot'])) {
                    $snap = json_decode((string) $order['plan_snapshot'], true);
                    $planLabel = is_array($snap) ? (string) ($snap['name'] ?? '') : '';
                }
                ?>
                <tr>
                    <td><?= e(License::formatDateId((string) $order['created_at'])) ?><br>
                        <span class="hint"><?= e(substr((string) $order['created_at'], 11, 5)) ?></span></td>
                    <td><?= e($tenantLabel) ?></td>
                    <td><?= e($planLabel !== '' ? $planLabel : '—') ?></td>
                    <td><code><?= e((string) $order['order_id']) ?></code></td>
                    <td><?= e($fmtRupiah($order['gross_amount'])) ?></td>
                    <td><?= e((string) ($order['payment_type'] ?? '—')) ?></td>
                    <td><?= e(Midtrans::statusLabel((string) $order['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
