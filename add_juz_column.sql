-- ============================================================
-- MIGRASI: Tambah kolom `juz` ke tabel book_content
-- Jalankan sekali di server MySQL/MariaDB
--
-- Logika penentuan juz:
--   • Urut per bkid berdasarkan id ASC (urutan insert asli)
--   • Juz = 1 untuk baris pertama setiap bkid
--   • Jika page saat ini <= page sebelumnya (dalam bkid sama)
--     → halaman di-reset → juz bertambah 1
-- ============================================================

-- LANGKAH 1: Tambah kolom juz (default 1, skip jika sudah ada)
ALTER TABLE book_content
  ADD COLUMN IF NOT EXISTS juz SMALLINT UNSIGNED NOT NULL DEFAULT 1
  AFTER bkid;

-- ============================================================
-- LANGKAH 2: Isi kolom juz dengan prosedur cursor
-- ============================================================
DROP PROCEDURE IF EXISTS sp_fill_juz;

DELIMITER //
CREATE PROCEDURE sp_fill_juz()
BEGIN
  DECLARE v_done      INT     DEFAULT FALSE;
  DECLARE v_id        BIGINT;
  DECLARE v_bkid      INT;
  DECLARE v_page      INT;
  DECLARE v_prev_bkid INT     DEFAULT -1;
  DECLARE v_prev_page INT     DEFAULT -1;
  DECLARE v_juz       SMALLINT DEFAULT 1;

  -- Cursor: urut bkid lalu id ASC agar kelompok & urutan insert terjaga
  DECLARE cur CURSOR FOR
    SELECT id, bkid, page
    FROM   book_content
    ORDER  BY bkid ASC, id ASC;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

  OPEN cur;

  row_loop: LOOP
    FETCH cur INTO v_id, v_bkid, v_page;
    IF v_done THEN LEAVE row_loop; END IF;

    IF v_bkid <> v_prev_bkid THEN
      -- Kitab baru → mulai dari juz 1
      SET v_juz = 1;
    ELSEIF v_page <= v_prev_page THEN
      -- Halaman di-reset dalam kitab yang sama → juz berikutnya
      SET v_juz = v_juz + 1;
    END IF;

    UPDATE book_content SET juz = v_juz WHERE id = v_id;

    SET v_prev_bkid = v_bkid;
    SET v_prev_page = v_page;
  END LOOP;

  CLOSE cur;
END //
DELIMITER ;

-- LANGKAH 3: Jalankan prosedur
CALL sp_fill_juz();

-- LANGKAH 4: Bersihkan prosedur setelah selesai
DROP PROCEDURE IF EXISTS sp_fill_juz;

-- LANGKAH 5: Tambahkan index agar query per juz cepat
ALTER TABLE book_content
  ADD INDEX IF NOT EXISTS idx_bkid_juz_page (bkid, juz, page);

-- Verifikasi hasil: lihat distribusi juz per kitab
-- (uncomment untuk cek)
-- SELECT bkid, juz, COUNT(*) AS total_halaman
-- FROM book_content
-- GROUP BY bkid, juz
-- ORDER BY bkid, juz;

SELECT 'Migrasi selesai. Kolom juz berhasil diisi.' AS status;
