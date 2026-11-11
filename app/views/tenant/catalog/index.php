<?php
/**
 * Daftar Paket — paket LAYANAN untuk klien event/wedding
 * (v0.28.0, bentuk v0.29.0). Berbeda dari Paket Sesi yang
 * dipakai tamu pada booth self-photo / photobox mandiri.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $templates
 * @var bool $tablesMissing Tabel daftar paket belum dimigrasi.
 */

$title = 'Daftar Paket';
$activeNav = 'catalog';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Paket layanan yang kamu tawarkan ke <strong>klien event / wedding</strong> — nama paket,
    durasi layanan, foto yang termasuk, dan harganya. Ini berbeda dari <strong>Paket Sesi</strong>,
    yaitu paket yang dipilih tamu di booth self-photo / photobox mandiri.
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
        <p>Belum ada paket layanan. Klik <strong>Tambah Paket</strong> untuk membuat paket pertamamu,
            misalnya "Limited 400 Foto", "Unlimited 3 Jam", atau "Paket Sekolah".</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Paket</th>
            <th>Durasi Layanan</th>
            <th>Foto Termasuk</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($templates as $template): ?>
            <?php $isActive = (int) ($template['is_active'] ?? 0) === 1; ?>
            <tr>
                <td>
                    <strong><?= e((string) $template['name']) ?></strong>
                    <?php if (!empty($template['description'])): ?>
                        <div class="hint"><?= e((string) $template['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= e(PackageTemplate::formatDuration($template['duration_hours'] ?? null)) ?></td>
                <td><?= e(PackageTemplate::formatPrints($template['prints_included'] ?? null)) ?></td>
                <td><?= e(Package::formatPrice($template['price'] ?? 0)) ?></td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="status-on">Aktif</span>
                    <?php else: ?>
                        <span class="status-off">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-small" href="/tenant/package-catalog/<?= (int) $template['id'] ?>/edit">Ubah</a>
                        <form method="post" action="/tenant/package-catalog/<?= (int) $template['id'] ?>/delete"
                              onsubmit="return confirm('Hapus paket ini dari daftar? Catatan booking klien yang sudah memakai nama paket ini tidak ikut berubah.');">
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
