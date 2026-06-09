-- =============================================================
--  Al-Maktabah As-Sunniyyah — Search Logs Migration
--  Jalankan sekali di database: quic1934_maktabah
-- =============================================================

CREATE TABLE IF NOT EXISTS `search_logs` (
  `id`           BIGINT        UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `search_type`  ENUM('basic','advanced') NOT NULL DEFAULT 'basic',
  `query`        VARCHAR(1000) NOT NULL DEFAULT '',
  `query_detail` TEXT          NULL COMMENT 'JSON: kolom+kategori untuk advanced search',
  `result_count` INT           UNSIGNED NOT NULL DEFAULT 0,
  `visitor_ip`   VARCHAR(45)   NOT NULL DEFAULT '',
  `user_agent`   VARCHAR(512)  NOT NULL DEFAULT '',
  `user_id`      INT           UNSIGNED NULL,
  `user_name`    VARCHAR(255)  NOT NULL DEFAULT '',
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created_at  (`created_at`),
  INDEX idx_search_type (`search_type`),
  INDEX idx_user_id     (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
