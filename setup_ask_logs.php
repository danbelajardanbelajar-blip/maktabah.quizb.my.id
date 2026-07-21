<?php
// =============================================================
//  Script untuk menambahkan tabel ask_logs
//  Jalankan file ini dari browser: https://maktabah.quizb.my.id/setup_ask_logs.php
// =============================================================

// Simple PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

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
