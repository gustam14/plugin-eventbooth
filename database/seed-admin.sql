-- =====================================================================
-- Plugin Eventbooth — Templat pembuatan akun Super Admin PERTAMA
-- (Milestone 2). Berkas ini adalah TEMPLAT, bukan untuk langsung
-- dijalankan: ada tiga placeholder yang WAJIB diganti dulu.
--
-- LANGKAH AMAN (ringkasan — versi lengkap ada di README.md):
--   1. Pastikan database/schema.sql sudah diimpor lebih dulu.
--   2. Buat hash kata sandi di mesin mana pun yang memiliki PHP:
--        php -r "echo password_hash('KATA-SANDI-KAMU', PASSWORD_DEFAULT), PHP_EOL;"
--      Pakai kata sandi yang kuat & unik. JANGAN pakai kata sandi
--      contoh dari dokumen/chat mana pun.
--   3. Ganti tiga placeholder di bawah ini:
--        GANTI_NAMA_SUPER_ADMIN   -> nama tampilan Super Admin
--        GANTI_EMAIL_SUPER_ADMIN  -> email untuk login
--        GANTI_HASH_KATA_SANDI    -> hash hasil langkah 2
--                                    (diawali $2y$..., panjang ±60)
--   4. Impor berkas hasil edit ini SEKALI (mis. via phpMyAdmin ->
--      Import, pada database yang sama dengan schema.sql).
--   5. HAPUS berkas hasil edit yang sudah berisi hash dari server
--      dan dari komputer kamu. Jangan commit berkas itu ke Git.
--   6. Masuk lewat https://plugin.eventbooth.id/login
--
-- Berkas templat ini sendiri tidak berisi akun maupun rahasia apa
-- pun — placeholder di bawah BUKAN hash yang valid.
-- =====================================================================

INSERT INTO users
    (tenant_id, name, email, password_hash, role, is_active)
VALUES
    (NULL,
     'GANTI_NAMA_SUPER_ADMIN',
     'GANTI_EMAIL_SUPER_ADMIN',
     'GANTI_HASH_KATA_SANDI',
     'super_admin',
     1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    role = 'super_admin',
    is_active = 1;
