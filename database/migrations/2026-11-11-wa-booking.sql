-- ---------------------------------------------------------------------
-- Booking via WhatsApp (v0.36.0, diminta pemilik 11 Nov 2026):
-- calon klien mengirim pesan WA berformat baku (Nama / No WA /
-- Paket / Nama Acara / Tanggal / Jam Mulai / DP / Share Lokasi)
-- ke nomor tenant; MPWA meneruskannya lewat webhook ke
-- website, lalu booking diproses otomatis persis seperti form
-- booking web (Buku Klien + event draf + notifikasi pemilik +
-- balasan konfirmasi ke klien).
-- Jalankan SEKALI di phpMyAdmin pada database plugin.eventbooth.id
-- SETELAH 2026-11-11-crew-reminders.sql.
-- ---------------------------------------------------------------------

ALTER TABLE tenant_notifications
    ADD COLUMN wa_booking_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Terima booking via pesan WhatsApp (webhook MPWA)',
    ADD COLUMN wa_webhook_token VARCHAR(64) NULL COMMENT 'Token rahasia pada URL webhook MPWA milik tenant';

ALTER TABLE booking_requests
    ADD COLUMN source ENUM('web','wa') NOT NULL DEFAULT 'web' COMMENT 'Asal booking: form web atau pesan WhatsApp';

CREATE TABLE wa_webhook_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id   BIGINT UNSIGNED NULL,
    sender      VARCHAR(64) NULL,
    matched     TINYINT(1) NOT NULL DEFAULT 0,
    result      VARCHAR(48) NULL,
    payload     TEXT NULL COMMENT 'Isi mentah webhook (dipotong) untuk diagnosis format MPWA',
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wa_webhook_logs_tenant (tenant_id, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
