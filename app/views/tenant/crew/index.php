<?php
/**
 * Manajemen Kru — daftar anggota kru tenant (v0.28.0).
 * Penugasan per event diatur dari panel Kru Bertugas di
 * Kelola Album tiap event.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<int, array<string, mixed>> $members
 * @var bool $tablesMissing Tabel kru belum dimigrasi.
 */

$title = 'Kru';
$activeNav = 'crew';
require BASE_PATH . '/app/views/partials/dashboard_top.php';
?>
<p class="sub">
    Daftar orang yang bekerja untuk usahamu — operator, fotografer, asisten.
    Siapa bertugas pada event apa diatur dari panel <strong>Kru Bertugas</strong> di Kelola Album tiap event.
    <a class="btn btn-primary btn-small" style="margin-left: 10px;" href="/tenant/crew/create">+ Tambah Kru</a>
</p>

<?php if (!empty($tablesMissing)): ?>
    <div class="panel-box">
        <p>Fitur ini belum aktif di database. Jalankan berkas migrasi
            <strong>database/migrations/2026-11-11-tenant-ops.sql</strong>
            sekali di phpMyAdmin (impor SQL pada database plugin), lalu muat ulang halaman ini.</p>
    </div>
<?php elseif (($members ?? []) === []): ?>
    <div class="panel-box">
        <p>Belum ada kru. Klik <strong>Tambah Kru</strong> untuk mencatat anggota tim pertamamu.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Nama</th>
            <th>Peran</th>
            <th>WhatsApp</th>
            <th>Status</th>
            <th>Catatan</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $member): ?>
            <?php $waLink = Crew::waLink(isset($member['phone']) ? (string) $member['phone'] : null); ?>
            <tr>
                <td><strong><?= e((string) $member['name']) ?></strong></td>
                <td><?= e((string) ($member['role'] ?? '—')) ?></td>
                <td>
                    <?php if (!empty($member['phone']) && $waLink !== null): ?>
                        <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= e((string) $member['phone']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((string) ($member['status'] ?? 'active') === 'active'): ?>
                        <span class="status-on">Aktif</span>
                    <?php else: ?>
                        <span class="status-off">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td><?= e((string) ($member['notes'] ?? '—')) ?></td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-small" href="/tenant/crew/<?= (int) $member['id'] ?>/edit">Ubah</a>
                        <form method="post" action="/tenant/crew/<?= (int) $member['id'] ?>/delete"
                              onsubmit="return confirm('Hapus kru ini? Penugasannya pada semua event ikut terhapus.');">
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
