-- =====================================================================
-- Migrasi SATU KALI: Milestone 4 — Tenant & Lisensi.
-- Plugin Eventbooth, 31 Oktober 2026 (kode v0.5.0).
--
-- Isi migrasi:
--   1. tenants: kolom data vendor lengkap untuk notifikasi
--      perpanjangan — vendor_name (nama vendor/bisnis), whatsapp
--      (nomor ternormalisasi format 62…), address (alamat).
--      contact_name (penanggung jawab) dan notes (catatan) SUDAH ada
--      sejak skema awal sehingga tidak ditambah lagi. Nilai awal
--      vendor_name diisi dari name dan whatsapp dari phone.
--   2. licenses: plan_snapshot (JSON — snapshot batas & fitur plan
--      saat lisensi terbit) dan last_seen_at (aktivasi/validasi API
--      terakhir dari aplikasi booth).
--   3. Tabel baru license_notifications: catatan pengingat
--      perpanjangan yang sudah dikirim per ambang 7/3/1 hari.
--
-- Cara pakai: buka phpMyAdmin, pilih database website ini, buka tab
-- SQL, tempel SELURUH isi berkas ini, lalu klik Kirim. Jalankan hanya
-- sekali — menjalankan ulang akan gagal karena kolom/tabelnya sudah
-- ada. Lakukan SEBELUM memakai menu Tenant & Lisensi di dashboard.
-- =====================================================================

ALTER TABLE tenants ADD COLUMN vendor_name VARCHAR(160) NULL COMMENT 'Nama vendor/bisnis — label tampilan utama' AFTER name;
ALTER TABLE tenants ADD COLUMN whatsapp VARCHAR(32) NULL COMMENT 'WhatsApp ternormalisasi format internasional (62…)' AFTER phone;
ALTER TABLE tenants ADD COLUMN address TEXT NULL COMMENT 'Alamat vendor' AFTER whatsapp;

UPDATE tenants SET vendor_name = name WHERE vendor_name IS NULL OR vendor_name = '';
UPDATE tenants SET whatsapp = phone WHERE (whatsapp IS NULL OR whatsapp = '') AND phone IS NOT NULL AND phone <> '';

ALTER TABLE licenses ADD COLUMN plan_snapshot JSON NULL COMMENT 'Snapshot lengkap batas & fitur plan saat lisensi terbit' AFTER max_devices;
ALTER TABLE licenses ADD COLUMN last_seen_at DATETIME NULL COMMENT 'Aktivasi/validasi API terakhir dari aplikasi booth' AFTER activated_at;

CREATE TABLE license_notifications (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    license_id          BIGINT UNSIGNED NOT NULL,
    threshold_days      INT NOT NULL COMMENT 'Ambang pengingat: hari sebelum kedaluwarsa (7/3/1)',
    expires_at_snapshot DATETIME NOT NULL COMMENT 'Nilai expires_at lisensi saat pengingat dicatat',
    channel             VARCHAR(32) NOT NULL DEFAULT 'whatsapp',
    status              VARCHAR(32) NOT NULL DEFAULT 'pending',
    sent_at             DATETIME NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_license_notifications (license_id, threshold_days, expires_at_snapshot),
    KEY idx_license_notifications_status (status),
    CONSTRAINT fk_license_notifications_license FOREIGN KEY (license_id) REFERENCES licenses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
