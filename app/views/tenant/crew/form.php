<?php
/**
 * Formulir buat/ubah anggota kru (v0.28.0).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $heading
 * @var string $action
 * @var array<string, mixed>  $values Nilai tampilan (Crew::formValues()).
 * @var array<string, string> $errors Peta error per kolom.
 */

$title = $heading;
$activeNav = 'crew';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};
?>
<p class="sub">Kru yang berstatus aktif dapat ditugaskan ke event dari Kelola Album.</p>

<div class="panel-box form-panel">
    <form method="post" action="<?= e($action) ?>">
        <?= Auth::csrfField() ?>

        <div class="field">
            <label for="name">Nama Kru</label>
            <input type="text" id="name" name="name" maxlength="120" required
                   value="<?= e((string) ($values['name'] ?? '')) ?>">
            <?= $err('name') ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="role">Peran</label>
                <input type="text" id="role" name="role" maxlength="60"
                       placeholder="Operator / Fotografer / Asisten"
                       value="<?= e((string) ($values['role'] ?? '')) ?>">
                <?= $err('role') ?>
            </div>
            <div class="field">
                <label for="phone">Nomor WhatsApp</label>
                <input type="text" id="phone" name="phone" maxlength="32" inputmode="tel"
                       placeholder="08xxxxxxxxxx"
                       value="<?= e((string) ($values['phone'] ?? '')) ?>">
                <?= $err('phone') ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (Crew::STATUSES as $statusValue => $statusLabel): ?>
                        <option value="<?= e($statusValue) ?>"<?= (string) ($values['status'] ?? 'active') === $statusValue ? ' selected' : '' ?>>
                            <?= e($statusLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Kru nonaktif tidak muncul pada pilihan penugasan event.</div>
            </div>
            <div class="field">
                <label for="notes">Catatan</label>
                <input type="text" id="notes" name="notes" maxlength="255"
                       value="<?= e((string) ($values['notes'] ?? '')) ?>">
                <?= $err('notes') ?>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Kru</button>
            <a class="btn" href="/tenant/crew">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
