-- Migrasi v0.20.0 — Retensi album per event (override Super Admin)
-- Jalankan SATU KALI di phpMyAdmin pada database plugin.
-- NULL  = retensi event mengikuti retensi plan tenant.
-- 0     = album event ini tidak pernah dibersihkan otomatis.
-- angka = retensi khusus event ini (hari), mengabaikan plan.
-- Kode website toleran: fitur lain tetap berjalan sebelum
-- migrasi ini dijalankan; hanya penyimpanan override yang
-- akan ditolak dengan pesan pengingat sampai kolom ada.

ALTER TABLE events
    ADD COLUMN retention_override_days INT NULL DEFAULT NULL
    COMMENT 'Override retensi album (hari) per event; NULL = ikut plan tenant';
