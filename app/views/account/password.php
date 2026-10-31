<?php
/**
 * Formulir ganti kata sandi — untuk semua pengguna yang login
 * (super_admin, tenant_admin, tenant_staff). Kolom kata sandi
 * tidak pernah diisi ulang dari isian sebelumnya.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, string> $errors Peta error per kolom.
 */

$title = 'Ganti Kata Sandi';
$activeNav = 'account';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};
?>
<p class="sub">Kata sandi baru minimal <strong>10 karakter</strong> dan harus berbeda dari kata sandi saat ini.</p>

<div class="panel-box form-panel">
    <form method="post" action="/account/password">
        <?= Auth::csrfField() ?>

        <div class="field">
            <label for="current_password">Kata Sandi Saat Ini</label>
            <input type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
            <?= $err('current_password') ?>
        </div>

        <div class="field">
            <label for="new_password">Kata Sandi Baru</label>
            <input type="password" id="new_password" name="new_password"
                   autocomplete="new-password" required>
            <div class="hint">Minimal 10 karakter. Gunakan kata sandi yang kuat dan unik, lalu simpan di password manager.</div>
            <?= $err('new_password') ?>
        </div>

        <div class="field">
            <label for="new_password_confirmation">Konfirmasi Kata Sandi Baru</label>
            <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                   autocomplete="new-password" required>
            <?= $err('new_password_confirmation') ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Kata Sandi Baru</button>
            <a class="btn" href="/dashboard">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
