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
│   ├── Crypto.php      # enkripsi AES-256-GCM dari APP_KEY (M3)
│   ├── Settings.php    # key-value app_settings (M3)
│   ├── GoogleDrive.php # klien OAuth + Drive API via cURL (M3)
│   ├── GoogleAccount.php # repository google_accounts (M3)
│   ├── Whatsapp.php    # normalisasi nomor WhatsApp 62… (M4)
│   ├── Tenant.php      # repository + validasi tenant (M4)
│   ├── License.php     # lisensi: kunci, snapshot, perangkat, status (M4)
│   └── views/
│       ├── landing.php
│       ├── partials/   # layout dashboard (atas & bawah)
│       ├── auth/       # halaman login
│       ├── admin/      # ringkasan Super Admin + CRUD plan
│       ├── tenant/     # ringkasan tenant
│       └── errors/     # 403 & 404
└── database/
    ├── schema.sql      # skema MySQL lengkap + seed plan contoh
    ├── seed-admin.sql  # TEMPLAT akun Super Admin pertama (M2)
    └── migrations/     # migrasi SATU KALI per milestone (phpMyAdmin)
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
6. Bila kata sandi pada langkah 1 bersifat sementara (misalnya
   hash-nya dibuatkan oleh orang lain), **segera ganti setelah
   login pertama** lewat menu **Ganti Kata Sandi** di dashboard
   (`/account/password`). Kata sandi baru minimal 10 karakter dan
   harus berbeda dari kata sandi sementara itu.

Akun tenant dan pengguna tenant dibuat dari dashboard Super Admin
lewat menu **Tenant & Lisensi** (Milestone 4, lihat di bawah).

## Milestone 3 — Mengaitkan Google Drive pusat

File semua tenant ditampung di akun Google Drive **milik platform**
(multi-akun, satu akun default). Mengaitkannya dilakukan Super
Admin dari dashboard — menu **Google Drive** (`/admin/drive`).
Persiapan sekali di Google Cloud Console:

1. Buka [Google Cloud Console](https://console.cloud.google.com/)
   dan buat project baru (mis. "Plugin Eventbooth").
2. Di **APIs & Services → Library**, aktifkan **Google Drive API**.
3. Di **APIs & Services → OAuth consent screen**, pilih tipe
   *External*, isi nama aplikasi & email dukungan. Selama status
   penerbitan masih **Testing**, tambahkan email akun Google yang
   akan dikaitkan sebagai **Test user** — tanpa itu Google menolak
   persetujuan.
4. Di **APIs & Services → Credentials → Create Credentials →
   OAuth client ID**, pilih tipe **Web application** dan tambahkan
   **Authorized redirect URI** persis seperti ini:
   `https://plugin.eventbooth.id/auth/google/callback`
5. Salin **Client ID** dan **Client Secret** hasil pembuatan ke
   halaman **Google Drive** di dashboard Super Admin (menu
   **Pengaturan OAuth Google**), lalu Simpan. Secret disimpan
   terenkripsi (memakai `APP_KEY` di `.env`) dan tidak pernah
   ditampilkan kembali.
6. Klik **Kaitkan Google Drive**, masuk dengan akun Google platform
   dan setujui aksesnya. Akun pertama otomatis menjadi default;
   akun berikutnya bisa dikaitkan dengan cara yang sama dan
   dijadikan default dari daftar akun.

Syarat server: `APP_KEY` di `.env` wajib terisi (string acak
panjang) — tanpa itu tombol pengaitan nonaktif, karena refresh
token tidak boleh disimpan tanpa enkripsi. Endpoint
`/api/drive/*` untuk n8n/aplikasi menyusul di M5.

## Milestone 4 — Tenant & Lisensi

Menu **Tenant & Lisensi** (`/admin/tenants`) di dashboard Super
Admin mengelola pembeli lisensi dan lisensinya:

- **Data tenant lengkap** — nama vendor, penanggung jawab, email,
  WhatsApp (dinormalisasi otomatis ke format 62…), alamat, dan
  catatan internal. Kontak ini dipakai untuk notifikasi
  perpanjangan, jadi wajib diisi. Dari halaman detail tenant bisa
  dibuat akun login tenant (`tenant_admin`/`tenant_staff`), tenant
  bisa diaktifkan/dinonaktifkan (tenant nonaktif ditolak API
  lisensinya), dan ada tombol Chat WhatsApp (wa.me).
- **Penerbitan lisensi** — pilih plan aktif; kunci berformat
  `EB-XXXX-XXXX-XXXX-XXXX` ditampilkan **sekali** (database hanya
  menyimpan hash SHA-256 + prefix). Batas & fitur plan dibekukan ke
  lisensi sebagai snapshot, jadi mengubah plan tidak mengubah
  lisensi yang sudah terbit. Aksi per lisensi: **Perpanjang**
  (basis max(sekarang, kedaluwarsa) + durasi plan; lisensi
  tersuspensi ikut aktif lagi), **Tangguhkan/Aktifkan**, **Cabut**.
- **API aplikasi booth** — `POST /api/license/activate` dan
  `POST /api/license/validate` (body JSON `license_key`,
  `device_id`, opsional `device_name`/`app_version`). Identitas
  perangkat disimpan sebagai hash SHA-256. Respons keberhasilan
  memuat entitlement dari snapshot plan dan ditandatangani
  HMAC-SHA256 dengan kunci turunan `APP_KEY`.
- **Pengingat perpanjangan (fondasi)** — ringkasan Super Admin
  menampilkan lisensi yang berakhir ≤ 30 hari. Untuk otomatisasi:
  `GET /api/automation/expiring-licenses` dan
  `POST /api/automation/mark-notified` (header
  `X-Automation-Token`; token dibuat dari kartu "Otomasi
  Pengingat" di daftar tenant dan ditampilkan sekali). Workflow
  n8n membaca daftar ambang 7/3/1 hari, mengirim WhatsApp lewat
  MPWA, lalu mencatat pengiriman — pengirimannya sendiri berada
  di n8n karena kredensial gateway WA hidup di luar website.

**Wajib sebelum memakai menu Tenant & Lisensi:** jalankan migrasi
M4 sekali di phpMyAdmin —
`database/migrations/2026-10-31-m4-tenant-license.sql`
(menambah kolom data vendor tenant, snapshot lisensi, dan tabel
`license_notifications`). Instalasi baru dari `schema.sql` tidak
memerlukannya karena skema sudah memuat perubahan yang sama.

**Keputusan pemilik (v0.5.1) — kunci lisensi terlihat penuh:**
kunci lisensi kini disimpan juga sebagai teks biasa di kolom
`licenses.license_key` dan tampil PENUH di dashboard Super Admin
serta di halaman **Lisensi Saya** milik tenant
(`/tenant/licenses`); pencarian API tetap memakai hash. Untuk
instalasi yang sudah jalan ada migrasi kedua yang dijalankan
setelah migrasi M4:
`database/migrations/2026-10-31-m4b-license-key-visible.sql`.
Lisensi yang diterbitkan sebelum migrasi kedua ini kuncinya tidak
tersimpan dan tidak bisa dimunculkan — cabut & terbitkan ulang
bila kuncinya diperlukan.

**Aktivasi melekat akun (v0.6.0) — meniru DSLRBooth:** aplikasi
booth tidak lagi wajib mengetik kunci. `POST /api/app/login`
(body JSON `email`, `password`, `device_id`, opsional
`device_name`/`app_version`) memeriksa kredensial akun tenant,
lalu otomatis memakai lisensi aktif dengan masa berlaku
terpanjang milik akun itu (`License::currentForTenant` — lisensi
tanpa kedaluwarsa dianggap terpanjang). Batas perangkat ditegakkan
per lisensi seperti aktivasi kunci (`device_limit` 409). Login
yang berhasil menerbitkan **token sesi perangkat**; token mentah
hanya dikembalikan satu kali pada respons login dan server hanya
menyimpan hash SHA-256-nya di kolom baru
`license_devices.access_token_hash`. `POST /api/app/me`
(header `Authorization: Bearer <token>`) mengembalikan payload
bertanda tangan yang sama ditambah `license_status` yang jujur
(`active`/`expired`/`suspended`/`revoked`/`tenant_inactive`)
agar aplikasi bisa mengunci diri dengan alasan yang jelas, dan
`POST /api/app/logout` menonaktifkan baris perangkat sehingga
slotnya langsung bebas. Bila slot penuh, tenant mencabut
perangkat lama sendiri dari halaman **Lisensi Saya** (daftar
"Perangkat Terhubung" + tombol **Cabut Perangkat**); Super Admin
juga bisa mencabutnya dari detail tenant untuk keperluan
dukungan. Lisensi yang sedang dipakai aplikasi ditandai
**Dipakai Aplikasi** di dashboard tenant. Untuk instalasi yang
sudah jalan ada migrasi ketiga yang dijalankan setelah migrasi
M4/M4b: `database/migrations/2026-10-31-m4c-device-token.sql`.
Jalur kunci (`/api/license/activate|validate`) tetap hidup
sebagai cadangan dan tidak terpengaruh migrasi ini.

**Model masa aktif (v0.6.1) — pembelian ulang = perpanjangan:**
penekanan lisensi ada di **masa aktifnya**, bukan pada jumlah
lisensinya. Saat tenant membeli plan lagi, yang bertambah adalah
masa aktif lisensi yang sedang berjalan — **bukan** lisensi baru.
Super Admin menjalankannya dari kartu **Tambah Masa Aktif** di
detail tenant (`POST /admin/tenants/{id}/extend` →
`License::extendWithPlan`): targetnya adalah lisensi yang dipakai
aplikasi (`currentForTenant`); bila tidak ada yang aktif, lisensi
terbaru yang belum dicabut dihidupkan kembali (termasuk yang sudah
kedaluwarsa atau tersuspensi, yang statusnya kembali `active`).
Bila plan yang dibeli berbeda, batas & fitur lisensi beralih
mengikuti plan terbaru (snapshot plan + `max_devices` diperbarui)
dan masa aktif ditambah sesuai durasi plan itu: basisnya sisa masa
aktif bila belum habis, atau hari ini bila sudah habis; plan
berdurasi 0 menjadikan lisensi tanpa kedaluwarsa. Lisensi yang
sudah **dicabut** tidak pernah menjadi target perpanjangan, dan
lisensi yang sudah permanen (tanpa kedaluwarsa) tetap permanen —
untuk membatasinya admin mencabutnya lalu menerbitkan lisensi
baru. Bila tenant belum memiliki lisensi yang bisa diperpanjang
sama sekali, aksi Tambah Masa Aktif menerbitkan lisensi baru.
Formulir **Terbitkan Lisensi Baru** tetap tersedia untuk lisensi
kedua yang berjalan bersamaan atau sesudah pencabutan. Aturan yang
sama akan berlaku untuk checkout Midtrans di milestone pembayaran.

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
- **M3 — Google Drive pusat (selesai):** manajemen koneksi Drive
  di dashboard Super Admin — kredensial OAuth (Client Secret
  terenkripsi), tombol "Kaitkan Google Drive" (OAuth,
  refresh token terenkripsi), multi-akun + akun default, tes
  koneksi & kuota, putuskan akun. Penugasan Drive per event dan
  endpoint `/api/drive/*` menyusul di M5.
- **M4 — Tenant & Lisensi (selesai):** manajemen tenant (data
  kontak lengkap untuk notifikasi), akun login tenant, penerbitan/
  perpanjangan/penangguhan/pencabutan lisensi dengan snapshot
  plan, API lisensi aplikasi booth bertanda tangan HMAC, dan
  fondasi pengingat perpanjangan (endpoint otomasi untuk n8n).
  Checkout & perpanjangan lisensi via Midtrans platform dan
  pembayaran tamu (Midtrans per tenant) menyusul setelah ini,
  dengan webhook notifikasi terverifikasi tanda tangan.
- **M5 — Publik & sesi:** album `/e/{slug}` dan `/s/{kode}`,
  `/api/session/register`, berbagi WhatsApp via n8n/MPWA,
  integrasi workflow n8n "Plugin Eventbooth".
- **M6 — API aplikasi booth:** aktivasi lisensi, konfigurasi
  bertanda tangan, heartbeat hemat (blueprint §2).

Endpoint M2–M6 sudah dipasang sebagai stub `501 not_implemented`
di `public/index.php` agar jalur API-nya tidak berubah lagi.
