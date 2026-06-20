<?php
require 'app/bootstrap.php';
try {
    $pdo = App\Config\Database::getConnection();
    $pdo->exec("ALTER TABLE users ADD COLUMN agreed_tos TINYINT(1) NOT NULL DEFAULT 0;");
    echo "SUCCESS: Column agreed_tos added.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "SUCCESS: Column already exists.\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
