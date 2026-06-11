<?php
if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui terminal/CLI.\n");
}

require __DIR__ . '/app/Config/Database.php';

use App\Config\Database;

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

    echo "Mengekstrak kosa kata dari book_content (ini mungkin memakan waktu)...\n";

    // Hitung total baris
    $totalRows = (int)$pdo->query("SELECT COUNT(*) FROM book_content")->fetchColumn();
    echo "Total $totalRows baris yang akan diproses.\n";

    $stmt = $pdo->query("SELECT content FROM book_content");
    $wordCounts = [];
    $processed = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $text = strip_tags($row['content']);
        // Ganti tanda baca dengan spasi
        $text = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $text);
        // Pecah jadi kata
        $words = preg_split('/\s+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($words as $w) {
            // Abaikan kata yang kurang dari 3 huruf
            if (mb_strlen($w, 'UTF-8') < 3) continue;
            // Abaikan jika angka murni
            if (is_numeric($w)) continue;

            if (!isset($wordCounts[$w])) {
                $wordCounts[$w] = 1;
            } else {
                $wordCounts[$w]++;
            }
        }
        $processed++;
        if ($processed % 1000 == 0) {
            echo "Memproses baris ke-$processed dari $totalRows... (" . count($wordCounts) . " kata unik sejauh ini)\n";
        }
    }

    echo "Selesai mengekstrak. Ditemukan total " . count($wordCounts) . " kata unik.\n";
    echo "Menyimpan ke dalam database...\n";

    // Batch insert for performance
    $pdo->beginTransaction();
    $insertStmt = $pdo->prepare("INSERT INTO search_dictionary (word, frequency) VALUES (:w, :f) ON DUPLICATE KEY UPDATE frequency = frequency + :f");
    
    $count = 0;
    foreach ($wordCounts as $w => $f) {
        $insertStmt->execute([':w' => $w, ':f' => $f]);
        $count++;
        if ($count % 5000 == 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            echo "Menyimpan $count kata...\n";
        }
    }
    $pdo->commit();

    echo "Selesai! Kamus berhasil dibuat dan disimpan ke database.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
