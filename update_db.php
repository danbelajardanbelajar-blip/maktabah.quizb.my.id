<?php
require_once __DIR__ . '/app/bootstrap.php';

use App\Config\Database;

try {
    $pdo = Database::getConnection();

    // 1. feedbacks table
    $pdo->exec("ALTER TABLE feedbacks ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER id;");
    $pdo->exec("ALTER TABLE feedbacks ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL AFTER content;");
    echo "Table feedbacks updated.\n";

    // 2. kitab_requests table
    $pdo->exec("ALTER TABLE kitab_requests ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER id;");
    $pdo->exec("ALTER TABLE kitab_requests ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL AFTER description;");
    echo "Table kitab_requests updated.\n";

    // 3. notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        type VARCHAR(50) DEFAULT 'system',
        reference_id INT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Table notifications checked/created.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
