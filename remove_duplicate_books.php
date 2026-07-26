<?php
require_once __DIR__ . '/app/bootstrap.php';
use App\Config\Database;

// Tingkatkan batas waktu jika diizinkan
@set_time_limit(300);
@ini_set('memory_limit', '256M');

try {
    $pdo = Database::getConnection();
    
    // Cari judul-judul kitab yang duplikat (batasi 10 judul per proses agar tidak RTO)
    $stmt = $pdo->query("SELECT title, COUNT(*) as c FROM books GROUP BY title HAVING c > 1 LIMIT 10");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "<h1 style='color:green;'>Aman! Selesai!</h1><p>Sudah tidak ada kitab duplikat yang tersisa di database.</p>";
        echo "<p><em>Silakan hapus file ini dari server demi keamanan.</em></p>";
        exit;
    }

    $totalDeleted = 0;
    
    foreach ($duplicates as $dup) {
        $title = $dup['title'];
        
        $stmtIds = $pdo->prepare("SELECT bkid FROM books WHERE title = ? ORDER BY bkid ASC");
        $stmtIds->execute([$title]);
        $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
        
        // Lewati ID pertama (keep), hapus sisanya
        $deleteIds = array_slice($ids, 1);
        
        foreach ($deleteIds as $delId) {
            // Hapus secara efisien
            $pdo->prepare("DELETE FROM book_toc WHERE bkid = ?")->execute([$delId]);
            $pdo->prepare("DELETE FROM book_content WHERE bkid = ?")->execute([$delId]);
            $pdo->prepare("DELETE FROM books WHERE bkid = ?")->execute([$delId]);
            $totalDeleted++;
        }
    }
    
    echo "<h1>Memproses...</h1>";
    echo "<p>Menghapus <strong>{$totalDeleted}</strong> duplikat pada putaran ini.</p>";
    echo "<p>Halaman akan dimuat ulang secara otomatis untuk memproses sisa duplikat...</p>";
    echo "<script>setTimeout(function() { window.location.reload(); }, 1500);</script>";
    
} catch (Exception $e) {
    echo "<h1>Terjadi Kesalahan (Error)</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
