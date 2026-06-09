-- =============================================================
--  Al-Maktabah As-Sunniyyah — Users & Auth Migration
--  Jalankan sekali di database: quic1934_maktabah
-- =============================================================

-- Tabel users (Google OAuth)
CREATE TABLE IF NOT EXISTS `users` (
  `id`          INT          UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `google_id`   VARCHAR(64)  NOT NULL UNIQUE,
  `email`       VARCHAR(255) NOT NULL,
  `name`        VARCHAR(255) NOT NULL DEFAULT '',
  `picture`     VARCHAR(512) NOT NULL DEFAULT '',
  `role`        ENUM('user','admin') NOT NULL DEFAULT 'user',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contoh: jadikan akun pertama sebagai admin setelah login pertama kali:
-- UPDATE users SET role = 'admin' WHERE email = 'email_anda@gmail.com';
