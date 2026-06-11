<?php
require __DIR__ . '/app/Config/Database.php';

use App\Config\Database;

// Tingkatkan batas waktu dan memori sebisa mungkin
@ini_set('max_execution_time', 300);
@ini_set('memory_limit', '512M');

try {
    $pdo = Database::getConnection();
    
    $limit = 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    if ($offset === 0) {
        echo "Membuat tabel search_dictionary...<br>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS search_dictionary (
                id INT AUTO_INCREMENT PRIMARY KEY,
                word VARCHAR(191) NOT NULL UNIQUE,
                frequency INT DEFAULT 1,
                INDEX idx_word (word)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "Tabel berhasil dibuat.<br>";
        
        echo "Menghapus data kamus lama (jika ada)...<br>";
        $pdo->exec("TRUNCATE TABLE search_dictionary;");
    }

    echo "<h3>Memproses baris ke-$offset hingga " . ($offset + $limit) . "</h3>";

    $stmt = $pdo->prepare("SELECT content FROM book_content ORDER BY id ASC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        echo "<h2>Selesai! Kamus berhasil dibuat dan disimpan.</h2>";
        echo "<a href='/'>Kembali ke Beranda</a>";
        exit;
    }

    $wordCounts = [];
    foreach ($rows as $row) {
        $text = strip_tags($row['content']);
        $text = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $text);
        $words = preg_split('/\s+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') < 3) continue;
            if (is_numeric($w)) continue;

            if (!isset($wordCounts[$w])) {
                $wordCounts[$w] = 1;
            } else {
                $wordCounts[$w]++;
            }
        }
    }

    $pdo->beginTransaction();
    $insertStmt = $pdo->prepare("INSERT INTO search_dictionary (word, frequency) VALUES (:w, :f) ON DUPLICATE KEY UPDATE frequency = frequency + VALUES(frequency)");
    foreach ($wordCounts as $w => $f) {
        $insertStmt->execute([':w' => $w, ':f' => $f]);
    }
    $pdo->commit();

    $nextOffset = $offset + $limit;
    echo "Tersimpan " . count($wordCounts) . " kata unik dari batch ini.<br>";
    echo "Melanjutkan ke batch berikutnya... mohon tunggu.";
    echo "<script>
        setTimeout(function() {
            window.location.href = '?offset=' + $nextOffset;
        }, 500);
    </script>";

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "<br>";
}
