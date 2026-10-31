<?php
/**
 * Formulir buat/ubah event tenant (Milestone 5a).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $heading   Judul formulir ("Buat Event" / "Ubah Event").
 * @var string $action    URL tujuan POST.
 * @var array<string, mixed>  $values Nilai tampilan (Event::formValues()).
 * @var array<string, string> $errors Peta error per kolom.
 * @var array<int, array<string, mixed>> $driveAccounts Akun Drive platform berstatus connected.
 */

$title = $heading;
$activeNav = 'events';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};

$fmtGb = static function ($bytes): string {
    $bytes = (float) $bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return rtrim(rtrim(number_format($bytes, 1, ',', '.'), '0'), ',') . ' ' . $units[$i];
};

$selectedDrive = (string) ($values['drive_account_id'] ?? 'auto');
?>
<p class="sub">
    Album event ini dapat dibuka publik di alamat
    <strong>/e/{slug}</strong>. Akun penyimpanan dikunci untuk
    event ini saat disimpan.
</p>

<div class="panel-box form-panel">
    <form method="post" action="<?= e($action) ?>">
        <?= Auth::csrfField() ?>

        <div class="field">
            <label for="name">Nama Event</label>
            <input type="text" id="name" name="name" maxlength="160" required
                   value="<?= e((string) ($values['name'] ?? '')) ?>">
            <?= $err('name') ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="slug">Slug Album <span class="hint">(kosongkan untuk otomatis dari nama)</span></label>
                <input type="text" id="slug" name="slug" maxlength="140"
                       value="<?= e((string) ($values['slug'] ?? '')) ?>">
                <div class="hint">Menjadi alamat album: /e/slug-ini. Harus unik di seluruh platform.</div>
                <?= $err('slug') ?>
            </div>
            <div class="field">
                <label for="venue">Lokasi / Venue <span class="hint">(opsional)</span></label>
                <input type="text" id="venue" name="venue" maxlength="190"
                       value="<?= e((string) ($values['venue'] ?? '')) ?>">
                <?= $err('venue') ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="starts_at">Tanggal &amp; Jam Mulai <span class="hint">(opsional)</span></label>
                <input type="datetime-local" id="starts_at" name="starts_at"
                       value="<?= e((string) ($values['starts_at'] ?? '')) ?>">
                <?= $err('starts_at') ?>
            </div>
            <div class="field">
                <label for="ends_at">Tanggal &amp; Jam Selesai <span class="hint">(opsional)</span></label>
                <input type="datetime-local" id="ends_at" name="ends_at"
                       value="<?= e((string) ($values['ends_at'] ?? '')) ?>">
                <?= $err('ends_at') ?>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (Event::STATUSES as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>"<?= (string) ($values['status'] ?? 'draft') === $statusOption ? ' selected' : '' ?>>
                            <?= e(Event::statusLabel($statusOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Aktif = event berjalan &amp; ditawarkan ke aplikasi booth. Selesai/Diarsipkan disembunyikan dari aplikasi.</div>
                <?= $err('status') ?>
            </div>
        </div>

        <div class="field">
            <label for="drive_account_id">Akun Penyimpanan (Drive Platform)</label>
            <select id="drive_account_id" name="drive_account_id">
                <option value="auto"<?= $selectedDrive === 'auto' ? ' selected' : '' ?>>
                    Otomatis — akun dengan sisa kuota paling lega
                </option>
                <option value=""<?= $selectedDrive === '' ? ' selected' : '' ?>>
                    Tanpa akun penyimpanan (upload &amp; album tidak berjalan)
                </option>
                <?php foreach (($driveAccounts ?? []) as $account): ?>
                    <?php
                    $accountId = (string) (int) $account['id'];
                    $accountLabel = trim((string) ($account['label'] ?? '')) !== ''
                        ? (string) $account['label']
                        : (string) ($account['google_email'] ?? '');
                    $quotaNote = '';
                    if (($account['quota_total_bytes'] ?? null) !== null && ($account['quota_used_bytes'] ?? null) !== null) {
                        $free = (int) $account['quota_total_bytes'] - (int) $account['quota_used_bytes'];
                        $quotaNote = ' — sisa ' . $fmtGb(max(0, $free));
                    }
                    ?>
                    <option value="<?= e($accountId) ?>"<?= $selectedDrive === $accountId ? ' selected' : '' ?>>
                        <?= e($accountLabel) ?><?= e($quotaNote) ?><?= !empty($account['is_default']) ? ' (default)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">
                Berkas event disimpan terpusat di akun penyimpanan platform yang diatur Super Admin.
                <?php if (($driveAccounts ?? []) === []): ?>
                    Saat ini belum ada akun yang terhubung — event tetap bisa dibuat, tetapi upload belum berjalan.
                <?php endif; ?>
            </div>
            <?= $err('drive_account_id') ?>
        </div>

        <fieldset>
            <legend>Pembayaran Tamu</legend>
            <label class="check">
                <input type="checkbox" name="payment_lock_enabled" value="1"
                    <?= !empty($values['payment_lock_enabled']) ? 'checked' : '' ?>>
                Kunci pembayaran (tamu harus membayar sebelum sesi)
            </label>
            <div class="hint">Matikan untuk event seperti wedding agar tamu langsung berfoto tanpa membayar.</div>

            <div class="form-grid" style="margin-top: 14px;">
                <div class="field">
                    <label for="limit_mode">Batas Saat Kunci Pembayaran Mati</label>
                    <select id="limit_mode" name="limit_mode">
                        <?php foreach (Event::LIMIT_MODES as $modeOption): ?>
                            <option value="<?= e($modeOption) ?>"<?= (string) ($values['limit_mode'] ?? 'none') === $modeOption ? ' selected' : '' ?>>
                                <?= e(Event::limitModeLabel($modeOption)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('limit_mode') ?>
                </div>
                <div class="field">
                    <label for="session_quota_limit">Batas Jumlah Sesi <span class="hint">(bila mode jumlah sesi)</span></label>
                    <input type="number" id="session_quota_limit" name="session_quota_limit" min="0" step="1"
                           value="<?= e((string) ($values['session_quota_limit'] ?? '')) ?>">
                    <?= $err('session_quota_limit') ?>
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Event</button>
            <a class="btn" href="/tenant/events">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
