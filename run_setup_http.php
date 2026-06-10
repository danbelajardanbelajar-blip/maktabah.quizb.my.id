<?php
require 'app/Config/Database.php';
try {
    $pdo = App\Config\Database::getConnection();
    $sql = file_get_contents('database/setup_kitab_requests.sql');
    $pdo->exec($sql);
    echo "Table created successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
