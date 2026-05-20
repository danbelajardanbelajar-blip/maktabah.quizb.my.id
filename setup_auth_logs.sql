-- =============================================================
--  Al-Maktabah As-Sunniyyah — Auth Log Migration
--  Jalankan sekali di database: quic1934_maktabah
-- =============================================================

-- Tabel log aktivitas login/register/logout pengguna
CREATE TABLE IF NOT EXISTS `user_activity_log` (
  `id`          BIGINT       UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT          UNSIGNED NULL,
  `user_name`   VARCHAR(255) NOT NULL DEFAULT '',
  `user_email`  VARCHAR(255) NOT NULL DEFAULT '',
  `user_role`   ENUM('user','admin') NOT NULL DEFAULT 'user',
  `event`       ENUM('login','register','logout') NOT NULL,
  `ip_address`  VARCHAR(45)  NOT NULL DEFAULT '',
  `user_agent`  VARCHAR(512) NOT NULL DEFAULT '',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event      (`event`),
  INDEX idx_user_email (`user_email`),
  INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
