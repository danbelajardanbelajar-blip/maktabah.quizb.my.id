<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Config/Database.php';

use App\Config\Database;

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM books");
    $columns = $stmt->fetchAll();
    echo json_encode($columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
