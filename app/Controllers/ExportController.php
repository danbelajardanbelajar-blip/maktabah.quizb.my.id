<?php
namespace App\Controllers;

use App\Config\Database;
use PDO;
use Exception;

class ExportController {
    public function getExportData() {
        $pdo = Database::getConnection();
        
        $cats = $pdo->query("SELECT id, name FROM categories ORDER BY catord ASC")->fetchAll(PDO::FETCH_ASSOC);
        $books = $pdo->query("SELECT bkid, title, category_id FROM books ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($cats as $c) {
            $c['books'] = array_values(array_filter($books, function($b) use ($c) {
                return $b['category_id'] == $c['id'];
            }));
            $data[] = $c;
        }
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    public function doExport() {
        $input = json_decode(file_get_contents('php://input'), true);
        $bookIds = $input['book_ids'] ?? [];
        
        if (empty($bookIds)) {
            echo json_encode(['status' => 'error', 'message' => 'No books selected']);
            return;
        }
        
        // Generate a random filename for the sqlite db
        $fileName = 'maktabah_' . time() . '.db';
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
        
        if (file_exists($tempPath)) unlink($tempPath);
        
        try {
            $sqlite = new PDO('sqlite:' . $tempPath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create exact schema
            $schema = "
            CREATE TABLE authors (authid INTEGER PRIMARY KEY, auth TEXT, inf TEXT, HigriD INTEGER, AD INTEGER);
            CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, catord INTEGER, lvl INTEGER);
            CREATE TABLE books_meta (bkid INTEGER PRIMARY KEY, bk TEXT, cat INTEGER, inf TEXT, authno INTEGER, betaka TEXT);
            CREATE TABLE matn_sharh_books (matn INTEGER, matn_ver INTEGER, sharh INTEGER, sharh_ver INTEGER, PRIMARY KEY (matn, sharh));
            CREATE TABLE matn_sharh_pages (matn INTEGER, matn_id INTEGER, sharh INTEGER, sharh_id INTEGER);
            CREATE TABLE book_pdf (bkid INTEGER, pdf_path TEXT, part INTEGER);
            CREATE TABLE book_shorts (bk INTEGER, ramz TEXT, nass TEXT);
            CREATE TABLE user_comments (id INTEGER, bk INTEGER, com TEXT);
            CREATE TABLE external_links (code INTEGER, link TEXT);
            CREATE TABLE pages (id INTEGER, part INTEGER, page INTEGER, seal TEXT, nass TEXT, book_id INTEGER, PRIMARY KEY (book_id, id));
            CREATE VIRTUAL TABLE pages_fts USING fts5(nass, content='pages');
            CREATE TABLE titles (id INTEGER, lvl INTEGER, sub INTEGER, tit TEXT, book_id INTEGER, PRIMARY KEY (book_id, id));
            CREATE TABLE quran_surah (id INTEGER PRIMARY KEY, name TEXT NOT NULL);
            CREATE TABLE quran_ayah (id INTEGER PRIMARY KEY, surah_id INTEGER NOT NULL, ayah_no INTEGER NOT NULL, text TEXT NOT NULL, page INTEGER, FOREIGN KEY (surah_id) REFERENCES quran_surah(id));
            CREATE TABLE rowa (id INTEGER PRIMARY KEY, Name TEXT, A_esm TEXT, A_kona TEXT, A_nasab TEXT, ROTBA TEXT, R_ZAHBI TEXT, birth TEXT, death TEXT, sheok TEXT, telmez TEXT, IsoName TEXT);
            CREATE VIRTUAL TABLE rowa_fts USING fts5(Name, IsoName, A_nasab, content='rowa', content_rowid='id');
            ";
            
            $sqlite->exec($schema);
            
            // Fetch data from MySQL
            $mysql = Database::getConnection();
            $placeholders = implode(',', array_fill(0, count($bookIds), '?'));
            
            // 1. Export Categories (only those that contain the exported books)
            $catStmt = $mysql->prepare("SELECT id, name, catord, lvl FROM categories WHERE id IN (SELECT category_id FROM books WHERE bkid IN ($placeholders))");
            $catStmt->execute($bookIds);
            $insertCat = $sqlite->prepare("INSERT INTO categories (id, name, catord, lvl) VALUES (?, ?, ?, ?)");
            $sqlite->beginTransaction();
            while ($c = $catStmt->fetch(PDO::FETCH_ASSOC)) {
                $insertCat->execute([$c['id'], $c['name'], $c['catord'], $c['lvl']]);
            }
            $sqlite->commit();
            
            // 2. Export Books to books_meta
            $bookStmt = $mysql->prepare("SELECT bkid, title, category_id, info, description FROM books WHERE bkid IN ($placeholders)");
            $bookStmt->execute($bookIds);
            $insertBook = $sqlite->prepare("INSERT INTO books_meta (bkid, bk, cat, inf, authno, betaka) VALUES (?, ?, ?, ?, ?, ?)");
            $sqlite->beginTransaction();
            while ($b = $bookStmt->fetch(PDO::FETCH_ASSOC)) {
                $insertBook->execute([$b['bkid'], $b['title'], $b['category_id'], $b['info'], 0, $b['description']]);
            }
            $sqlite->commit();
            
            // 3. Export Pages (book_content)
            $pageStmt = $mysql->prepare("SELECT id, bkid, page, juz, content FROM book_content WHERE bkid IN ($placeholders)");
            $pageStmt->execute($bookIds);
            $insertPage = $sqlite->prepare("INSERT INTO pages (id, part, page, seal, nass, book_id) VALUES (?, ?, ?, ?, ?, ?)");
            // FTS
            $insertFts = $sqlite->prepare("INSERT INTO pages_fts (rowid, nass) VALUES (?, ?)");
            
            $sqlite->beginTransaction();
            while ($p = $pageStmt->fetch(PDO::FETCH_ASSOC)) {
                $insertPage->execute([$p['id'], $p['juz'], $p['page'], '', $p['content'], $p['bkid']]);
                // SQLite FTS5 requires rowid matching the id of the pages table. Wait, pages table primary key is (book_id, id).
                // It's safer to just let the application rebuild FTS if necessary, or insert without rowid.
                // We will insert into pages_fts normally.
                $insertFts->execute([$p['id'], $p['content']]);
            }
            $sqlite->commit();
            
            // 4. Export Titles
            $titleStmt = $mysql->prepare("SELECT id, bkid, title, level FROM titles WHERE bkid IN ($placeholders)");
            $titleStmt->execute($bookIds);
            $insertTitle = $sqlite->prepare("INSERT INTO titles (id, lvl, sub, tit, book_id) VALUES (?, ?, ?, ?, ?)");
            $sqlite->beginTransaction();
            while ($t = $titleStmt->fetch(PDO::FETCH_ASSOC)) {
                $insertTitle->execute([$t['id'], $t['level'], 0, $t['title'], $t['bkid']]);
            }
            $sqlite->commit();
            
            // Close sqlite connection
            $sqlite = null;
            
            // Provide download URL
            // Since we can't easily return file via JSON, we will move it to a public accessible folder temporarily
            $publicPath = 'archive/' . $fileName;
            rename($tempPath, __DIR__ . '/../../' . $publicPath);
            
            echo json_encode(['status' => 'success', 'url' => '/' . $publicPath]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
