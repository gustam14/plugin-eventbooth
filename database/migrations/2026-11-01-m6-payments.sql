-- Migrasi M6 — checkout lisensi Midtrans (jalankan SEKALI di phpMyAdmin).
-- Tabel `payments` sudah ada sejak skema awal; migrasi ini hanya
-- menambah penanda plan yang dibeli pada sebuah pesanan lisensi:
--   plan_id       -> plan yang dibeli (untuk tampilan & pelacakan)
--   plan_snapshot -> salinan baris plan saat pesanan dibuat, dipakai
--                    webhook untuk memenuhi pesanan walau plan-nya
--                    kemudian diubah/dihapus admin.
ALTER TABLE payments
    ADD COLUMN plan_id BIGINT UNSIGNED NULL AFTER tenant_id,
    ADD COLUMN plan_snapshot JSON NULL AFTER plan_id,
    ADD KEY idx_payments_plan (plan_id),
    ADD CONSTRAINT fk_payments_plan
        FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL;
