-- =====================================================================
-- Migrasi SATU KALI: kolom masa aktif lisensi pada tabel `plans`.
-- Plugin Eventbooth, 31 Oktober 2026 (kode v0.3.2).
--
-- Cara pakai: buka phpMyAdmin, pilih database website ini, buka tab
-- SQL, tempel SELURUH isi berkas ini, lalu klik Kirim. Jalankan hanya
-- sekali — menjalankan ulang akan gagal karena kolomnya sudah ada.
--
-- Setelah migrasi, kolom terisi nilai bawaan 30 hari untuk semua
-- plan. Sesuaikan lewat dashboard Super Admin -> Plan Lisensi -> Ubah
-- (mis. plan Trial diatur 14 hari) bila perlu.
-- =====================================================================

ALTER TABLE plans ADD COLUMN license_duration_days INT NOT NULL DEFAULT 30 AFTER billing_period_days;
