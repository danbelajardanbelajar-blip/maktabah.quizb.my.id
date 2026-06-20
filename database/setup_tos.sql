-- Menambahkan kolom agreed_tos ke tabel users
ALTER TABLE `users` ADD COLUMN `agreed_tos` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`;
