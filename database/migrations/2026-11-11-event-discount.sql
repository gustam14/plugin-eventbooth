-- ---------------------------------------------------------------------
-- Diskon klien pada event (v0.32.0, diminta pemilik 11 Nov 2026):
-- selain DP, event kini bisa mencatat diskon nominal (Rp).
-- Sisa pembayaran klien = nilai paket - diskon - DP.
-- Jalankan SEKALI di phpMyAdmin pada database plugin.eventbooth.id
-- SETELAH 2026-11-11-client-flow.sql.
-- ---------------------------------------------------------------------

ALTER TABLE events
    ADD COLUMN client_discount DECIMAL(12,2) NULL COMMENT 'Diskon nominal (Rp) untuk klien' AFTER client_total_price;
