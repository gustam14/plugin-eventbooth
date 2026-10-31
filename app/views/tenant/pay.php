<?php
/**
 * Halaman bayar checkout lisensi (tenant, Milestone 6): membuka
 * popup Snap Midtrans untuk satu pesanan. Callback Snap di sini
 * hanya mengarahkan tampilan — pelunasan resmi ditentukan oleh
 * notifikasi server (webhook) yang terverifikasi.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed> $order Baris payments + plan_name.
 * @var string $snapJsUrl
 * @var string $clientKey
 */

$title = 'Pembayaran Lisensi';
$activeNav = 'licenses';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$planLabel = (string) ($order['plan_name'] ?? '');
if ($planLabel === '' && !empty($order['plan_snapshot'])) {
    $snap = json_decode((string) $order['plan_snapshot'], true);
    $planLabel = is_array($snap) ? (string) ($snap['name'] ?? '') : '';
}
?>

<div class="panel-box" style="max-width: 560px;">
    <h2 style="margin-top: 0;">Selesaikan Pembayaran</h2>
    <dl>
        <dt>Pesanan</dt>
        <dd><code><?= e((string) $order['order_id']) ?></code></dd>
        <dt>Plan</dt>
        <dd><?= e($planLabel !== '' ? $planLabel : 'Lisensi Plugin Eventbooth') ?></dd>
        <dt>Total</dt>
        <dd><strong>Rp <?= e(number_format((float) $order['gross_amount'], 0, ',', '.')) ?></strong></dd>
    </dl>
    <p class="sub">
        Jendela pembayaran Midtrans dibuka otomatis. Setelah kamu
        membayar, status pesanan dan masa aktif lisensimu diperbarui
        otomatis begitu notifikasi pembayaran terverifikasi —
        biasanya dalam beberapa detik. Bila jendela tidak muncul,
        gunakan tombol di bawah ini.
    </p>
    <p>
        <button class="btn btn-primary" type="button" onclick="payNow()">Buka Pembayaran</button>
        <a class="btn" href="/tenant/licenses">Kembali ke Lisensi Saya</a>
    </p>
</div>

<script src="<?= e($snapJsUrl) ?>" data-client-key="<?= e($clientKey) ?>"></script>
<script>
    function payNow() {
        window.snap.pay(<?= json_encode((string) $order['snap_token'], JSON_UNESCAPED_SLASHES) ?>, {
            onSuccess: function () { window.location = '/tenant/licenses?pay=success'; },
            onPending: function () { window.location = '/tenant/licenses?pay=pending'; },
            onError: function () { window.location = '/tenant/licenses?pay=error'; },
            onClose: function () { /* Tetap di halaman; tombol Buka Pembayaran tersedia. */ }
        });
    }
    window.addEventListener('load', payNow);
</script>

<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
