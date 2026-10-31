<?php
/**
 * Formulir buat/ubah tenant (Super Admin, Milestone 4).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $heading   Judul formulir ("Tambah Tenant" / "Ubah Tenant").
 * @var string $action    URL tujuan POST.
 * @var array<string, string> $values Nilai tampilan (Tenant::formValues()).
 * @var array<string, string> $errors Peta error per kolom.
 * @var bool $isEdit      True pada mode ubah (tampilkan pilihan status).
 */

$title = $heading;
$activeNav = 'tenants';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};
?>
<p class="sub">
    Data kontak tenant dipakai untuk notifikasi perpanjangan lisensi
    otomatis (WhatsApp &amp; email) — isi selengkap mungkin.
</p>

<div class="panel-box form-panel">
    <form method="post" action="<?= e($action) ?>">
        <?= Auth::csrfField() ?>

        <div class="form-grid">
            <div class="field">
                <label for="vendor_name">Nama Vendor *</label>
                <input type="text" id="vendor_name" name="vendor_name" maxlength="160" required
                       value="<?= e((string) ($values['vendor_name'] ?? '')) ?>"
                       placeholder="Mis. SB Photobooth">
                <?= $err('vendor_name') ?>
            </div>
            <div class="field">
                <label for="contact_name">Nama Penanggung Jawab *</label>
                <input type="text" id="contact_name" name="contact_name" maxlength="120" required
                       value="<?= e((string) ($values['contact_name'] ?? '')) ?>"
                       placeholder="Nama orang yang bisa dihubungi">
                <?= $err('contact_name') ?>
            </div>
            <div class="field">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" maxlength="190" required
                       value="<?= e((string) ($values['email'] ?? '')) ?>"
                       placeholder="halo@vendor.example">
                <?= $err('email') ?>
            </div>
            <div class="field">
                <label for="whatsapp">WhatsApp *</label>
                <input type="text" id="whatsapp" name="whatsapp" maxlength="32" required
                       value="<?= e((string) ($values['whatsapp'] ?? '')) ?>"
                       placeholder="0812 3456 7890">
                <div class="hint">Nomor Indonesia; otomatis diubah ke format internasional (62…). Dipakai untuk pengingat perpanjangan.</div>
                <?= $err('whatsapp') ?>
            </div>
        </div>

        <div class="field">
            <label for="address">Alamat</label>
            <textarea id="address" name="address" maxlength="1000"
                      placeholder="Alamat lengkap vendor (opsional)"><?= e((string) ($values['address'] ?? '')) ?></textarea>
            <?= $err('address') ?>
        </div>

        <div class="field">
            <label for="notes">Catatan <span class="hint">(internal, tidak terlihat tenant)</span></label>
            <textarea id="notes" name="notes" maxlength="2000"
                      placeholder="Catatan internal Super Admin (opsional)"><?= e((string) ($values['notes'] ?? '')) ?></textarea>
            <?= $err('notes') ?>
        </div>

        <?php if (($isEdit ?? false)): ?>
            <div class="field">
                <label for="status">Status Tenant</label>
                <select id="status" name="status">
                    <option value="active" <?= ($values['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="suspended" <?= ($values['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Nonaktif — semua lisensi tenant ditolak API</option>
                </select>
                <div class="hint">Tenant nonaktif tidak bisa memakai aplikasi booth; lisensinya tidak diubah, hanya divalidasi sebagai nonaktif.</div>
            </div>
        <?php else: ?>
            <fieldset>
                <legend>Akun Login Tenant <span class="hint">(opsional)</span></legend>
                <p class="hint" style="margin-bottom: 14px;">
                    Buat akun agar tenant bisa masuk dashboard tenant.
                    Kosongkan seluruh seksi ini bila belum perlu.
                </p>
                <div class="form-grid">
                    <div class="field">
                        <label for="login_name">Nama Pengguna</label>
                        <input type="text" id="login_name" name="login_name" maxlength="120"
                               value="<?= e((string) ($values['login_name'] ?? '')) ?>"
                               placeholder="Kosongkan = nama penanggung jawab">
                        <?= $err('login_name') ?>
                    </div>
                    <div class="field">
                        <label for="login_email">Email Login</label>
                        <input type="email" id="login_email" name="login_email" maxlength="190"
                               value="<?= e((string) ($values['login_email'] ?? '')) ?>"
                               placeholder="login@vendor.example">
                        <?= $err('login_email') ?>
                    </div>
                    <div class="field">
                        <label for="login_password">Kata Sandi Awal</label>
                        <input type="password" id="login_password" name="login_password"
                               value="" autocomplete="new-password">
                        <div class="hint">Minimal 8 karakter. Sampaikan ke tenant lewat jalur aman; tenant bisa menggantinya dari dashboard.</div>
                        <?= $err('login_password') ?>
                    </div>
                </div>
            </fieldset>
        <?php endif; ?>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Tenant</button>
            <a class="btn" href="/admin/tenants">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
