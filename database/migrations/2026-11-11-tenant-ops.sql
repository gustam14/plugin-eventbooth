-- ---------------------------------------------------------------------
-- Fitur operasional tenant (v0.28.0, bentuk paket disesuaikan
-- v0.29.0 atas penegasan pemilik 11 Nov 2026):
--   1. package_templates — Daftar Paket: paket LAYANAN untuk
--      klien event/wedding (nama, durasi layanan, foto termasuk,
--      isi paket, harga). BERBEDA dari Paket Sesi (tabel
--      `packages`) yang dipakai tamu pada booth self-photo /
--      photobox mandiri.
--   2. crew_members + event_crew — Manajemen Kru: daftar kru
--      tenant dan penugasannya per event.
--   3. client_bookings — Buku Klien: catatan klien & booking
--      beserta nilai paket, DP, dan sisa pembayaran.
-- Jalankan SEKALI di phpMyAdmin pada database plugin.eventbooth.id.
-- ---------------------------------------------------------------------

CREATE TABLE package_templates (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id      BIGINT UNSIGNED NOT NULL,
    name           VARCHAR(120) NOT NULL,
    duration_hours DECIMAL(4,1) NULL COMMENT 'Lama layanan booth di acara (jam); NULL = tidak dibatasi jam',
    prints_included INT NULL COMMENT 'Jumlah foto/cetakan termasuk; NULL = tanpa batas (unlimited)',
    description    TEXT NULL COMMENT 'Isi / fasilitas paket (backdrop, properti, bingkai custom, dsb.)',
    price          DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    sort_order     INT NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_package_templates_tenant (tenant_id),
    CONSTRAINT fk_package_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crew_members (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id  BIGINT UNSIGNED NOT NULL,
    name       VARCHAR(120) NOT NULL,
    role       VARCHAR(60) NULL COMMENT 'Peran umum, mis. Operator / Fotografer / Asisten',
    phone      VARCHAR(32) NULL COMMENT 'Nomor WhatsApp kru',
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes      VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crew_members_tenant (tenant_id),
    CONSTRAINT fk_crew_members_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_crew (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id       BIGINT UNSIGNED NOT NULL,
    crew_member_id BIGINT UNSIGNED NOT NULL,
    role           VARCHAR(60) NULL COMMENT 'Peran pada event ini (mengikuti peran umum bila kosong)',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_crew (event_id, crew_member_id),
    KEY idx_event_crew_member (crew_member_id),
    CONSTRAINT fk_event_crew_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_crew_member FOREIGN KEY (crew_member_id) REFERENCES crew_members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_bookings (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id    BIGINT UNSIGNED NOT NULL,
    client_name  VARCHAR(120) NOT NULL COMMENT 'Nama klien, mis. nama pasangan / perusahaan',
    contact_name VARCHAR(120) NULL COMMENT 'Nama penanggung jawab / yang dapat dihubungi',
    whatsapp     VARCHAR(32) NULL,
    email        VARCHAR(120) NULL,
    event_date   DATE NULL,
    venue        VARCHAR(160) NULL,
    package_name VARCHAR(120) NULL COMMENT 'Nama paket yang diambil (teks bebas)',
    total_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
    dp_paid      DECIMAL(12,2) NOT NULL DEFAULT 0,
    status       ENUM('inquiry','booked','done','cancelled') NOT NULL DEFAULT 'inquiry',
    event_id     BIGINT UNSIGNED NULL COMMENT 'Event galeri terkait (bila sudah dibuat)',
    notes        TEXT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client_bookings_tenant (tenant_id),
    KEY idx_client_bookings_event (event_id),
    CONSTRAINT fk_client_bookings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_client_bookings_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
