-- Jalankan sekali di phpMyAdmin (pilih database inventaris dulu, lalu tab SQL)
-- Menyimpan nama file foto profil (filenya ada di folder uploads/foto_profil/).

ALTER TABLE users
    ADD COLUMN foto VARCHAR(255) NULL AFTER nim_nip;
