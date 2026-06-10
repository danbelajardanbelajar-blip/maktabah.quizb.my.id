-- =============================================================
--  Al-Maktabah As-Sunniyyah — Kitab Requests Migration
-- =============================================================

CREATE TABLE IF NOT EXISTS `kitab_requests` (
  `id`                 INT          UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_email`         VARCHAR(255) NOT NULL,
  `request_type`       ENUM('bahsul_masail','kitab') NOT NULL,
  `title`              VARCHAR(255) NOT NULL,
  `author_or_category` VARCHAR(255) NULL,
  `description`        TEXT         NULL,
  `status`             ENUM('pending','fulfilled','rejected') NOT NULL DEFAULT 'pending',
  `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (`status`),
  INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Request kitab / hasil bahsul masail dari pengunjung';
