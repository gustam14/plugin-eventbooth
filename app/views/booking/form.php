<?php
/**
 * Form booking publik per tenant (v0.33.0): /booking/{slug}.
 * Kiriman otomatis membuat klien di Buku Klien + event draf
 * pada dashboard tenant.
 *
 * @var string $appName
 * @var string $appUrl
 * @var array<string, mixed> $tenant Baris tenant pemilik form.
 * @var bool $formEnabled
 * @var array<int, array{id: int, name: string, price: float}> $packages
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var bool $submitted
 */

$vendor = Tenant::displayName($tenant);
$tenantWa = Tenant::whatsappOf($tenant);
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="field-error">' . e((string) $errors[$field]) . '</div>'
        : '';
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0C0F">
<title>Booking · <?= e($vendor) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230A0C0F'/%3E%3Cg fill='none' stroke='%23C9A24B' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 26h6l4-5h16l4 5h6a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V30a4 4 0 0 1 4-4z'/%3E%3Ccircle cx='32' cy='37' r='7'/%3E%3C/g%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
<?php require BASE_PATH . '/app/views/partials/app.css.php'; ?>
</style>
</head>
<body class="auth">
<div class="auth-shell">
    <div class="auth-logo">
        <span class="logo-mark lg" aria-hidden="true">
            <svg width="29" height="29" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
        </span>
        <span class="brandmark-text"><?= e($vendor) ?></span>
    </div>

    <main class="auth-card">
        <?php if (!empty($submitted)): ?>
            <h1 class="card-title">Terima kasih, <?= e((string) ($values['name'] ?? '')) ?>!</h1>
            <p class="sub">Permintaan bookingmu sudah kami terima dan tercatat. Tim <?= e($vendor) ?> akan menghubungimu lewat WhatsApp untuk konfirmasi ketersediaan dan langkah berikutnya.</p>
            <?php if ($tenantWa !== null): ?>
                <p style="margin-top: 14px;"><a class="btn btn-primary" href="<?= e(Whatsapp::link($tenantWa)) ?>" target="_blank" rel="noopener">Chat WhatsApp Kami</a></p>
            <?php endif; ?>
        <?php elseif (empty($formEnabled)): ?>
            <h1 class="card-title">Form booking belum dibuka</h1>
            <p class="sub"><?= e($vendor) ?> belum membuka form booking online saat ini. Silakan hubungi langsung lewat WhatsApp untuk menanyakan ketersediaan tanggal.</p>
            <?php if ($tenantWa !== null): ?>
                <p style="margin-top: 14px;"><a class="btn btn-primary" href="<?= e(Whatsapp::link($tenantWa)) ?>" target="_blank" rel="noopener">Chat WhatsApp</a></p>
            <?php endif; ?>
        <?php else: ?>
            <h1 class="card-title">Booking <?= e($vendor) ?></h1>
            <p class="sub">Isi formulir singkat ini — permintaanmu langsung masuk ke sistem kami dan kami balas lewat WhatsApp.<?php if (!empty($tenant['brand_tagline'])): ?> <?= e((string) $tenant['brand_tagline']) ?><?php endif; ?></p>

            <form method="post" action="/booking/<?= e((string) ($tenant['slug'] ?? '')) ?>">
                <?= Auth::csrfField() ?>
                <div style="position: absolute; left: -9999px; top: -9999px;" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="field">
                    <label for="bk-name">Nama Kamu / Nama Pasangan / Nama Instansi</label>
                    <input type="text" id="bk-name" name="name" maxlength="120" required
                           placeholder="cth. Diva &amp; Anasita"
                           value="<?= e((string) ($values['name'] ?? '')) ?>">
                    <?= $err('name') ?>
                </div>

                <div class="field">
                    <label for="bk-wa">Nomor WhatsApp Aktif</label>
                    <input type="text" id="bk-wa" name="whatsapp" maxlength="32" required inputmode="tel"
                           placeholder="08xxxxxxxxxx"
                           value="<?= e((string) ($values['whatsapp'] ?? '')) ?>">
                    <?= $err('whatsapp') ?>
                </div>

                <div class="field">
                    <label for="bk-address">Alamat / Domisili</label>
                    <input type="text" id="bk-address" name="address" maxlength="255"
                           placeholder="cth. Tianyar Barat, Karangasem"
                           value="<?= e((string) ($values['address'] ?? '')) ?>">
                    <?= $err('address') ?>
                </div>

                <div class="field">
                    <label for="bk-date">Tanggal Acara</label>
                    <input type="date" id="bk-date" name="event_date"
                           value="<?= e((string) ($values['event_date'] ?? '')) ?>">
                    <?= $err('event_date') ?>
                </div>

                <?php if (($packages ?? []) !== []): ?>
                    <div class="field">
                        <label for="bk-package">Paket yang Diminati</label>
                        <select id="bk-package" name="package_id">
                            <option value="">— Belum menentukan —</option>
                            <?php foreach ($packages as $package): ?>
                                <option value="<?= (int) $package['id'] ?>"<?= (string) ($values['package_id'] ?? '') === (string) (int) $package['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $package['name']) ?> — <?= e(Package::formatPrice($package['price'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="bk-notes">Catatan Tambahan <span class="hint">(opsional)</span></label>
                    <textarea id="bk-notes" name="notes" rows="3" maxlength="1000"
                              placeholder="cth. Acara di gedung, mulai jam 10 pagi, tema rustic"><?= e((string) ($values['notes'] ?? '')) ?></textarea>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Kirim Permintaan Booking</button>
                </div>
                <p class="hint" style="margin-top: 10px;">Mengirim formulir ini berarti kamu setuju dihubungi <?= e($vendor) ?> lewat WhatsApp terkait booking ini.</p>
            </form>
        <?php endif; ?>
    </main>
    <p class="hint" style="text-align: center; margin-top: 14px;">Didukung oleh Plugin Eventbooth</p>
</div>
</body>
</html>
