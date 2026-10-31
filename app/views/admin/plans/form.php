<?php
/**
 * Formulir buat/ubah plan lisensi (Super Admin).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $heading   Judul formulir ("Tambah Plan" / "Ubah Plan").
 * @var string $action    URL tujuan POST.
 * @var array<string, mixed>  $values Nilai tampilan (Plan::formValues()).
 * @var array<string, string> $errors Peta error per kolom.
 */

$title = $heading;
$activeNav = 'plans';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};
?>
<p class="sub">Kolom batas bernilai 0 berarti <strong>tanpa batas</strong>.</p>

<div class="panel-box form-panel">
    <form method="post" action="<?= e($action) ?>">
        <?= Auth::csrfField() ?>

        <div class="field">
            <label for="name">Nama Plan</label>
            <input type="text" id="name" name="name" maxlength="60" required
                   value="<?= e((string) ($values['name'] ?? '')) ?>">
            <?= $err('name') ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="slug">Slug <span class="hint">(kosongkan untuk otomatis dari nama)</span></label>
                <input type="text" id="slug" name="slug" maxlength="60"
                       value="<?= e((string) ($values['slug'] ?? '')) ?>">
                <?= $err('slug') ?>
            </div>
            <div class="field">
                <label for="price">Harga (Rp)</label>
                <input type="text" id="price" name="price" inputmode="numeric" required
                       value="<?= e((string) ($values['price'] ?? '')) ?>">
                <div class="hint">Contoh: 149000 atau 149.000. Isi 0 untuk plan gratis.</div>
                <?= $err('price') ?>
            </div>
        </div>

        <div class="field">
            <label for="description">Deskripsi <span class="hint">(opsional)</span></label>
            <textarea id="description" name="description" maxlength="255"><?= e((string) ($values['description'] ?? '')) ?></textarea>
            <?= $err('description') ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="billing_period_days">Periode Tagihan (hari)</label>
                <input type="number" id="billing_period_days" name="billing_period_days" min="1" step="1" required
                       value="<?= e((string) ($values['billing_period_days'] ?? '30')) ?>">
                <?= $err('billing_period_days') ?>
            </div>
            <div class="field">
                <label for="max_devices">Maks. Perangkat (PC Booth)</label>
                <input type="number" id="max_devices" name="max_devices" min="0" step="1" required
                       value="<?= e((string) ($values['max_devices'] ?? '1')) ?>">
                <?= $err('max_devices') ?>
            </div>
            <div class="field">
                <label for="max_events">Maks. Event</label>
                <input type="number" id="max_events" name="max_events" min="0" step="1" required
                       value="<?= e((string) ($values['max_events'] ?? '0')) ?>">
                <div class="hint">0 = tanpa batas.</div>
                <?= $err('max_events') ?>
            </div>
            <div class="field">
                <label for="drive_quota_gb">Kuota Drive (GB)</label>
                <input type="number" id="drive_quota_gb" name="drive_quota_gb" min="0" step="1" required
                       value="<?= e((string) ($values['drive_quota_gb'] ?? '0')) ?>">
                <div class="hint">0 = tanpa batas.</div>
                <?= $err('drive_quota_gb') ?>
            </div>
            <div class="field">
                <label for="session_quota">Kuota Sesi per Periode</label>
                <input type="number" id="session_quota" name="session_quota" min="0" step="1" required
                       value="<?= e((string) ($values['session_quota'] ?? '0')) ?>">
                <div class="hint">0 = tanpa batas.</div>
                <?= $err('session_quota') ?>
            </div>
            <div class="field">
                <label for="retention_days">Retensi Album (hari)</label>
                <input type="number" id="retention_days" name="retention_days" min="0" step="1" required
                       value="<?= e((string) ($values['retention_days'] ?? '90')) ?>">
                <?= $err('retention_days') ?>
            </div>
            <div class="field">
                <label for="sort_order">Urutan Tampil</label>
                <input type="number" id="sort_order" name="sort_order" step="1" required
                       value="<?= e((string) ($values['sort_order'] ?? '0')) ?>">
                <?= $err('sort_order') ?>
            </div>
        </div>

        <fieldset>
            <legend>Fitur Plan</legend>
            <?php foreach (Plan::FEATURE_FLAGS as $key => $label): ?>
                <label class="check">
                    <input type="checkbox" name="features[<?= e($key) ?>]" value="1"
                        <?= !empty($values['features'][$key]) ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <label class="check">
            <input type="checkbox" name="is_active" value="1"
                <?= !empty($values['is_active']) ? 'checked' : '' ?>>
            Plan aktif (bisa dipilih untuk lisensi baru)
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Plan</button>
            <a class="btn" href="/admin/plans">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
