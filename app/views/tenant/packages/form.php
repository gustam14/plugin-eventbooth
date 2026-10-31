<?php
/**
 * Formulir buat/ubah paket sesi tenant (Milestone 5a).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $heading   Judul formulir ("Tambah Paket" / "Ubah Paket").
 * @var string $action    URL tujuan POST.
 * @var array<string, mixed>  $values Nilai tampilan (Package::formValues()).
 * @var array<string, string> $errors Peta error per kolom.
 * @var array<int, array<string, mixed>> $events Event milik tenant (pilihan event induk).
 */

$title = $heading;
$activeNav = 'packages';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};
?>
<p class="sub">Paket menempel pada satu event. Mode timer menentukan batas sesi tamu di booth.</p>

<div class="panel-box form-panel">
    <form method="post" action="<?= e($action) ?>">
        <?= Auth::csrfField() ?>

        <div class="field">
            <label for="event_id">Event</label>
            <select id="event_id" name="event_id" required>
                <option value="">— Pilih event —</option>
                <?php foreach (($events ?? []) as $eventOption): ?>
                    <option value="<?= (int) $eventOption['id'] ?>"<?= (string) ($values['event_id'] ?? '') === (string) (int) $eventOption['id'] ? ' selected' : '' ?>>
                        <?= e((string) $eventOption['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $err('event_id') ?>
        </div>

        <div class="field">
            <label for="name">Nama Paket</label>
            <input type="text" id="name" name="name" maxlength="120" required
                   value="<?= e((string) ($values['name'] ?? '')) ?>">
            <?= $err('name') ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="timer_mode">Mode Timer</label>
                <select id="timer_mode" name="timer_mode">
                    <?php foreach (Package::TIMER_MODES as $modeValue => $modeLabel): ?>
                        <option value="<?= e($modeValue) ?>"<?= (string) ($values['timer_mode'] ?? 'per_session') === $modeValue ? ' selected' : '' ?>>
                            <?= e($modeLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Per Sesi = dibatasi jumlah sesi foto. Per Durasi = dibatasi menit. Gabungan = keduanya.</div>
                <?= $err('timer_mode') ?>
            </div>
            <div class="field">
                <label for="price">Harga (Rp)</label>
                <input type="text" id="price" name="price" inputmode="numeric" required
                       value="<?= e((string) ($values['price'] ?? '')) ?>">
                <div class="hint">Contoh: 25000 atau 25.000. Isi 0 untuk paket gratis.</div>
                <?= $err('price') ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="session_count">Jumlah Sesi <span class="hint">(mode Per Sesi / Gabungan)</span></label>
                <input type="number" id="session_count" name="session_count" min="0" step="1"
                       value="<?= e((string) ($values['session_count'] ?? '')) ?>">
                <?= $err('session_count') ?>
            </div>
            <div class="field">
                <label for="duration_minutes">Durasi (menit) <span class="hint">(mode Per Durasi / Gabungan)</span></label>
                <input type="number" id="duration_minutes" name="duration_minutes" min="0" step="1"
                       value="<?= e((string) ($values['duration_minutes'] ?? '')) ?>">
                <?= $err('duration_minutes') ?>
            </div>
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
            Paket aktif (ditawarkan ke aplikasi booth)
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Paket</button>
            <a class="btn" href="/tenant/packages">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
