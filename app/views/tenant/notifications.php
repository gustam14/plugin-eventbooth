<?php
/**
 * Menu Notifikasi tenant (v0.33.0): pengaturan WhatsApp
 * (berbagi galeri aplikasi, notifikasi klien & kru),
 * form booking publik, kirim WA manual, dan daftar
 * permintaan booking yang masuk. Pengiriman dijembatani
 * MPWA platform; API key & nomor pengirim diatur
 * Super Admin.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed> $settings Pengaturan (Notify::settings()).
 * @var array<int, array<string, mixed>> $requests Permintaan booking terakhir.
 * @var array<int, array<string, mixed>> $manualEvents Event berklien untuk kirim manual.
 * @var bool $gatewayReady
 * @var bool $tablesMissing
 * @var array<string, mixed>|null $tenant
 * @var string|null $bookingUrl
 * @var string $appUrl
 */

$title = 'Notifikasi';
$activeNav = 'notify';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$isAdmin = (string) ($user['role'] ?? '') === 'tenant_admin';
$s = $settings ?? Notify::defaults();
?>
<p class="sub">
    Atur pesan WhatsApp usahamu di sini. Semua pesan dikirim otomatis lewat gateway
    WhatsApp platform (MPWA) — kamu tidak perlu menyiapkan nomor atau API key sendiri.
</p>

<?php if (!empty($tablesMissing)): ?>
    <div class="panel-box">
        <p>Fitur ini belum aktif di database. Jalankan berkas migrasi
            <strong>database/migrations/2026-11-11-notifications.sql</strong>
            sekali di phpMyAdmin pada database plugin, lalu muat ulang halaman ini.</p>
    </div>
<?php else: ?>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 18px; margin-bottom: 6px;">Status Gateway WhatsApp</h2>
    <?php if (!empty($gatewayReady)): ?>
        <p><span class="status-on">Terhubung</span> — gateway platform siap mengirim pesan dari nomor pengirim platform.</p>
    <?php else: ?>
        <p><span class="status-warn">Belum diatur admin</span> — API key / nomor pengirim platform belum lengkap, jadi pesan belum bisa terkirim. Pengaturan &amp; template di bawah tetap bisa kamu simpan lebih dulu.</p>
    <?php endif; ?>
</div>

<div class="panel-box form-panel" style="margin-bottom: 22px;">
    <h2 style="font-size: 18px; margin-bottom: 6px;">Pengaturan WhatsApp</h2>
    <?php if (!$isAdmin): ?>
        <p class="hint">Pengaturan hanya dapat diubah oleh admin tenant. Kamu tetap dapat memakai kirim manual dan melihat form booking di bawah.</p>
    <?php endif; ?>
    <form method="post" action="/tenant/notifications">
        <?= Auth::csrfField() ?>

        <fieldset>
            <legend>1 · Berbagi Galeri dari Aplikasi Booth</legend>
            <label class="check">
                <input type="checkbox" name="share_enabled" value="1" <?= !empty($s['share_enabled']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>
                Aktifkan berbagi tautan sesi via WhatsApp dari aplikasi booth
            </label>
            <div class="hint">Tamu mengisi nomornya di layar selesai kios; pesan berikut terkirim otomatis (fitur ini juga mengikuti plan lisensimu).</div>
            <div class="field" style="margin-top: 10px;">
                <label for="share_template">Template pesan berbagi</label>
                <textarea id="share_template" name="share_template" rows="4" maxlength="2000" <?= $isAdmin ? '' : 'disabled' ?>><?= e((string) $s['share_template']) ?></textarea>
                <div class="hint">Placeholder: {kode_sesi} · {nama_event} · {tautan} · {nama_vendor}. Kosongkan untuk kembali ke bawaan.</div>
            </div>
        </fieldset>

        <fieldset>
            <legend>2 · Notifikasi ke Klien</legend>
            <label class="check">
                <input type="checkbox" name="client_enabled" value="1" <?= !empty($s['client_enabled']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>
                Kirim WA konfirmasi ke klien setiap event untuknya dibuat
            </label>
            <div class="hint">Berlaku untuk event yang dibuat dari dashboard maupun dari form booking publik, selama event itu terhubung ke klien Buku Klien yang punya nomor WhatsApp.</div>
            <div class="field" style="margin-top: 10px;">
                <label for="client_template">Template pesan klien</label>
                <textarea id="client_template" name="client_template" rows="6" maxlength="2000" <?= $isAdmin ? '' : 'disabled' ?>><?= e((string) $s['client_template']) ?></textarea>
                <div class="hint">Placeholder: {nama_klien} · {nama_event} · {tanggal} · {nama_paket} · {sisa} · {nama_vendor}.</div>
            </div>
        </fieldset>

        <fieldset>
            <legend>3 · Notifikasi ke Kru</legend>
            <label class="check">
                <input type="checkbox" name="crew_enabled" value="1" <?= !empty($s['crew_enabled']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>
                Kirim WA ke kru setiap ia BARU ditugaskan pada sebuah event
            </label>
            <div class="hint">Dikirim saat kamu menyimpan panel Kru Bertugas di Kelola Album; kru yang sudah bertugas sebelumnya tidak dikirimi lagi.</div>
            <div class="field" style="margin-top: 10px;">
                <label for="crew_template">Template pesan kru</label>
                <textarea id="crew_template" name="crew_template" rows="5" maxlength="2000" <?= $isAdmin ? '' : 'disabled' ?>><?= e((string) $s['crew_template']) ?></textarea>
                <div class="hint">Placeholder: {nama_kru} · {nama_event} · {tanggal} · {venue} · {nama_vendor}.</div>
            </div>
        </fieldset>

        <fieldset>
            <legend>4 · Form Booking Publik</legend>
            <label class="check">
                <input type="checkbox" name="booking_form_enabled" value="1" <?= !empty($s['booking_form_enabled']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>
                Aktifkan form booking publik
            </label>
            <div class="hint">Calon klien mengisi nama, WhatsApp, alamat, tanggal acara, dan paket. Setiap kiriman otomatis membuat <strong>klien di Buku Klien</strong> (nomor WA yang sama dipakai ulang) dan <strong>event berstatus Draf</strong> — kamu tinggal menindaklanjuti.</div>
            <label class="check" style="margin-top: 8px;">
                <input type="checkbox" name="owner_notify_enabled" value="1" <?= !empty($s['owner_notify_enabled']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>
                Beri tahu saya lewat WhatsApp setiap ada booking masuk
            </label>
            <div class="hint">Dikirim ke nomor WhatsApp tenant yang tercatat di platform.</div>
            <?php if (!empty($bookingUrl)): ?>
                <div class="field" style="margin-top: 10px;">
                    <label>Tautan form booking kamu</label>
                    <input type="text" readonly value="<?= e((string) $bookingUrl) ?>" onclick="this.select();">
                    <div class="hint">Bagikan tautan ini di Instagram / WhatsApp / brosurmu<?= !empty($s['booking_form_enabled']) ? '' : ' — form baru bisa dibuka setelah saklar di atas aktif dan pengaturan disimpan' ?>.</div>
                </div>
            <?php endif; ?>
        </fieldset>

        <?php if ($isAdmin): ?>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 18px; margin-bottom: 6px;">Kirim WhatsApp Manual ke Klien</h2>
    <p class="hint" style="margin-bottom: 12px;">Untuk event yang terhubung ke klien Buku Klien. Pengingat membawa sisa pembayaran (nilai − diskon − DP); tautan album memakai alamat galeri publik event.</p>
    <?php if (($manualEvents ?? []) === []): ?>
        <p>Belum ada event yang terhubung ke klien. Hubungkan klien pada formulir Buat/Ubah Event terlebih dulu.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="list">
            <thead>
            <tr><th>Event</th><th>Klien</th><th>Sisa</th><th>Kirim</th></tr>
            </thead>
            <tbody>
            <?php foreach ($manualEvents as $manualEvent): ?>
                <?php $remaining = Event::clientRemaining($manualEvent); ?>
                <tr>
                    <td><strong><?= e((string) $manualEvent['name']) ?></strong></td>
                    <td><?= e((string) ($manualEvent['client_name'] ?? '—')) ?></td>
                    <td>
                        <?php if ($remaining === null): ?>
                            —
                        <?php elseif ($remaining > 0): ?>
                            <?= e(Package::formatPrice($remaining)) ?>
                        <?php else: ?>
                            <span class="status-on">Lunas</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <form method="post" action="/tenant/notifications/send-reminder">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="event_id" value="<?= (int) $manualEvent['id'] ?>">
                                <button class="btn btn-small" type="submit">Pengingat Sisa</button>
                            </form>
                            <form method="post" action="/tenant/notifications/send-album">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="event_id" value="<?= (int) $manualEvent['id'] ?>">
                                <button class="btn btn-small" type="submit">Tautan Album</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="panel-box">
    <h2 style="font-size: 18px; margin-bottom: 6px;">Form Booking Masuk</h2>
    <?php if (($requests ?? []) === []): ?>
        <p>Belum ada permintaan booking yang masuk lewat form publik.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="list">
            <thead>
            <tr><th>Waktu</th><th>Nama</th><th>WhatsApp</th><th>Tanggal Acara</th><th>Paket</th><th>Hasil Otomatis</th></tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e(License::formatDateId((string) ($request['created_at'] ?? ''))) ?></td>
                    <td><strong><?= e((string) $request['name']) ?></strong></td>
                    <td><?= e((string) ($request['whatsapp'] ?? '—')) ?></td>
                    <td><?= !empty($request['event_date']) ? e(License::formatDateId((string) $request['event_date'])) : '—' ?></td>
                    <td><?= e((string) ($request['package_name'] ?? '—')) ?></td>
                    <td>
                        <?php if (!empty($request['event_id'])): ?>
                            <a href="/tenant/events/<?= (int) $request['event_id'] ?>/edit">Event draf #<?= (int) $request['event_id'] ?></a>
                        <?php else: ?>
                            <span class="hint">Hanya tercatat</span>
                        <?php endif; ?>
                        <?php if (!empty($request['client_id'])): ?>
                            · <a href="/tenant/clients">Buku Klien</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
