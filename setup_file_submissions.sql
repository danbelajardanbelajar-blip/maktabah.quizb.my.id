-- =============================================================
--  Al-Maktabah As-Sunniyyah — File Submissions Migration
--  Jalankan sekali di database: quic1934_maktabah
-- =============================================================

CREATE TABLE IF NOT EXISTS `file_submissions` (
  `id`            INT          UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT          UNSIGNED NOT NULL,
  `user_name`     VARCHAR(255) NOT NULL DEFAULT '',
  `user_email`    VARCHAR(255) NOT NULL DEFAULT '',
  `file_name`     VARCHAR(255) NOT NULL,
  `file_type`     ENUM('bahsul_masail','kitab') NOT NULL,
  `category_id`   INT          UNSIGNED NULL,
  `category_name` VARCHAR(255) NOT NULL DEFAULT '',
  `file_url`      VARCHAR(1024) NOT NULL DEFAULT '',
  `file_size`     INT          UNSIGNED NOT NULL DEFAULT 0 COMMENT 'bytes',
  `mime_type`     VARCHAR(128) NOT NULL DEFAULT '',
  `description`   TEXT         NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT          UNSIGNED NULL,
  `reviewer_name` VARCHAR(255) NOT NULL DEFAULT '',
  `review_note`   TEXT         NULL,
  `reviewed_at`   TIMESTAMP    NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status      (`status`),
  INDEX idx_user_id     (`user_id`),
  INDEX idx_category_id (`category_id`),
  INDEX idx_created_at  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kiriman file dari pengguna — Hasil Bahsul Masail & Kitab';

-- Folder upload (buat folder ini di server dengan chmod 775):
-- /uploads/submissions/

-- Pastikan tabel categories sudah ada (dari migrasi utama).
-- Tabel ini mereferensikan categories.id secara soft (tanpa FK) agar
-- kategori bisa dihapus tanpa cascade menghapus kiriman.
