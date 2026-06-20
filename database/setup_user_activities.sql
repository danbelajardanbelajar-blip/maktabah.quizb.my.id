-- =============================================================
--  Al-Maktabah As-Sunniyyah — Pembaruan Fitur User Activity & Notifikasi
-- =============================================================

-- 1. Tabel Feedbacks
ALTER TABLE `feedbacks` ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`;
ALTER TABLE `feedbacks` ADD COLUMN `admin_reply` TEXT NULL AFTER `content`;

-- 2. Tabel Kitab Requests
ALTER TABLE `kitab_requests` ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`;
ALTER TABLE `kitab_requests` ADD COLUMN `admin_reply` TEXT NULL AFTER `description`;

-- 3. Tabel Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `type` VARCHAR(50) DEFAULT 'system',
  `reference_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
