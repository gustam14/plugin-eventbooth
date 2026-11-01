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
        <p class="hint" style="margin-bottom: 14px;">
            Petunjuk kunci: kunci <strong>Sandbox</strong> selalu
            diawali <code>SB-</code> (SB-Mid-client-… /
            SB-Mid-server-…) dan diambil dari dashboard Sandbox;
            kunci <strong>Production</strong> diawali
            <code>Mid-</code>. Pastikan pilihan Environment di atas
            sama dengan asal kuncimu — campuran keduanya adalah
            penyebab paling umum pembayaran ditolak Midtrans.
        </p>
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
        berjalan tanpa operator. Ada <strong>dua alamat notifikasi
        yang berbeda</strong> — jangan tertukar:
    </p>
    <p class="hint" style="margin-bottom: 8px;">
        1. Notifikasi <strong>transaksi booth</strong> dikirim ke
        alamat sistem kami:
        <code><?= e($guestNotifyUrl) ?></code> — ini berjalan
        <strong>otomatis</strong> lewat mekanisme override khusus
        per transaksi, tidak ada yang perlu kamu atur.
    </p>
    <p class="hint">
        2. Kolom <strong>Payment Notification URL di dashboard
        Midtrans</strong> adalah milik sistem utamamu yang lain
        (misalnya website utamamu memakai alamat callback-nya
        sendiri) — <strong>biarkan apa adanya, jangan diganti
        dengan alamat di atas</strong>, karena alamat itu hanya
        melayani transaksi booth di sistem kami, sedangkan
        dashboard-mu melayani sistemmu sendiri. Status lunas
        booth juga dipastikan aplikasi lewat pengecekan langsung
        ke Midtrans, jadi tidak bergantung pada kolom itu.
    </p>
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
                    <?php
                    $failReason = '';
                    if ((string) $order['status'] === 'failed' && !empty($order['raw_webhook'])) {
                        $raw = json_decode((string) $order['raw_webhook'], true);
                        if (is_array($raw) && !empty($raw['error'])) {
                            $failReason = (string) $raw['error'];
                        }
                    }
                    ?>
                    <tr>
                        <td><?= e((string) $order['order_id']) ?></td>
                        <td><?= e((string) ($order['session_code'] ?? '—')) ?></td>
                        <td>Rp<?= number_format((float) $order['gross_amount'], 0, ',', '.') ?></td>
                        <td>
                            <?= e(Midtrans::statusLabel((string) $order['status'])) ?>
                            <?php if ($failReason !== ''): ?>
                                <br><span class="hint" style="color: #B00020;"><?= e($failReason) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) $order['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
