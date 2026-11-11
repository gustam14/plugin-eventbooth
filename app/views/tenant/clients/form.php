<?php
/**
 * Formulir buat/ubah klien (v0.31.0) — hanya nama, WhatsApp,
 * dan alamat. Detail booking diisi pada event.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $heading
 * @var string $action
 * @var array<string, mixed>  $values Nilai tampilan (ClientBook::formValues()).
 * @var array<string, string> $errors Peta error per kolom.
 */

$title = $heading;
$activeNav = 'clients';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};
?>
<p class="sub">Catat klien di sini dulu; begitu booking pasti, buat event-nya dari daftar Buku Klien — tanggal, paket, dan pembayaran diisi pada event.</p>

<div class="panel-box form-panel">
    <form method="post" action="<?= e($action) ?>">
        <?= Auth::csrfField() ?>

        <div class="field">
            <label for="client_name">Nama Klien</label>
            <input type="text" id="client_name" name="client_name" maxlength="120" required
                   placeholder="cth. Diva &amp; Anasita / PT Sinar Abadi / SMAN 1 Singaraja"
                   value="<?= e((string) ($values['client_name'] ?? '')) ?>">
            <?= $err('client_name') ?>
        </div>

        <div class="field">
            <label for="whatsapp">WhatsApp</label>
            <input type="text" id="whatsapp" name="whatsapp" maxlength="32" inputmode="tel"
                   placeholder="08xxxxxxxxxx"
                   value="<?= e((string) ($values['whatsapp'] ?? '')) ?>">
            <?= $err('whatsapp') ?>
        </div>

        <div class="field">
            <label for="address">Alamat</label>
            <textarea id="address" name="address" rows="2" maxlength="255"
                      placeholder="cth. Br. Dinas Taman Sari, Tianyar Barat, Karangasem"><?= e((string) ($values['address'] ?? '')) ?></textarea>
            <?= $err('address') ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Klien</button>
            <a class="btn" href="/tenant/clients">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
