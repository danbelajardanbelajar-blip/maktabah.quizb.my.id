<?php
if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui terminal/CLI.\n");
}

require __DIR__ . '/app/Config/Database.php';

use App\Config\Database;

// Memaksimalkan limit memory di PHP
@ini_set('memory_limit', '1024M');

try {
    $pdo = Database::getConnection();

    echo "Membuat tabel search_dictionary...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS search_dictionary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            word VARCHAR(191) NOT NULL UNIQUE,
            frequency INT DEFAULT 1,
            INDEX idx_word (word)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel berhasil dibuat.\n";

    echo "Menghapus data kamus lama (jika ada)...\n";
    $pdo->exec("TRUNCATE TABLE search_dictionary;");

    echo "Mengekstrak kosa kata dari book_content (dicicil agar RAM aman)...\n";

    $totalRows = (int)$pdo->query("SELECT COUNT(*) FROM book_content")->fetchColumn();
    echo "Total $totalRows baris yang akan diproses.\n";

    $batchSize = 2500; // Jumlah baris yang diambil setiap cicilan
    $insertStmt = $pdo->prepare("INSERT INTO search_dictionary (word, frequency) VALUES (:w, :f) ON DUPLICATE KEY UPDATE frequency = frequency + :f");

    for ($offset = 0; $offset < $totalRows; $offset += $batchSize) {
        $stmt = $pdo->prepare("SELECT content FROM book_content ORDER BY id ASC LIMIT :lim OFFSET :off");
        $stmt->bindValue(':lim', $batchSize, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $wordCounts = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
        
        // Simpan per batch agar memori tidak penuh
        $pdo->beginTransaction();
        foreach ($wordCounts as $w => $f) {
            $insertStmt->execute([':w' => $w, ':f' => $f]);
        }
        $pdo->commit();
        
        // Kosongkan memori variabel
        unset($wordCounts);
        
        echo "Telah memproses " . min($offset + $batchSize, $totalRows) . " dari $totalRows baris...\n";
    }

    echo "Selesai! Kamus berhasil dibuat dan seluruh kata telah dimasukkan ke database.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
