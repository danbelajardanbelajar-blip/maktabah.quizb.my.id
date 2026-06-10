<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use PDO;
use Exception;

class AdminController {
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
    
        $rawText = '';
        if ($origExt === 'docx') {
            // python-docx (single-quoted shell command to avoid PHP string interpolation issues)
            $pyArg = escapeshellarg($tmpCopy);
            $pyCmd = 'python3 -c \'import docx,sys; d=docx.Document(' . $pyArg . '); print("\\n".join(p.text for p in d.paragraphs))\' 2>/dev/null';
            $out = shell_exec($pyCmd);
            if (!$out) {
                // fallback: unzip + grep XML
                $out = shell_exec('unzip -p ' . $pyArg . ' word/document.xml 2>/dev/null | sed \'s/<[^>]*>//g\' | grep -v \'^$\'');
            }
            $rawText = (string)$out;
        } else {
            // antiword untuk .doc
            $out = shell_exec('antiword ' . escapeshellarg($tmpCopy) . ' 2>/dev/null');
            if (!$out) {
                $out = shell_exec('catdoc ' . escapeshellarg($tmpCopy) . ' 2>/dev/null');
            }
            $rawText = (string)$out;
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
                "INSERT INTO book_content (bkid, page, content) VALUES (:bkid, :page, :content)"
            );
            foreach ($pages as $pageIndex => $pageContent) {
                $insertContent->execute([
                    ':bkid'    => $bkid,
                    ':page'    => $pageIndex + 1,
                    ':content' => $pageContent,
                ]);
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
        if ($query)      { $where[] = 'query LIKE :query';     $params[':query'] = "%{$query}%"; }
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
    
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM download_logs {$whereStr}");
        foreach ($params as $key => $value) {
            $cntStmt->bindValue($key, $value);
        }
        $cntStmt->execute();
        $totalCount = (int)$cntStmt->fetchColumn();
    
        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            "SELECT id, bkid, book_title, user_id, user_name, user_email, user_role,
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
            'success' => true,
            'rows'    => $stmt->fetchAll(),
            'total'   => $totalCount,
            'page'    => $page,
            'limit'   => $limit,
            'pages'   => (int)ceil($totalCount / $limit),
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

            // Auto-import ke daftar kitab jika disetujui
            if ($action === 'approve') {
                $fileUrl = $sub['file_url'];
                $filePath = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $fileUrl;
                if (!file_exists($filePath)) {
                    $filePath = dirname(__DIR__, 2) . $fileUrl;
                }
                
                if (file_exists($filePath)) {
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $rawText = '';
                    if ($ext === 'docx') {
                        $pyArg = escapeshellarg($filePath);
                        $pyCmd = 'python3 -c \'import docx,sys; d=docx.Document(' . $pyArg . '); print("\\n".join(p.text for p in d.paragraphs))\' 2>/dev/null';
                        $out = shell_exec($pyCmd);
                        if (!$out) {
                            $out = shell_exec('unzip -p ' . $pyArg . ' word/document.xml 2>/dev/null | sed \'s/<[^>]*>//g\' | grep -v \'^$\'');
                        }
                        $rawText = (string)$out;
                    } elseif ($ext === 'doc') {
                        $out = shell_exec('antiword ' . escapeshellarg($filePath) . ' 2>/dev/null');
                        if (!$out) {
                            $out = shell_exec('catdoc ' . escapeshellarg($filePath) . ' 2>/dev/null');
                        }
                        $rawText = (string)$out;
                    } elseif ($ext === 'pdf') {
                        $out = shell_exec('pdftotext ' . escapeshellarg($filePath) . ' - 2>/dev/null');
                        if (!$out) {
                            $pyCmd = 'python3 -c \'import PyPDF2,sys; r=PyPDF2.PdfReader(' . escapeshellarg($filePath) . '); print("\\n".join(p.extract_text() for p in r.pages if p.extract_text()))\' 2>/dev/null';
                            $out = shell_exec($pyCmd);
                        }
                        $rawText = (string)$out;
                    } else {
                        $rawText = (string)file_get_contents($filePath);
                    }
                    
                    if (!mb_check_encoding($rawText, 'UTF-8')) {
                        $rawText = mb_convert_encoding($rawText, 'UTF-8', 'ISO-8859-1');
                    }

                    if (strlen(trim($rawText)) > 5) {
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
        $rawText = '';
        if ($ext === 'docx') {
            $pyArg = escapeshellarg($filePath);
            $pyCmd = 'python3 -c \'import docx,sys; d=docx.Document(' . $pyArg . '); print("\\n".join(p.text for p in d.paragraphs))\' 2>/dev/null';
            $out = shell_exec($pyCmd);
            if (!$out) {
                $out = shell_exec('unzip -p ' . $pyArg . ' word/document.xml 2>/dev/null | sed \'s/<[^>]*>//g\' | grep -v \'^$\'');
            }
            $rawText = (string)$out;
        } elseif ($ext === 'doc') {
            $out = shell_exec('antiword ' . escapeshellarg($filePath) . ' 2>/dev/null');
            if (!$out) {
                $out = shell_exec('catdoc ' . escapeshellarg($filePath) . ' 2>/dev/null');
            }
            $rawText = (string)$out;
        } elseif ($ext === 'pdf') {
            $out = shell_exec('pdftotext ' . escapeshellarg($filePath) . ' - 2>/dev/null');
            if (!$out) {
                $pyCmd = 'python3 -c \'import PyPDF2,sys; r=PyPDF2.PdfReader(' . escapeshellarg($filePath) . '); print("\\n".join(p.extract_text() for p in r.pages if p.extract_text()))\' 2>/dev/null';
                $out = shell_exec($pyCmd);
            }
            $rawText = (string)$out;
        } else {
            $rawText = file_get_contents($filePath);
        }
        
        $rawText = (string)$rawText;
        if (!mb_check_encoding($rawText, 'UTF-8')) {
            $rawText = mb_convert_encoding($rawText, 'UTF-8', 'ISO-8859-1');
        }

        $pageTexts = [];
        if (strlen(trim($rawText)) > 0) {
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
        }

        echo json_encode(['success' => true, 'pages' => $pageTexts, 'content' => $rawText]);
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

}
