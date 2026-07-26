<?php
require_once __DIR__ . '/app/bootstrap.php';
use App\Config\Database;

try {
    $pdo = Database::getConnection();
    
    // Cari judul-judul kitab yang duplikat
    $stmt = $pdo->query("SELECT title, COUNT(*) as c FROM books GROUP BY title HAVING c > 1");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "<h1>Aman!</h1><p>Tidak ada kitab duplikat yang ditemukan di database.</p>";
        exit;
    }

    $totalDeleted = 0;
    
    foreach ($duplicates as $dup) {
        $title = $dup['title'];
        
        // Ambil semua kitab dengan judul ini, urutkan dari yang ID-nya paling kecil (yang paling lama)
        // Kita akan pertahankan ID yang pertama (tertua), dan hapus sisanya.
        $stmtIds = $pdo->prepare("SELECT bkid FROM books WHERE title = ? ORDER BY bkid ASC");
        $stmtIds->execute([$title]);
        $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
        
        // Lewati ID pertama (keep), hapus index 1 dan seterusnya
        $keepId = $ids[0];
        $deleteIds = array_slice($ids, 1);
        
        foreach ($deleteIds as $delId) {
            // Hapus daftar isi
            $pdo->prepare("DELETE FROM book_toc WHERE bkid = ?")->execute([$delId]);
            // Hapus isi/konten buku
            $pdo->prepare("DELETE FROM book_content WHERE bkid = ?")->execute([$delId]);
            // Hapus kitab utama
            $pdo->prepare("DELETE FROM books WHERE bkid = ?")->execute([$delId]);
            
            $totalDeleted++;
        }
    }
    
    echo "<h1>Pembersihan Berhasil</h1>";
    echo "<p>Berhasil menemukan dan menghapus <strong>{$totalDeleted}</strong> kitab duplikat.</p>";
    echo "<p><em>Silakan hapus file ini dari server setelah digunakan.</em></p>";
    
} catch (Exception $e) {
    echo "<h1>Terjadi Kesalahan (Error)</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
