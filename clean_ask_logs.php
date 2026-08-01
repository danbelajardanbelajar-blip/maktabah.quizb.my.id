<?php
require 'app/Config/Database.php';

try {
    $pdo = App\Config\Database::getConnection();
    $stmt = $pdo->query("DELETE FROM ask_logs WHERE response LIKE 'Error:%' OR response LIKE 'Maaf, terjadi kesalahan%'");
    echo "Successfully deleted " . $stmt->rowCount() . " error logs from ask_logs table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
