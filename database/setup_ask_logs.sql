-- =============================================================
--  Al-Maktabah As-Sunniyyah — Ask Logs Migration
-- =============================================================

CREATE TABLE IF NOT EXISTS `ask_logs` (
  `id`           BIGINT        UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `question`     VARCHAR(1000) NOT NULL DEFAULT '',
  `response`     TEXT          NULL,
  `visitor_ip`   VARCHAR(45)   NOT NULL DEFAULT '',
  `user_id`      INT           UNSIGNED NULL,
  `user_name`    VARCHAR(255)  NOT NULL DEFAULT '',
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created_at  (`created_at`),
  INDEX idx_user_id     (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
