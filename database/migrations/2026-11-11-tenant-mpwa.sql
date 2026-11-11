-- ---------------------------------------------------------------------
-- Gateway WA per tenant + form booking lengkap (v0.34.0,
-- keputusan pemilik 11 Nov 2026):
-- 1. Setiap tenant memakai nomor WhatsApp + API key MPWA
--    MILIKNYA SENDIRI untuk notifikasi (bukan nomor Super
--    Admin): kolom kredensial pada tenant_notifications.
-- 2. Form booking publik dilengkapi Nama Acara, Jam Mulai,
--    dan DP — dicatat pada log booking_requests.
-- Jalankan SEKALI di phpMyAdmin pada database plugin.eventbooth.id
-- SETELAH 2026-11-11-notifications.sql.
-- ---------------------------------------------------------------------

ALTER TABLE tenant_notifications
    ADD COLUMN mpwa_api_key TEXT NULL COMMENT 'API key MPWA milik tenant (terenkripsi)',
    ADD COLUMN mpwa_sender VARCHAR(32) NULL COMMENT 'Nomor perangkat MPWA milik tenant';

ALTER TABLE booking_requests
    ADD COLUMN event_name VARCHAR(160) NULL COMMENT 'Nama acara dari form booking',
    ADD COLUMN start_time TIME NULL COMMENT 'Jam mulai acara dari form booking',
    ADD COLUMN dp_amount DECIMAL(12,2) NULL COMMENT 'DP yang diisi calon klien di form booking';
