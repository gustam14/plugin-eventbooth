-- Banner galeri publik (v0.25.0, 11 Nov 2026): banner bawaan
-- per tenant (tenants.brand_banner) dan banner khusus per
-- event (events.banner_image). Nilai = nama berkas di
-- storage/banners. Jalankan SATU KALI di phpMyAdmin. Kode
-- website toleran sebelum migrasi ini dijalankan (galeri
-- tampil tanpa banner).
ALTER TABLE tenants
    ADD COLUMN brand_banner VARCHAR(255) NULL COMMENT 'Nama berkas banner bawaan galeri di storage/banners';
ALTER TABLE events
    ADD COLUMN banner_image VARCHAR(255) NULL COMMENT 'Nama berkas banner khusus event di storage/banners';
