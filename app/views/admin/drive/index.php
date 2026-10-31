<?php
/**
 * Google Drive pusat (Super Admin, Milestone 3).
 *
 * Halaman ini mengelola KONEKSI akun Drive milik platform: kredensial
 * OAuth Google (Client ID/Secret) dan daftar akun yang terkait.
 * Refresh token & Client Secret TIDAK PERNAH ditampilkan di sini —
 * secret hanya ditandai "sudah tersimpan" atau belum.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var bool $cryptoReady      APP_KEY di .env sudah diatur (enkripsi bisa dipakai).
 * @var string $clientId       Client ID tersimpan (boleh kosong).
 * @var bool $secretSet        Client Secret sudah tersimpan (terenkripsi).
 * @var bool $credentialsReady Client ID + Secret lengkap dan APP_KEY siap.
 * @var string $redirectUri    Redirect URI yang didaftarkan di Google Cloud Console.
 * @var array<int, array<string, mixed>> $accounts Baris google_accounts.
 */

$title = 'Google Drive';
$activeNav = 'drive';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

/** Format byte menjadi satuan terbaca gaya Indonesia. */
$fmtBytes = static function (?int $bytes): string {
    if ($bytes === null) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
};

/** Label + kelas badge untuk status akun. */
$statusBadge = static function (string $status): array {
    return match ($status) {
        'connected' => ['Terhubung', 'status-on'],
        'error' => ['Error', 'status-off'],
        default => ['Terputus', 'status-off'],
    };
};
?>
<p class="sub">
    Akun Google Drive <strong>milik platform</strong> yang menampung file
    semua tenant. Tenant tidak menyiapkan Drive sendiri; penugasan akun
    ke event dilakukan saat event dibuat.
</p>

<?php if (!($cryptoReady ?? false)): ?>
    <div class="notice">
        <strong>APP_KEY belum diatur di berkas <code>.env</code> server.</strong><br>
        Tanpa APP_KEY, refresh token Google tidak dapat disimpan secara
        terenkripsi, sehingga pengaitan akun Drive dinonaktifkan. Isi
        <code>APP_KEY</code> dengan string acak yang panjang (minimal 32
        karakter) di <code>.env</code>, lalu muat ulang halaman ini.
    </div>
<?php endif; ?>

<div class="panel-box form-panel" style="margin-bottom: 22px;">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 6px;">Pengaturan OAuth Google</h2>
    <p class="hint" style="margin-bottom: 18px;">
        Client ID &amp; Client Secret dari Google Cloud Console (satu
        project milik platform). Daftarkan Redirect URI berikut persis
        sama di sana:
    </p>
    <p style="margin-bottom: 18px;">
        <code style="font-size: 14px; word-break: break-all;"><?= e((string) ($redirectUri ?? '')) ?></code>
    </p>
    <form method="post" action="/admin/drive/settings">
        <?= Auth::csrfField() ?>
        <div class="field">
            <label for="google_client_id">Client ID</label>
            <input type="text" id="google_client_id" name="google_client_id"
                   value="<?= e((string) ($clientId ?? '')) ?>"
                   placeholder="xxxx.apps.googleusercontent.com" autocomplete="off">
        </div>
        <div class="field">
            <label for="google_client_secret">Client Secret</label>
            <input type="password" id="google_client_secret" name="google_client_secret"
                   value="" autocomplete="new-password"
                   placeholder="<?= ($secretSet ?? false) ? '•••••••• (sudah tersimpan)' : 'Belum tersimpan' ?>">
            <div class="hint">
                <?php if ($secretSet ?? false): ?>
                    Sudah tersimpan terenkripsi. Kosongkan untuk mempertahankan
                    yang lama; isi hanya bila ingin mengganti.
                <?php else: ?>
                    Belum ada secret tersimpan. Secret disimpan terenkripsi
                    dan tidak pernah ditampilkan kembali.
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
        </div>
    </form>
</div>

<div class="panel-box" style="margin-bottom: 22px;">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 6px;">Akun Drive Terkait</h2>
    <p class="hint" style="margin-bottom: 16px;">
        Kaitkan akun Google milik platform. Kamu akan diarahkan ke
        halaman persetujuan Google dan masuk sendiri dengan akun
        tersebut — persetujuan itu tidak bisa diwakilkan.
    </p>
    <?php if ($credentialsReady ?? false): ?>
        <a class="btn btn-primary" href="/admin/drive/connect">Kaitkan Google Drive</a>
    <?php else: ?>
        <button class="btn btn-primary" type="button" disabled>Kaitkan Google Drive</button>
        <div class="hint" style="margin-top: 8px;">
            Tombol aktif setelah Client ID &amp; Client Secret tersimpan
            <?php if (!($cryptoReady ?? false)): ?>
                dan <code>APP_KEY</code> diatur di <code>.env</code>
            <?php endif; ?>.
        </div>
    <?php endif; ?>
</div>

<?php if (($accounts ?? []) === []): ?>
    <div class="panel-box">
        <p>Belum ada akun Drive yang terkait. Gunakan tombol
            <strong>Kaitkan Google Drive</strong> di atas untuk menambahkan
            akun pertama — akun pertama otomatis menjadi akun default.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table class="list">
        <thead>
        <tr>
            <th>Akun</th>
            <th>Status</th>
            <th>Kuota</th>
            <th>Folder Root</th>
            <th>Terakhir Dicek</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($accounts as $account): ?>
            <?php
            [$statusLabel, $statusClass] = $statusBadge((string) ($account['status'] ?? ''));
            $isDefault = (int) ($account['is_default'] ?? 0) === 1;
            $quotaTotal = isset($account['quota_total_bytes']) && $account['quota_total_bytes'] !== null
                ? (int) $account['quota_total_bytes'] : null;
            $quotaUsed = isset($account['quota_used_bytes']) && $account['quota_used_bytes'] !== null
                ? (int) $account['quota_used_bytes'] : null;
            $quotaPct = ($quotaTotal !== null && $quotaTotal > 0 && $quotaUsed !== null)
                ? min(100, (int) round($quotaUsed / $quotaTotal * 100)) : null;
            $rootId = (string) ($account['root_folder_id'] ?? '');
            $checkedAt = (string) ($account['quota_checked_at'] ?? '');
            ?>
            <tr>
                <td>
                    <strong><?= e((string) ($account['google_email'] ?? '')) ?></strong>
                    <?php if ($isDefault): ?>
                        <span class="status-on" style="margin-left: 6px;">Default</span>
                    <?php endif; ?>
                    <?php if (!empty($account['label'])): ?>
                        <br><span style="color: var(--muted); font-size: 13px;"><?= e((string) $account['label']) ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="<?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                <td>
                    <?php if ($quotaUsed === null): ?>
                        <span class="quota-text">Belum terukur</span>
                    <?php elseif ($quotaTotal === null || $quotaTotal === 0): ?>
                        <span class="quota-text"><?= e($fmtBytes($quotaUsed)) ?> terpakai (tanpa batas)</span>
                    <?php else: ?>
                        <div class="quota-bar"><div class="quota-fill" style="width: <?= $quotaPct ?>%;"></div></div>
                        <span class="quota-text"><?= e($fmtBytes($quotaUsed)) ?> dari <?= e($fmtBytes($quotaTotal)) ?> (<?= $quotaPct ?>%)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rootId !== ''): ?>
                        <code title="<?= e($rootId) ?>"><?= e(strlen($rootId) > 14 ? substr($rootId, 0, 14) . '…' : $rootId) ?></code>
                    <?php else: ?>
                        <span style="color: var(--muted);">—</span>
                    <?php endif; ?>
                </td>
                <td><?= $checkedAt !== '' ? e($checkedAt) : '—' ?></td>
                <td>
                    <div class="row-actions">
                        <?php if (!$isDefault): ?>
                            <form method="post" action="/admin/drive/<?= (int) $account['id'] ?>/default">
                                <?= Auth::csrfField() ?>
                                <button class="btn btn-small" type="submit">Jadikan Default</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="/admin/drive/<?= (int) $account['id'] ?>/test">
                            <?= Auth::csrfField() ?>
                            <button class="btn btn-small" type="submit">Tes Koneksi</button>
                        </form>
                        <form method="post" action="/admin/drive/<?= (int) $account['id'] ?>/disconnect">
                            <?= Auth::csrfField() ?>
                            <button class="btn btn-small btn-danger" type="submit">Putuskan</button>
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
