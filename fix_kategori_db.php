<?php
require_once __DIR__ . '/app/bootstrap.php';

use App\Config\Database;

try {
    $pdo = Database::getConnection();
    
    // Pastikan kategori "علوم أخرى" ada di database
    $cek = $pdo->query("SELECT id FROM categories WHERE name = 'علوم أخرى' LIMIT 1")->fetchColumn();
    $catId = 0; // Default ID untuk kategori tersebut
    
    if ($cek !== false) {
        $catId = (int)$cek;
    } else {
        // Jika belum ada, buat kategorinya
        $cordStmt = $pdo->query('SELECT MAX(catord) FROM categories');
        $maxCord = (int)$cordStmt->fetchColumn();
        
        $idStmt = $pdo->query('SELECT MAX(id) FROM categories');
        $nextId = (int)$idStmt->fetchColumn() + 1;
        
        $insCat = $pdo->prepare('INSERT INTO categories (id, name, catord, lvl) VALUES (:id, :name, :cord, 0)');
        $insCat->execute([':id' => $nextId, ':name' => 'علوم أخرى', ':cord' => $maxCord + 1]);
        $catId = $nextId;
    }

    // Update semua kitab yang kategori-nya kosong/NULL
    $stmt = $pdo->prepare("UPDATE books SET category_id = :catId, category_name = 'علوم أخرى' WHERE category_id IS NULL OR category_name = ''");
    $stmt->execute([':catId' => $catId]);
    
    $affected = $stmt->rowCount();
    
    echo "<h1>Perbaikan Selesai</h1>";
    echo "<p>Berhasil memperbaiki <strong>{$affected}</strong> kitab di database yang sebelumnya tidak memiliki kategori.</p>";
    echo "<p>Sekarang kitab-kitab tersebut sudah masuk ke kategori <strong>علوم أخرى</strong>.</p>";
    echo "<p><em>Silakan hapus file ini jika sudah selesai digunakan untuk alasan keamanan.</em></p>";
    
} catch (Exception $e) {
    echo "<h1>Terjadi Kesalahan (Error)</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
