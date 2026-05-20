-- =============================================================
--  Al-Maktabah As-Sunniyyah — Fix Import Bug (bkid = 0)
--  Jalankan di database: quic1934_maktabah
--  Aman dijalankan berulang kali.
-- =============================================================

-- 1. Hapus data orphan bkid = 0 (sisa import gagal)
DELETE FROM book_content WHERE bkid = 0;
DELETE FROM books        WHERE bkid = 0;

-- 2. Pastikan kolom bkid memiliki AUTO_INCREMENT
--    (jalankan jika import masih gagal setelah step 1)
ALTER TABLE books
    MODIFY COLUMN bkid MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- 3. Reset AUTO_INCREMENT ke nilai yang aman (max bkid + 1)
--    MySQL akan mengabaikan nilai lebih kecil dari max yang ada.
ALTER TABLE books AUTO_INCREMENT = 1;

-- Selesai. Coba import ulang dari halaman Kelola Kitab.
