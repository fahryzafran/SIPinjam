-- Jalankan sekali di phpMyAdmin (pilih database inventaris dulu, lalu tab SQL)
-- Menambah kolom yang dibutuhkan halaman Saran Alat (mahasiswa/saran.php).

ALTER TABLE saran_alat
    ADD COLUMN jenis ENUM('stok', 'baru') NOT NULL DEFAULT 'stok' AFTER user_id,
    ADD COLUMN nama_alat_baru VARCHAR(150) NULL AFTER alat_id,
    ADD COLUMN spesifikasi TEXT NULL AFTER nama_alat_baru,
    ADD COLUMN kategori_id INT(11) NULL AFTER spesifikasi,
    ADD COLUMN jumlah INT(11) NOT NULL DEFAULT 1 AFTER kategori_id,
    ADD COLUMN tautan VARCHAR(255) NULL AFTER alasan,
    ADD COLUMN catatan_admin TEXT NULL AFTER status,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD INDEX idx_saran_kategori (kategori_id),
    ADD CONSTRAINT fk_saran_kategori
        FOREIGN KEY (kategori_id) REFERENCES kategori_alat (id)
        ON DELETE SET NULL ON UPDATE CASCADE;
