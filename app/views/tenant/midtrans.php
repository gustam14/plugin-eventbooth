<?php
/**
 * Midtrans Tamu (tenant): pengaturan akun Midtrans MILIK TENANT
 * untuk menerima pembayaran tamu booth (QRIS) — uang masuk
 * langsung ke rekening tenant (keputusan platform 2026-11-01).
 * Server Key tersimpan terenkripsi dan tidak pernah ditampilkan.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array{environment: string, merchant_id: ?string, client_key: ?string, server_key: ?string, enabled: bool, configured: bool}|null $midtrans
 * @var string $guestNotifyUrl
 * @var array<int, array<string, mixed>> $orders Riwayat pembayaran tamu terakhir.
 */

$title = 'Midtrans Tamu';
$activeNav = 'midtrans';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$cfg = $midtrans ?? null;
$env = $cfg['environment'] ?? 'sandbox';
?>
<p class="sub">
    Pembayaran tamu booth (QRIS) masuk <strong>langsung ke akun
    Midtrans kamu sendiri</strong> — bukan ke platform. Isi kunci
    dari dashboard Midtrans milikmu (Settings &gt; Access Keys).
    Server Key tersimpan terenkripsi di server dan tidak pernah
    ditampilkan kembali.
</p>

<div class="panel-box">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 6px;">Status</h2>
    <p class="hint" style="margin-bottom: 14px;">
        <?php if ($cfg === null): ?>
            Belum diatur — pembayaran tamu belum dapat dipakai.
        <?php elseif (!$cfg['configured']): ?>
            Belum lengkap — Client Key dan Server Key wajib terisi.
        <?php elseif (!$cfg['enabled']): ?>
            Kunci lengkap (<?= e($env) ?>) tetapi pembayaran tamu sedang <strong>nonaktif</strong>.
        <?php else: ?>
            Aktif · environment <strong><?= e($env) ?></strong> · kunci lengkap.
        <?php endif; ?>
    </p>

    <form method="post" action="/tenant/midtrans">
        <?= Auth::csrfField() ?>
        <div class="field">
            <label>
                <input type="checkbox" name="is_enabled" value="1"<?= ($cfg['enabled'] ?? false) ? ' checked' : '' ?>>
                Aktifkan pembayaran tamu (QRIS) pada mode kios
            </label>
        </div>
        <div class="field">
            <label for="environment">Environment</label>
            <select id="environment" name="environment">
                <option value="sandbox"<?= $env === 'sandbox' ? ' selected' : '' ?>>Sandbox (uji coba)</option>
                <option value="production"<?= $env === 'production' ? ' selected' : '' ?>>Production (uang sungguhan)</option>
            </select>
        </div>
        <div class="field">
            <label for="merchant_id">Merchant ID <span class="hint">(opsional)</span></label>
            <input type="text" id="merchant_id" name="merchant_id" maxlength="64"
                   value="<?= e((string) ($cfg['merchant_id'] ?? '')) ?>">
        </div>
        <div class="field">
            <label for="client_key">Client Key</label>
            <input type="text" id="client_key" name="client_key" maxlength="128" autocomplete="off"
                   value="<?= e((string) ($cfg['client_key'] ?? '')) ?>"
                   placeholder="SB-Mid-client-… / Mid-client-…">
        </div>
        <div class="field">
            <label for="server_key">Server Key
                <span class="hint">(<?= ($cfg['server_key'] ?? null) !== null ? 'sudah tersimpan — kosongkan bila tidak diubah' : 'wajib diisi' ?>)</span>
            </label>
            <input type="password" id="server_key" name="server_key" maxlength="255" autocomplete="new-password"
                   placeholder="SB-Mid-server-… / Mid-server-…">
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
        </div>
    </form>
</div>

<div class="panel-box">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 10px;">Cara kerja — tanpa mengubah dashboard Midtrans</h2>
    <p class="hint" style="margin-bottom: 8px;">
        Tamu membayar dengan memindai QRIS di layar kios; aplikasi
        mengecek status lunasnya otomatis ke Midtrans, jadi booth
        berjalan tanpa operator. Kamu <strong>tidak perlu mengubah
        Payment Notification URL</strong> di dashboard Midtrans
        milikmu — kolom itu biarkan untuk sistemmu yang lain
        (misalnya website utamamu). Notifikasi untuk transaksi
        booth diarahkan khusus per transaksi ke sistem kami lewat
        mekanisme override Midtrans, dan alamat tujuannya adalah:
    </p>
    <p><code><?= e($guestNotifyUrl) ?></code></p>
</div>

<?php if (($orders ?? []) !== []): ?>
    <div class="panel-box">
        <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 14px;">Pembayaran Tamu Terakhir</h2>
        <div class="table-wrap" style="box-shadow: none;">
            <table class="list" style="min-width: 0;">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Sesi</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Waktu</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e((string) $order['order_id']) ?></td>
                        <td><?= e((string) ($order['session_code'] ?? '—')) ?></td>
                        <td>Rp<?= number_format((float) $order['gross_amount'], 0, ',', '.') ?></td>
                        <td><?= e(Midtrans::statusLabel((string) $order['status'])) ?></td>
                        <td><?= e((string) $order['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
