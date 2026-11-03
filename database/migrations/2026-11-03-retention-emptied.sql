-- v0.22.0 — Album Expired: penanda event yang albumnya
-- dikosongkan oleh retensi. Halaman publik album menampilkan
-- keterangan Expired; event dibersihkan sepenuhnya (folder
-- Drive + baris event) oleh sapuan harian 30 hari setelah
-- penandaan. Jalankan SATU KALI di phpMyAdmin pada database
-- plugin (kode toleran sebelum migrasi ini dijalankan:
-- penandaan dilewati diam-diam, purge sesi tetap berjalan).
ALTER TABLE events ADD COLUMN retention_emptied_at DATETIME NULL DEFAULT NULL;
