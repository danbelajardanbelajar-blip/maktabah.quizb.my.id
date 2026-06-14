<?php
// =============================================================
//  Script untuk menambahkan kolom remember_token pada tabel users
//  Jalankan file ini dari browser: https://maktabah.quizb.my.id/setup_remember_token.php
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
    
    // Cek apakah kolom sudah ada (opsional, tapi baik untuk safety)
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Menambahkan kolom remember_token
        $sql = "ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL DEFAULT NULL;";
        $pdo->exec($sql);
        echo "<h3 style='color:green;'>Berhasil: Kolom 'remember_token' berhasil ditambahkan ke tabel users.</h3>";
    } else {
        echo "<h3 style='color:blue;'>Info: Kolom 'remember_token' sudah ada di tabel users. Tidak ada perubahan yang dilakukan.</h3>";
    }

    echo "<p>Sistem chache login (Remember Me) sekarang sudah siap digunakan.</p>";
    echo "<a href='/'>Kembali ke Beranda</a>";
    
} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Error Database: " . htmlspecialchars($e->getMessage()) . "</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
