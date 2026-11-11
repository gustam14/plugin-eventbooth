-- ---------------------------------------------------------------------
-- Menu Notifikasi tenant (v0.33.0, diminta pemilik 11 Nov 2026):
-- pengaturan WhatsApp per tenant (template berbagi album untuk
-- aplikasi booth, notifikasi klien, notifikasi kru), form
-- booking publik yang otomatis mengisi Buku Klien + Event, dan
-- log permintaan booking yang masuk. Pengiriman WA dijembatani
-- MPWA platform (wa.eventbooth.id) — API key & nomor pengirim
-- tetap diatur Super Admin, bukan per tenant.
-- Jalankan SEKALI di phpMyAdmin pada database plugin.eventbooth.id.
-- ---------------------------------------------------------------------

CREATE TABLE tenant_notifications (
    tenant_id            BIGINT UNSIGNED NOT NULL,
    share_enabled        TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Berbagi galeri via WA dari aplikasi booth',
    share_template       TEXT NULL COMMENT 'Template pesan berbagi; NULL = bawaan platform',
    client_enabled       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'WA konfirmasi booking ke klien saat event dibuat',
    client_template      TEXT NULL,
    crew_enabled         TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'WA ke kru saat ditugaskan ke event',
    crew_template        TEXT NULL,
    booking_form_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Form booking publik /booking/{slug tenant} aktif',
    owner_notify_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'WA ke pemilik usaha saat form booking masuk',
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id),
    CONSTRAINT fk_tenant_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_requests (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id    BIGINT UNSIGNED NOT NULL,
    client_id    BIGINT UNSIGNED NULL COMMENT 'Klien Buku Klien yang dibuat/dipakai ulang',
    event_id     BIGINT UNSIGNED NULL COMMENT 'Event draf yang dibuat otomatis',
    name         VARCHAR(120) NOT NULL,
    whatsapp     VARCHAR(32) NULL,
    address      VARCHAR(255) NULL,
    event_date   DATE NULL,
    package_name VARCHAR(120) NULL,
    notes        TEXT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking_requests_tenant (tenant_id),
    KEY idx_booking_requests_client (client_id),
    KEY idx_booking_requests_event (event_id),
    CONSTRAINT fk_booking_requests_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_requests_client FOREIGN KEY (client_id) REFERENCES client_bookings (id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_requests_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
