<?php
/**
 * Buku Klien — daftar klien tenant (v0.31.0): nama, WhatsApp,
 * alamat, dan event-event yang terhubung ke tiap klien.
 * Detail booking (tanggal, paket, pembayaran) ada di event.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $clients
 * @var array<int, array<int, array<string, mixed>>> $clientEvents Event per ID klien.
 * @var string $query
 * @var bool $tablesMissing Tabel buku klien belum dimigrasi.
 */

$title = 'Buku Klien';
$activeNav = 'clients';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Daftar klien usahamu — cukup nama, WhatsApp, dan alamat. Bila klien jadi booking,
    klik <strong>Buat Event</strong> pada barisnya: tanggal acara, paket yang diambil,
    dan pembayaran diisi pada event itu.
    <a class="btn btn-primary btn-small" style="margin-left: 10px;" href="/tenant/clients/create">+ Tambah Klien</a>
</p>

<?php if (!empty($tablesMissing)): ?>
    <div class="panel-box">
        <p>Fitur ini belum aktif di database. Jalankan berkas migrasi
            <strong>database/migrations/2026-11-11-tenant-ops.sql</strong> dan
            <strong>database/migrations/2026-11-11-client-flow.sql</strong>
            sekali di phpMyAdmin (impor SQL pada database plugin), lalu muat ulang halaman ini.</p>
    </div>
<?php else: ?>
    <form method="get" action="/tenant/clients" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin-bottom: 14px;">
        <label style="display: block;">
            <span class="hint" style="display: block; margin-bottom: 4px;">Cari nama / WhatsApp / alamat</span>
            <input type="text" name="q" value="<?= e((string) ($query ?? '')) ?>" placeholder="cth. Diva, Singaraja, 0812">
        </label>
        <button class="btn" type="submit">Cari</button>
    </form>

    <?php if (($clients ?? []) === []): ?>
        <div class="panel-box">
            <p>Belum ada klien yang cocok. Klik <strong>Tambah Klien</strong> untuk mencatat klien pertamamu.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th>Nama</th>
                <th>WhatsApp</th>
                <th>Alamat</th>
                <th>Event</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <?php
                $waLink = Crew::waLink(isset($client['whatsapp']) ? (string) $client['whatsapp'] : null);
                $linkedEvents = $clientEvents[(int) $client['id']] ?? [];
                ?>
                <tr>
                    <td><strong><?= e((string) $client['client_name']) ?></strong></td>
                    <td>
                        <?php if (!empty($client['whatsapp']) && $waLink !== null): ?>
                            <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= e((string) $client['whatsapp']) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) ($client['address'] ?? '—')) ?></td>
                    <td>
                        <?php if ($linkedEvents === []): ?>
                            <span class="hint">Belum ada event</span>
                        <?php else: ?>
                            <?php foreach ($linkedEvents as $linkedEvent): ?>
                                <div>
                                    <a href="/tenant/events/<?= (int) $linkedEvent['id'] ?>/album"><?= e((string) $linkedEvent['name']) ?></a>
                                    <span class="hint">(<?= e(Event::statusLabel((string) ($linkedEvent['status'] ?? 'draft'))) ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a class="btn btn-small btn-primary" href="/tenant/events/create?client_id=<?= (int) $client['id'] ?>">Buat Event</a>
                            <a class="btn btn-small" href="/tenant/clients/<?= (int) $client['id'] ?>/edit">Ubah</a>
                            <form method="post" action="/tenant/clients/<?= (int) $client['id'] ?>/delete"
                                  onsubmit="return confirm('Hapus klien ini dari buku klien? Event yang sudah terhubung tidak ikut terhapus, hanya tautan kliennya yang lepas.');">
                                <?= Auth::csrfField() ?>
                                <button class="btn btn-small" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
