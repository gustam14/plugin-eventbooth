<?php
/**
 * Daftar Paket — katalog paket tingkat tenant (v0.28.0).
 * Cetakan paket yang dipakai berulang; dapat disalin menjadi
 * paket sungguhan pada event mana pun.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $templates
 * @var array<int, array<string, mixed>> $events Event milik tenant (tujuan salin).
 * @var bool $tablesMissing Tabel katalog belum dimigrasi.
 */

$title = 'Daftar Paket';
$activeNav = 'catalog';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Katalog paket usahamu — buat sekali, lalu salin ke event mana pun tanpa mengetik ulang.
    Paket yang berjalan di booth tetap diatur per event di menu Paket Sesi.
    <a class="btn btn-primary btn-small" style="margin-left: 10px;" href="/tenant/package-catalog/create">+ Tambah Paket</a>
</p>

<?php if (!empty($tablesMissing)): ?>
    <div class="panel-box">
        <p>Fitur ini belum aktif di database. Jalankan berkas migrasi
            <strong>database/migrations/2026-11-11-tenant-ops.sql</strong>
            sekali di phpMyAdmin (impor SQL pada database plugin), lalu muat ulang halaman ini.</p>
    </div>
<?php elseif (($templates ?? []) === []): ?>
    <div class="panel-box">
        <p>Belum ada paket di katalog. Klik <strong>Tambah Paket</strong> untuk membuat cetakan paket pertamamu,
            misalnya "Limited 400 Foto", "Unlimited 2 Jam", atau "Paket Sekolah".</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Paket</th>
            <th>Mode Timer</th>
            <th>Sesi</th>
            <th>Durasi</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Salin ke Event</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($templates as $template): ?>
            <?php $isActive = (int) ($template['is_active'] ?? 0) === 1; ?>
            <tr>
                <td><strong><?= e((string) $template['name']) ?></strong></td>
                <td><?= e(Package::timerModeLabel((string) ($template['timer_mode'] ?? ''))) ?></td>
                <td><?= ($template['session_count'] ?? null) !== null ? number_format((int) $template['session_count'], 0, ',', '.') . ' sesi' : '—' ?></td>
                <td><?= ($template['duration_minutes'] ?? null) !== null ? number_format((int) $template['duration_minutes'], 0, ',', '.') . ' menit' : '—' ?></td>
                <td><?= e(Package::formatPrice($template['price'] ?? 0)) ?></td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="status-on">Aktif</span>
                    <?php else: ?>
                        <span class="status-off">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($events ?? []) === []): ?>
                        <span class="hint">Belum ada event</span>
                    <?php else: ?>
                        <form method="post" action="/tenant/package-catalog/<?= (int) $template['id'] ?>/apply"
                              style="display: flex; gap: 6px; align-items: center;">
                            <?= Auth::csrfField() ?>
                            <select name="event_id" required>
                                <option value="">— Pilih event —</option>
                                <?php foreach ($events as $eventOption): ?>
                                    <option value="<?= (int) $eventOption['id'] ?>"><?= e((string) $eventOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-small" type="submit">Salin</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-small" href="/tenant/package-catalog/<?= (int) $template['id'] ?>/edit">Ubah</a>
                        <form method="post" action="/tenant/package-catalog/<?= (int) $template['id'] ?>/delete"
                              onsubmit="return confirm('Hapus paket ini dari daftar? Paket pada event yang sudah memakai salinannya tidak ikut terhapus.');">
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
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
