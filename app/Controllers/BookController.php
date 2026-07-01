<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use PDO;
use Exception;

class BookController {
    public function handleBooks(): void {
        $pdo    = Database::getConnection();
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(48, max(1, (int)($_GET['limit'] ?? 24)));
        $offset = ($page - 1) * $limit;
        $catId  = isset($_GET['cat']) && $_GET['cat'] !== '' ? (int)$_GET['cat'] : null;
    
        $where  = $catId !== null ? 'WHERE b.category_id = :cat' : '';
        $params = $catId !== null ? [':cat' => $catId] : [];
    
        // total count
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM books b $where");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
    
        // rows
        $sql  = "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name
                 FROM books b $where
                 ORDER BY b.bkid DESC
                 LIMIT :lim OFFSET :off";
        $stmt = $pdo->prepare($sql);
        if ($catId !== null) $stmt->bindValue(':cat', $catId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':off',  $offset, PDO::PARAM_INT);
        $stmt->execute();
        $books = $stmt->fetchAll();
    
        echo json_encode([
            'data'        => $books,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ]);
    }

    public function handleBook(): void {
        $pdo  = Database::getConnection();
        $id   = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid id.']); return; }
    
        $stmt = $pdo->prepare(
            "SELECT b.*, c.name AS cat_name
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             WHERE b.bkid = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $book = $stmt->fetch();
    
        if (!$book) { http_response_code(404); echo json_encode(['error' => 'Kitab not found.']); return; }

        // Increment views safely (ignore if fail)
        try {
            $pdo->prepare("UPDATE books SET views = views + 1 WHERE bkid = :id")->execute([':id' => $id]);
        } catch (\Exception $e) {
            try {
                $this->ensureViewsColumn();
                $pdo->prepare("UPDATE books SET views = views + 1 WHERE bkid = :id")->execute([':id' => $id]);
            } catch (\Exception $e2) {
                // Ignore gracefully so book loading continues
            }
        }

        // Ambil daftar juz beserta jumlah halaman masing-masing
        // Kolom juz sudah diisi oleh fill_juz.php (SMALLINT, default 1)
        $juzStmt = $pdo->prepare(
            "SELECT juz, COUNT(*) AS pages
             FROM book_content
             WHERE bkid = :id
             GROUP BY juz
             ORDER BY juz ASC"
        );
        $juzStmt->execute([':id' => $id]);
        $juzRows = $juzStmt->fetchAll();
    
        $juzList = array_map(fn($r) => [
            'juz'   => (int)$r['juz'],
            'pages' => (int)$r['pages'],
        ], $juzRows);
    
        $book['juz_list']      = $juzList;
        $book['total_juz']     = count($juzList);
        $book['content_pages'] = array_sum(array_column($juzList, 'pages'));
    
        echo json_encode(['data' => $book]);
    }

    public function handleDownloadBook(): void {
        $pdo = Database::getConnection();
        $id  = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid book id.']);
            return;
        }
    
        // ── Metadata kitab ────────────────────────────────────────
        $stmt = $pdo->prepare(
            "SELECT b.title, b.author, b.iso, c.name AS cat_name
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             WHERE b.bkid = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $book = $stmt->fetch();
        if (!$book) {
            http_response_code(404);
            echo json_encode(['error' => 'Kitab tidak ditemukan.']);
            return;
        }
    
        // ── Ambil seluruh konten, dikelompokkan per juz ───────────
        $contentStmt = $pdo->prepare(
            "SELECT juz, page, content
             FROM book_content
             WHERE bkid = :id
             ORDER BY juz ASC, id ASC"
        );
        $contentStmt->execute([':id' => $id]);
        $allRows = $contentStmt->fetchAll();
    
        if (empty($allRows)) {
            http_response_code(404);
            echo json_encode(['error' => 'Konten kitab tidak tersedia.']);
            return;
        }
    
        $this->logDownloadBook($id, $book['title'] ?? 'Kitab tanpa judul');
    
        // Kelompokkan baris per juz: [ juzNumber => [ row, row, ... ] ]
        $byJuz = [];
        foreach ($allRows as $row) {
            $byJuz[(int)$row['juz']][] = $row;
        }
        ksort($byJuz);
    
        $totalJuz    = count($byJuz);
        $title       = trim($book['title'] ?: 'kitab');
        $baseFilename = $this->normalizeDownloadFilename($title);
        $isArabic    = (bool)preg_match('/\p{Arabic}/u', $title);
    
        if (function_exists('loadComposerAutoloader')) {
            loadComposerAutoloader();
        }
        $hasPhpWord = class_exists('\PhpOffice\PhpWord\PhpWord');
    
        // ── KASUS 1: Satu juz → download DOCX langsung ───────────
        if ($totalJuz === 1) {
            $pageMeta  = reset($byJuz);
            $juzNumber = (int)array_key_first($byJuz);
    
            if ($hasPhpWord) {
                $phpWord = $this->buildJuzDocx($book, $pageMeta, $juzNumber, 1, $isArabic);
                $filename = $baseFilename . '.docx';
    
                // Bersihkan output buffer yang mungkin mengandung whitespace / karakter sampah
                while (ob_get_level()) {
                    @ob_end_clean();
                }

                header_remove('Content-Type');
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header('Content-Disposition: ' . $this->contentDispositionHeader($filename));
                header('Cache-Control: no-store, must-revalidate');
    
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
                $writer->save('php://output');
                return;
            }
    
            // Fallback TXT
            $pages = array_column($pageMeta, 'content');
            $this->fallbackDownloadTxt($book, $pages);
            return;
        }
    
        // ── KASUS 2: Multi-juz → ZIP berisi N file DOCX ──────────
        if (!class_exists('\ZipArchive')) {
            // ZipArchive tidak tersedia: fallback satu TXT gabungan
            $this->fallbackDownloadMultiJuzTxt($book, $byJuz, $baseFilename);
            return;
        }
    
        // Prefix unik untuk semua temp file sesi ini
        $tmpDir    = sys_get_temp_dir();
        $tmpPrefix = 'maktabah_' . $id . '_' . uniqid('', true);
        $zipPath   = $tmpDir . DIRECTORY_SEPARATOR . $tmpPrefix . '.zip';
        $zip       = new \ZipArchive();
    
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal membuat file ZIP.']);
            return;
        }
    
        // Catat semua temp DOCX agar bisa dihapus setelah readfile()
        $tempDocxFiles = [];
    
        foreach ($byJuz as $juzNumber => $pageMeta) {
            if ($hasPhpWord) {
                // Bangun DOCX untuk juz ini, simpan ke temp file
                $phpWord  = $this->buildJuzDocx($book, $pageMeta, $juzNumber, $totalJuz, $isArabic);
                $juzLabel = $isArabic ? 'الجزء_' . $juzNumber : 'Juz_' . $juzNumber;
                $docxName = $baseFilename . '_' . $juzLabel . '.docx';          // nama di dalam ZIP
                $tmpDocx  = $tmpDir . DIRECTORY_SEPARATOR . $tmpPrefix . '_juz' . $juzNumber . '.docx';
    
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
                $writer->save($tmpDocx);
    
                $zip->addFile($tmpDocx, $docxName);
                $tempDocxFiles[] = $tmpDocx;    // tandai untuk dihapus nanti
            } else {
                // Fallback: file TXT per juz langsung ke string (tidak perlu temp file)
                $juzLabel = 'Juz_' . $juzNumber;
                $txtName  = $baseFilename . '_' . $juzLabel . '.txt';
                $pages    = array_column($pageMeta, 'content');
                $content  = $this->buildJuzTxtContent($book, $pages, $juzNumber, $totalJuz);
                $zip->addFromString($txtName, $content);
            }
        }
    
        // Tambahkan README singkat di root ZIP
        $readmeLines = $isArabic
            ? [
                'الكتاب: ' . ($book['title'] ?: '—'),
                'المؤلف: ' . ($book['author'] ?: '—'),
                'عدد الأجزاء: ' . $totalJuz,
                '',
                'تم إنشاء هذا الملف بواسطة مكتبة السنية',
              ]
            : [
                'Judul    : ' . ($book['title'] ?: '—'),
                'Penulis  : ' . ($book['author'] ?: '—'),
                'Jumlah Juz: ' . $totalJuz,
                '',
                'File ini dibuat oleh Al-Maktabah As-Sunniyyah',
              ];
        $zip->addFromString('README.txt', implode("\r\n", $readmeLines));
    
        // Tutup ZIP agar file di-flush ke disk sebelum dibaca
        $zip->close();
    
        // ── Kirim ZIP ke browser ──────────────────────────────────
        $zipFilename = $baseFilename . '.zip';
        // Bersihkan output buffer yang mungkin mengandung whitespace / karakter sampah
        while (ob_get_level()) {
            @ob_end_clean();
        }

        header_remove('Content-Type');
        header('Content-Type: application/zip');
        header('Content-Disposition: ' . $this->contentDispositionHeader($zipFilename));
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-store, must-revalidate');
    
        readfile($zipPath);
    
        // ── Bersihkan semua temp file setelah dikirim ─────────────
        @unlink($zipPath);
        foreach ($tempDocxFiles as $f) {
            @unlink($f);
        }
    }

    public function handleContent(): void {
        $pdo  = Database::getConnection();
        $bkid = (int)($_GET['bkid'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $juz  = max(1, (int)($_GET['juz']  ?? 1));
        $contentId = (int)($_GET['content_id'] ?? 0);
    
        // Resolve content_id to bkid, juz, and its page offset
        if ($contentId > 0) {
            $st = $pdo->prepare("SELECT bkid, juz FROM book_content WHERE id = :id");
            $st->execute([':id' => $contentId]);
            if ($r = $st->fetch()) {
                $bkid = (int)$r['bkid'];
                $juz  = (int)$r['juz'];
                $stOff = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid = :b AND juz = :j AND id <= :id");
                $stOff->execute([':b' => $bkid, ':j' => $juz, ':id' => $contentId]);
                $page = (int)$stOff->fetchColumn();
            }
        }
    
        if ($bkid <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid bkid.']); return; }
    
        // Ambil total juz untuk buku ini
        $juzTotal = $pdo->prepare(
            "SELECT COUNT(DISTINCT juz) FROM book_content WHERE bkid = :bkid"
        );
        $juzTotal->execute([':bkid' => $bkid]);
        $totalJuz = (int)$juzTotal->fetchColumn();
    
        // Pastikan juz tidak melebihi yang tersedia
        $juz = min($juz, max(1, $totalJuz));
    
        // Ambil halaman ke-N dalam juz yang dipilih (ORDER BY id ASC agar urutan insert terjaga)
        $stmt = $pdo->prepare(
            "SELECT bc.page, bc.content
             FROM book_content bc
             WHERE bc.bkid = :bkid AND bc.juz = :juz
             ORDER BY bc.id ASC
             LIMIT 1 OFFSET :off"
        );
        $stmt->bindValue(':bkid', $bkid, PDO::PARAM_INT);
        $stmt->bindValue(':juz',  $juz,  PDO::PARAM_INT);
        $stmt->bindValue(':off',  $page - 1, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
    
        // Total halaman dalam juz ini
        $ct = $pdo->prepare(
            "SELECT COUNT(*) FROM book_content WHERE bkid = :bkid AND juz = :juz"
        );
        $ct->execute([':bkid' => $bkid, ':juz' => $juz]);
        $total = (int)$ct->fetchColumn();
    
        echo json_encode([
            'bkid'        => $bkid,
            'juz'         => $juz,
            'total_juz'   => $totalJuz,
            'page'        => $page,
            'total_pages' => $total,
            'page_number' => $row ? (int)$row['page'] : null,
            'content'     => $row ? $row['content'] : null,
        ]);
    }

    public function handleLatest(): void {
        $pdo   = Database::getConnection();
        $limit = min(48, max(1, (int)($_GET['limit'] ?? 12)));
    
        $stmt = $pdo->prepare(
            "SELECT bkid, title, author, pages, iso, category_id, category_name
             FROM books
             ORDER BY bkid DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
    
        echo json_encode(['data' => $stmt->fetchAll()]);
    }

    /**
     * Buat nama file yang aman untuk download.
     *
     * • Karakter Arab/Unicode DIPERTAHANKAN (tidak diganti '_').
     * • Karakter yang tidak aman untuk filesystem (/, \, :, *, ?, ", <, >, |, null)
     *   diganti dengan '_'.
     * • Spasi beruntun → satu spasi.
     * • Fallback ke 'kitab' bila hasilnya kosong.
     * • Maks 150 karakter (cukup untuk judul Arab panjang).
     */
    public function normalizeDownloadFilename(string $title): string {
        $title = trim($title);
        // Ganti karakter yang tidak aman di filesystem — biarkan Unicode/Arab lewat
        $safe  = preg_replace('/[\/\\\\:*?"<>|\x00]/', '_', $title);
        // Kolaps spasi/underscore beruntun
        $safe  = preg_replace('/[\s_]+/u', '_', $safe);
        $safe  = trim($safe, '_');
        if ($safe === '') {
            $safe = 'kitab';
        }
        return mb_substr($safe, 0, 150, 'UTF-8');
    }

    /**
     * Buat header Content-Disposition yang benar untuk nama file UTF-8 (RFC 5987).
     *
     * Browser modern mengikuti parameter filename* (RFC 5987).
     * Browser lama mendapat fallback filename ASCII.
     */
    public function contentDispositionHeader(string $filename): string {
        // Fallback ASCII: ganti semua non-ASCII dengan '_'
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename);
        // RFC 5987 encoded
        $encoded = rawurlencode($filename);
        // Kirim keduanya — browser memilih yang terbaik
        return "attachment; filename=\"{$ascii}\"; filename*=UTF-8''{$encoded}";
    }

    public function fallbackDownloadTxt(array $book, array $pages): void {
        $title    = trim($book['title'] ?: 'kitab');
        $filename = $this->normalizeDownloadFilename($title) . '.txt';
    
        $lines   = [];
        $lines[] = $book['title'] ?: 'Kitab tanpa judul';
        if (!empty($book['author'])) {
            $lines[] = 'Pengarang: ' . $book['author'];
        }
        if (!empty($book['cat_name'])) {
            $lines[] = 'Kategori: ' . $book['cat_name'];
        }
        $lines[] = '';
        foreach ($pages as $idx => $content) {
            $lines[] = '--- Halaman ' . ($idx + 1) . ' ---';
            $lines[] = $content;
            if ($idx < count($pages) - 1) {
                $lines[] = '';
            }
        }
    
        header_remove('Content-Type');
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: ' . $this->contentDispositionHeader($filename));
        echo implode("\n", $lines);
    }

    public function fallbackDownloadMultiJuzTxt(array $book, array $byJuz, string $baseFilename): void {
        $filename = $baseFilename . '_all_juz.txt';
        $lines = [];
        $lines[] = $book['title'] ?: 'Kitab tanpa judul';
        if (!empty($book['author'])) {
            $lines[] = 'Pengarang: ' . $book['author'];
        }
        $lines[] = 'Total Juz: ' . count($byJuz);
        $lines[] = '';

        foreach ($byJuz as $juzNumber => $pageMeta) {
            $lines[] = '======================================';
            $lines[] = 'JUZ ' . $juzNumber;
            $lines[] = '======================================';
            $pages = array_column($pageMeta, 'content');
            foreach ($pages as $idx => $content) {
                $lines[] = '--- Halaman ' . ($idx + 1) . ' ---';
                $lines[] = $content;
                $lines[] = '';
            }
        }

        header_remove('Content-Type');
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: ' . $this->contentDispositionHeader($filename));
        echo implode("\n", $lines);
    }

    public function buildJuzTxtContent(array $book, array $pages, int $juzNumber, int $totalJuz): string {
        $lines = [];
        $lines[] = $book['title'] ?: 'Kitab tanpa judul';
        if (!empty($book['author'])) {
            $lines[] = 'Pengarang: ' . $book['author'];
        }
        $lines[] = 'Juz: ' . $juzNumber . ' dari ' . $totalJuz;
        $lines[] = '';
        foreach ($pages as $idx => $content) {
            $lines[] = '--- Halaman ' . ($idx + 1) . ' ---';
            $lines[] = $content;
            if ($idx < count($pages) - 1) {
                $lines[] = '';
            }
        }
        return implode("\n", $lines);
    }

    public function buildJuzDocx(array $book, array $pageMeta, int $juzNumber, int $totalJuz, bool $isArabic) {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Fungsi pembersih karakter terlarang XML (mencegah corrupt DOCX)
        $sanitize = function($text) {
            // Pastikan UTF-8
            $text = mb_convert_encoding($text ?? '', 'UTF-8', 'UTF-8');
            // Decode HTML entities yang sudah ada agar tidak double-encode
            $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            // Hapus tag HTML
            $text = strip_tags($text);
            // Buang karakter kontrol (0x00 - 0x1F) kecuali tab/newline/cr
            $text = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $text);
            // Escape karakter khusus menjadi entitas XML (& menjadi &amp;, < menjadi &lt;, dst.)
            // Ini WAJIB karena PhpWord secara default tidak meng-escape teks ke dalam w:t
            return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };
        
        // Set default font
        $phpWord->setDefaultFontName($isArabic ? 'Traditional Arabic' : 'Arial');
        $phpWord->setDefaultFontSize(14);
        
        $section = $phpWord->addSection([
            'orientation' => 'portrait',
            'marginTop' => 600,
            'marginRight' => 600,
            'marginBottom' => 600,
            'marginLeft' => 600,
        ]);
        
        $pStyle = ['alignment' => 'both'];
        if ($isArabic) {
            $pStyle['bidi'] = true;
        }
        
        $fontStyleTitle = ['bold' => true, 'size' => 16];
        if ($isArabic) { $fontStyleTitle['rtl'] = true; }
        
        $fontStyleNormal = ['size' => 14];
        if ($isArabic) { $fontStyleNormal['rtl'] = true; }
        
        // Header
        $title = $sanitize($book['title'] ?: 'Kitab tanpa judul');
        $author = $book['author'] ? $sanitize('Pengarang: ' . $book['author']) : '';
        $juzInfo = 'Juz: ' . $juzNumber . ' dari ' . $totalJuz;
        
        $section->addText($title, $fontStyleTitle, $pStyle);
        if ($author) {
            $section->addText($author, $fontStyleNormal, $pStyle);
        }
        $section->addText($juzInfo, $fontStyleNormal, $pStyle);
        $section->addText('======================================', $fontStyleNormal, $pStyle);
        $section->addTextBreak(1);
        
        // Content
        $pages = array_column($pageMeta, 'content');
        foreach ($pages as $idx => $content) {
            $section->addText('--- Halaman ' . ($idx + 1) . ' ---', ['bold' => true], $pStyle);
            
            $lines = explode("\n", str_replace("\r", "", $content));
            foreach ($lines as $line) {
                $line = $sanitize($line);
                if (trim($line) !== '') {
                    // PhpWord menggunakan internal escaper, tapi kita pastikan stringnya aman
                    $section->addText($line, $fontStyleNormal, $pStyle);
                } else {
                    $section->addTextBreak(1);
                }
            }
            $section->addTextBreak(1);
        }
        
        return $phpWord;
    }

    public function logDownloadBook(int $bkid, string $title): void {
        try {
            $this->ensureDownloadLogsTable();
            $pdo = Database::getConnection();
            $user = AuthHelper::getSessionUser();
            $ip   = $_SERVER['HTTP_X_FORWARDED_FOR']
                    ?? $_SERVER['HTTP_X_REAL_IP']
                    ?? $_SERVER['REMOTE_ADDR']
                    ?? '';
            $ip = trim(explode(',', $ip)[0]);
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    
            $stmt = $pdo->prepare(
                "INSERT INTO download_logs
                 (bkid, book_title, user_id, user_name, user_email, user_role, ip_address, user_agent)
                 VALUES (:bkid, :title, :uid, :uname, :uemail, :urole, :ip, :ua)"
            );
            $stmt->execute([
                ':bkid'   => $bkid,
                ':title'  => mb_substr($title, 0, 255),
                ':uid'    => $user['id']    ?? null,
                ':uname'  => $user['name']  ?? '',
                ':uemail' => $user['email'] ?? '',
                ':urole'  => $user['role']  ?? 'user',
                ':ip'     => $ip,
                ':ua'     => $ua,
            ]);
        } catch (\Exception $e) {
            // ignore logging failures
        }
    }

    public function ensureDownloadLogsTable(): void {
        try {
            $pdo = Database::getConnection();
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS download_logs (
                   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   bkid INT UNSIGNED NOT NULL,
                   book_title VARCHAR(255) NOT NULL DEFAULT '',
                   user_id INT UNSIGNED NULL,
                   user_name VARCHAR(255) NOT NULL DEFAULT '',
                   user_email VARCHAR(255) NOT NULL DEFAULT '',
                   user_role ENUM('user','admin') NOT NULL DEFAULT 'user',
                   ip_address VARCHAR(45) NOT NULL DEFAULT '',
                   user_agent VARCHAR(512) NOT NULL DEFAULT '',
                   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                   INDEX idx_bkid (bkid),
                   INDEX idx_user_id (user_id),
                   INDEX idx_created_at (created_at)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Exception $e) {
            // ignore creation failures
        }
    }


    private function ensureViewsColumn(): void {
        $pdo = Database::getConnection();
        try {
            $pdo->query("SELECT views FROM books LIMIT 1");
        } catch (\Exception $e) {
            $pdo->exec("ALTER TABLE books ADD COLUMN views INT NOT NULL DEFAULT 0");
        }
    }

    public function handlePopularBooks(): void {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                "SELECT b.bkid, b.title, b.author, b.pages, b.iso, c.name AS category_name
                 FROM books b
                 LEFT JOIN categories c ON c.id = b.category_id
                 ORDER BY b.views DESC, b.bkid DESC
                 LIMIT 5"
            );
            $stmt->execute();
            $books = $stmt->fetchAll();
            echo json_encode(['data' => $books]);
        } catch (\Exception $e) {
            echo json_encode(['data' => []]);
        }
    }

    public function handleGetBookToc(): void {
        header('Cache-Control: public, max-age=300');
        $pdo = Database::getConnection();
        $bkid = (int)($_GET['bkid'] ?? 0);
        
        if ($bkid <= 0) {
            echo json_encode([]);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM book_toc WHERE bkid = :bkid ORDER BY page ASC, id ASC");
            $stmt->execute([':bkid' => $bkid]);
            $toc = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode($toc);
        } catch (\Exception $e) {
            // Table might not exist yet
            echo json_encode([]);
        }
    }
}

