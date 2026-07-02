<?php
// api_import_json.php
header('Content-Type: application/json');

require_once __DIR__ . '/app/Config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status" => "error", "message" => "No file uploaded or upload error"]);
    exit;
}

$fileTmpPath = $_FILES['json_file']['tmp_name'];
$fileName = $_FILES['json_file']['name'];

$jsonData = file_get_contents($fileTmpPath);
$data = json_decode($jsonData, true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON format"]);
    exit;
}

if (!isset($data['buku']) || !isset($data['halaman'])) {
    echo json_encode(["status" => "error", "message" => "Missing required JSON nodes (buku/halaman)"]);
    exit;
}

$title = $data['buku']['title'] ?? 'Kitab Tanpa Judul';
$author = $data['buku']['author'] ?? 'Anonim';
$pages = $data['halaman'];
$tocs = $data['daftar_isi'] ?? [];

try {
    $mysql = Database::getConnection();
    
    $mysql->beginTransaction();
    
    // Insert Book
    $stmt = $mysql->prepare("INSERT INTO books (title, author, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$title, $author]);
    $bkid = $mysql->lastInsertId();
    
    // Insert Pages
    $insertContent = $mysql->prepare("INSERT INTO book_content (bkid, page, juz, content) VALUES (?, ?, ?, ?)");
    foreach ($pages as $p) {
        $insertContent->execute([
            $bkid, 
            $p['page'] ?? 1, 
            $p['juz'] ?? 1, 
            $p['text'] ?? ''
        ]);
    }
    
    // Insert TOC
    if (!empty($tocs)) {
        $insertToc = $mysql->prepare("INSERT INTO book_toc (bkid, title, level, page, juz) VALUES (?, ?, ?, ?, ?)");
        foreach ($tocs as $t) {
            $insertToc->execute([
                $bkid,
                $t['title'] ?? '',
                $t['level'] ?? 1,
                $t['page'] ?? 1,
                $t['juz'] ?? 1
            ]);
        }
    }
    
    $mysql->commit();
    echo json_encode(["status" => "success", "title" => $title, "bkid" => $bkid, "filename" => $fileName]);

} catch (Exception $e) {
    if (isset($mysql) && $mysql->inTransaction()) {
        $mysql->rollBack();
    }
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
