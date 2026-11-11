<?php
/**
 * Formulir buat/ubah catatan buku klien (v0.28.0).
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $heading
 * @var string $action
 * @var array<string, mixed>  $values Nilai tampilan (ClientBook::formValues()).
 * @var array<string, string> $errors Peta error per kolom.
 * @var array<int, array<string, mixed>> $events Event milik tenant (tautan galeri).
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
<p class="sub">Catat klien sejak pertama bertanya sampai acara selesai — pembayaran terpantau dari DP sampai lunas.</p>

<div class="panel-box form-panel">
    <form method="post" action="<?= e($action) ?>">
        <?= Auth::csrfField() ?>

        <div class="form-grid">
            <div class="field">
                <label for="client_name">Nama Klien</label>
                <input type="text" id="client_name" name="client_name" maxlength="120" required
                       placeholder="cth. Diva &amp; Anasita / PT Sinar Abadi / SMAN 1 Singaraja"
                       value="<?= e((string) ($values['client_name'] ?? '')) ?>">
                <?= $err('client_name') ?>
            </div>
            <div class="field">
                <label for="contact_name">Nama Kontak (yang dihubungi)</label>
                <input type="text" id="contact_name" name="contact_name" maxlength="120"
                       value="<?= e((string) ($values['contact_name'] ?? '')) ?>">
                <?= $err('contact_name') ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="whatsapp">WhatsApp Klien</label>
                <input type="text" id="whatsapp" name="whatsapp" maxlength="32" inputmode="tel"
                       placeholder="08xxxxxxxxxx"
                       value="<?= e((string) ($values['whatsapp'] ?? '')) ?>">
                <?= $err('whatsapp') ?>
            </div>
            <div class="field">
                <label for="email">Email Klien</label>
                <input type="email" id="email" name="email" maxlength="120"
                       value="<?= e((string) ($values['email'] ?? '')) ?>">
                <?= $err('email') ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="event_date">Tanggal Acara</label>
                <input type="date" id="event_date" name="event_date"
                       value="<?= e((string) ($values['event_date'] ?? '')) ?>">
                <?= $err('event_date') ?>
            </div>
            <div class="field">
                <label for="venue">Lokasi / Venue</label>
                <input type="text" id="venue" name="venue" maxlength="160"
                       value="<?= e((string) ($values['venue'] ?? '')) ?>">
                <?= $err('venue') ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="package_name">Paket yang Diambil</label>
                <input type="text" id="package_name" name="package_name" maxlength="120"
                       placeholder="cth. Limited 400 Foto"
                       value="<?= e((string) ($values['package_name'] ?? '')) ?>">
                <?= $err('package_name') ?>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (ClientBook::STATUSES as $statusValue => $statusLabel): ?>
                        <option value="<?= e($statusValue) ?>"<?= (string) ($values['status'] ?? 'inquiry') === $statusValue ? ' selected' : '' ?>>
                            <?= e($statusLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $err('status') ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="total_price">Nilai Paket (Rp)</label>
                <input type="text" id="total_price" name="total_price" inputmode="numeric"
                       value="<?= e((string) ($values['total_price'] ?? '')) ?>">
                <div class="hint">Contoh: 2500000 atau 2.500.000.</div>
                <?= $err('total_price') ?>
            </div>
            <div class="field">
                <label for="dp_paid">DP Sudah Dibayar (Rp)</label>
                <input type="text" id="dp_paid" name="dp_paid" inputmode="numeric"
                       value="<?= e((string) ($values['dp_paid'] ?? '')) ?>">
                <div class="hint">Sisa pembayaran dihitung otomatis di daftar buku klien.</div>
                <?= $err('dp_paid') ?>
            </div>
        </div>

        <div class="field">
            <label for="event_id">Event Galeri Terkait</label>
            <select id="event_id" name="event_id">
                <option value="">— Belum dikaitkan —</option>
                <?php foreach (($events ?? []) as $eventOption): ?>
                    <option value="<?= (int) $eventOption['id'] ?>"<?= (string) ($values['event_id'] ?? '') === (string) (int) $eventOption['id'] ? ' selected' : '' ?>>
                        <?= e((string) $eventOption['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">Kaitkan bila event galerinya sudah dibuat, agar catatan klien dan galeri saling terhubung.</div>
            <?= $err('event_id') ?>
        </div>

        <div class="field">
            <label for="notes">Catatan</label>
            <textarea id="notes" name="notes" rows="3"><?= e((string) ($values['notes'] ?? '')) ?></textarea>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Klien</button>
            <a class="btn" href="/tenant/clients">Batal</a>
        </div>
    </form>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
