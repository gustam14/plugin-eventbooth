# Plugin Eventbooth — Website

- **Streaming video dari server sendiri (v0.13.0, pilihan
  pemilik "ala fotoshare"):** kelas MediaStream meneruskan
  byte video/GIF dari Drive API (alt=media, token akun pusat
  pemilik media) lewat /media/{id}?s=video — inline,
  Content-Type video, Range diteruskan (206). Foto tetap 302
  ke CDN Google; hanya byte VIDEO yang lewat hosting (berkas
  kecil). Penampil memakai pemutar bawaan: GIF berputar
  otomatis senyap berulang, video berkontrol; galat aliran
  mundur ke pemutar pratinjau Drive. Mundur total ke
  pengalihan export=view bila token/akun tak siap.

- **Pemutaran video lewat pemutar pratinjau Drive (v0.12.5):**
  walau rantai export=view terbukti sehat di sisi server
  (H.264 Constrained Baseline yuv420p, moov di depan, 206
  Partial + Accept-Ranges + inline), elemen <video> tetap
  berhenti di layar hitam pada Chrome desktop & Android.
  Penampil sesi kini menyematkan pemutar pratinjau Drive
  (iframe /file/d/{id}/preview) untuk item video/GIF —
  Google mengalirkan & menyesuaikan formatnya sendiri.
  Konsekuensi yang diterima: pemutar membawa antarmuka
  Google di dalam bingkai penampil.

- **Pemutar video memakai aliran inline (v0.12.4):** akar
  masalah GIF tak berputar terbukti dari rantai header:
  export=download menyajikan Content-Disposition: attachment
  yang ditolak elemen <video> (berkasnya sendiri video/mp4
  utuh). Album::streamUrl (export=view → inline) kini dipakai
  cabang ?s=video; unduhan tetap export=download. Pengalihan
  video tidak dicache (no-store) agar peramban tidak menyimpan
  pengalihan lama.

- **Kartu galeri ramping + putar GIF diperbaiki (v0.12.3):**
  tombol Lihat/Unduh dihapus dari kartu media halaman sesi
  (klik kartu membuka penampil; unduh hanya dari dalam
  penampil). Rute /media kini melayani ?s=video juga untuk
  berkas .mp4/.mov berjenis gif — sebelumnya syarat jenis
  "video" membuat GIF .mp4 menerima URL gambar pada pemutar
  (layar hitam 0:00, terbukti pada sesi uji 9EYR9ETC).

- **Galeri memutar GIF .mp4 sebagai video (v0.12.2):** GIF
  versi DSLRBooth dapat berupa berkas .mp4 berjenis "gif" —
  halaman sesi kini menentukan putar-sebagai-video dari jenis
  ATAU ekstensi berkas (lencana putar pada kartu + pemutar
  video di lightbox), bukan dari jenis saja.

- **Diagnosis charge production + dua alamat notifikasi
  (v0.12.1):** uji production tenant menunjukkan Midtrans
  menerima charge (2xx) tetapi responsnya tanpa data QR —
  chargeQris kini melempar status_code/status_message mentah
  Midtrans dan respons gateway disimpan di raw_webhook baris
  failed (MidtransException membawa gatewayResponse), jadi
  riwayat dashboard menampilkan jawaban asli Midtrans. Kartu
  Cara kerja ditulis ulang menjelaskan DUA alamat notifikasi
  yang berbeda (alamat booth via override otomatis vs kolom
  dashboard milik sistem utama tenant) agar tidak tertukar.

- **Berbagi WhatsApp + diagnosis pembayaran (v0.12.0):**
  POST /api/session/share-wa live (dulu stub): aplikasi
  mengirim tautan galeri sesi ke nomor WhatsApp tamu lewat
  MPWA platform, digerbang fitur plan whatsapp_share. Halaman
  /tenant/midtrans: URL notifikasi ditampilkan lagi dengan
  bingkai yang benar (isi bila kosong; JANGAN ganti bila
  sudah dipakai sistem lain — override per transaksi yang
  bekerja), petunjuk awalan kunci Sandbox vs Production, dan
  alasan gagal Midtrans kini tampil pada riwayat pembayaran
  (dari raw_webhook baris failed). Pembuatan tagihan
  menyembuhkan tagihan pending basi (>15 menit → expired,
  buat baru) agar sesi tidak terkunci tagihan yatim.

- **Klarifikasi instruksi Midtrans tenant (v0.11.2):** URL
  endpoint notifikasi dihapus dari kartu "Cara kerja" halaman
  /tenant/midtrans — menampilkannya tetap terbaca sebagai
  instruksi untuk mengisi dashboard Midtrans, padahal override
  per transaksi membuatnya tidak perlu.

- **Perbaikan notifikasi tamu (v0.11.1):** tombol "Test
  notification URL" Midtrans gagal karena endpoint menjawab 404
  untuk order contoh yang tidak dikenal. Kini endpoint selalu
  200 dan hanya menerapkan notifikasi yang dikenal +
  terverifikasi; charge QRIS menyertakan header
  X-Override-Notification (pola v0.8.1) sehingga notifikasi
  transaksi booth tiba di plugin TANPA tenant mengubah URL
  notifikasi dashboard Midtrans-nya — instruksi di halaman
  /tenant/midtrans diperbaiki mengikuti ini.

- **Pembayaran tamu QRIS via Midtrans tenant (v0.11.0):**
  keputusan pemilik — uang tamu masuk langsung ke Midtrans
  MASING-MASING tenant. Tenant mengatur kuncinya di menu
  dashboard baru /tenant/midtrans (Server Key terenkripsi;
  tabel tenant_midtrans_settings memang sudah ada sejak skema
  awal — tanpa migrasi). Endpoint perangkat: POST
  /api/payment/create (tagihan QRIS Core API untuk sesi;
  satu tagihan menunggu per sesi), GET /api/payment/status
  (server memeriksa langsung ke Midtrans + verifikasi tanda
  tangan — jalur utama), POST /api/payment/notification
  (webhook pelengkap, terverifikasi per tenant). Status lunas
  hanya dari Midtrans, tidak pernah dari klien.

- **Galeri mandiri + folder Drive per event (v0.10.0):** halaman
  album tidak lagi memuat tautan Google Drive — media disajikan
  lewat rute domain sendiri `/media/{id}` (dan
  `/media/{id}/download`) yang hanya mengarahkan 302 ke CDN
  Google (byte tetap tidak lewat shared hosting). Halaman sesi
  punya penampil lightbox di dalam situs (gambar besar, video,
  filmstrip, navigasi keyboard). Struktur folder Drive berubah
  PER EVENT: `{Tenant}/{Event}/final|originals|gif|video/
  {KodeSesi}` — satu folder jenis berisi banyak sesi.

- **URL galeri + album per sesi + folder gif/video (v0.9.0):**
  album event kini juga hidup di `/gallery/{slug-tenant}/
  {slug-event}` (URL lama `/e/{slug}` tetap bekerja; tautan
  kembali dari halaman sesi dan `album_url` pada API aplikasi
  memakai bentuk galeri). Kartu sesi pada album event bergaya
  galeri per sesi: sampul potret, lencana jumlah "N Item" dan
  lencana "VIDEO" bila sesi memuat video. Rantai folder Drive
  sesi bertambah subfolder `gif` dan `video` (boomerang);
  pemetaan jenis media: final→final, original→originals,
  gif→gif, video→video.

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
  Nomor pengirim MPWA-nya sendiri diatur dari kartu yang sama
  (pengaturan `mpwa_sender`, ternormalisasi 62…; v0.7.2) dan
  ikut dikembalikan sebagai field `sender` pada respons
  `expiring-licenses`, jadi workflow mengambilnya otomatis
  setiap berjalan — mengganti nomor pengirim cukup dari
  dashboard, tanpa mengubah workflow. Teks pesannya memakai
  templat profesional berformat WhatsApp (salam + blok
  *Detail Lisensi* + ajakan perpanjangan + tanda tangan
  Tim EventBooth; v0.7.3, disetujui pemilik).

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
terbaru dihidupkan kembali (termasuk yang sudah kedaluwarsa atau
tersuspensi — dan sejak v0.6.3 juga yang sudah dicabut — yang
statusnya kembali `active`, dengan kunci yang sama).
Bila plan yang dibeli berbeda, batas & fitur lisensi beralih
mengikuti plan terbaru (snapshot plan + `max_devices` diperbarui)
dan masa aktif ditambah sesuai durasi plan itu: basisnya sisa masa
aktif bila belum habis, atau hari ini bila sudah habis; plan
berdurasi 0 menjadikan lisensi tanpa kedaluwarsa. Lisensi yang
sudah permanen (tanpa kedaluwarsa) tetap permanen. Bila tenant
belum pernah memiliki lisensi sama sekali, aksi Tambah Masa
Aktif menerbitkan lisensi pertamanya. Aturan yang sama akan
berlaku untuk checkout Midtrans di milestone pembayaran.

**Satu akun, satu lisensi (v0.6.2) & lisensi abadi (v0.6.3):**
keputusan pemilik setelah menguji dashboard — satu akun tenant
hanya boleh memiliki **satu** lisensi (`License::liveForTenant` —
lisensi terbaru tenant dalam keadaan apa pun). Selama lisensi
itu ada, formulir **Terbitkan Lisensi Baru** di detail tenant
digantikan catatan satu baris, dan rute penerbitannya
(`POST /admin/tenants/{id}/licenses`) pun menolak dengan pesan
galat — lisensi baru hanya bisa terbit bila tenant belum pernah
memiliki baris lisensi sama sekali. Lisensi yang masa aktifnya
habis — bahkan yang sudah **dicabut** — tidak diganti: Tambah
Masa Aktif menghidupkannya kembali **dengan kunci yang sama**,
jadi pembelian ulang tidak pernah menghasilkan lisensi baru.
Khusus lisensi yang **dicabut**: sisa masa aktifnya hangus
(keputusan pemilik, v0.6.4) — penghidupan kembali lewat Tambah
Masa Aktif dihitung dari saat penambahan (sekarang + durasi
plan), bukan dari tanggal berakhir sebelum pencabutan;
perpanjangan lisensi yang tidak dicabut tetap dihitung dari
sisa masa aktifnya.
Lisensi yang berjalan hanya tumbuh dengan dua cara:
**Tambah Masa Aktif** (di atas) dan **Tambah Perangkat**
(`POST /admin/licenses/{id}/devices/add` →
`License::addDevices`, +1..20 perangkat per aksi) yang menaikkan
batas perangkat lisensi melampaui bawaan plan-nya — snapshot plan
dan kolom `max_devices` diperbarui bersamaan agar entitlement API
ikut berubah; lisensi tanpa batas (0) tetap tanpa batas.
Tombol **Perpanjang** per lisensi dicabut dari tabel karena
perpanjangan kini terpusat di kartu Tambah Masa Aktif (rute renew
lama masih terdaftar tetapi tidak lagi ditautkan dari dashboard).
Tenant lama yang datanya telanjur berisi banyak lisensi tetap
tampil semua sebagai riwayat; Tambah Masa Aktif/Tambah Perangkat
bekerja pada lisensi terbarunya (yang sedang dipakai aplikasi
bila ada yang aktif), dan lisensi yang dicabut dihidupkan
kembali lewat Tambah Masa Aktif — bukan diganti. Dashboard
tenant juga punya halaman **Perangkat** (`GET /tenant/devices`,
v0.6.3) yang memuat daftar perangkat dari satu-satunya lisensi
tenant lengkap dengan tombol **Cabut Perangkat**; daftar
tertanam di halaman Lisensi Saya tetap ada.

## Milestone 5a — Event, Paket, Album Publik & API Sesi

Dashboard tenant kini mengelola dua hal yang dipakai aplikasi
booth setiap hari:

- **Event** (`/tenant/events`) — nama, slug album (otomatis dari
  nama, unik global dengan akhiran -2, -3, …, bisa diisi manual),
  lokasi, tanggal mulai/selesai (`starts_at`/`ends_at`), dan
  status (`draft`/`active`/`finished`/`archived`, diubah dari
  formulir ubah). **Akun penyimpanan dikunci per event** saat
  dibuat: pilihan *Otomatis* menugaskan akun Drive platform
  berstatus `connected` dengan sisa kuota paling lega (kuota
  belum terukur hanya dipilih bila tidak ada yang terukur; seri
  dimenangkan akun default). Bila belum ada akun terhubung,
  event tetap dibuat dengan peringatan bahwa upload & album
  belum berjalan. **Kunci pembayaran per event**
  (`payment_lock_enabled`, bawaan menyala) menentukan tamu wajib
  membayar sebelum sesi; matikan untuk event seperti wedding,
  dengan batas pengganti `limit_mode` (`none`/`event_hours`/
  `session_quota`) + `session_quota_limit`. Pembuatan event
  menegakkan `max_events` dari snapshot plan lisensi tenant
  (0 = tanpa batas).
- **Paket Sesi** (`/tenant/packages`) — paket MENEMPEL pada
  event (`packages.event_id`), jadi daftar paket menggabungkan
  semua event tenant. Mode timer mengikuti enum skema:
  `per_session` (jumlah sesi), `per_duration` (durasi menit),
  `combined` (keduanya) — kolom yang relevan wajib diisi sesuai
  mode. Harga rupiah, status aktif, dan urutan tampil.

**Album publik (tanpa login)** — `/e/{slug}` menampilkan kepala
event (nama event, nama vendor, tanggal), kisi sesi terbaru
(60 per halaman, `?page=`), tiap kartu memakai thumbnail media
pertama sesi sebagai sampul; `/s/{kode}` menampilkan media sesi
itu (final lebih dulu) dengan tautan **Lihat** (`web_link`
tersimpan) dan **Unduh** (diturunkan dari `drive_file_id`).
Slug/kode tak dikenal menjawab 404 publik. Byte foto tidak
pernah lewat server ini — halaman hanya membaca metadata dan
tautan yang tersimpan. Kata sandi album
(`album_password_hash`) belum ditegakkan di milestone ini.

**API aplikasi booth (token perangkat Bearer, sama seperti
`/api/app/me`)** — `GET /api/app/events` mengembalikan event
tenant perangkat (status `draft`/`active`: `id`, `name`,
`slug`, `event_date`, `payment_lock`, `has_drive`) dan paket
aktifnya (`id`, `event_id`, `name`, `timer_mode`,
`duration_minutes`, `session_count`, `price` rupiah penuh).
`POST /api/app/sessions` (body `event_id`, opsional
`package_id`, `guest_name`, `guest_whatsapp`) membuat sesi
berstatus `created` dengan kode unik 8 karakter pada event
milik tenant perangkat itu sendiri (event tenant lain menjawab
404 `not_found`) dan membalas `album_url`/`session_url`
absolut. Endpoint Drive (`/api/drive/*`) dikerjakan pada
M5b (lihat bagian berikutnya); registrasi media via n8n
(`/api/session/register`) dan berbagi WhatsApp masih stub
501. Tidak ada perubahan skema pada milestone ini.

## Milestone 5b — Endpoint Operasi Google Drive (API)

Tiga endpoint yang dipanggil **aplikasi booth di PC** agar foto
mengalir PC → Google langsung; website hanya menangani JSON
kecil dan token (blueprint §2). Otentikasi sama seperti
`/api/app/*`: token perangkat (Bearer) dari login akun tenant —
**hanya token perangkat**; n8n tidak memanggil endpoint ini,
melainkan mengorkestrasi lewat aplikasi. Refresh token tidak
pernah keluar server: rute menukarnya menjadi access token
(segar per request) memakai kredensial OAuth dari M3, dan semua
operasi memakai akun `google_accounts` yang **terkunci pada
event** sesi itu (`events.drive_account_id`).

- `POST /api/drive/session-folders` `{session_id}` — memastikan
  rantai folder `Plugin Eventbooth/{NamaTenant}/{NamaEvent}/
  {KodeSesi}` (di bawah folder root akun dari M3) plus
  subfolder `final` dan `originals` ada, lalu membalas
  `{status: "ok", folders: {event, session, final, originals}}`
  berisi ID folder Drive. ID folder sesi disimpan di
  `sessions.drive_folder_id`.
- `POST /api/drive/init-upload` `{session_id, kind, file_name,
  mime_type?, size_bytes?}` — `kind` salah satu dari enum
  `media_files.kind` (`final`/`original`/`gif`/`video`/`thumb`);
  folder tujuan: `final` → subfolder final, `original` →
  originals, sisanya → folder sesi. Server menginisiasi
  resumable upload ke Google dan membalas `{status: "ok",
  upload_url, folder_id}`; aplikasi kemudian PUT byte file
  **langsung ke `upload_url` itu** (byte tidak lewat server).
- `POST /api/drive/complete` `{session_id, kind, file_id,
  file_name?}` — dipanggil setelah PUT selesai: server membaca
  metadata file dari Google, menetapkan izin *anyone with link*
  (reader) agar album publik bisa membukanya, dan mencatat
  baris `media_files` (termasuk `web_link`, `thumbnail_link`,
  `drive_folder_id`, dan akun penampung). Balasan `{status:
  "ok", media: {id, web_link, thumbnail_link, download_url}}`
  dengan `download_url` diturunkan dari `drive_file_id` seperti
  di halaman album. Snapshot `quota_used_bytes` akun digeser
  sebesar ukuran file secara best-effort.

Kode error mengikuti gaya `/api/app/*`: `invalid_token` 401,
`invalid_request` 422, `not_found` 404 (termasuk sesi milik
tenant lain), `server_not_configured`/`server_error` 503,
ditambah **`drive_not_configured` 503** (event belum punya akun
Drive atau akunnya terputus/belum lengkap kredensialnya) dan
**`drive_error` 502** (Google menolak/gagal — token refresh
yang ditolak juga menandai akun berstatus `error` seperti tes
koneksi M3). Tidak ada perubahan skema pada milestone ini;
`events` memang tidak memiliki kolom folder, jadi ID folder
event tidak disimpan — folder ditemukan kembali lewat nama
pada setiap panggilan (operasi cari-atau-buat bersifat
idempoten).

## Milestone 6 — Checkout Lisensi Midtrans (platform)

Tenant kini dapat membeli dan memperpanjang lisensi sendiri
dengan pembayaran Midtrans milik platform:

- **Migrasi wajib sekali jalan:**
  `database/migrations/2026-11-01-m6-payments.sql` — menambah
  kolom `plan_id` dan `plan_snapshot` pada tabel `payments`
  (tabelnya sendiri sudah ada sejak skema awal). Sebelum migrasi
  dijalankan, checkout menjawab dengan pesan bahwa fitur belum
  aktif dan halaman admin menampilkan catatan migrasi.
- **Super Admin — menu Pembayaran (`/admin/payments`):**
  environment (Sandbox/Production), Client Key, dan Server Key
  Midtrans platform; Server Key disimpan terenkripsi (APP_KEY)
  dan tidak pernah ditampilkan/dikirim ke peramban. Halaman ini
  juga menampilkan 50 pesanan lisensi terbaru semua tenant.
  URL notifikasi dashboard Midtrans **tidak perlu diubah**:
  akun Midtrans platform dipakai bersama website utama pemilik,
  jadi setiap pembuatan transaksi Snap menyertakan header
  `X-Override-Notification` berisi URL webhook aplikasi ini
  (v0.8.1) — notifikasi checkout lisensi menggantikan alamat
  dashboard hanya untuk transaksi lisensi itu sendiri.
- **Tenant — halaman Lisensi Saya:** seksi "Beli / Perpanjang
  Lisensi" (plan aktif berbayar; membeli berarti menambah masa
  aktif lisensi berjalan mengikuti aturan Tambah Masa Aktif,
  bukan lisensi baru) dan "Riwayat Pembayaran" (pesanan pending
  dapat dilanjutkan ke halaman bayar). Halaman bayar
  `/tenant/licenses/pay/{order_id}` membuka popup Snap.
- **Webhook `POST /api/payments/midtrans/notify`:** satu-satunya
  jalur pelunasan resmi — tanda tangan SHA-512 diverifikasi
  dengan Server Key sebelum status diubah. Saat pesanan menjadi
  `paid` pertama kali, lisensi dipenuhi lewat
  `License::extendWithPlan` (atau `License::issue` untuk tenant
  yang belum pernah memiliki lisensi) dan ID lisensinya dicatat
  di pesanan; notifikasi berulang bersifat idempoten. Callback
  JavaScript Snap hanya mengarahkan tampilan, tidak pernah
  dianggap bukti lunas.
- **Kembali ke dashboard + notifikasi WA (v0.8.2):** payload
  Snap kini menyertakan `callbacks.finish` (dan callback GoPay)
  ke `/tenant/licenses?pay=success`, karena metode deeplink
  seperti GoPay membawa pembayar keluar dari halaman sehingga
  callback JavaScript tidak berjalan dan pembayar terdampar di
  halaman Midtrans. Tepat setelah lisensi dipenuhi, webhook
  juga mengirim **notifikasi WhatsApp ke tenant** lewat MPWA
  (kelas `Mpwa`): API key MPWA disimpan terenkripsi dari kartu
  di `/admin/payments`, nomor pengirim memakai `mpwa_sender`,
  dan pengiriman bersifat best-effort — kegagalan gateway tidak
  menggagalkan pencatatan pembayaran.

Uji coba: gunakan kunci Sandbox (awalan `SB-Mid-...`) dengan
environment Sandbox, lalu bayar memakai metode uji Midtrans —
masa aktif tenant uji harus bertambah tanpa campur tangan admin.

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
  Checkout & perpanjangan lisensi via Midtrans platform selesai
  di M6 (lihat bagian Milestone 6 di atas); pembayaran tamu
  (Midtrans per tenant) menyusul.
- **M5 — Publik & sesi:** 5a selesai (event & paket tenant,
  album `/e/{slug}` dan `/s/{kode}`, `GET /api/app/events`,
  `POST /api/app/sessions`); 5b selesai (endpoint operasi
  Drive `/api/drive/session-folders|init-upload|complete`
  bertoken perangkat — folder sesi, resumable upload, dan
  registrasi media). Sisa: `/api/session/register`, berbagi
  WhatsApp via n8n/MPWA, integrasi workflow n8n
  "Plugin Eventbooth".
- **M6 — Checkout lisensi Midtrans (selesai):** lihat bagian
  Milestone 6 di atas. API aplikasi booth (aktivasi lisensi,
  sesi login akun, heartbeat perangkat) sudah dikirim lebih awal
  pada M4/M4c.

Jalur API M1–M6 di `public/index.php` sudah terimplementasi
penuh; tidak ada lagi stub `501` yang tersisa untuk cakupan ini.
