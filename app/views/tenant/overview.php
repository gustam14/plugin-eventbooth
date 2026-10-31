<?php
/**
 * Ringkasan tenant.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed>|null $tenant Baris tenant milik pengguna (atau null).
 * @var array{events: int, licenses: int, sessions: int} $counts Jumlah milik tenant ini saja.
 * @var array{plan_name: string, status: string, expires_at: ?string}|null $license Lisensi terakhir tenant.
 */

$title = 'Ringkasan Tenant';
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
<p class="sub">Selamat datang, <?= e($user['name']) ?>. Ini ringkasan akun booth kamu.</p>

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

<div class="cards">
    <div class="card soon">
        <span class="soon-tag">Segera hadir</span>
        <h2>Event &amp; Album</h2>
        <p>Buat event, atur tampilan album publik, dan tetapkan akun Google Drive penampung file event.</p>
    </div>
    <div class="card soon">
        <span class="soon-tag">Segera hadir</span>
        <h2>Paket &amp; Timer</h2>
        <p>Paket harga/timer fleksibel per event — per sesi, per durasi, atau gabungan — plus kunci payment yang bisa dimatikan untuk wedding.</p>
    </div>
    <div class="card">
        <h2>Lisensi Saya</h2>
        <p>Lihat kunci, masa berlaku, dan perangkat yang terikat pada lisensimu.</p>
        <p style="margin-top: 10px;"><a class="btn btn-small" href="/tenant/licenses">Buka Lisensi Saya</a></p>
    </div>
    <div class="card soon">
        <span class="soon-tag">Segera hadir</span>
        <h2>Pengaturan Midtrans</h2>
        <p>Hubungkan akun Midtrans milikmu agar pembayaran tamu masuk langsung ke merchant kamu.</p>
    </div>
    <div class="card soon">
        <span class="soon-tag">Segera hadir</span>
        <h2>Statistik</h2>
        <p>Ringkasan sesi, pendapatan, dan pemakaian kuota plan kamu.</p>
    </div>
</div>
<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
