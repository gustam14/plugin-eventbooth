<?php
/**
 * Branding Galeri (tenant, v0.24.0): logo, warna aksen,
 * tagline, dan Instagram tenant untuk galeri publik.
 * Galeri yang memakai branding tenant menampilkan merek
 * tenant di kepala halaman; platform hanya disebut kecil
 * di kaki sebagai pendukung.
 *
 * @var string $appName
 * @var array{id: int, tenant_id: ?int, name: string, email: string, role: string} $user
 * @var array<int, array{type: string, message: string}> $flashes
 * @var array<string, mixed> $tenant Baris tenant milik pengguna.
 * @var array<string, mixed> $brand Branding efektif (Branding::forTenantId) + nilai mentah formulir.
 * @var array<string, string> $presets Palet warna siap pakai.
 * @var string|null $sampleUrl Tautan contoh galeri tenant.
 */

$title = 'Branding Galeri';
$activeNav = 'branding';
require BASE_PATH . '/app/views/partials/dashboard_top.php';

$vendor = Tenant::displayName($tenant);
$logoUrl = (string) ($brand['logo_url'] ?? '');
$bannerUrl = (string) ($brand['banner_url'] ?? '');
$colorVal = (string) ($brand['raw_color'] ?? '');
$taglineVal = (string) ($brand['raw_tagline'] ?? '');
$instagramVal = (string) ($brand['raw_instagram'] ?? '');
$waUrl = (string) ($brand['whatsapp_url'] ?? '');
?>
<style>
    .brand-logo-preview {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 180px; min-height: 76px; padding: 12px 18px;
        background: #0A0C0F; border: 1px solid var(--line, #262B33); border-radius: 12px;
    }
    .brand-logo-preview img { max-height: 56px; max-width: 240px; object-fit: contain; display: block; }
    .brand-logo-preview .none { color: #9AA3AF; font-size: 13px; }
    .brand-banner-preview {
        display: flex; align-items: center; justify-content: center;
        min-height: 84px; padding: 10px;
        background: #0A0C0F; border: 1px solid var(--line, #262B33); border-radius: 12px;
        margin-bottom: 16px; overflow: hidden;
    }
    .brand-banner-preview img { width: 100%; max-height: 170px; object-fit: cover; object-position: center top; border-radius: 8px; display: block; }
    .brand-banner-preview .none { color: #9AA3AF; font-size: 13px; }
    .swatches { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .swatches button {
        width: 34px; height: 34px; border-radius: 10px; cursor: pointer;
        border: 2px solid rgba(255, 255, 255, .18); padding: 0;
    }
    .swatches button:hover { border-color: rgba(255, 255, 255, .55); }
    .color-pair { display: flex; gap: 10px; align-items: center; }
    .color-pair input[type="color"] { width: 52px; height: 40px; padding: 2px; cursor: pointer; }
    .color-pair input[type="text"] { width: 130px; text-transform: uppercase; }
</style>

<p class="sub">
    Atur tampilan <strong>galeri publik</strong> milik <?= e($vendor) ?> — halaman album yang
    dibuka tamu dari kode QR. Begitu branding terisi, kepala galeri memakai logomu dan
    warna aksenmu; nama platform hanya tampil kecil di kaki halaman sebagai pendukung.
    <?php if (($sampleUrl ?? null) !== null): ?>
        <a class="btn btn-small" style="margin-left: 8px;" href="<?= e((string) $sampleUrl) ?>" target="_blank" rel="noopener">Lihat Contoh Galeri</a>
    <?php endif; ?>
</p>

<div class="panel-box">
    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 6px;">Logo</h2>
    <p class="hint" style="margin-bottom: 14px;">
        Tampil di kepala galeri menggantikan logo platform. PNG dengan latar transparan
        paling bagus; JPG dan WebP juga diterima. Ukuran maksimal 2 MB.
    </p>
    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center; margin-bottom: 16px;">
        <span class="brand-logo-preview">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= e($logoUrl) ?>" alt="Logo <?= e($vendor) ?>">
            <?php else: ?>
                <span class="none">Belum ada logo — galeri memakai merek platform.</span>
            <?php endif; ?>
        </span>
        <?php if ($logoUrl !== ''): ?>
            <form method="post" action="/tenant/branding/logo-remove"
                  onsubmit="return confirm('Hapus logo dari galeri? Kepala galeri kembali memakai merek platform sampai logo baru diunggah.');">
                <?= Auth::csrfField() ?>
                <button class="btn" type="submit">Hapus Logo</button>
            </form>
        <?php endif; ?>
    </div>

    <h2 style="font-size: 16.5px; font-weight: 700; margin-bottom: 6px;">Banner Bawaan Galeri</h2>
    <p class="hint" style="margin-bottom: 14px;">
        Gambar lebar di kepala album publik — membuat galeri terlihat jauh lebih profesional.
        Banner bawaan ini dipakai oleh semua event yang belum punya banner sendiri;
        banner khusus per event diatur dari Kelola Album event tersebut.
    </p>
    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center; margin-bottom: 16px;">
        <span class="brand-banner-preview" style="flex: 1 1 320px; margin-bottom: 0;">
            <?php if ($bannerUrl !== ''): ?>
                <img src="<?= e($bannerUrl) ?>" alt="Banner bawaan <?= e($vendor) ?>">
            <?php else: ?>
                <span class="none">Belum ada banner bawaan — galeri tampil tanpa banner sampai ada yang dipasang.</span>
            <?php endif; ?>
        </span>
        <?php if ($bannerUrl !== ''): ?>
            <form method="post" action="/tenant/branding/banner-remove"
                  onsubmit="return confirm('Hapus banner bawaan? Event tanpa banner khusus akan tampil tanpa banner.');">
                <?= Auth::csrfField() ?>
                <button class="btn" type="submit">Hapus Banner</button>
            </form>
        <?php endif; ?>
    </div>

    <form method="post" action="/tenant/branding" enctype="multipart/form-data">
        <?= Auth::csrfField() ?>
        <div class="field">
            <label for="logo">Unggah logo baru</label>
            <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
            <p class="hint">Kosongkan bila tidak ingin mengganti logo yang sekarang.</p>
        </div>
        <div class="field">
            <label for="banner">Unggah banner bawaan baru</label>
            <input type="file" id="banner" name="banner" accept="image/png,image/jpeg,image/webp">
            <p class="hint">Disarankan gambar lebar 1600×900 atau lebih besar, maks 5 MB. Kosongkan bila tidak ingin mengganti banner yang sekarang.</p>
        </div>

        <h2 style="font-size: 16.5px; font-weight: 700; margin: 22px 0 6px;">Warna, Tagline &amp; Sosial Media</h2>
        <div class="field">
            <label for="brand_color_text">Warna aksen galeri</label>
            <div class="color-pair">
                <input type="color" id="brand_color_picker"
                       value="<?= e($colorVal !== '' ? $colorVal : Branding::DEFAULT_COLOR) ?>">
                <input type="text" id="brand_color_text" name="brand_color" maxlength="7"
                       value="<?= e($colorVal) ?>" placeholder="<?= e(Branding::DEFAULT_COLOR) ?> (bawaan)">
            </div>
            <div class="swatches" aria-label="Warna siap pakai">
                <?php foreach (($presets ?? []) as $presetName => $presetHex): ?>
                    <button type="button" data-color="<?= e($presetHex) ?>"
                            title="<?= e($presetName) ?> · <?= e($presetHex) ?>"
                            style="background: <?= e($presetHex) ?>;"></button>
                <?php endforeach; ?>
            </div>
            <p class="hint">Mewarnai tombol, lencana, dan aksen galeri. Kosongkan kode hex untuk kembali ke emas bawaan platform.</p>
        </div>
        <div class="field">
            <label for="brand_tagline">Tagline <span class="hint">(opsional)</span></label>
            <input type="text" id="brand_tagline" name="brand_tagline" maxlength="160"
                   value="<?= e($taglineVal) ?>"
                   placeholder="mis. Abadikan momen spesialmu bersama kami">
            <p class="hint">Tampil kecil di bawah nama vendor pada kepala galeri.</p>
        </div>
        <div class="field">
            <label for="brand_instagram">Instagram <span class="hint">(opsional)</span></label>
            <input type="text" id="brand_instagram" name="brand_instagram" maxlength="31"
                   value="<?= e($instagramVal !== '' ? '@' . $instagramVal : '') ?>"
                   placeholder="@namavendor">
            <p class="hint">Tampil sebagai tautan di kepala album agar tamu mudah mengikuti akunmu.</p>
        </div>
        <div class="field">
            <p class="hint">
                Tombol <strong>Chat WhatsApp</strong> di galeri memakai nomor WhatsApp tenant
                yang sudah tercatat di platform
                <?php if ($waUrl !== ''): ?>
                    — saat ini tamu diarahkan ke nomor tersebut.
                <?php else: ?>
                    — saat ini belum ada nomor terdaftar, jadi tombolnya belum tampil. Sampaikan ke admin platform bila ingin mengganti nomor.
                <?php endif; ?>
            </p>
        </div>
        <button class="btn btn-primary" type="submit">Simpan Branding</button>
    </form>
</div>

<script>
(function () {
    var picker = document.getElementById('brand_color_picker');
    var text = document.getElementById('brand_color_text');
    if (!picker || !text) return;
    picker.addEventListener('input', function () { text.value = picker.value.toUpperCase(); });
    text.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
    });
    document.querySelectorAll('.swatches button[data-color]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var c = btn.getAttribute('data-color');
            text.value = c;
            picker.value = c;
        });
    });
})();
</script>

<?php require BASE_PATH . '/app/views/partials/dashboard_bottom.php'; ?>
