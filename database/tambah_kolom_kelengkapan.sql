-- Jalankan sekali di phpMyAdmin (pilih database inventaris dulu, lalu tab SQL)
-- Menyimpan kelengkapan tambahan yang dicentang di langkah Informasi Alat,
-- contoh isi: "Adaptor HDMI to Type-C, Pointer / Wireless Presenter"

ALTER TABLE peminjaman
    ADD COLUMN kelengkapan_tambahan VARCHAR(255) NULL AFTER keterangan;
