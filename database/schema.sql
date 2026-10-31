-- =====================================================================
-- Plugin Eventbooth — Skema Database (Milestone 1)
-- MySQL 8 / MariaDB 10.3+ · InnoDB · utf8mb4
--
-- Buat database & user-nya lebih dulu dari panel hosting (DH-Panel),
-- pilih database tersebut, lalu impor berkas ini (mis. via phpMyAdmin).
-- Nilai seed plan di bagian bawah adalah CONTOH yang bisa diubah dari
-- dashboard Super Admin (blueprint: plan tidak di-hardcode).
-- Berkas ini tidak berisi akun pengguna maupun rahasia apa pun.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- ---------------------------------------------------------------------
-- Plan lisensi (diatur Super Admin: harga, periode, batas & fitur)
-- Konvensi: nilai 0 pada kolom batas = tanpa batas (unlimited).
-- ---------------------------------------------------------------------
CREATE TABLE plans (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(60)  NOT NULL,
    slug                VARCHAR(60)  NOT NULL,
    description         VARCHAR(255) NULL,
    price               DECIMAL(12,2) NOT NULL DEFAULT 0,
    billing_period_days INT NOT NULL DEFAULT 30,
    max_devices         INT NOT NULL DEFAULT 1,
    max_events          INT NOT NULL DEFAULT 0,
    drive_quota_gb      INT NOT NULL DEFAULT 0,
    session_quota       INT NOT NULL DEFAULT 0 COMMENT 'Kuota sesi per periode tagihan; 0 = tanpa batas',
    retention_days      INT NOT NULL DEFAULT 90 COMMENT 'Retensi album (hari)',
    features            JSON NULL COMMENT 'Flag fitur, mis. {"payment_lock":true,"whatsapp_share":true}',
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    sort_order          INT NOT NULL DEFAULT 0,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_plans_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tenant (pembeli/pemakai lisensi) — website ini multi-tenant.
-- ---------------------------------------------------------------------
CREATE TABLE tenants (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(120) NOT NULL,
    slug         VARCHAR(80)  NOT NULL,
    contact_name VARCHAR(120) NULL,
    email        VARCHAR(190) NULL,
    phone        VARCHAR(32)  NULL COMMENT 'Nomor WhatsApp utama tenant',
    status       ENUM('active','suspended') NOT NULL DEFAULT 'active',
    notes        TEXT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pengguna dashboard: Super Admin (tenant_id NULL) dan pengguna tenant.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id     BIGINT UNSIGNED NULL,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('super_admin','tenant_admin','tenant_staff') NOT NULL DEFAULT 'tenant_admin',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_tenant (tenant_id),
    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Akun Google Drive pusat (multi-akun, blueprint §2A v4.2).
-- Refresh token disimpan TERENKRIPSI (kolom *_encrypted, enkripsi
-- memakai APP_KEY di .env). Penugasan akun dilakukan per event.
-- Hanya satu baris yang boleh is_default = 1 (dijaga level aplikasi).
-- ---------------------------------------------------------------------
CREATE TABLE google_accounts (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    label                   VARCHAR(120) NULL COMMENT 'Nama tampilan, mis. Drive Utama SB',
    google_email            VARCHAR(190) NOT NULL,
    refresh_token_encrypted TEXT NOT NULL,
    root_folder_id          VARCHAR(64) NULL COMMENT 'ID folder "Plugin Eventbooth" di akun ini',
    status                  ENUM('connected','disconnected','error') NOT NULL DEFAULT 'connected',
    is_default              TINYINT(1) NOT NULL DEFAULT 0,
    quota_total_bytes       BIGINT NULL,
    quota_used_bytes        BIGINT NULL,
    quota_checked_at        DATETIME NULL,
    connected_by            BIGINT UNSIGNED NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_google_accounts_email (google_email),
    KEY idx_google_accounts_connected_by (connected_by),
    CONSTRAINT fk_google_accounts_user FOREIGN KEY (connected_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lisensi aplikasi booth per tenant.
-- Kunci lisensi TIDAK disimpan mentah: hanya hash SHA-256 + prefix
-- untuk tampilan; kunci utuh ditampilkan sekali saat penerbitan.
-- ---------------------------------------------------------------------
CREATE TABLE licenses (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    plan_id          BIGINT UNSIGNED NOT NULL,
    license_key_hash CHAR(64) NOT NULL,
    license_key_prefix VARCHAR(16) NOT NULL COMMENT 'Awalan kunci untuk identifikasi di dashboard',
    status           ENUM('pending','active','suspended','revoked','expired') NOT NULL DEFAULT 'pending',
    max_devices      INT NOT NULL DEFAULT 1 COMMENT 'Snapshot dari plan saat terbit',
    starts_at        DATETIME NULL,
    expires_at       DATETIME NULL,
    activated_at     DATETIME NULL,
    source           ENUM('checkout','manual') NOT NULL DEFAULT 'manual' COMMENT 'checkout = dibayar via Midtrans; manual = penjualan offline',
    created_by       BIGINT UNSIGNED NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_licenses_key_hash (license_key_hash),
    KEY idx_licenses_tenant (tenant_id),
    KEY idx_licenses_plan (plan_id),
    KEY idx_licenses_created_by (created_by),
    CONSTRAINT fk_licenses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_licenses_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT,
    CONSTRAINT fk_licenses_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Perangkat (PC booth) yang terikat ke lisensi + heartbeat terakhir.
-- ---------------------------------------------------------------------
CREATE TABLE license_devices (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    license_id        BIGINT UNSIGNED NOT NULL,
    device_id_hash    CHAR(64) NOT NULL COMMENT 'Hash identitas hardware PC booth',
    device_label      VARCHAR(120) NULL,
    app_version       VARCHAR(32) NULL,
    dslrbooth_version VARCHAR(32) NULL,
    is_active         TINYINT(1) NOT NULL DEFAULT 1,
    first_seen_at     DATETIME NULL,
    last_heartbeat_at DATETIME NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_license_devices (license_id, device_id_hash),
    CONSTRAINT fk_license_devices_license FOREIGN KEY (license_id) REFERENCES licenses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pengaturan Midtrans MILIK TENANT (uang tamu masuk ke merchant
-- tenant — asumsi desain blueprint §5B). Server Key terenkripsi.
-- Berbeda dari Midtrans platform (checkout lisensi) yang diatur
-- Super Admin lewat app_settings / .env.
-- ---------------------------------------------------------------------
CREATE TABLE tenant_midtrans_settings (
    tenant_id           BIGINT UNSIGNED NOT NULL,
    is_enabled          TINYINT(1) NOT NULL DEFAULT 0,
    environment         ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
    merchant_id         VARCHAR(64) NULL,
    client_key          VARCHAR(128) NULL,
    server_key_encrypted TEXT NULL,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id),
    CONSTRAINT fk_tenant_midtrans_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Event & album. drive_account_id dikunci per event (blueprint §2A):
-- file satu event tidak tersebar ke banyak akun Drive.
-- ---------------------------------------------------------------------
CREATE TABLE events (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id            BIGINT UNSIGNED NOT NULL,
    drive_account_id     BIGINT UNSIGNED NULL COMMENT 'Akun Drive penampung event ini (terkunci saat event dibuat)',
    name                 VARCHAR(160) NOT NULL,
    slug                 VARCHAR(140) NOT NULL COMMENT 'Untuk URL publik /e/{slug}',
    venue                VARCHAR(190) NULL,
    starts_at            DATETIME NULL,
    ends_at              DATETIME NULL,
    logo_url             VARCHAR(255) NULL,
    banner_url           VARCHAR(255) NULL,
    primary_color        CHAR(7) NOT NULL DEFAULT '#111111',
    album_password_hash  VARCHAR(255) NULL COMMENT 'Kosong = album tanpa kata sandi',
    payment_lock_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Kunci payment per event (blueprint §4): 0 = mode wedding/gratis',
    limit_mode           ENUM('none','event_hours','session_quota') NOT NULL DEFAULT 'none' COMMENT 'Batas saat kunci payment OFF',
    session_quota_limit  INT NULL COMMENT 'Terpakai bila limit_mode = session_quota',
    status               ENUM('draft','active','finished','archived') NOT NULL DEFAULT 'draft',
    expires_at           DATETIME NULL COMMENT 'Album kedaluwarsa/retensi',
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_events_slug (slug),
    KEY idx_events_tenant (tenant_id),
    KEY idx_events_drive_account (drive_account_id),
    CONSTRAINT fk_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_events_drive_account FOREIGN KEY (drive_account_id) REFERENCES google_accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Paket harga/timer per event (blueprint keputusan #3):
-- timer_mode: per_session | per_duration | combined.
-- ---------------------------------------------------------------------
CREATE TABLE packages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id        BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(120) NOT NULL,
    timer_mode      ENUM('per_session','per_duration','combined') NOT NULL DEFAULT 'per_session',
    session_count   INT NULL COMMENT 'Jumlah sesi foto (mode per_session/combined)',
    duration_minutes INT NULL COMMENT 'Durasi menit (mode per_duration/combined)',
    price           DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_packages_event (event_id),
    CONSTRAINT fk_packages_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Sesi foto tamu. code = kode sesi untuk URL /s/{kode} dan QR.
-- ---------------------------------------------------------------------
CREATE TABLE sessions (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id       BIGINT UNSIGNED NOT NULL,
    package_id     BIGINT UNSIGNED NULL,
    code           VARCHAR(32) NOT NULL,
    drive_folder_id VARCHAR(64) NULL COMMENT 'Folder sesi di Google Drive',
    guest_name     VARCHAR(120) NULL,
    guest_whatsapp VARCHAR(32) NULL,
    status         ENUM('created','in_progress','completed','expired') NOT NULL DEFAULT 'created',
    started_at     DATETIME NULL,
    finished_at    DATETIME NULL,
    wa_shared_at   DATETIME NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_code (code),
    KEY idx_sessions_event (event_id),
    KEY idx_sessions_package (package_id),
    CONSTRAINT fk_sessions_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_sessions_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Media per sesi. Byte file TIDAK pernah disimpan di hosting;
-- tabel ini hanya metadata + drive_file_id (blueprint §2 & §3).
-- ---------------------------------------------------------------------
CREATE TABLE media_files (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id      BIGINT UNSIGNED NOT NULL,
    event_id        BIGINT UNSIGNED NOT NULL,
    drive_account_id BIGINT UNSIGNED NULL COMMENT 'Akun Drive pemilik file (album bisa lintas akun)',
    drive_file_id   VARCHAR(64) NOT NULL,
    drive_folder_id VARCHAR(64) NULL,
    kind            ENUM('final','original','gif','video','thumb') NOT NULL DEFAULT 'final',
    file_name       VARCHAR(255) NULL,
    mime_type       VARCHAR(100) NULL,
    size_bytes      BIGINT NULL,
    width           INT NULL,
    height          INT NULL,
    web_link        VARCHAR(512) NULL COMMENT 'Tautan lihat file di Drive',
    thumbnail_link  VARCHAR(512) NULL COMMENT 'Tautan thumbnail Drive untuk halaman album',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_media_session (session_id),
    KEY idx_media_event (event_id),
    KEY idx_media_drive_file (drive_file_id),
    KEY idx_media_drive_account (drive_account_id),
    CONSTRAINT fk_media_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_media_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_media_drive_account FOREIGN KEY (drive_account_id) REFERENCES google_accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pembayaran Midtrans — satu tabel untuk dua keperluan (blueprint
-- §5A & §5B), dibedakan kolom purpose:
--   license = checkout/perpanjangan lisensi (Midtrans platform)
--   guest   = pembayaran sesi tamu (Midtrans tenant)
-- Status hanya boleh dianggap lunas setelah webhook terverifikasi.
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purpose          ENUM('license','guest') NOT NULL,
    tenant_id        BIGINT UNSIGNED NULL,
    license_id       BIGINT UNSIGNED NULL,
    event_id         BIGINT UNSIGNED NULL,
    session_id       BIGINT UNSIGNED NULL,
    gateway          VARCHAR(32) NOT NULL DEFAULT 'midtrans',
    order_id         VARCHAR(64) NOT NULL,
    gross_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency         CHAR(3) NOT NULL DEFAULT 'IDR',
    status           ENUM('pending','challenge','paid','denied','expired','cancelled','failed','refunded') NOT NULL DEFAULT 'pending',
    snap_token       VARCHAR(128) NULL,
    payment_type     VARCHAR(64) NULL COMMENT 'Mis. qris, bank_transfer',
    transaction_id   VARCHAR(64) NULL COMMENT 'ID transaksi di Midtrans',
    status_code      VARCHAR(10) NULL,
    raw_webhook      JSON NULL COMMENT 'Payload notifikasi terakhir (untuk audit)',
    paid_at          DATETIME NULL,
    expired_at       DATETIME NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_order_id (order_id),
    KEY idx_payments_tenant (tenant_id),
    KEY idx_payments_license (license_id),
    KEY idx_payments_session (session_id),
    KEY idx_payments_event (event_id),
    CONSTRAINT fk_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_license FOREIGN KEY (license_id) REFERENCES licenses (id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Jejak audit (penerbitan/pencabutan lisensi, override kunci payment
-- dengan PIN, perubahan pengaturan penting, dsb).
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type  ENUM('super_admin','tenant','system','booth_app','n8n') NOT NULL DEFAULT 'system',
    actor_id    BIGINT UNSIGNED NULL,
    tenant_id   BIGINT UNSIGNED NULL,
    action      VARCHAR(80) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id   BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    ip_address  VARCHAR(45) NULL,
    metadata    JSON NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_tenant (tenant_id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pengaturan aplikasi level platform (diubah dari Super Admin).
-- Nilai sensitif disimpan terenkripsi dan ditandai is_encrypted = 1.
-- ---------------------------------------------------------------------
CREATE TABLE app_settings (
    `key`       VARCHAR(120) NOT NULL,
    `value`     TEXT NULL,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    updated_by  BIGINT UNSIGNED NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`),
    KEY idx_settings_updated_by (updated_by),
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED — hanya plan contoh & pengaturan bawaan.
-- TIDAK ada akun pengguna dan TIDAK ada rahasia di sini.
-- Akun Super Admin pertama dibuat lewat langkah instalasi (Milestone 2).
-- =====================================================================
INSERT INTO plans
    (name, slug, description, price, billing_period_days, max_devices, max_events, drive_quota_gb, session_quota, retention_days, features, is_active, sort_order)
VALUES
    ('Trial', 'trial', 'Plan percobaan tenant baru. Nilai contoh — ubah dari dashboard Super Admin.', 0,       14, 1, 3,  15,  100,  30,  '{"payment_lock":true,"whatsapp_share":true}', 1, 1),
    ('Basic', 'basic', 'Plan dasar untuk satu booth. Nilai contoh — ubah dari dashboard Super Admin.', 149000,  30, 1, 20, 50,  1000, 90,  '{"payment_lock":true,"whatsapp_share":true}',  1, 2),
    ('Pro',   'pro',   'Plan pro untuk pemakaian intensif. Nilai contoh — ubah dari dashboard Super Admin.', 349000, 30, 3, 0, 200, 0,    365, '{"payment_lock":true,"whatsapp_share":true}', 1, 3)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), description = VALUES(description), price = VALUES(price),
    billing_period_days = VALUES(billing_period_days), max_devices = VALUES(max_devices),
    max_events = VALUES(max_events), drive_quota_gb = VALUES(drive_quota_gb),
    session_quota = VALUES(session_quota), retention_days = VALUES(retention_days),
    features = VALUES(features), is_active = VALUES(is_active), sort_order = VALUES(sort_order);

INSERT INTO app_settings (`key`, `value`, is_encrypted)
VALUES
    ('platform_name', 'Plugin Eventbooth', 0),
    ('google_root_folder_name', 'Plugin Eventbooth', 0),
    ('license_offline_grace_days', '3', 0),
    ('platform_midtrans_env', 'sandbox', 0),
    ('platform_midtrans_merchant_id', '', 0),
    ('platform_midtrans_client_key', '', 0),
    ('platform_midtrans_server_key_encrypted', '', 1)
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), is_encrypted = VALUES(is_encrypted);
