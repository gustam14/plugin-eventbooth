-- ---------------------------------------------------------------------
-- Alur klien -> event (v0.31.0, arahan pemilik 11 Nov 2026):
-- Buku Klien dirampingkan menjadi daftar klien saja (nama,
-- WhatsApp, alamat). Tanggal acara, paket yang diambil,
-- nilai/DP, dan status diisi pada EVENT. Alurnya: klien mau
-- booking -> catat dulu di Buku Klien -> buat event-nya dari
-- klien itu (event terhubung lewat events.client_id).
-- Jalankan SEKALI di phpMyAdmin pada database plugin.eventbooth.id
-- SETELAH 2026-11-11-tenant-ops.sql.
-- ---------------------------------------------------------------------

-- Buku klien: tambah alamat, buang kolom-kolom booking lama.
ALTER TABLE client_bookings DROP FOREIGN KEY fk_client_bookings_event;
ALTER TABLE client_bookings DROP KEY idx_client_bookings_event;
ALTER TABLE client_bookings
    ADD COLUMN address VARCHAR(255) NULL AFTER whatsapp,
    DROP COLUMN contact_name,
    DROP COLUMN email,
    DROP COLUMN event_date,
    DROP COLUMN venue,
    DROP COLUMN package_name,
    DROP COLUMN total_price,
    DROP COLUMN dp_paid,
    DROP COLUMN status,
    DROP COLUMN event_id,
    DROP COLUMN notes;

-- Event: klien terkait + paket klien + pembayaran klien.
ALTER TABLE events
    ADD COLUMN client_id BIGINT UNSIGNED NULL COMMENT 'Klien dari Buku Klien (v0.31.0)',
    ADD COLUMN client_package_name VARCHAR(120) NULL COMMENT 'Paket layanan yang diambil klien (dari Daftar Paket)',
    ADD COLUMN client_total_price DECIMAL(12,2) NULL COMMENT 'Nilai paket klien',
    ADD COLUMN client_dp_paid DECIMAL(12,2) NULL COMMENT 'DP yang sudah dibayar klien',
    ADD KEY idx_events_client (client_id),
    ADD CONSTRAINT fk_events_client FOREIGN KEY (client_id) REFERENCES client_bookings (id) ON DELETE SET NULL;
