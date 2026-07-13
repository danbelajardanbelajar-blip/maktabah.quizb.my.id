<?php
require_once __DIR__ . '/app/bootstrap.php';

use App\Config\Database;

try {
    $pdo = Database::getConnection();
    
    // Mengecek apakah tabel download_logs sudah ada
    $checkTable = $pdo->query("SHOW TABLES LIKE 'download_logs'")->rowCount();
    
    if ($checkTable > 0) {
        // Menambahkan kolom source
        $pdo->exec("ALTER TABLE download_logs ADD COLUMN source ENUM('maktabah', 'scholarium') NOT NULL DEFAULT 'maktabah' AFTER book_title");
        echo "✅ Berhasil! Kolom 'source' telah ditambahkan ke tabel download_logs.";
    } else {
        echo "❌ Tabel download_logs belum ada di database.";
    }

} catch (\PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
         echo "⚠️ Kolom 'source' sudah ada di tabel download_logs (tidak perlu ditambahkan lagi).";
    } else {
         echo "❌ Terjadi kesalahan: " . $e->getMessage();
    }
}
