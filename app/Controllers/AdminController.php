<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use PDO;
use Exception;
use ZipArchive;

class AdminController {
    
    private function extractTextFromFile(string $filePath): string {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'docx') {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($filePath) === true) {
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();
                    if ($xml !== false) {
                        // Replace <w:p> with newlines, then strip all XML tags
                        $xml = str_replace('</w:p>', "\n\n", $xml);
                        return strip_tags($xml);
                    }
                }
            }
            return "Gagal membaca isi file .docx (pastikan ZipArchive aktif di server).";
        } elseif ($ext === 'doc') {
            return "Pratinjau .doc tidak didukung di server ini. Silakan konversi ke .docx untuk melihat pratinjau.";
        } elseif ($ext === 'pdf') {
            return "Pratinjau PDF tidak didukung secara native karena keterbatasan server (shell_exec dinonaktifkan).";
        }
        
        $content = @file_get_contents($filePath);
        return $content !== false ? (string)$content : '';
    }

    public function handleAdminSaveBook(): void {
        $pdo  = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $bkid   = (int)($data['bkid']        ?? 0);
        $title  = trim($data['title']        ?? '');
        $author = trim($data['author']       ?? '');
        $catId  = (int)($data['category_id'] ?? 0);
        $iso    = $data['iso'] ?? 'ar';
    
        if (!$title) { http_response_code(400); echo json_encode(['error' => 'Judul wajib diisi.']); return; }
    
        $catName = '';
        if ($catId) {
            $cs = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
            $cs->execute([':id' => $catId]);
            $catName = $cs->fetchColumn() ?: '';
        }
    
        if ($bkid) {
            $stmt = $pdo->prepare(
                "UPDATE books SET title=:title, author=:author, category_id=:catid,
                 category_name=:catname, iso=:iso WHERE bkid=:bkid"
            );
            $stmt->execute([
                ':title'   => $title,  ':author'  => $author,
                ':catid'   => $catId ?: null, ':catname' => $catName,
                ':iso'     => $iso,    ':bkid'    => $bkid,
            ]);
            AuthHelper::logCrudHistory('UPDATE', 'books', (string)$bkid,
                "Judul: {$title}" . ($author ? " | Penulis: {$author}" : '') . ($catName ? " | Kategori: {$catName}" : ''));
            echo json_encode(['success' => true, 'bkid' => $bkid]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO books (title, author, category_id, category_name, iso, pages)
                 VALUES (:title, :author, :catid, :catname, :iso, 0)"
            );
            $stmt->execute([
                ':title'   => $title, ':author'  => $author,
                ':catid'   => $catId ?: null, ':catname' => $catName,
                ':iso'     => $iso,
            ]);
            $newId = (int)$pdo->lastInsertId();
            AuthHelper::logCrudHistory('CREATE', 'books', (string)$newId,
                "Judul: {$title}" . ($author ? " | Penulis: {$author}" : '') . ($catName ? " | Kategori: {$catName}" : ''));
            echo json_encode(['success' => true, 'bkid' => $newId]);
        }
    }

    public function handleAdminDeleteBook(): void {
        $pdo  = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $bkid = (int)($data['bkid'] ?? 0);
        if (!$bkid) { http_response_code(400); echo json_encode(['error' => 'bkid wajib diisi.']); return; }
        // Ambil judul sebelum dihapus untuk catatan log
        $titleRow = $pdo->prepare("SELECT title, author FROM books WHERE bkid = :bkid LIMIT 1");
        $titleRow->execute([':bkid' => $bkid]);
        $bookInfo = $titleRow->fetch();
        $pdo->prepare("DELETE FROM book_content WHERE bkid = :bkid")->execute([':bkid' => $bkid]);
        $pdo->prepare("DELETE FROM books WHERE bkid = :bkid")->execute([':bkid' => $bkid]);
        AuthHelper::logCrudHistory('DELETE', 'books', (string)$bkid,
            $bookInfo ? "Judul: {$bookInfo['title']}" . ($bookInfo['author'] ? " | Penulis: {$bookInfo['author']}" : '') : '');
        echo json_encode(['success' => true]);
    }

    public function handleAdminSaveCategory(): void {
        $pdo  = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id     = (int)($data['id']   ?? 0);
        $name   = trim($data['name']  ?? '');
        $ord    = (int)($data['catord'] ?? 0);
        $level  = (int)($data['lvl']  ?? 0);
        if (!$name) { http_response_code(400); echo json_encode(['error' => 'Nama kategori wajib diisi.']); return; }
    
        if ($id) {
            $pdo->prepare("UPDATE categories SET name=:name, catord=:ord, level=:lvl WHERE id=:id")
                ->execute([':name' => $name, ':ord' => $ord, ':lvl' => $level, ':id' => $id]);
            AuthHelper::logCrudHistory('UPDATE', 'categories', (string)$id,
                "Nama: {$name} | Urutan: {$ord} | Level: {$level}");
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO categories (name, catord, level) VALUES (:name, :ord, :lvl)")
                ->execute([':name' => $name, ':ord' => $ord, ':lvl' => $level]);
            $newCatId = (int)$pdo->lastInsertId();
            AuthHelper::logCrudHistory('CREATE', 'categories', (string)$newCatId,
                "Nama: {$name} | Urutan: {$ord} | Level: {$level}");
            echo json_encode(['success' => true, 'id' => $newCatId]);
        }
    }

    public function handleAdminDeleteCategory(): void {
        $pdo  = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($data['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID wajib diisi.']); return; }
        $nameRow = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
        $nameRow->execute([':id' => $id]);
        $catName = $nameRow->fetchColumn() ?: '';
        $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id' => $id]);
        AuthHelper::logCrudHistory('DELETE', 'categories', (string)$id, "Nama: {$catName}");
        echo json_encode(['success' => true]);
    }

    public function handleAdminSaveContent(): void {
        $pdo  = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $bkid    = (int)($data['bkid']    ?? 0);
        $page    = (int)($data['page']    ?? 0);
        $juz     = (int)($data['juz']     ?? 0); // 0 = auto-detect
        $content = trim($data['content']  ?? '');
        $isNew   = (bool)($data['is_new'] ?? false);
    
        if (!$bkid || !$page) {
            http_response_code(400); echo json_encode(['error' => 'bkid dan page wajib diisi.']); return;
        }
    
        if ($isNew) {
            // Auto-detect juz jika tidak dikirim:
            // Ambil juz dan page terakhir per juz untuk bkid ini
            if ($juz < 1) {
                $lastRow = $pdo->prepare(
                    "SELECT juz, page FROM book_content WHERE bkid = :bkid ORDER BY juz DESC, id DESC LIMIT 1"
                );
                $lastRow->execute([':bkid' => $bkid]);
                $last = $lastRow->fetch();
                if (!$last) {
                    // Kitab masih kosong, mulai dari juz 1
                    $juz = 1;
                } elseif ($page > (int)$last['page']) {
                    // Halaman baru lebih besar → lanjutan juz yang sama
                    $juz = (int)$last['juz'];
                } else {
                    // Halaman lebih kecil/sama → juz baru
                    $juz = (int)$last['juz'] + 1;
                }
            }
    
            // Cek halaman sudah ada di juz yang sama
            $check = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid=:bkid AND juz=:juz AND page=:page");
            $check->execute([':bkid' => $bkid, ':juz' => $juz, ':page' => $page]);
            if ((int)$check->fetchColumn() > 0) {
                http_response_code(409); echo json_encode(['error' => "Halaman {$page} pada Juz {$juz} sudah ada."]); return;
            }
            $pdo->prepare("INSERT INTO book_content (bkid, juz, page, content) VALUES (:bkid, :juz, :page, :content)")
                ->execute([':bkid' => $bkid, ':juz' => $juz, ':page' => $page, ':content' => $content]);
            SearchHelper::syncContentToDictionary($content);
            // Update jumlah halaman di tabel books
            $pdo->prepare("UPDATE books SET pages = (SELECT COUNT(*) FROM book_content WHERE bkid=:bkid) WHERE bkid=:bkid2")
                ->execute([':bkid' => $bkid, ':bkid2' => $bkid]);
            AuthHelper::logCrudHistory('CREATE', 'book_content', "bkid:{$bkid}|juz:{$juz}|page:{$page}",
                "Tambah halaman {$page} juz {$juz} pada kitab bkid={$bkid}");
        } else {
            // Update: filter by juz jika dikirim, otherwise fallback ke page saja
            if ($juz > 0) {
                $pdo->prepare("UPDATE book_content SET content=:content WHERE bkid=:bkid AND juz=:juz AND page=:page")
                    ->execute([':content' => $content, ':bkid' => $bkid, ':juz' => $juz, ':page' => $page]);
            } else {
                $pdo->prepare("UPDATE book_content SET content=:content WHERE bkid=:bkid AND page=:page ORDER BY id ASC LIMIT 1")
                    ->execute([':content' => $content, ':bkid' => $bkid, ':page' => $page]);
            }
            SearchHelper::syncContentToDictionary($content);
            AuthHelper::logCrudHistory('UPDATE', 'book_content', "bkid:{$bkid}|juz:{$juz}|page:{$page}",
                "Edit halaman {$page} juz {$juz} pada kitab bkid={$bkid}");
        }
        echo json_encode(['success' => true, 'juz' => $juz]);
    }

    public function handleAdminDeleteContent(): void {
        $pdo  = Database::getConnection();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $bkid = (int)($data['bkid'] ?? 0);
        $page = (int)($data['page'] ?? 0);
        $juz  = (int)($data['juz']  ?? 0); // 0 = hapus semua juz dengan page ini
        if (!$bkid || !$page) {
            http_response_code(400); echo json_encode(['error' => 'bkid dan page wajib diisi.']); return;
        }
        if ($juz > 0) {
            $pdo->prepare("DELETE FROM book_content WHERE bkid=:bkid AND juz=:juz AND page=:page")
                ->execute([':bkid' => $bkid, ':juz' => $juz, ':page' => $page]);
        } else {
            // Fallback: hapus hanya baris pertama dengan page tersebut
            $pdo->prepare("DELETE FROM book_content WHERE bkid=:bkid AND page=:page ORDER BY id ASC LIMIT 1")
                ->execute([':bkid' => $bkid, ':page' => $page]);
        }
        // Recalculate pages
        $pdo->prepare("UPDATE books SET pages=(SELECT COUNT(*) FROM book_content WHERE bkid=:bkid) WHERE bkid=:bkid2")
            ->execute([':bkid' => $bkid, ':bkid2' => $bkid]);
        AuthHelper::logCrudHistory('DELETE', 'book_content', "bkid:{$bkid}|juz:{$juz}|page:{$page}",
            "Hapus halaman {$page} juz {$juz} pada kitab bkid={$bkid}");
        echo json_encode(['success' => true]);
    }

    public function handleAdminImportBook(): void {
        $pdo = Database::getConnection();
    
        // --- Bersihkan orphan bkid=0 dari import sebelumnya yang gagal ---
        try {
            $pdo->exec("DELETE FROM book_content WHERE bkid = 0");
            $pdo->exec("DELETE FROM books WHERE bkid = 0");
        } catch (\Exception $e) { /* ignore */ }
    
        $json = ResponseHelper::getJsonRequest();
        $bkid   = (int)($_POST['bkid']        ?? $json['bkid'] ?? 0);
        $title  = trim($_POST['title']        ?? $json['title'] ?? '');
        $author = trim($_POST['author']       ?? $json['author'] ?? '');
        $catId  = (int)($_POST['category_id'] ?? $json['category_id'] ?? 0);
        $iso    = $_POST['iso']               ?? $json['iso'] ?? 'ar';
        $pages  = $json['pages'] ?? null;
        $juzs   = $json['juzs'] ?? [];
    
        if (!$title) { http_response_code(400); echo json_encode(['error' => 'Judul wajib diisi.']); return; }
    
        $pageTexts = null;
        if (is_array($pages)) {
            $pageTexts = array_values($pages);
            foreach ($pageTexts as $pageText) {
                if (!is_string($pageText)) {
                    http_response_code(400); echo json_encode(['error' => 'Halaman import tidak valid.']); return;
                }
            }
            if (count($pageTexts) === 0) {
                http_response_code(400); echo json_encode(['error' => 'Tidak ada halaman import.']); return;
            }
        }
    
        if ($pageTexts === null) {
            if (empty($_FILES['docfile']['tmp_name'])) {
                http_response_code(400); echo json_encode(['error' => 'File .doc/.docx wajib diunggah.']); return;
            }
    
            $tmpFile = $_FILES['docfile']['tmp_name'];
        $origExt = strtolower(pathinfo($_FILES['docfile']['name'], PATHINFO_EXTENSION));
        if (!in_array($origExt, ['doc', 'docx'])) {
            http_response_code(400); echo json_encode(['error' => 'Hanya file .doc dan .docx yang didukung.']); return;
        }
    
        // --- Konversi ke teks menggunakan antiword / docx2txt / python-docx ---
        $tmpCopy = sys_get_temp_dir() . '/' . uniqid('mkt_', true) . '.' . $origExt;
        move_uploaded_file($tmpFile, $tmpCopy);
          if (!empty($origExt) && in_array($origExt, ['pdf', 'doc', 'docx'])) {
            $rawText = $this->extractTextFromFile($tmpCopy);
        } else {
            $rawText = @file_get_contents($tmpCopy) ?: '';
        }
        @unlink($tmpCopy);
    
        if (strlen(trim($rawText)) < 5) {
            http_response_code(422); echo json_encode(['error' => 'Gagal membaca isi file. Pastikan file tidak kosong atau terenkripsi.']); return;
        }
    
        // Pecah per halaman (tiap 3000 karakter atau per paragraf besar)
        $pageTexts = [];
        $paragraphs = preg_split('/\n{2,}/', trim($rawText));
        $buf = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;
            if (strlen($buf) + strlen($para) > 3000 && $buf !== '') {
                $pageTexts[] = trim($buf);
                $buf = $para;
            } else {
                $buf .= ($buf ? "\n\n" : '') . $para;
            }
        }
        if ($buf !== '') $pageTexts[] = trim($buf);
        if (empty($pageTexts)) {
            http_response_code(422); echo json_encode(['error' => 'Tidak ada konten yang bisa diimpor. Pastikan file tidak kosong.']); return;
        }
        }
    
        $pages = $pageTexts;
    
        $catName = '';
        if ($catId) {
            $cs = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
            $cs->execute([':id' => $catId]);
            $catName = $cs->fetchColumn() ?: '';
        }
    
        $pdo->beginTransaction();
        try {
            if ($bkid) {
                $pdo->prepare(
                    "UPDATE books SET title=:title, author=:author, category_id=:catid,
                     category_name=:catname, iso=:iso, pages=:pages WHERE bkid=:bkid"
                )->execute([
                    ':title'   => $title,
                    ':author'  => $author,
                    ':catid'   => $catId ?: null,
                    ':catname' => $catName,
                    ':iso'     => $iso,
                    ':pages'   => count($pages),
                    ':bkid'    => $bkid,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO books (title, author, category_id, category_name, iso, pages)
                     VALUES (:title, :author, :catid, :catname, :iso, :pages)"
                );
                $stmt->execute([
                    ':title'   => $title,
                    ':author'  => $author,
                    ':catid'   => $catId ?: null,
                    ':catname' => $catName,
                    ':iso'     => $iso,
                    ':pages'   => count($pages),
                ]);
                $bkid = (int)$pdo->lastInsertId();
            }
    
            $insertContent = $pdo->prepare(
                "INSERT INTO book_content (bkid, page, juz, content) VALUES (:bkid, :page, :juz, :content)"
            );
            foreach ($pages as $pageIndex => $pageContent) {
                $juz = isset($juzs[$pageIndex]) ? (int)$juzs[$pageIndex] : 1;
                $insertContent->execute([
                    ':bkid'    => $bkid,
                    ':page'    => $pageIndex + 1,
                    ':juz'     => $juz,
                    ':content' => $pageContent,
                ]);
                SearchHelper::syncContentToDictionary($pageContent);
            }
    
            $pdo->commit();
    
            AuthHelper::logCrudHistory('IMPORT', 'books', (string)$bkid,
                "Impor kitab: {$title} | Penulis: {$author} | Halaman: " . count($pages)
            );
    
            echo json_encode(['success' => true, 'bkid' => $bkid, 'pages' => count($pages)]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Import gagal: ' . $e->getMessage()]);
        }
    }

    public function handleAdminImportJsonInit(): void {
        header('Content-Type: application/json');
        AuthHelper::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $title = trim($input['title'] ?? 'Kitab Tanpa Judul');
        $author = trim($input['author'] ?? 'Anonim');
        $catId = isset($input['category_id']) ? (int)$input['category_id'] : 0;
        $catName = '';

        $pdo = Database::getConnection();

        try {
            if ($catId) {
                $cs = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
                $cs->execute([':id' => $catId]);
                $catName = $cs->fetchColumn() ?: '';
            }

            $stmt = $pdo->prepare("INSERT INTO books (title, author, category_id, category_name, pages, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$title, $author, $catId ?: null, $catName]);
            $newBookId = $pdo->lastInsertId();

            echo json_encode(['success' => true, 'bkid' => $newBookId]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal menginisialisasi kitab: ' . $e->getMessage()]);
        }
    }

    public function handleAdminImportJsonChunk(): void {
        header('Content-Type: application/json');
        AuthHelper::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['bkid']) || empty($input['contents'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Request']);
            return;
        }

        $bkid = (int)$input['bkid'];
        $contents = $input['contents'];

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO book_content (bkid, page, juz, content) VALUES (?, ?, ?, ?)");
            
            foreach ($contents as $item) {
                $page = (int)($item['page'] ?? 1);
                $juz = (int)($item['juz'] ?? 1);
                $text = trim($item['text'] ?? '');
                
                if ($page <= 0) $page = 1;
                if ($juz <= 0) $juz = 1;
                if ($text !== '') {
                    $stmt->execute([$bkid, $page, $juz, $text]);
                }
            }
            $pdo->commit();
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Gagal menyimpan teks: ' . $e->getMessage()]);
        }
    }

    public function handleAdminImportJsonTocChunk(): void {
        header('Content-Type: application/json');
        AuthHelper::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['bkid']) || empty($input['tocs'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Request']);
            return;
        }

        $bkid = (int)$input['bkid'];
        $tocs = $input['tocs'];

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO book_toc (bkid, title, level, page, juz) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($tocs as $item) {
                $title = trim($item['title'] ?? '');
                $level = (int)($item['level'] ?? 1);
                $page = (int)($item['page'] ?? 1);
                $juz = (int)($item['juz'] ?? 1);
                
                if ($title !== '') {
                    $stmt->execute([$bkid, $title, $level, $page, $juz]);
                }
            }
            $pdo->commit();
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Gagal menyimpan daftar isi: ' . $e->getMessage()]);
        }
    }

    public function handleAdminImportJsonFinish(): void {
        header('Content-Type: application/json');
        AuthHelper::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['bkid'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Request']);
            return;
        }

        $bkid = (int)$input['bkid'];

        $pdo = Database::getConnection();
        try {
            // Get max juz and total pages
            $stmtJuz = $pdo->prepare("SELECT MAX(juz) FROM book_content WHERE bkid = ?");
            $stmtJuz->execute([$bkid]);
            $maxJuz = (int)$stmtJuz->fetchColumn() ?: 1;

            $stmtPages = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid = ?");
            $stmtPages->execute([$bkid]);
            $totalPages = (int)$stmtPages->fetchColumn() ?: 0;

            $stmtUpdate = $pdo->prepare("UPDATE books SET pages = ? WHERE bkid = ?");
            $stmtUpdate->execute([$totalPages, $bkid]);

            // Get book title for log
            $stmtTitle = $pdo->prepare("SELECT title FROM books WHERE bkid = ?");
            $stmtTitle->execute([$bkid]);
            $title = $stmtTitle->fetchColumn() ?: 'Unknown';

            AuthHelper::logCrudHistory('IMPORT', 'books', (string)$bkid, "Impor kitab JSON selesai: {$title} | Paragraf/Halaman: {$totalPages}");
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal menyelesaikan import: ' . $e->getMessage()]);
        }
    }

    public function handleAdminGetHistory(): void {
        $pdo  = Database::getConnection();
        $req  = ResponseHelper::getJsonRequest();
        $page   = max(1, (int)($req['page']        ?? $_GET['page']        ?? 1));
        $limit  = min(100, max(5, (int)($req['per_page']  ?? $_GET['per_page']  ?? 20)));
        $action = trim($req['action']     ?? $_GET['action']     ?? '');
        $table  = trim($req['table_name'] ?? $_GET['table_name'] ?? '');
        $admin  = trim($req['admin_name'] ?? $_GET['admin_name'] ?? '');
    
        $where  = [];
        $params = [];
    
        if ($action) { $where[] = 'action = :action'; $params[':action'] = $action; }
        if ($table)  { $where[] = 'table_name = :table'; $params[':table'] = $table; }
        if ($admin)  { $where[] = 'admin_name LIKE :admin'; $params[':admin'] = "%{$admin}%"; }
    
        $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    
        // Total
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM crud_history {$whereStr}");
        foreach ($params as $k => $v) $cntStmt->bindValue($k, $v);
        $cntStmt->execute();
        $total = (int)$cntStmt->fetchColumn();
    
        // Rows
        $offset = ($page - 1) * $limit;
        $stmt   = $pdo->prepare(
            "SELECT id, admin_id, admin_name, admin_email, action, table_name, record_id, detail,
                    DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS created_at
             FROM crud_history
             {$whereStr}
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        echo json_encode([
            'success' => true,
            'rows'    => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'pages'   => (int)ceil($total / $limit),
        ]);
    }

    public function handleAdminGetActivity(): void {
        $pdo  = Database::getConnection();
        $req  = ResponseHelper::getJsonRequest();
        $page = max(1, (int)($req['page'] ?? $_GET['page'] ?? 1));
        $limit = min(100, max(5, (int)($req['per_page'] ?? $_GET['per_page'] ?? 20)));
        $event = trim($req['event'] ?? '');
        $query = trim($req['query'] ?? '');
        $date  = trim($req['date'] ?? '');
    
        $where = [];
        $params = [];
        if ($event) { $where[] = 'event = :event'; $params[':event'] = $event; }
        if ($query) {
            $where[] = '(event_data LIKE :query1 OR user_name LIKE :query2 OR user_email LIKE :query3 OR ip_address LIKE :query4)';
            $params[':query1'] = '%' . $query . '%';
            $params[':query2'] = '%' . $query . '%';
            $params[':query3'] = '%' . $query . '%';
            $params[':query4'] = '%' . $query . '%';
        }
        if ($date) { $where[] = 'DATE(created_at) = :date'; $params[':date'] = $date; }
    
        $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM user_activity_log {$whereStr}");
        foreach ($params as $k => $v) { $cntStmt->bindValue($k, $v); }
        $cntStmt->execute();
        $total = (int)$cntStmt->fetchColumn();
    
        $todayCount = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $weekCount  = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $topEvents  = $pdo->query(
            "SELECT event, COUNT(*) AS cnt FROM user_activity_log GROUP BY event ORDER BY cnt DESC LIMIT 10"
        )->fetchAll();
    
        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            "SELECT id, event, event_data, user_id, user_name, user_email, user_role,
                    ip_address, user_agent,
                    DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS created_at
             FROM user_activity_log
             {$whereStr}
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        echo json_encode([
            'success' => true,
            'rows'    => $stmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'pages'   => (int)ceil($total / $limit),
            'stats'   => [
                'today' => $todayCount,
                'week'  => $weekCount,
            ],
            'top_events' => $topEvents,
        ]);
    }

    public function handleAdminGetSearchLogs(): void {
        $pdo  = Database::getConnection();
        $req  = ResponseHelper::getJsonRequest();
        $page       = max(1, (int)($req['page']        ?? $_GET['page']        ?? 1));
        $limit      = min(100, max(5, (int)($req['per_page']  ?? $_GET['per_page']  ?? 25)));
        $searchType = trim($req['search_type'] ?? $_GET['search_type'] ?? '');
        $query      = trim($req['query']       ?? $_GET['query']       ?? '');
        $date       = trim($req['date']        ?? $_GET['date']        ?? '');
    
        $where  = [];
        $params = [];
    
        if ($searchType) { $where[] = 'search_type = :type';  $params[':type']  = $searchType; }
        if ($query)      { 
            $where[] = '(query LIKE :query1 OR visitor_ip LIKE :query2)'; 
            $params[':query1'] = "%{$query}%"; 
            $params[':query2'] = "%{$query}%"; 
        }
        if ($date)       { $where[] = 'DATE(created_at) = :date'; $params[':date'] = $date; }
    
        $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    
        // Total
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM search_logs {$whereStr}");
        foreach ($params as $k => $v) $cntStmt->bindValue($k, $v);
        $cntStmt->execute();
        $totalCount = (int)$cntStmt->fetchColumn();
    
        // Stats — hari ini & minggu ini (global, tidak dipengaruhi filter)
        $todayCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM search_logs WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();
        $weekCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM search_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();
        $uniqueCount = (int)$pdo->query(
            "SELECT COUNT(DISTINCT query) FROM search_logs"
        )->fetchColumn();
    
        // Top queries (global)
        $topQueries = $pdo->query(
            "SELECT query, COUNT(*) AS cnt FROM search_logs
             GROUP BY query ORDER BY cnt DESC LIMIT 10"
        )->fetchAll();
    
        // Rows
        $offset = ($page - 1) * $limit;
        $stmt   = $pdo->prepare(
            "SELECT id, search_type, query, query_detail, result_count,
                    visitor_ip, user_agent, user_name,
                    DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS created_at
             FROM search_logs
             {$whereStr}
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        $rows = $stmt->fetchAll();
        echo json_encode([
            'success'     => true,
            'rows'        => $rows,
            'total'       => $totalCount,
            'page'        => $page,
            'limit'       => $limit,
            'pages'       => (int)ceil($totalCount / $limit),
            'stats'       => [
                'today'  => $todayCount,
                'week'   => $weekCount,
                'unique' => $uniqueCount,
            ],
            'top_queries' => $topQueries,
        ]);
    }

    public function handleAdminGetAskLogs(): void {
        AuthHelper::requireAdmin();
        $pdo  = Database::getConnection();
        $req  = ResponseHelper::getJsonRequest();
        $page       = max(1, (int)($req['page']        ?? $_GET['page']        ?? 1));
        $limit      = min(100, max(5, (int)($req['per_page']  ?? $_GET['per_page']  ?? 25)));
        $query      = trim($req['query']       ?? $_GET['query']       ?? '');
        $date       = trim($req['date']        ?? $_GET['date']        ?? '');
    
        $where  = [];
        $params = [];
    
        if ($query)      { 
            $where[] = '(question LIKE :query1 OR visitor_ip LIKE :query2)'; 
            $params[':query1'] = "%{$query}%"; 
            $params[':query2'] = "%{$query}%"; 
        }
        if ($date)       { $where[] = 'DATE(created_at) = :date'; $params[':date'] = $date; }
    
        $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    
        // Total
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM ask_logs {$whereStr}");
        foreach ($params as $k => $v) $cntStmt->bindValue($k, $v);
        $cntStmt->execute();
        $totalCount = (int)$cntStmt->fetchColumn();
    
        // Stats
        $todayCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM ask_logs WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();
        $weekCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM ask_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();
        $uniqueCount = (int)$pdo->query(
            "SELECT COUNT(DISTINCT question) FROM ask_logs"
        )->fetchColumn();
    
        // Rows
        $offset = ($page - 1) * $limit;
        $stmt   = $pdo->prepare(
            "SELECT id, question, response, visitor_ip, user_name, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS created_at
             FROM ask_logs
             {$whereStr}
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        $rows = $stmt->fetchAll();
        echo json_encode([
            'success'     => true,
            'rows'        => $rows,
            'total'       => $totalCount,
            'page'        => $page,
            'limit'       => $limit,
            'pages'       => (int)ceil($totalCount / $limit),
            'stats'       => [
                'today'  => $todayCount,
                'week'   => $weekCount,
                'unique' => $uniqueCount,
            ]
        ]);
    }

    public function handleAdminDeleteSearchLog(): void {
        AuthHelper::requireAdmin();
        $pdo = Database::getConnection();
        $req = ResponseHelper::getJsonRequest();
        
        $id = isset($req['id']) ? (int)$req['id'] : 0;
        $query = isset($req['query']) ? trim($req['query']) : '';
        
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM search_logs WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($query !== '') {
            $stmt = $pdo->prepare("DELETE FROM search_logs WHERE LOWER(TRIM(query)) = LOWER(TRIM(?))");
            $stmt->execute([$query]);
        } else {
            ResponseHelper::json(['success' => false, 'error' => 'Parameter tidak lengkap.']);
            return;
        }
        
        ResponseHelper::json(['success' => true, 'message' => 'Log pencarian berhasil dihapus.']);
    }

    public function handleAdminGetDownloadLogs(): void {
        $pdo  = Database::getConnection();
        $req  = ResponseHelper::getJsonRequest();
        $page = max(1, (int)($req['page'] ?? $_GET['page'] ?? 1));
        $limit = min(100, max(5, (int)($req['per_page'] ?? $_GET['per_page'] ?? 25)));
        $bkid = isset($req['bkid']) ? (int)$req['bkid'] : (int)($_GET['bkid'] ?? 0);
        $query = trim($req['query'] ?? $_GET['query'] ?? '');
        $date = trim($req['date'] ?? $_GET['date'] ?? '');
    
        $where = [];
        $params = [];
        if ($bkid > 0) {
            $where[] = 'bkid = :bkid';
            $params[':bkid'] = $bkid;
        }
        if ($query !== '') {
            $where[] = '(book_title LIKE :query OR user_name LIKE :query OR user_email LIKE :query OR ip_address LIKE :query OR user_agent LIKE :query)';
            $params[':query'] = '%' . $query . '%';
        }
        if ($date !== '') {
            $where[] = 'DATE(created_at) = :date';
            $params[':date'] = $date;
        }
    
        $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    
        // Total
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM download_logs {$whereStr}");
        foreach ($params as $key => $value) {
            $cntStmt->bindValue($key, $value);
        }
        $cntStmt->execute();
        $totalCount = (int)$cntStmt->fetchColumn();
    
        // Stats
        $todayCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM download_logs WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();
        $weekCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM download_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();
        $uniqueCount = (int)$pdo->query(
            "SELECT COUNT(DISTINCT bkid) FROM download_logs"
        )->fetchColumn();
    
        // Top Downloads
        $topDownloads = $pdo->query(
            "SELECT bkid, book_title, source, COUNT(*) AS cnt FROM download_logs
             GROUP BY bkid, book_title, source ORDER BY cnt DESC LIMIT 10"
        )->fetchAll();
    
        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            "SELECT id, bkid, book_title, source, user_id, user_name, user_email, user_role,
                    ip_address, user_agent,
                    DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS created_at
             FROM download_logs
             {$whereStr}
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        echo json_encode([
            'success'       => true,
            'rows'          => $stmt->fetchAll(),
            'total'         => $totalCount,
            'page'          => $page,
            'limit'         => $limit,
            'pages'         => (int)ceil($totalCount / $limit),
            'stats'         => [
                'today'  => $todayCount,
                'week'   => $weekCount,
                'unique' => $uniqueCount,
            ],
            'top_downloads' => $topDownloads,
        ]);
    }

    public function handleAdminGetSubmissions(): void {
        $pdo    = Database::getConnection();
        $status = trim($_GET['status'] ?? '');
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;
    
        $where  = [];
        $params = [];
        if (in_array($status, ['pending','approved','rejected'], true)) {
            $where[]           = 'status = :status';
            $params[':status'] = $status;
        }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM file_submissions $whereSQL");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();
    
        $params[':limit']  = $limit;
        $params[':offset'] = $offset;
        $rows = $pdo->prepare(
            "SELECT id, user_name, user_email, file_name, file_type, category_name,
                    file_url, file_size, mime_type, description, status,
                    reviewer_name, review_note,
                    DATE_FORMAT(reviewed_at, '%d/%m/%Y %H:%i') AS reviewed_at,
                    DATE_FORMAT(created_at,  '%d/%m/%Y %H:%i') AS created_at
             FROM file_submissions $whereSQL
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') $rows->bindValue($k, $v, PDO::PARAM_INT);
            else $rows->bindValue($k, $v);
        }
        $rows->execute();
    
        echo json_encode([
            'data'  => $rows->fetchAll(),
            'total' => $total,
            'page'  => $page,
            'pages' => (int)ceil($total / $limit),
        ]);
    }

    public function handleAdminReviewSubmission(): void {
        $admin  = AuthHelper::getSessionUser();
        $pdo    = Database::getConnection();
        $req    = ResponseHelper::getJsonRequest();
        $id     = intval($req['id']            ?? 0);
        $action = trim($req['review_action']   ?? '');
        $note   = trim($req['note']            ?? '');
    
        if ($id <= 0 || !in_array($action, ['approve','reject'], true)) {
            http_response_code(400); echo json_encode(['error' => 'Parameter tidak valid.']); return;
        }

        $stmt = $pdo->prepare("SELECT * FROM file_submissions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $sub = $stmt->fetch();
        if (!$sub) {
            http_response_code(404); echo json_encode(['error' => 'Kiriman tidak ditemukan.']); return;
        }

        if ($sub['status'] !== 'pending') {
            http_response_code(400); echo json_encode(['error' => 'Kiriman ini sudah direview.']); return;
        }
    
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                "UPDATE file_submissions
                 SET status = :status, reviewed_by = :reviewed_by, reviewer_name = :reviewer_name,
                     review_note = :note, reviewed_at = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':status'        => $newStatus,
                ':reviewed_by'   => $admin['id'],
                ':reviewer_name' => $admin['name'],
                ':note'          => $note,
                ':id'            => $id,
            ]);

            // Notify user if exists
            if ($sub['user_id']) {
                $notifMsg = "Kiriman file Anda '{$sub['file_name']}' telah di-" . ($action === 'approve' ? 'setujui' : 'tolak') . ".\nCatatan Admin: {$note}";
                $insNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, reference_id) VALUES (?, ?, ?, 'submission', ?)");
                $insNotif->execute([$sub['user_id'], 'Review Kiriman File', $notifMsg, $id]);
            }
            if ($sub['user_email']) {
                $statusText = $action === 'approve' ? 'disetujui' : 'ditolak';
                $emailBody = "Halo,<br><br>Kiriman file Anda yang berjudul <b>{$sub['file_name']}</b> telah <b>{$statusText}</b> oleh admin.<br><br><b>Catatan Admin:</b><br>{$note}<br><br>Terima kasih.";
                \App\Helpers\MailHelper::sendNotification($sub['user_email'], 'Status Kiriman File Anda', $emailBody);
            }

            // Auto-import ke daftar kitab jika disetujui
            if ($action === 'approve') {
                $fileUrl = $sub['file_url'];
                $filePath = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $fileUrl;
                if (!file_exists($filePath)) {
                    $filePath = dirname(__DIR__, 2) . $fileUrl;
                }
                
                if (file_exists($filePath)) {
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $rawText = $this->extractTextFromFile($filePath);
                    
                    if (!mb_check_encoding($rawText, 'UTF-8')) {
                        $rawText = mb_convert_encoding($rawText, 'UTF-8', 'ISO-8859-1');
                    }

                    if (strlen(trim($rawText)) > 5) {
                        $pageTexts = [];
                        $paragraphs = preg_split('/\n{2,}/', trim($rawText));
                        if (!is_array($paragraphs)) {
                            $paragraphs = [trim($rawText)];
                        }
                        $buf = '';
                        foreach ($paragraphs as $para) {
                            $para = trim($para);
                            if ($para === '') continue;
                            if (strlen($buf) + strlen($para) > 3000 && $buf !== '') {
                                $pageTexts[] = trim($buf);
                                $buf = $para;
                            } else {
                                $buf .= ($buf ? "\n\n" : '') . $para;
                            }
                        }
                        if ($buf !== '') $pageTexts[] = trim($buf);
                        
                        if (!empty($pageTexts)) {
                            $title = pathinfo($sub['file_name'], PATHINFO_FILENAME);
                            $author = !empty($sub['user_name']) ? $sub['user_name'] : 'Tidak Diketahui';
                            
                            $ib = $pdo->prepare(
                                "INSERT INTO books (title, author, category_id, category_name, iso, pages)
                                 VALUES (:title, :author, :catid, :catname, 'ar', :pages)"
                            );
                            $ib->execute([
                                ':title'   => $title,
                                ':author'  => $author,
                                ':catid'   => $sub['category_id'] ?: null,
                                ':catname' => $sub['category_name'] ?: '',
                                ':pages'   => count($pageTexts),
                            ]);
                            $bkid = (int)$pdo->lastInsertId();

                            $ic = $pdo->prepare(
                                "INSERT INTO book_content (bkid, page, content) VALUES (:bkid, :page, :content)"
                            );
                            foreach ($pageTexts as $pageIndex => $pageContent) {
                                $ic->execute([
                                    ':bkid'    => $bkid,
                                    ':page'    => $pageIndex + 1,
                                    ':content' => $pageContent,
                                ]);
                                SearchHelper::syncContentToDictionary($pageContent);
                            }
                            
                            AuthHelper::logCrudHistory('IMPORT', 'books', (string)$bkid,
                                "Auto-impor dari kiriman #{$id}: {$title} | Penulis: {$author}"
                            );
                        }
                    }
                }
            }

            $pdo->commit();
            AuthHelper::logCrudHistory($action === 'approve' ? 'APPROVE_SUBMISSION' : 'REJECT_SUBMISSION', 'file_submissions', (string)$id, $note);
            echo json_encode(['success' => true, 'status' => $newStatus]);
        } catch (\Exception $e) {
            $pdo->rollBack();
            http_response_code(500); 
            echo json_encode(['error' => 'Gagal memproses review: ' . $e->getMessage()]);
        }
    }

    public function handleAdminGetSubmissionContent(): void {
        try {
            $pdo = Database::getConnection();
            $id  = (int)($_GET['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID wajib diisi.']); return; }

            $stmt = $pdo->prepare("SELECT file_url FROM file_submissions WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $fileUrl = $stmt->fetchColumn();
            if (!$fileUrl) { http_response_code(404); echo json_encode(['error' => 'File tidak ditemukan.']); return; }

            $filePath = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $fileUrl;
            if (!file_exists($filePath)) {
                $filePath = dirname(__DIR__, 2) . $fileUrl;
            }
            if (!file_exists($filePath)) {
                http_response_code(404); echo json_encode(['error' => 'File fisik tidak ditemukan.']); return;
            }

            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $rawText = $this->extractTextFromFile($filePath);
            
            $rawText = (string)$rawText;
            if (function_exists('mb_check_encoding') && !mb_check_encoding($rawText, 'UTF-8')) {
                $rawText = mb_convert_encoding($rawText, 'UTF-8', 'ISO-8859-1');
            }

            $pageTexts = [];
            if (strlen(trim($rawText)) > 0) {
                $paragraphs = preg_split('/\n{2,}/', trim($rawText));
                if (!is_array($paragraphs)) {
                    $paragraphs = [trim($rawText)];
                }
                $buf = '';
                foreach ($paragraphs as $para) {
                    $para = trim($para);
                    if ($para === '') continue;
                    if (strlen($buf) + strlen($para) > 3000 && $buf !== '') {
                        $pageTexts[] = trim($buf);
                        $buf = $para;
                    } else {
                        $buf .= ($buf ? "\n\n" : '') . $para;
                    }
                }
                if ($buf !== '') $pageTexts[] = trim($buf);
            }

            echo json_encode(['success' => true, 'pages' => $pageTexts, 'content' => $rawText]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'API Error: ' . $e->getMessage() . ' on line ' . $e->getLine()]);
        }
    }

    public function handleAdminDeleteSubmission(): void {
        $admin = AuthHelper::getSessionUser();
        $pdo   = Database::getConnection();
        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $id    = (int)($data['id'] ?? 0);

        if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID wajib diisi.']); return; }

        $stmt = $pdo->prepare("SELECT file_name, file_url FROM file_submissions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404); echo json_encode(['error' => 'Kiriman tidak ditemukan.']); return;
        }

        // Hapus file fisik
        $filePath = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $row['file_url'];
        if (!file_exists($filePath)) {
            $filePath = dirname(__DIR__, 2) . $row['file_url'];
        }
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $pdo->prepare("DELETE FROM file_submissions WHERE id = :id")->execute([':id' => $id]);
        AuthHelper::logCrudHistory('DELETE', 'file_submissions', (string)$id, "Hapus kiriman: {$row['file_name']}");

        echo json_encode(['success' => true]);
    }

    public function handleAdminGetRequests(): void {
        AuthHelper::requireAdmin();
        $pdo    = Database::getConnection();
        $status = trim($_GET['status'] ?? '');
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where  = [];
        $params = [];
        if (in_array($status, ['pending','fulfilled','rejected'], true)) {
            $where[]           = 'status = :status';
            $params[':status'] = $status;
        }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM kitab_requests $whereSQL");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;
        $stmt = $pdo->prepare(
            "SELECT * FROM kitab_requests
             $whereSQL
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        echo json_encode([
            'data'  => $rows,
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]);
    }

    public function handleAdminUpdateRequestStatus(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo   = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id     = intval($input['id'] ?? 0);
        $status = trim($input['status'] ?? '');

        if ($id <= 0 || !in_array($status, ['pending','fulfilled','rejected'], true)) {
            http_response_code(400); echo json_encode(['error' => 'Input tidak valid.']); return;
        }

        $stmt = $pdo->prepare("UPDATE kitab_requests SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);

        AuthHelper::logCrudHistory('UPDATE', 'kitab_requests', (string)$id, "Update status menjadi {$status} oleh {$admin['name']}");

        echo json_encode(['success' => true]);
    }

    public function handleAdminDeleteRequest(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo   = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id    = intval($input['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400); echo json_encode(['error' => 'Input tidak valid.']); return;
        }

        $stmt = $pdo->prepare("DELETE FROM kitab_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);

        AuthHelper::logCrudHistory('DELETE', 'kitab_requests', (string)$id, "Delete request oleh {$admin['name']}");

        echo json_encode(['success' => true]);
    }
    public function handleAdminGetFeedbacks(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();
        
        $stmt = $pdo->query("SELECT * FROM feedbacks ORDER BY created_at DESC");
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $feedbacks]);
    }

    public function handleAdminUpdateFeedbackStatus(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = intval($input['id'] ?? 0);
        $status = $input['status'] ?? '';

        if (!$id || !in_array($status, ['pending', 'read', 'resolved'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            return;
        }

        $stmt = $pdo->prepare("UPDATE feedbacks SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);

        AuthHelper::logCrudHistory('UPDATE', 'feedbacks', (string)$id, "Update status menjadi {$status} oleh {$admin['name']}");

        echo json_encode(['success' => true]);
    }

    public function handleAdminDeleteFeedback(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = intval($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM feedbacks WHERE id = :id");
        $stmt->execute([':id' => $id]);

        AuthHelper::logCrudHistory('DELETE', 'feedbacks', (string)$id, "Hapus feedback oleh {$admin['name']}");

        echo json_encode(['success' => true]);
    }

    public function handleAdminReplyFeedback(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = intval($input['id'] ?? 0);
        $reply = trim($input['reply'] ?? '');

        if (!$id || !$reply) {
            http_response_code(400);
            echo json_encode(['error' => 'ID dan balasan wajib diisi']);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM feedbacks WHERE id = ?");
        $stmt->execute([$id]);
        $fb = $stmt->fetch();
        if (!$fb) {
            http_response_code(404);
            echo json_encode(['error' => 'Feedback tidak ditemukan']);
            return;
        }

        $upd = $pdo->prepare("UPDATE feedbacks SET admin_reply = ?, status = 'resolved' WHERE id = ?");
        $upd->execute([$reply, $id]);

        if ($fb['user_id']) {
            $insNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, reference_id) VALUES (?, ?, ?, 'feedback', ?)");
            $insNotif->execute([$fb['user_id'], 'Balasan Feedback', "Admin membalas feedback Anda:\n{$reply}", $id]);
        }
        if ($fb['email']) {
            $emailBody = "Halo,<br><br>Admin telah membalas feedback Anda:<br><br><i>\"{$fb['content']}\"</i><br><br><b>Balasan Admin:</b><br>{$reply}<br><br>Terima kasih.";
            \App\Helpers\MailHelper::sendNotification($fb['email'], 'Balasan Feedback Anda', $emailBody);
        }

        AuthHelper::logCrudHistory('UPDATE', 'feedbacks', (string)$id, "Membalas feedback oleh {$admin['name']}");
        echo json_encode(['success' => true]);
    }

    public function handleAdminReplyRequest(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = intval($input['id'] ?? 0);
        $reply = trim($input['reply'] ?? '');

        if (!$id || !$reply) {
            http_response_code(400);
            echo json_encode(['error' => 'ID dan balasan wajib diisi']);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM kitab_requests WHERE id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) {
            http_response_code(404);
            echo json_encode(['error' => 'Request tidak ditemukan']);
            return;
        }

        $upd = $pdo->prepare("UPDATE kitab_requests SET admin_reply = ? WHERE id = ?");
        $upd->execute([$reply, $id]);

        if ($req['user_id']) {
            $insNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, reference_id) VALUES (?, ?, ?, 'request', ?)");
            $insNotif->execute([$req['user_id'], 'Balasan Request Kitab', "Admin membalas request kitab Anda '{$req['title']}':\n{$reply}", $id]);
        }
        if ($req['user_email']) {
            $emailBody = "Halo,<br><br>Admin telah memberikan catatan pada request kitab Anda yang berjudul <b>{$req['title']}</b>:<br><br><b>Catatan Admin:</b><br>{$reply}<br><br>Terima kasih.";
            \App\Helpers\MailHelper::sendNotification($req['user_email'], 'Update Request Kitab Anda', $emailBody);
        }

        AuthHelper::logCrudHistory('UPDATE', 'kitab_requests', (string)$id, "Membalas request oleh {$admin['name']}");
        echo json_encode(['success' => true]);
    }

    public function handleAdminReplySubmission(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = intval($input['id'] ?? 0);
        $reply = trim($input['reply'] ?? '');

        if (!$id || !$reply) {
            http_response_code(400);
            echo json_encode(['error' => 'ID dan balasan wajib diisi']);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM file_submissions WHERE id = ?");
        $stmt->execute([$id]);
        $sub = $stmt->fetch();
        if (!$sub) {
            http_response_code(404);
            echo json_encode(['error' => 'Submission tidak ditemukan']);
            return;
        }

        // Just append or set the review_note
        $newNote = $sub['review_note'] ? $sub['review_note'] . "\n\nAdmin Reply:\n" . $reply : $reply;

        $upd = $pdo->prepare("UPDATE file_submissions SET review_note = ? WHERE id = ?");
        $upd->execute([$newNote, $id]);

        if ($sub['user_id']) {
            $insNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, reference_id) VALUES (?, ?, ?, 'submission', ?)");
            $insNotif->execute([$sub['user_id'], 'Pesan Tambahan Admin', "Admin mengirim pesan terkait kiriman Anda '{$sub['file_name']}':\n{$reply}", $id]);
        }
        if ($sub['user_email']) {
            $emailBody = "Halo,<br><br>Admin telah memberikan pesan/catatan tambahan pada kiriman file Anda yang berjudul <b>{$sub['file_name']}</b>:<br><br><b>Pesan Admin:</b><br>{$reply}<br><br>Terima kasih.";
            \App\Helpers\MailHelper::sendNotification($sub['user_email'], 'Pesan Kiriman File Anda', $emailBody);
        }

        AuthHelper::logCrudHistory('UPDATE', 'file_submissions', (string)$id, "Membalas submission oleh {$admin['name']}");
        echo json_encode(['success' => true]);
    }

    public function handleAdminGetToc(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();
        $bkid = (int)($_GET['bkid'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM book_toc WHERE bkid = ? ORDER BY juz ASC, page ASC, id ASC");
            $stmt->execute([$bkid]);
            echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            echo json_encode([]);
        }
    }

    public function handleAdminSaveToc(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();
        $input = json_decode(file_get_contents('php://input'), true);
        $bkid = (int)($input['bkid'] ?? 0);
        $items = $input['items'] ?? [];

        if (!$bkid) {
            http_response_code(400);
            echo json_encode(['error' => 'ID kitab wajib diisi']);
            return;
        }

        try {
            $pdo->beginTransaction();
            // Clear existing TOC for this book
            $del = $pdo->prepare("DELETE FROM book_toc WHERE bkid = ?");
            $del->execute([$bkid]);

            if (!empty($items)) {
                $ins = $pdo->prepare("INSERT INTO book_toc (bkid, title, juz, page, level) VALUES (?, ?, ?, ?, ?)");
                foreach ($items as $item) {
                    $title = trim($item['title'] ?? '');
                    if ($title === '') continue;
                    $ins->execute([
                        $bkid,
                        $title,
                        (int)($item['juz'] ?? 1),
                        (int)($item['page'] ?? 1),
                        (int)($item['level'] ?? 1)
                    ]);
                }
            }
            $pdo->commit();
            AuthHelper::logCrudHistory('UPDATE', 'book_toc', (string)$bkid, "Update Daftar Isi Kitab oleh {$admin['name']}");
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function handleAdminGenerateToc(): void {
        $admin = AuthHelper::requireAdmin();
        $pdo = Database::getConnection();
        $input = json_decode(file_get_contents('php://input'), true);
        $bkid = (int)($input['bkid'] ?? 0);
        if (!$bkid) {
            http_response_code(400);
            echo json_encode(['error' => 'ID kitab wajib diisi']);
            return;
        }

        try {
            $stmt = $pdo->prepare("SELECT page, juz, content FROM book_content WHERE bkid = ? ORDER BY juz ASC, page ASC, id ASC");
            $stmt->execute([$bkid]);
            $pages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $tocItems = [];
            foreach ($pages as $p) {
                // normalize newlines
                $normalized = str_replace(['\r', '\n'], ["\r", "\n"], $p['content']);
                $lines = preg_split('/[\r\n]+/', $normalized);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (mb_strlen($line) >= 3 && mb_strlen($line) <= 80) {
                        if (strpos($line, '.') === false && strpos($line, '،') === false && strpos($line, '؟') === false && strpos($line, '@') === false && strpos($line, 'صفحة') === false && strpos($line, 'بسم الله الرحمن الرحيم') === false) {
                            // Exclude lines that don't have at least one real word (2+ letters) or look like pure page numbers
                            if (preg_match('/[a-zA-Z\p{Arabic}]{2,}/u', $line) && !preg_match('/^[-_@\s]*ص?\s*\d+\s*[-_@\s]*$/u', $line)) {
                                // Extract traditional headings as well as potential standard short lines if they look good
                            if (preg_match('/^(كتاب|باب|فصل|مقدمة|خاتمة|المبحث|المطلب|القسم|تنبيه|فائدة|مسألة)/u', $line) || 
                                (mb_strlen($line) <= 60 && strpos($line, ' ') !== false && mb_substr($line, -1) !== ':')) {
                                $tocItems[] = [
                                    'title' => mb_substr($line, 0, 200),
                                    'juz' => $p['juz'],
                                    'page' => $p['page'],
                                    'level' => preg_match('/^(كتاب|باب)/u', $line) ? 1 : 2
                                ];
                            }
                            }
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $tocItems]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
