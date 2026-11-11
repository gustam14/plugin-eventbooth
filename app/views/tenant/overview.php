<?php
/**
 * Beranda tenant (sebelumnya "Ringkasan"; diganti nama dan
 * disesuaikan isinya pada v0.30.0): statistik utama, booking
 * terdekat dari Buku Klien, ringkasan operasional, profil
 * tenant, dan pintasan ke empat grup menu.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed>|null $tenant Baris tenant milik pengguna (atau null).
 * @var array{events: int, licenses: int, sessions: int} $counts Jumlah milik tenant ini saja.
 * @var array{plan_name: string, status: string, expires_at: ?string}|null $license Lisensi terakhir tenant.
 * @var array<int, array<string, mixed>> $upcomingBookings Booking mendatang (maks 5).
 * @var array{crew: int, packages: int, bookings_active: int, outstanding: float} $opsStats
 */

$title = 'Beranda';
$activeNav = 'tenant';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$statusLabel = static function (?string $status): string {
    return match ($status) {
        'active' => 'Aktif',
        'suspended' => 'Disuspensi',
        default => (string) $status,
    };
};
?>
<p class="sub">Selamat datang, <?= e($user['name']) ?>. Ini beranda akun booth kamu — semua hal penting dalam satu tampilan.</p>

<div class="stats stats-3">
    <div class="stat">
        <span class="icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 9.8h18M8 3v4M16 3v4"/></svg></span>
        <div class="num"><?= number_format((int) ($counts['events'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Event Saya</div>
    </div>
    <div class="stat">
        <span class="icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4.2"/><path d="M11.2 11.8 20 3M16.5 6.5l2.8 2.8M13.8 9.2l2.3 2.3"/></svg></span>
        <div class="num"><?= number_format((int) ($counts['licenses'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Lisensi Saya</div>
    </div>
    <div class="stat">
        <span class="icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg></span>
        <div class="num"><?= number_format((int) ($counts['sessions'] ?? 0), 0, ',', '.') ?></div>
        <div class="lbl">Sesi Foto</div>
    </div>
</div>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 18px; margin-bottom: 6px;">Booking Terdekat</h2>
    <?php if (($upcomingBookings ?? []) === []): ?>
        <p style="color: var(--muted); margin-bottom: 12px;">Belum ada booking mendatang yang tercatat. Catat klienmu di Buku Klien agar tanggal acara dan sisa pembayaran selalu terpantau di sini.</p>
        <a class="btn btn-small" href="/tenant/clients/create">+ Catat Klien</a>
    <?php else: ?>
        <div class="table-wrap">
        <table class="list">
            <thead>
            <tr><th>Tanggal</th><th>Klien</th><th>Paket</th><th>Sisa</th></tr>
            </thead>
            <tbody>
            <?php foreach ($upcomingBookings as $booking): ?>
                <?php $remaining = ClientBook::remaining($booking); ?>
                <tr>
                    <td><?= e(License::formatDateId((string) $booking['event_date'])) ?></td>
                    <td>
                        <strong><?= e((string) $booking['client_name']) ?></strong>
                        <?php if (!empty($booking['venue'])): ?>
                            <div class="hint"><?= e((string) $booking['venue']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) ($booking['package_name'] ?? '—')) ?></td>
                    <td>
                        <?php if ($remaining > 0): ?>
                            <?= e(Package::formatPrice($remaining)) ?>
                        <?php else: ?>
                            <span class="status-on">Lunas</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="margin-top: 12px;"><a class="btn btn-small" href="/tenant/clients">Buka Buku Klien</a></p>
    <?php endif; ?>
</div>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 18px; margin-bottom: 14px;">Operasional</h2>
    <div style="display: flex; flex-wrap: wrap; gap: 12px 40px; margin-bottom: 14px;">
        <div>
            <div class="hint">Kru aktif</div>
            <strong style="font-size: 20px;"><?= (int) ($opsStats['crew'] ?? 0) ?> orang</strong>
        </div>
        <div>
            <div class="hint">Paket layanan</div>
            <strong style="font-size: 20px;"><?= (int) ($opsStats['packages'] ?? 0) ?> paket</strong>
        </div>
        <div>
            <div class="hint">Booking berjalan</div>
            <strong style="font-size: 20px;"><?= (int) ($opsStats['bookings_active'] ?? 0) ?> klien</strong>
        </div>
        <div>
            <div class="hint">Sisa belum lunas</div>
            <strong style="font-size: 20px;"><?= e(Package::formatPrice($opsStats['outstanding'] ?? 0)) ?></strong>
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
        <a class="btn btn-small" href="/tenant/package-catalog">Daftar Paket</a>
        <a class="btn btn-small" href="/tenant/crew">Kru</a>
        <a class="btn btn-small" href="/tenant/clients">Buku Klien</a>
    </div>
</div>

<div class="cards" style="margin-bottom: 22px;">
    <div class="card">
        <h2>Event &amp; Galeri</h2>
        <p>Buat event, kelola album foto tamu, dan atur tampilan galeri publik dengan logo, warna, dan banner usahamu.</p>
        <p style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="btn btn-small" href="/tenant/events">Event</a>
            <a class="btn btn-small" href="/tenant/branding">Branding Galeri</a>
        </p>
    </div>
    <div class="card">
        <h2>Paket &amp; Pembayaran</h2>
        <p>Paket sesi untuk tamu di booth self-photo, dan akun Midtrans milikmu agar pembayaran QRIS tamu masuk langsung ke kamu.</p>
        <p style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="btn btn-small" href="/tenant/packages">Paket Sesi</a>
            <a class="btn btn-small" href="/tenant/midtrans">Midtrans Tamu</a>
        </p>
    </div>
    <div class="card">
        <h2>Akun &amp; Lisensi</h2>
        <p>Lihat kunci lisensi dan masa berlakunya, atur perangkat yang terikat, dan ganti kata sandi akunmu.</p>
        <p style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="btn btn-small" href="/tenant/licenses">Lisensi Saya</a>
            <a class="btn btn-small" href="/tenant/devices">Perangkat</a>
        </p>
    </div>
    <div class="card soon">
        <span class="soon-tag">Segera hadir</span>
        <h2>Statistik</h2>
        <p>Ringkasan sesi, pendapatan, dan pemakaian kuota plan kamu.</p>
    </div>
</div>

<div class="panel-box">
    <h2 style="font-size: 18px; margin-bottom: 14px;">Profil Tenant</h2>
    <?php if ($tenant !== null): ?>
        <dl class="profile">
            <dt>Nama Tenant</dt>
            <dd><?= e((string) $tenant['name']) ?></dd>
            <dt>Status</dt>
            <dd><?= e($statusLabel(isset($tenant['status']) ? (string) $tenant['status'] : null)) ?></dd>
            <?php if (!empty($tenant['contact_name'])): ?>
                <dt>Nama Kontak</dt>
                <dd><?= e((string) $tenant['contact_name']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($tenant['email'])): ?>
                <dt>Email</dt>
                <dd><?= e((string) $tenant['email']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($tenant['phone'])): ?>
                <dt>WhatsApp</dt>
                <dd><?= e((string) $tenant['phone']) ?></dd>
            <?php endif; ?>
            <dt>Terdaftar Sejak</dt>
            <dd><?= e(date('d-m-Y', strtotime((string) $tenant['created_at']))) ?></dd>
            <?php if ($license !== null): ?>
                <dt>Lisensi Terakhir</dt>
                <dd>
                    Plan <?= e((string) $license['plan_name']) ?> · status <?= e((string) $license['status']) ?>
                    <?php if (!empty($license['expires_at'])): ?>
                        · berlaku s.d. <?= e(date('d-m-Y', strtotime((string) $license['expires_at']))) ?>
                    <?php endif; ?>
                </dd>
            <?php endif; ?>
        </dl>
    <?php else: ?>
        <p style="color: var(--muted);">
            Data tenant belum ditemukan untuk akun ini. Hubungi Super
            Admin bila ini tidak sesuai.
        </p>
    <?php endif; ?>
    <div class="form-actions">
        <a class="btn btn-primary" href="/tenant/licenses">Lihat Lisensi Saya</a>
    </div>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
