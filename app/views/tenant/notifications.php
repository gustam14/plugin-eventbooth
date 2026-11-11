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
    Atur pesan WhatsApp usahamu di sini. Semua pesan dikirim dari <strong>nomor WhatsApp milikmu sendiri</strong>
    lewat gateway MPWA (wa.eventbooth.id) — bukan dari nomor platform. Isi API key &amp; nomor pengirim
    akun MPWA-mu pada bagian Gateway di bawah.
</p>

<?php if (!empty($tablesMissing)): ?>
    <div class="panel-box">
        <p>Fitur ini belum aktif di database. Jalankan berkas-berkas migrasi terbaru
            (<strong>2026-11-11-notifications.sql</strong>, <strong>2026-11-11-tenant-mpwa.sql</strong>,
            <strong>2026-11-11-crew-reminders.sql</strong>, <strong>2026-11-11-wa-booking.sql</strong>)
            sekali di phpMyAdmin pada database plugin, lalu muat ulang halaman ini.</p>
    </div>
<?php else: ?>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 18px; margin-bottom: 6px;">Gateway WhatsApp Kamu (MPWA)</h2>
    <?php if (!empty($gatewayReady)): ?>
        <p><span class="status-on">Terhubung</span> — pesan notifikasi terkirim dari nomormu sendiri: <strong><?= e((string) ($s['mpwa_sender'] ?? '')) ?></strong>.</p>
    <?php else: ?>
        <p><span class="status-warn">Belum lengkap</span> — isi <strong>Nomor Pengirim</strong> dan <strong>API Key MPWA</strong> milikmu pada formulir Pengaturan di bawah (bagian 0), lalu simpan. Ambil API key dari panel MPWA kamu di wa.eventbooth.id (menu API / profil akun), dan pastikan nomor perangkatmu terhubung di sana.</p>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
        <form method="post" action="/tenant/notifications/test-send" style="margin-top: 10px;">
            <?= Auth::csrfField() ?>
            <button class="btn btn-small" type="submit">Kirim Pesan Tes ke WhatsApp Saya</button>
        </form>
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
            <legend>0 · Gateway MPWA Milikmu</legend>
            <div class="form-grid">
                <div class="field">
                    <label for="mpwa_sender">Nomor Pengirim (perangkat MPWA kamu)</label>
                    <input type="text" id="mpwa_sender" name="mpwa_sender" maxlength="32" inputmode="tel"
                           placeholder="08xxxxxxxxxx"
                           value="<?= e((string) ($s['mpwa_sender'] ?? '')) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                    <div class="hint">Nomor WhatsApp yang terhubung sebagai perangkat di akun MPWA kamu — dari nomor inilah semua notifikasi terkirim.</div>
                </div>
                <div class="field">
                    <label for="mpwa_api_key">API Key MPWA</label>
                    <input type="password" id="mpwa_api_key" name="mpwa_api_key" autocomplete="off"
                           placeholder="<?= !empty($s['mpwa_api_key_set']) ? 'Sudah tersimpan — isi hanya bila ingin mengganti' : 'API key dari panel MPWA kamu' ?>"
                           <?= $isAdmin ? '' : 'disabled' ?>>
                    <div class="hint">Tersimpan terenkripsi di server<?= !empty($s['mpwa_api_key_set']) ? ' (saat ini: <strong>sudah diatur</strong>)' : '' ?>. Kosongkan bila tidak ingin mengganti.</div>
                </div>
            </div>
        </fieldset>

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
                <label for="crew_template">Template pesan kru (tugas baru)</label>
                <textarea id="crew_template" name="crew_template" rows="5" maxlength="2000" <?= $isAdmin ? '' : 'disabled' ?>><?= e((string) $s['crew_template']) ?></textarea>
                <div class="hint">Placeholder: {nama_kru} · {nama_event} · {tanggal} · {jam} · {venue} · {nama_vendor}.</div>
            </div>
            <label class="check" style="margin-top: 12px;">
                <input type="checkbox" name="crew_reminder_enabled" value="1" <?= !empty($s['crew_reminder_enabled']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>
                Aktifkan juga pengingat H-1 &amp; hari-H (dikirim otomatis pukul 06.00 pagi)
            </label>
            <div class="hint">Setiap kru bertugas menerima pengingat sehari sebelum acara dan pada pagi hari acara, dari nomor WhatsApp milikmu. Kru yang sama tidak pernah menerima pengingat yang sama dua kali.</div>
            <div class="field" style="margin-top: 10px;">
                <label for="crew_h1_template">Template pengingat H-1</label>
                <textarea id="crew_h1_template" name="crew_h1_template" rows="5" maxlength="2000" <?= $isAdmin ? '' : 'disabled' ?>><?= e((string) ($s['crew_h1_template'] ?? '')) ?></textarea>
                <div class="hint">Placeholder sama seperti template tugas baru.</div>
            </div>
            <div class="field" style="margin-top: 10px;">
                <label for="crew_h0_template">Template pengingat hari-H</label>
                <textarea id="crew_h0_template" name="crew_h0_template" rows="5" maxlength="2000" <?= $isAdmin ? '' : 'disabled' ?>><?= e((string) ($s['crew_h0_template'] ?? '')) ?></textarea>
                <div class="hint">Placeholder sama seperti template tugas baru.</div>
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

        <fieldset>
            <legend>5 · Booking via WhatsApp</legend>
            <label class="check">
                <input type="checkbox" name="wa_booking_enabled" value="1" <?= !empty($s['wa_booking_enabled']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>
                Terima booking dari pesan WhatsApp (webhook MPWA)
            </label>
            <div class="hint">Calon klien mengirim pesan berformat baku ke nomor WhatsApp-mu; booking otomatis tercatat (Buku Klien + event Draf + notifikasi ke kamu) dan klien menerima balasan konfirmasi. Pesan biasa yang tidak berformat <strong>diabaikan diam-diam</strong> — percakapan normal tidak terganggu. Booking lengkap yang dikirim dari <strong>nomormu sendiri</strong> (mis. kamu mencatat booking lewat chat pribadi) atau dari <strong>dalam grup</strong> juga diproses; selain yang lengkap, pesan dari kedua arah itu dilewati diam-diam. Pesan yang menyebut "booking" tetapi formatnya belum terbaca akan dibalas contoh format (maksimal 1x per jam per nomor).</div>
            <?php if (!empty($webhookUrl)): ?>
                <div class="field" style="margin-top: 10px;">
                    <label>URL Webhook MPWA kamu</label>
                    <input type="text" readonly value="<?= e((string) $webhookUrl) ?>" onclick="this.select();">
                    <div class="hint">Tempel URL ini pada pengaturan <strong>Webhook</strong> perangkatmu di panel MPWA (wa.eventbooth.id). URL ini rahasia — jangan dibagikan ke siapa pun.</div>
                </div>
            <?php else: ?>
                <div class="flash flash-info" style="margin-top: 10px;">URL webhook belum tersedia — jalankan migrasi terbaru (2026-11-11-wa-booking.sql) lalu muat ulang halaman ini.</div>
            <?php endif; ?>
            <div class="field" style="margin-top: 10px;">
                <label>Format pesan untuk calon klien (salin &amp; bagikan)</label>
                <textarea readonly rows="10" onclick="this.select();"><?= e((string) ($bookingFormatText ?? '')) ?></textarea>
                <div class="hint">Bagikan format ini di Instagram / status WhatsApp / brosur — calon klien tinggal mengisi setelah tanda titik dua lalu mengirimnya ke nomormu.</div>
            </div>
            <?php if (($webhookLogs ?? []) !== []): ?>
                <div class="field" style="margin-top: 10px;">
                    <label>Webhook terakhir diterima</label>
                    <div class="table-wrap">
                        <table class="list list-cards">
                            <thead><tr><th>Waktu</th><th>Pengirim</th><th>Hasil</th></tr></thead>
                            <tbody>
                            <?php foreach ($webhookLogs as $log): ?>
                                <tr>
                                    <td data-label="Waktu"><?= e((string) ($log['received_at'] ?? '')) ?></td>
                                    <td data-label="Pengirim"><?= e((string) ($log['sender'] ?? '—')) ?></td>
                                    <td data-label="Hasil"><?= e((string) ($log['result'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="hint">Hasil "created" berarti booking berhasil tercatat; "ignored" berarti pesan biasa yang memang tidak diproses.</div>
                </div>
            <?php endif; ?>
            <?php if (!empty($webhookDiag)): ?>
                <div class="field" style="margin-top: 10px;">
                    <label>Diagnosis webhook terakhir</label>
                    <div class="hint">
                        Hasil: <strong><?= e((string) $webhookDiag['result']) ?></strong> ·
                        Pengirim terbaca: <?= e((string) ($webhookDiag['sender'] ?? '—')) ?> ·
                        Konteks: <?= !empty($webhookDiag['is_group']) ? 'grup' : (!empty($webhookDiag['from_me']) ? 'pesan sendiri' : 'chat pribadi') ?>
                        <?php if ($webhookDiag['parsed'] !== null): ?>
                            <br>Label terbaca: <strong><?= (int) $webhookDiag['parsed']['labels'] ?>/8</strong> ·
                            Nama: "<?= e((string) $webhookDiag['parsed']['nama']) ?>" ·
                            Paket: "<?= e((string) $webhookDiag['parsed']['paket']) ?>" ·
                            Tanggal: "<?= e((string) $webhookDiag['parsed']['tanggal']) ?>"
                            → <?= $webhookDiag['parsed_date'] !== null ? e((string) $webhookDiag['parsed_date']) : 'tidak terbaca' ?>
                        <?php endif; ?>
                    </div>
                    <label style="margin-top: 8px;">Teks yang terbaca sistem</label>
                    <textarea readonly rows="6" onclick="this.select();"><?= e((string) ($webhookDiag['text'] ?? '')) ?></textarea>
                    <details style="margin-top: 8px;">
                        <summary>Payload mentah dari MPWA</summary>
                        <textarea readonly rows="8" onclick="this.select();"><?= e((string) $webhookDiag['raw']) ?></textarea>
                    </details>
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
            <tr><th>Waktu</th><th>Nama</th><th>WhatsApp</th><th>Nama Acara</th><th>Tanggal</th><th>Paket</th><th>DP</th><th>Hasil Otomatis</th></tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e(License::formatDateId((string) ($request['created_at'] ?? ''))) ?></td>
                    <td><strong><?= e((string) $request['name']) ?></strong><?= ($request['source'] ?? 'web') === 'wa' ? ' <span class="badge">via WA</span>' : '' ?></td>
                    <td><?= e((string) ($request['whatsapp'] ?? '—')) ?></td>
                    <td><?= e((string) ($request['event_name'] ?? '—')) ?></td>
                    <td><?= !empty($request['event_date']) ? e(License::formatDateId((string) $request['event_date'])) : '—' ?><?= !empty($request['start_time']) ? ' · ' . e(substr((string) $request['start_time'], 0, 5)) : '' ?></td>
                    <td><?= e((string) ($request['package_name'] ?? '—')) ?></td>
                    <td><?= isset($request['dp_amount']) && $request['dp_amount'] !== null ? e(Package::formatPrice($request['dp_amount'])) : '—' ?></td>
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
