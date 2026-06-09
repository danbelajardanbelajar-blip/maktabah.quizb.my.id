-- =============================================================
--  Al-Maktabah As-Sunniyyah — CRUD History Migration
--  Jalankan sekali di database: quic1934_maktabah
-- =============================================================

CREATE TABLE IF NOT EXISTS `crud_history` (
  `id`           BIGINT        UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id`     INT           UNSIGNED NULL,
  `admin_name`   VARCHAR(255)  NOT NULL DEFAULT '',
  `admin_email`  VARCHAR(255)  NOT NULL DEFAULT '',
  `action`       ENUM('CREATE','UPDATE','DELETE','IMPORT') NOT NULL,
  `table_name`   VARCHAR(64)   NOT NULL DEFAULT '',
  `record_id`    VARCHAR(64)   NOT NULL DEFAULT '',
  `detail`       TEXT          NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created_at (`created_at`),
  INDEX idx_action     (`action`),
  INDEX idx_table      (`table_name`),
  INDEX idx_admin_id   (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
