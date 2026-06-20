<?php
require_once __DIR__ . '/app/Config/Database.php';

use App\Config\Database;

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM books");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $c) {
        echo $c['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
