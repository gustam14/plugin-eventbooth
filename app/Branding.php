<?php
declare(strict_types=1);

/**
 * Branding tenant di galeri publik (v0.24.0, diminta pemilik
 * 11 Nov 2026): tenant mengatur logo, warna aksen, tagline,
 * dan Instagram dari dashboard; kepala galeri publik (event
 * dan sesi) lalu memakai merek tenant, dan platform hanya
 * disebut kecil di kaki sebagai "Didukung oleh".
 *
 * Kolom brand_* pada tenants berasal dari migrasi
 * database/migrations/2026-11-11-tenant-branding.sql — semua
 * pembaca toleran bila migrasi belum dijalankan (galeri
 * kembali ke tampilan platform bawaan). Berkas logo disimpan
 * di storage/branding (di luar docroot publik) dan disajikan
 * lewat rute /brand-logo/{berkas}.
 */
final class Branding
{
    public const DEFAULT_COLOR = '#C9A24B';
    private const MAX_LOGO_BYTES = 2097152; // 2 MB
    private const MAX_BANNER_BYTES = 5242880; // 5 MB

    /** Palet warna siap pakai untuk pemilih di dashboard tenant. */
    public const PRESETS = [
        'Emas' => '#C9A24B',
        'Merah' => '#E5484D',
        'Oranye' => '#F76B15',
        'Hijau' => '#30A46C',
        'Teal' => '#12A594',
        'Biru' => '#3E63DD',
        'Ungu' => '#8E4EC6',
        'Merah muda' => '#D6409F',
    ];

    public static function storageDir(): string
    {
        return BASE_PATH . '/storage/branding';
    }

    public static function logoUrl(?string $file): ?string
    {
        $file = trim((string) $file);

        return $file === '' ? null : '/brand-logo/' . rawurlencode($file);
    }

    /** Folder banner galeri (di luar docroot, seperti logo). */
    public static function bannerDir(): string
    {
        return BASE_PATH . '/storage/banners';
    }

    public static function bannerUrl(?string $file): ?string
    {
        $file = trim((string) $file);

        return $file === '' ? null : '/gallery-banner/' . rawurlencode($file);
    }

    /**
     * Keadaan branding kosong (tampilan platform bawaan).
     *
     * @return array{logo_file: ?string, logo_url: ?string, color: ?string, color2: ?string, rgb: ?string, ink: ?string, tagline: string, instagram: string, instagram_url: ?string, whatsapp_url: ?string, has_custom: bool, vendor_name: string}
     */
    public static function defaults(): array
    {
        return [
            'logo_file' => null,
            'logo_url' => null,
            'color' => null,
            'color2' => null,
            'rgb' => null,
            'ink' => null,
            'tagline' => '',
            'instagram' => '',
            'instagram_url' => null,
            'whatsapp_url' => null,
            'banner_file' => null,
            'banner_url' => null,
            'has_custom' => false,
            'vendor_name' => '',
        ];
    }

    /**
     * Branding efektif tenant untuk galeri publik.
     *
     * @return array{logo_file: ?string, logo_url: ?string, color: ?string, color2: ?string, rgb: ?string, ink: ?string, tagline: string, instagram: string, instagram_url: ?string, whatsapp_url: ?string, has_custom: bool, vendor_name: string}
     */
    public static function forTenantId(PDO $pdo, int $tenantId): array
    {
        $brand = self::defaults();
        if ($tenantId <= 0) {
            return $brand;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT brand_logo, brand_color, brand_tagline, brand_instagram
                   FROM tenants WHERE id = :id'
            );
            $stmt->execute(['id' => $tenantId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $brand['logo_file'] = trim((string) ($row['brand_logo'] ?? '')) !== ''
                    ? (string) $row['brand_logo'] : null;
                $color = self::validColor((string) ($row['brand_color'] ?? ''));
                if ($color !== null) {
                    $palette = self::palette($color);
                    $brand['color'] = $palette['color'];
                    $brand['color2'] = $palette['color2'];
                    $brand['rgb'] = $palette['rgb'];
                    $brand['ink'] = $palette['ink'];
                }
                $brand['tagline'] = trim((string) ($row['brand_tagline'] ?? ''));
                $brand['instagram'] = ltrim(trim((string) ($row['brand_instagram'] ?? '')), '@');
            }
        } catch (PDOException) {
            // Kolom branding belum dimigrasi — tampilan bawaan.
        }

        // Banner bawaan tenant (v0.25.0) dibaca terpisah agar
        // kolomnya yang belum dimigrasi tidak menggagalkan
        // pembacaan branding lain.
        try {
            $stmt = $pdo->prepare('SELECT brand_banner FROM tenants WHERE id = :id');
            $stmt->execute(['id' => $tenantId]);
            $bannerFile = $stmt->fetchColumn();
            if ($bannerFile !== false && trim((string) $bannerFile) !== '') {
                $brand['banner_file'] = (string) $bannerFile;
                $brand['banner_url'] = self::bannerUrl((string) $bannerFile);
            }
        } catch (PDOException) {
            // Kolom banner belum dimigrasi.
        }

        try {
            $stmt = $pdo->prepare('SELECT whatsapp, phone FROM tenants WHERE id = :id');
            $stmt->execute(['id' => $tenantId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $wa = Whatsapp::normalize($row['whatsapp'] ?? null)
                    ?? Whatsapp::normalize($row['phone'] ?? null);
                if ($wa !== null) {
                    $brand['whatsapp_url'] = 'https://wa.me/' . $wa;
                }
            }
        } catch (PDOException) {
            // Nomor kontak tidak tersedia.
        }

        $brand['logo_url'] = self::logoUrl($brand['logo_file']);
        if ($brand['instagram'] !== '') {
            $brand['instagram_url'] = 'https://instagram.com/' . $brand['instagram'];
        }
        $brand['has_custom'] = $brand['logo_file'] !== null
            || $brand['color'] !== null
            || $brand['tagline'] !== ''
            || $brand['instagram'] !== '';

        return $brand;
    }

    /**
     * Branding + nama vendor dari ID event (halaman sesi hanya
     * memegang ID event). Kolom yang dibaca di sini adalah
     * kolom asli skema, aman sebelum migrasi branding.
     *
     * @return array{logo_file: ?string, logo_url: ?string, color: ?string, color2: ?string, rgb: ?string, ink: ?string, tagline: string, instagram: string, instagram_url: ?string, whatsapp_url: ?string, has_custom: bool, vendor_name: string}
     */
    public static function forEventId(PDO $pdo, int $eventId): array
    {
        $tenantId = 0;
        $vendor = '';
        try {
            $stmt = $pdo->prepare(
                "SELECT e.tenant_id,
                        COALESCE(NULLIF(t.vendor_name, ''), t.name) AS vendor_name
                   FROM events e
                   JOIN tenants t ON t.id = e.tenant_id
                  WHERE e.id = :id"
            );
            $stmt->execute(['id' => $eventId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $tenantId = (int) $row['tenant_id'];
                $vendor = (string) ($row['vendor_name'] ?? '');
            }
        } catch (PDOException) {
            // Branding tidak tersedia.
        }

        $brand = self::forTenantId($pdo, $tenantId);
        $brand['vendor_name'] = $vendor;

        return $brand;
    }

    /** Warna sah (#RRGGBB) atau null bila kosong/tidak sah. */
    public static function validColor(?string $color): ?string
    {
        $color = trim((string) $color);
        if ($color === '' || preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            return null;
        }

        return strtoupper($color);
    }

    /**
     * Turunan warna aksen: warna terang pendamping (gradasi),
     * komponen RGB untuk rgba(), dan warna tinta terbaca di
     * atas gradasi.
     *
     * @return array{color: string, color2: string, rgb: string, ink: string}
     */
    public static function palette(string $hex): array
    {
        $r = (int) hexdec(substr($hex, 1, 2));
        $g = (int) hexdec(substr($hex, 3, 2));
        $b = (int) hexdec(substr($hex, 5, 2));
        $mix = static fn (int $c): int => (int) round($c + (255 - $c) * 0.28);
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return [
            'color' => strtoupper($hex),
            'color2' => sprintf('#%02X%02X%02X', $mix($r), $mix($g), $mix($b)),
            'rgb' => $r . ', ' . $g . ', ' . $b,
            'ink' => $luminance > 0.55 ? '#1A1405' : '#FFFFFF',
        ];
    }

    /** Blok penimpa variabel CSS galeri ('' bila warna bawaan). */
    public static function cssVars(array $brand): string
    {
        if (($brand['color'] ?? null) === null) {
            return '';
        }

        return ':root{--gold:' . $brand['color']
            . ';--gold-2:' . $brand['color2']
            . ';--gold-grad:linear-gradient(135deg,' . $brand['color'] . ' 0%,' . $brand['color2'] . ' 100%)'
            . ';--gold-ink:' . $brand['ink']
            . ';--gold-rgb:' . $brand['rgb'] . ';}';
    }

    /**
     * Simpan warna/tagline/Instagram. PDOException dibiarkan
     * naik agar pemanggil dapat menunjukkan petunjuk migrasi.
     */
    public static function saveSettings(PDO $pdo, int $tenantId, ?string $color, string $tagline, string $instagram): void
    {
        $colorRaw = trim((string) $color);
        if ($colorRaw !== '' && self::validColor($colorRaw) === null) {
            throw new BrandingException('Format warna aksen tidak sah — gunakan kode hex seperti #C9A24B, atau kosongkan untuk warna bawaan.');
        }
        $tagline = trim($tagline);
        if (mb_strlen($tagline) > 160) {
            $tagline = mb_substr($tagline, 0, 160);
        }
        $instagram = ltrim(trim($instagram), '@');
        if ($instagram !== '' && preg_match('/^[A-Za-z0-9._]{1,30}$/', $instagram) !== 1) {
            throw new BrandingException('Nama pengguna Instagram tidak sah — cukup tulis namanya saja, tanpa @ dan tanpa tautan.');
        }

        $stmt = $pdo->prepare(
            'UPDATE tenants
                SET brand_color = :color, brand_tagline = :tagline, brand_instagram = :instagram
              WHERE id = :id'
        );
        $stmt->execute([
            'color' => $colorRaw === '' ? null : self::validColor($colorRaw),
            'tagline' => $tagline === '' ? null : $tagline,
            'instagram' => $instagram === '' ? null : $instagram,
            'id' => $tenantId,
        ]);
    }

    /**
     * Terima unggahan logo tenant: gambar PNG/JPG/WebP maks
     * 2 MB, disimpan sebagai {tenant}-{acak}.{ext} di folder
     * penyimpanan; logo lama dibuang. Mengembalikan nama
     * berkas yang tersimpan.
     *
     * @param array<string, mixed> $file Satu entri $_FILES.
     */
    public static function storeLogo(PDO $pdo, int $tenantId, array $file): string
    {
        // Pastikan kolom tersedia lebih dulu agar berkas tidak
        // yatim bila migrasi belum dijalankan (PDOException naik).
        $old = self::rawLogoFile($pdo, $tenantId);

        $dir = self::storageDir();
        $name = self::moveImageUpload($file, $dir, $tenantId . '-', self::MAX_LOGO_BYTES, 'logo');

        try {
            $stmt = $pdo->prepare('UPDATE tenants SET brand_logo = :file WHERE id = :id');
            $stmt->execute(['file' => $name, 'id' => $tenantId]);
        } catch (PDOException $e) {
            @unlink($dir . '/' . $name);
            throw $e;
        }
        if ($old !== null && $old !== $name) {
            @unlink($dir . '/' . basename($old));
        }

        return $name;
    }

    /** Hapus logo tenant (berkas + kolom). */
    public static function removeLogo(PDO $pdo, int $tenantId): void
    {
        $old = self::rawLogoFile($pdo, $tenantId);
        $stmt = $pdo->prepare('UPDATE tenants SET brand_logo = NULL WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);
        if ($old !== null) {
            @unlink(self::storageDir() . '/' . basename($old));
        }
    }

    /* -----------------------------------------------------------------
     * Banner galeri (v0.25.0, permintaan pemilik 11 Nov 2026):
     * gambar lebar di kepala album publik. Banner per event
     * (events.banner_image, diatur dari Kelola Album) menimpa
     * banner bawaan tenant (tenants.brand_banner, diatur dari
     * Branding Galeri). Berkas di storage/banners, disajikan
     * lewat rute /gallery-banner/{berkas}.
     * --------------------------------------------------------------- */

    /** Simpan banner khusus sebuah event. Mengembalikan nama berkas. */
    public static function storeEventBanner(PDO $pdo, int $eventId, array $file): string
    {
        $old = self::rawEventBanner($pdo, $eventId);
        $dir = self::bannerDir();
        $name = self::moveImageUpload($file, $dir, 'e' . $eventId . '-', self::MAX_BANNER_BYTES, 'banner');
        try {
            $stmt = $pdo->prepare('UPDATE events SET banner_image = :file WHERE id = :id');
            $stmt->execute(['file' => $name, 'id' => $eventId]);
        } catch (PDOException $e) {
            @unlink($dir . '/' . $name);
            throw $e;
        }
        if ($old !== null && $old !== $name) {
            @unlink($dir . '/' . basename($old));
        }

        return $name;
    }

    /** Hapus banner khusus event (galeri kembali ke banner bawaan tenant). */
    public static function removeEventBanner(PDO $pdo, int $eventId): void
    {
        $old = self::rawEventBanner($pdo, $eventId);
        $stmt = $pdo->prepare('UPDATE events SET banner_image = NULL WHERE id = :id');
        $stmt->execute(['id' => $eventId]);
        if ($old !== null) {
            @unlink(self::bannerDir() . '/' . basename($old));
        }
    }

    /** Simpan banner bawaan tenant. Mengembalikan nama berkas. */
    public static function storeTenantBanner(PDO $pdo, int $tenantId, array $file): string
    {
        $old = self::rawTenantBanner($pdo, $tenantId);
        $dir = self::bannerDir();
        $name = self::moveImageUpload($file, $dir, 't' . $tenantId . '-', self::MAX_BANNER_BYTES, 'banner');
        try {
            $stmt = $pdo->prepare('UPDATE tenants SET brand_banner = :file WHERE id = :id');
            $stmt->execute(['file' => $name, 'id' => $tenantId]);
        } catch (PDOException $e) {
            @unlink($dir . '/' . $name);
            throw $e;
        }
        if ($old !== null && $old !== $name) {
            @unlink($dir . '/' . basename($old));
        }

        return $name;
    }

    /** Hapus banner bawaan tenant. */
    public static function removeTenantBanner(PDO $pdo, int $tenantId): void
    {
        $old = self::rawTenantBanner($pdo, $tenantId);
        $stmt = $pdo->prepare('UPDATE tenants SET brand_banner = NULL WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);
        if ($old !== null) {
            @unlink(self::bannerDir() . '/' . basename($old));
        }
    }

    /** Nama berkas banner event (PDOException naik bila kolom belum ada). */
    private static function rawEventBanner(PDO $pdo, int $eventId): ?string
    {
        $stmt = $pdo->prepare('SELECT banner_image FROM events WHERE id = :id');
        $stmt->execute(['id' => $eventId]);
        $v = $stmt->fetchColumn();

        return $v === false || $v === null || trim((string) $v) === '' ? null : (string) $v;
    }

    /** Nama berkas banner bawaan tenant (PDOException naik bila kolom belum ada). */
    private static function rawTenantBanner(PDO $pdo, int $tenantId): ?string
    {
        $stmt = $pdo->prepare('SELECT brand_banner FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);
        $v = $stmt->fetchColumn();

        return $v === false || $v === null || trim((string) $v) === '' ? null : (string) $v;
    }

    /**
     * Validasi + pindahkan satu unggahan gambar (PNG/JPG/WebP)
     * ke folder tujuan dengan nama {prefix}{acak}.{ext}.
     *
     * @param array<string, mixed> $file Satu entri $_FILES.
     */
    private static function moveImageUpload(array $file, string $dir, string $prefix, int $maxBytes, string $label): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new BrandingException('Unggah ' . $label . ' gagal. Silakan coba lagi dengan berkas lain.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new BrandingException(
                'Ukuran ' . $label . ' maksimal ' . (string) (int) ($maxBytes / 1048576) . ' MB.'
            );
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $info = $tmp !== '' ? @getimagesize($tmp) : false;
        if ($info === false) {
            throw new BrandingException('Berkas ' . $label . ' harus berupa gambar (PNG, JPG, atau WebP).');
        }
        $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null;
        if ($ext === null) {
            throw new BrandingException('Format ' . $label . ' harus PNG, JPG, atau WebP.');
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new BrandingException('Folder penyimpanan ' . $label . ' belum dapat dibuat di server.');
        }
        $name = $prefix . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new BrandingException(ucfirst($label) . ' gagal disimpan di server. Silakan coba lagi.');
        }

        return $name;
    }

    /** Nama berkas logo tersimpan (PDOException naik bila kolom belum ada). */
    private static function rawLogoFile(PDO $pdo, int $tenantId): ?string
    {
        $stmt = $pdo->prepare('SELECT brand_logo FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);
        $v = $stmt->fetchColumn();

        return $v === false || $v === null || trim((string) $v) === '' ? null : (string) $v;
    }
}
