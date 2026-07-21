<?php
// =============================================================
//  Script untuk menambahkan tabel ask_logs
//  Jalankan file ini dari browser: https://maktabah.quizb.my.id/setup_ask_logs.php
// =============================================================

require_once __DIR__ . '/app/bootstrap.php';

use App\Config\Database;

try {
    $pdo = Database::getConnection();
    
    $sql = file_get_contents(__DIR__ . '/database/setup_ask_logs.sql');
    if (!$sql) {
        throw new Exception("File setup_ask_logs.sql tidak ditemukan.");
    }

    $pdo->exec($sql);
    echo "<h3 style='color:green;'>Berhasil: Tabel 'ask_logs' berhasil dibuat/sudah ada.</h3>";

    echo "<p>Sistem pencatatan log (Tanya AI) sekarang sudah siap digunakan.</p>";
    echo "<a href='/'>Kembali ke Beranda</a>";
    
} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Error Database: " . htmlspecialchars($e->getMessage()) . "</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
