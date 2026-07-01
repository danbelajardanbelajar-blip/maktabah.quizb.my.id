<?php

/**
 * Maktabah QuizB - Shamela (.bok) Importer
 * 
 * Usage:
 * php import_shamela.php "C:\path\to\kitab.bok"
 */

if (php_sapi_name() !== 'cli') {
    die("Error: Script ini hanya bisa dijalankan melalui Command Line (CLI).\n");
}

if (!extension_loaded('pdo_odbc')) {
    die("Error: Ekstensi 'pdo_odbc' tidak aktif. Harap aktifkan di php.ini Anda terlebih dahulu.\n");
}

if ($argc < 2) {
    echo "Penggunaan: php import_shamela.php \"<path_ke_file_bok>\"\n";
    echo "Contoh: php import_shamela.php \"D:\\Buku\\Fathul Muin.bok\"\n";
    exit(1);
}

$bokFile = $argv[1];

if (!file_exists($bokFile)) {
    die("Error: File '$bokFile' tidak ditemukan.\n");
}

require_once __DIR__ . '/app/Config/Database.php';

try {
    // 1. Connect to MySQL
    $mysql = Database::getConnection();
    echo "[+] Terhubung ke database MySQL.\n";

    // 2. Connect to .bok (MS Access MDB)
    echo "[*] Membaca file Shamela: $bokFile\n";
    $dsn = "odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=$bokFile;";
    
    // We use Latin1 or CP1256 if needed, but PDO ODBC typically returns UTF-8 or we might need to convert.
    // Shamela 3.x uses Windows-1256 (Arabic) encoding. We must convert it to UTF-8!
    $odbc = new PDO($dsn);
    $odbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[+] Berhasil membuka file .bok.\n";

    // 3. Read Metadata from `title` table
    $stmt = $odbc->query("SELECT * FROM title");
    $titleRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$titleRow) {
        die("Error: Tidak dapat membaca tabel 'title' dari file .bok ini.\n");
    }

    $shamelaBkId = $titleRow['bkid'] ?? $titleRow['bk_id'] ?? null;
    $rawTitle = $titleRow['bk'] ?? $titleRow['tit'] ?? 'Kitab Tanpa Judul';
    $rawAuthor = $titleRow['auth'] ?? $titleRow['author'] ?? 'Anonim';

    // Convert Windows-1256 to UTF-8
    function toUtf8($text) {
        if (empty($text)) return '';
        // Shamela uses Windows-1256 for Arabic text
        $utf8 = @iconv('windows-1256', 'UTF-8//IGNORE', $text);
        return $utf8 !== false ? $utf8 : $text;
    }

    $bookTitle = trim(toUtf8($rawTitle));
    $bookAuthor = trim(toUtf8($rawAuthor));

    echo "[+] Ditemukan Kitab: $bookTitle (Penulis: $bookAuthor)\n";

    // Insert into MySQL `books` table
    $stmt = $mysql->prepare("INSERT INTO books (title, author, total_juz, created_at) VALUES (?, ?, 1, NOW())");
    $stmt->execute([$bookTitle, $bookAuthor]);
    $newBookId = $mysql->lastInsertId();
    echo "[+] Kitab disimpan di MySQL dengan ID: $newBookId\n";

    // Find the content table and TOC table
    // Normally they are named b<bkid> and t<bkid> but if we don't know the exact bkid, we scan tables
    $stmt = $odbc->query("SELECT Name FROM MSysObjects WHERE Type=1 AND Flags=0");
    $contentTable = null;
    $tocTable = null;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tableName = $row['Name'];
        if (preg_match('/^b(\d+)$/i', $tableName)) {
            $contentTable = $tableName;
        } else if (preg_match('/^t(\d+)$/i', $tableName)) {
            $tocTable = $tableName;
        } else if (preg_match('/^book$/i', $tableName)) {
            $contentTable = $tableName;
        } else if (preg_match('/^toc$/i', $tableName)) {
            $tocTable = $tableName;
        }
    }

    if (!$contentTable) {
        // Fallback for some variations
        if ($shamelaBkId) {
            $contentTable = "b" . $shamelaBkId;
            $tocTable = "t" . $shamelaBkId;
        } else {
            die("Error: Tidak dapat menemukan tabel isi kitab (bXXX) dalam database ini.\n");
        }
    }

    echo "[*] Mengimpor teks dari tabel '$contentTable'...\n";
    $contentStmt = $odbc->query("SELECT * FROM `$contentTable` ORDER BY id ASC");
    
    $insertContent = $mysql->prepare("INSERT INTO book_content (bkid, page, juz, content) VALUES (?, ?, ?, ?)");
    
    $maxJuz = 1;
    $rowCount = 0;
    
    $mysql->beginTransaction();
    while ($row = $contentStmt->fetch(PDO::FETCH_ASSOC)) {
        $part = isset($row['part']) ? (int)$row['part'] : 1;
        $page = isset($row['page']) ? (int)$row['page'] : 1;
        
        // Handle nass (text)
        $nass = isset($row['nass']) ? $row['nass'] : '';
        if (empty($nass) && isset($row['text'])) $nass = $row['text'];
        
        $nass = toUtf8($nass);
        
        if ($part > $maxJuz) $maxJuz = $part;
        if ($part <= 0) $part = 1;

        $insertContent->execute([$newBookId, $page, $part, $nass]);
        $rowCount++;

        if ($rowCount % 500 == 0) {
            echo "    - $rowCount halaman/paragraf terimpor...\n";
        }
    }
    $mysql->commit();
    echo "[+] Selesai mengimpor $rowCount bagian teks.\n";

    // Update total juz
    $mysql->prepare("UPDATE books SET total_juz = ? WHERE id = ?")->execute([$maxJuz, $newBookId]);

    // 5. Import TOC
    if ($tocTable) {
        echo "[*] Mengimpor Daftar Isi dari tabel '$tocTable'...\n";
        try {
            $tocStmt = $odbc->query("SELECT * FROM `$tocTable` ORDER BY id ASC");
            $insertToc = $mysql->prepare("INSERT INTO book_toc (bkid, title, level, page, juz) VALUES (?, ?, ?, ?, ?)");
            
            $tocCount = 0;
            $mysql->beginTransaction();
            while ($row = $tocStmt->fetch(PDO::FETCH_ASSOC)) {
                $lvl = isset($row['lvl']) ? (int)$row['lvl'] : 1;
                $tit = isset($row['tit']) ? $row['tit'] : '';
                if (empty($tit) && isset($row['title'])) $tit = $row['title'];
                
                $part = isset($row['part']) ? (int)$row['part'] : 1;
                $page = isset($row['page']) ? (int)$row['page'] : 1;

                if ($part <= 0) $part = 1;
                
                $tit = toUtf8($tit);
                if (trim($tit) !== '') {
                    $insertToc->execute([$newBookId, $tit, $lvl, $page, $part]);
                    $tocCount++;
                }
            }
            $mysql->commit();
            echo "[+] Selesai mengimpor $tocCount item Daftar Isi.\n";
        } catch (Exception $e) {
            $mysql->rollBack();
            echo "[-] Peringatan: Gagal membaca tabel TOC '$tocTable' atau tabel kosong. Anda bisa membuat ulang daftar isi melalui panel admin.\n";
            echo "    Error Detail: " . $e->getMessage() . "\n";
        }
    } else {
        echo "[-] Peringatan: Tabel Daftar Isi tidak ditemukan. Anda dapat menggunakan fitur Auto Generate di panel admin.\n";
    }

    echo "\n============================================\n";
    echo "[+] IMPORT BERHASIL!\n";
    echo "Kitab '$bookTitle' telah tersimpan di sistem.\n";
    echo "============================================\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("General Error: " . $e->getMessage() . "\n");
}
