<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'app/Config/Database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = \App\Config\Database::getConnection();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM search_dictionary');
    $count = $stmt->fetchColumn();
    
    echo "=== LAPORAN STATUS KAMUS ===\n";
    echo "Total Kata dalam Kamus: " . number_format($count) . "\n\n";
    
    if ($count > 0) {
        $stmtLatin = $pdo->query('SELECT COUNT(*) FROM search_dictionary WHERE word REGEXP "^[a-zA-Z0-9]+$"');
        $latinCount = $stmtLatin->fetchColumn();
        echo "Total Kata Karakter Latin (a-z, 0-9): " . number_format($latinCount) . "\n\n";

        echo "--- 20 Kata dengan Frekuensi Tertinggi ---\n";
        $top = $pdo->query('SELECT word, frequency FROM search_dictionary ORDER BY frequency DESC LIMIT 20');
        foreach($top as $row) {
            echo "- " . $row['word'] . " (frekuensi: " . $row['frequency'] . ")\n";
        }
        
        echo "\n--- Pencarian Kata 'fajar' & 'fagar' di Kamus ---\n";
        $stmtFajar = $pdo->prepare('SELECT word, frequency FROM search_dictionary WHERE word LIKE "%fajar%" OR word LIKE "%fagar%"');
        $stmtFajar->execute();
        $fajarMatches = $stmtFajar->fetchAll();
        
        if (count($fajarMatches) > 0) {
            foreach($fajarMatches as $row) {
                echo "- Ditemukan: " . $row['word'] . " (frekuensi: " . $row['frequency'] . ")\n";
            }
        } else {
            echo "Kata 'fajar' atau 'fagar' TIDAK DITEMUKAN sama sekali di tabel kamus.\n";
        }
    } else {
        echo "TABEL KOSONG! (0 kata). Anda perlu menjalankan sinkronisasi kamus (setup_dictionary.php).\n";
    }
    
    echo "\n============================\n";
    echo "Silakan salin (copy) seluruh teks hasil halaman ini dan laporkan kembali ke saya.\n";

} catch (Exception $e) {
    echo "ERROR DATABASE:\n" . $e->getMessage() . "\n";
}
