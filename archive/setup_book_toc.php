<?php
require 'app/bootstrap.php';
try {
    $pdo = App\Config\Database::getConnection();
    $sql = "
    CREATE TABLE IF NOT EXISTS `book_toc` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `bkid` int(11) NOT NULL,
      `title` varchar(500) NOT NULL,
      `juz` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
      `page` int(11) NOT NULL,
      `level` tinyint(2) NOT NULL DEFAULT 1,
      PRIMARY KEY (`id`),
      KEY `bkid` (`bkid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "SUCCESS: Table book_toc created successfully.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
