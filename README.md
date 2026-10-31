# Plugin Eventbooth — Website

Platform pendamping DSLRBooth untuk self-booth mandiri:
manajemen lisensi aplikasi booth (multi-tenant + Super Admin),
album event online (file tetap di Google Drive), dan pembayaran
sesi via Midtrans. Rancangan lengkap: lihat `blueprint-selfbooth-website.md`
(v4, termasuk §2A koneksi Google Drive multi-akun).

Stack: **PHP 8.1+ polos + MySQL** — tanpa composer, tanpa framework.
Sengaja begitu: hosting DH-Panel tidak menyediakan SSH, jadi website
harus bisa jalan hanya dengan menyalin berkas.

## Struktur

```
website/
├── .env.example        # contoh konfigurasi (salin jadi .env)
├── .htaccess           # aturan bila document root = root repo
├── index.php           # shim ke public/ (cadangan, lihat "Deploy")
├── public/
│   ├── .htaccess       # rewrite semua request ke index.php
│   └── index.php       # front controller + router
├── app/
│   ├── bootstrap.php   # autoloader sederhana, muat .env, helper
│   ├── Env.php         # parser .env
│   ├── Config.php      # akses konfigurasi
│   ├── Database.php    # PDO factory (null bila DB belum disetel)
│   ├── Json.php        # helper respons JSON
│   ├── View.php        # renderer template PHP
│   ├── Router.php      # router kecil tulisan tangan
│   ├── Auth.php        # login session, peran, CSRF (M2)
│   ├── Flash.php       # pesan kilat + isian formulir lama (M2)
│   ├── Plan.php        # repository + validasi plan lisensi (M2)
│   └── views/
│       ├── landing.php
│       ├── partials/   # layout dashboard (atas & bawah)
│       ├── auth/       # halaman login
│       ├── admin/      # ringkasan Super Admin + CRUD plan
│       ├── tenant/     # ringkasan tenant
│       └── errors/     # 403 & 404
└── database/
    ├── schema.sql      # skema MySQL lengkap + seed plan contoh
    └── seed-admin.sql  # TEMPLAT akun Super Admin pertama (M2)
```

## Kebutuhan server

- PHP 8.1+ dengan ekstensi `pdo_mysql`
- MySQL 8 atau MariaDB 10.3+ (InnoDB, utf8mb4)
- Apache + `mod_rewrite` (berkas `.htaccess` yang disediakan)

## Deploy (DH-Panel, tanpa SSH)

Jalur utama: **Git deploy dari panel — DH-Panel → Software → Git**
(tarik repositori GitHub/GitLab langsung ke hosting; tersedia juga
deploy otomatis via webhook untuk update berikutnya).

1. Buat database + user MySQL dari panel, lalu impor
   `database/schema.sql` (phpMyAdmin → Import).
2. Deploy repo ini ke folder subdomain `plugin.eventbooth.id`.
3. **Document root — cara yang disarankan:** arahkan document root
   subdomain ke folder `public/` di dalam repo. Dengan begitu `.env`,
   `app/`, dan `database/` berada DI LUAR area web dan tidak bisa
   diakses dari browser sama sekali.
   **Cadangan:** bila panel hanya mengizinkan document root di folder
   subdomain itu sendiri (root repo), tidak perlu mengubah apa pun —
   `index.php` + `.htaccess` di root repo sudah meneruskan semua
   request ke `public/` dan memblokir akses ke `.env`, `app/`, dan
   `database/`.
4. Salin `.env.example` menjadi `.env` di root repo dan isi nilainya
   (kredensial database, `APP_KEY` acak ≥ 32 karakter, dsb).
   `.env` tidak ikut Git (sudah di-`.gitignore`).
5. Uji: buka `https://plugin.eventbooth.id/` (halaman landing) dan
   `https://plugin.eventbooth.id/api/health` — field `db` harus
   bernilai `configured-ok` setelah langkah 1 & 4 benar.
   Nilai lain: `not-configured` (`.env`/DB belum diisi) atau
   `configured-error` (kredensial/host database salah).

Catatan: berkas foto TIDAK pernah diunggah ke hosting ini
(blueprint §2–§3) — file berjalan langsung dari aplikasi PC ke
Google Drive; website hanya menyimpan metadata.

## Membuat akun Super Admin pertama

Setelah `schema.sql` terimpor dan `.env` terisi, buat akun Super
Admin pertama lewat templat `database/seed-admin.sql`. Tidak ada
akun bawaan dan tidak ada kata sandi bawaan di repo ini.

1. Di mesin mana pun yang memiliki PHP (PC kamu sendiri cukup),
   buat hash kata sandi:
   ```bash
   php -r "echo password_hash('KATA-SANDI-KAMU', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   Ganti `KATA-SANDI-KAMU` dengan kata sandi yang kuat dan unik.
   Simpan kata sandi aslinya di password manager — yang dimasukkan
   ke database hanya hash-nya.
2. Salin `database/seed-admin.sql`, lalu ganti tiga placeholder di
   salinan itu: `GANTI_NAMA_SUPER_ADMIN`, `GANTI_EMAIL_SUPER_ADMIN`,
   dan `GANTI_HASH_KATA_SANDI` (hasil langkah 1).
3. Impor salinan tersebut sekali, ke database yang sama dengan
   `schema.sql` (mis. phpMyAdmin → Import).
4. **Hapus salinan yang sudah terisi hash** dari server dan dari
   komputer kamu. Jangan pernah commit salinan itu ke Git.
5. Masuk lewat `https://plugin.eventbooth.id/login` — pengguna ini
   berperan `super_admin` dan diarahkan ke `/admin`.

Akun tenant dan pengguna tenant dibuat dari dashboard Super Admin
pada milestone berikutnya.

## Roadmap milestone

- **M1 — Fondasi (selesai):** scaffold ini — router, konfigurasi
  `.env`, PDO factory, renderer view, landing page, `/api/health`,
  dan skema database lengkap.
- **M2 — Auth & dashboard (selesai):** login/logout berbasis
  session (peran `super_admin` / `tenant_admin` / `tenant_staff`,
  CSRF di semua formulir POST), gerbang `/dashboard` per role,
  kerangka dashboard Super Admin (`/admin`) dan tenant (`/tenant`),
  **CRUD plan lisensi** dari Super Admin (buat/ubah/aktif-nonaktif,
  sesuai keputusan plan diatur dari dashboard), dan templat
  pembuatan akun Super Admin pertama (`database/seed-admin.sql`).
  CRUD tenant & lisensi, ikatan device, dan audit log menyusul pada
  milestone lanjutan (lisensi & heartbeat API di M6).
- **M3 — Google Drive pusat:** tombol "Kaitkan Google Drive"
  (OAuth, refresh token terenkripsi), multi-akun + akun default,
  penugasan Drive per event, endpoint `/api/drive/*`.
- **M4 — Midtrans:** checkout & perpanjangan lisensi (Midtrans
  platform) dan pembayaran tamu (Midtrans per tenant), webhook
  notifikasi terverifikasi tanda tangan.
- **M5 — Publik & sesi:** album `/e/{slug}` dan `/s/{kode}`,
  `/api/session/register`, berbagi WhatsApp via n8n/MPWA,
  integrasi workflow n8n "Plugin Eventbooth".
- **M6 — API aplikasi booth:** aktivasi lisensi, konfigurasi
  bertanda tangan, heartbeat hemat (blueprint §2).

Endpoint M2–M6 sudah dipasang sebagai stub `501 not_implemented`
di `public/index.php` agar jalur API-nya tidak berubah lagi.
