<?php
/**
 * Formulir buat/ubah paket layanan klien (v0.29.0).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $heading
 * @var string $action
 * @var array<string, mixed>  $values Nilai tampilan (PackageTemplate::formValues()).
 * @var array<string, string> $errors Peta error per kolom.
 */

$title = $heading;
$activeNav = 'catalog';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};
?>
<p class="sub">Paket layanan untuk klien event / wedding. Nama paket ini dapat dipilih saat mencatat klien di Buku Klien.</p>

<div class="panel-box form-panel">
    <form method="post" action="<?= e($action) ?>">
        <?= Auth::csrfField() ?>

        <div class="field">
            <label for="name">Nama Paket</label>
            <input type="text" id="name" name="name" maxlength="120" required
                   placeholder="cth. Limited 400 Foto / Unlimited 3 Jam / Paket Sekolah"
                   value="<?= e((string) ($values['name'] ?? '')) ?>">
            <?= $err('name') ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="duration_hours">Durasi Layanan (jam)</label>
                <input type="text" id="duration_hours" name="duration_hours" inputmode="decimal"
                       placeholder="cth. 3 atau 2,5"
                       value="<?= e((string) ($values['duration_hours'] ?? '')) ?>">
                <div class="hint">Lama booth beroperasi di acara. Kosongkan bila tidak dibatasi jam.</div>
                <?= $err('duration_hours') ?>
            </div>
            <div class="field">
                <label for="prints_included">Jumlah Foto / Cetakan Termasuk</label>
                <input type="number" id="prints_included" name="prints_included" min="0" step="1"
                       placeholder="cth. 400"
                       value="<?= e((string) ($values['prints_included'] ?? '')) ?>">
                <div class="hint">Kosongkan bila tanpa batas (unlimited).</div>
                <?= $err('prints_included') ?>
            </div>
        </div>

        <div class="field">
            <label for="price">Harga Paket (Rp)</label>
            <input type="text" id="price" name="price" inputmode="numeric" required
                   value="<?= e((string) ($values['price'] ?? '')) ?>">
            <div class="hint">Harga total untuk klien. Contoh: 2500000 atau 2.500.000.</div>
            <?= $err('price') ?>
        </div>

        <div class="field">
            <label for="description">Isi / Fasilitas Paket</label>
            <textarea id="description" name="description" rows="3"
                      placeholder="cth. Backdrop standar, properti foto, bingkai custom nama pasangan, semua softcopy via galeri online, 1 operator standby"><?= e((string) ($values['description'] ?? '')) ?></textarea>
            <?= $err('description') ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="sort_order">Urutan Tampil</label>
                <input type="number" id="sort_order" name="sort_order" step="1"
                       value="<?= e((string) ($values['sort_order'] ?? '0')) ?>">
                <?= $err('sort_order') ?>
            </div>
        </div>

        <label class="check">
            <input type="checkbox" name="is_active" value="1"
                <?= !empty($values['is_active']) ? 'checked' : '' ?>>
            Paket aktif (ditawarkan ke klien &amp; dapat dipilih di Buku Klien)
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Paket</button>
            <a class="btn" href="/tenant/package-catalog">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
