<?php
/**
 * Buku Klien — daftar klien & booking tenant (v0.28.0),
 * lengkap dengan nilai paket, DP, dan sisa pembayaran.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $bookings
 * @var array{active_count: int, active_total: float, outstanding: float} $stats
 * @var string $query
 * @var string $statusFilter
 * @var bool $tablesMissing Tabel buku klien belum dimigrasi.
 */

$title = 'Buku Klien';
$activeNav = 'clients';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Catatan semua klien &amp; booking usahamu — tanggal acara, paket yang diambil,
    DP yang sudah masuk, dan sisa yang belum lunas.
    <a class="btn btn-primary btn-small" style="margin-left: 10px;" href="/tenant/clients/create">+ Tambah Klien</a>
</p>

<?php if (!empty($tablesMissing)): ?>
    <div class="panel-box">
        <p>Fitur ini belum aktif di database. Jalankan berkas migrasi
            <strong>database/migrations/2026-11-11-tenant-ops.sql</strong>
            sekali di phpMyAdmin (impor SQL pada database plugin), lalu muat ulang halaman ini.</p>
    </div>
<?php else: ?>
    <div class="panel-box" style="display: flex; flex-wrap: wrap; gap: 12px 40px;">
        <div>
            <div class="hint">Booking berjalan</div>
            <strong style="font-size: 20px;"><?= (int) ($stats['active_count'] ?? 0) ?> klien</strong>
        </div>
        <div>
            <div class="hint">Total nilai booking berjalan</div>
            <strong style="font-size: 20px;"><?= e(Package::formatPrice($stats['active_total'] ?? 0)) ?></strong>
        </div>
        <div>
            <div class="hint">Sisa belum lunas</div>
            <strong style="font-size: 20px;"><?= e(Package::formatPrice($stats['outstanding'] ?? 0)) ?></strong>
        </div>
    </div>

    <form method="get" action="/tenant/clients" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin-bottom: 14px;">
        <label style="display: block;">
            <span class="hint" style="display: block; margin-bottom: 4px;">Cari klien / lokasi / paket</span>
            <input type="text" name="q" value="<?= e((string) ($query ?? '')) ?>" placeholder="cth. Diva, Tianyar, Limited">
        </label>
        <label style="display: block;">
            <span class="hint" style="display: block; margin-bottom: 4px;">Status</span>
            <select name="status">
                <option value="">Semua status</option>
                <?php foreach (ClientBook::STATUSES as $statusValue => $statusLabel): ?>
                    <option value="<?= e($statusValue) ?>"<?= (string) ($statusFilter ?? '') === $statusValue ? ' selected' : '' ?>>
                        <?= e($statusLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit">Terapkan</button>
    </form>

    <?php if (($bookings ?? []) === []): ?>
        <div class="panel-box">
            <p>Belum ada catatan yang cocok. Klik <strong>Tambah Klien</strong> untuk mencatat klien pertamamu.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th>Klien</th>
                <th>Tanggal Acara</th>
                <th>Paket</th>
                <th>Nilai</th>
                <th>DP</th>
                <th>Sisa</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $booking): ?>
                <?php
                $remaining = ClientBook::remaining($booking);
                $waLink = Crew::waLink(isset($booking['whatsapp']) ? (string) $booking['whatsapp'] : null);
                ?>
                <tr>
                    <td>
                        <strong><?= e((string) $booking['client_name']) ?></strong>
                        <?php if (!empty($booking['venue'])): ?>
                            <div class="hint"><?= e((string) $booking['venue']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($booking['whatsapp']) && $waLink !== null): ?>
                            <div class="hint">WA: <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= e((string) $booking['whatsapp']) ?></a></div>
                        <?php endif; ?>
                        <?php if (!empty($booking['event_name'])): ?>
                            <div class="hint">Galeri: <?= e((string) $booking['event_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($booking['event_date']) ? e(License::formatDateId((string) $booking['event_date'])) : '—' ?></td>
                    <td><?= e((string) ($booking['package_name'] ?? '—')) ?></td>
                    <td><?= e(Package::formatPrice($booking['total_price'] ?? 0)) ?></td>
                    <td><?= e(Package::formatPrice($booking['dp_paid'] ?? 0)) ?></td>
                    <td>
                        <?php if ($remaining > 0): ?>
                            <strong><?= e(Package::formatPrice($remaining)) ?></strong>
                        <?php else: ?>
                            <span class="status-on">Lunas</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(ClientBook::statusLabel((string) ($booking['status'] ?? ''))) ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn btn-small" href="/tenant/clients/<?= (int) $booking['id'] ?>/edit">Ubah</a>
                            <form method="post" action="/tenant/clients/<?= (int) $booking['id'] ?>/delete"
                                  onsubmit="return confirm('Hapus catatan klien ini dari buku klien?');">
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
