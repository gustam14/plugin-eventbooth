-- Branding tenant di galeri publik (v0.24.0, 11 Nov 2026):
-- logo (nama berkas di storage/branding), warna aksen hex,
-- tagline, dan nama pengguna Instagram untuk tenants.
-- Jalankan SATU KALI di phpMyAdmin. Kode website toleran
-- sebelum migrasi ini dijalankan (galeri tampil bawaan).
ALTER TABLE tenants
    ADD COLUMN brand_logo VARCHAR(255) NULL COMMENT 'Nama berkas logo tenant di storage/branding',
    ADD COLUMN brand_color VARCHAR(7) NULL COMMENT 'Warna aksen galeri publik (#RRGGBB)',
    ADD COLUMN brand_tagline VARCHAR(160) NULL COMMENT 'Tagline tampil di kepala galeri publik',
    ADD COLUMN brand_instagram VARCHAR(80) NULL COMMENT 'Nama pengguna Instagram tenant (tanpa @)';
