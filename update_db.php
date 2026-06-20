<?php
require_once __DIR__ . '/app/bootstrap.php';
use App\Config\Database;

try {
    $pdo = Database::getConnection();

    $sqlFile = __DIR__ . '/database/setup_tos.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        echo "✅ Migration setup_tos.sql successful!\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
