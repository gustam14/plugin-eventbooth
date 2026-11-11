-- ---------------------------------------------------------------------
-- Pengingat kru H-1 & Hari-H (v0.35.0, diminta pemilik
-- 11 Nov 2026): notifikasi kru kini tiga jenis — (1) saat
-- tugas baru ditentukan, (2) H-1 acara, (3) hari-H acara.
-- Pengingat H-1 dan hari-H dikirim pukul 06.00 oleh endpoint
-- otomasi /api/automation/crew-reminders (workflow n8n
-- harian), dengan penanda anti-dobel per kru per event.
-- Jalankan SEKALI di phpMyAdmin pada database plugin.eventbooth.id
-- SETELAH 2026-11-11-tenant-mpwa.sql.
-- ---------------------------------------------------------------------

ALTER TABLE tenant_notifications
    ADD COLUMN crew_reminder_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pengingat kru H-1 & hari-H pukul 06.00 aktif',
    ADD COLUMN crew_h1_template TEXT NULL COMMENT 'Template pengingat kru H-1; NULL = bawaan',
    ADD COLUMN crew_h0_template TEXT NULL COMMENT 'Template pengingat kru hari-H; NULL = bawaan';

CREATE TABLE crew_notifications_sent (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id       BIGINT UNSIGNED NOT NULL,
    crew_member_id BIGINT UNSIGNED NOT NULL,
    kind           ENUM('h1','h0') NOT NULL,
    sent_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_crew_notifications (event_id, crew_member_id, kind),
    KEY idx_crew_notifications_event (event_id),
    CONSTRAINT fk_crew_notifications_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_crew_notifications_member FOREIGN KEY (crew_member_id) REFERENCES crew_members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
