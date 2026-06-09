<?php
// =============================================================
//  Al-Maktabah As-Sunniyyah — REST-like JSON API
// =============================================================

require_once __DIR__ . '/koneksi.php';

// Session untuk auth check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CORS & Headers ---
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($payload === false) {
        $payload = json_encode(['error' => 'JSON encoding failed'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    echo $payload;
}

function getJsonRequest(): array {
    static $json = null;
    if ($json !== null) {
        return $json;
    }
    $input = file_get_contents('php://input');
    if ($input === false || trim($input) === '') {
        return $json = [];
    }
    $decoded = json_decode($input, true);
    return $json = is_array($decoded) ? $decoded : [];
}

function getComposerAutoloadPath(): ?string {
    $candidates = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/vendor/autoload.php',
        dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/vendor/autoload.php',
    ];
    foreach ($candidates as $path) {
        if (!$path) {
            continue;
        }
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function loadComposerAutoloader(): bool {
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $autoload = getComposerAutoloadPath();
    if ($autoload) {
        require_once $autoload;
        return $loaded = true;
    }
    return $loaded = false;
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
function normalizeDownloadFilename(string $title): string {
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
function contentDispositionHeader(string $filename): string {
    // Fallback ASCII: ganti semua non-ASCII dengan '_'
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename);
    // RFC 5987 encoded
    $encoded = rawurlencode($filename);
    // Kirim keduanya — browser memilih yang terbaik
    return "attachment; filename=\"{$ascii}\"; filename*=UTF-8''{$encoded}";
}

function fallbackDownloadTxt(array $book, array $pages): void {
    $title    = trim($book['title'] ?: 'kitab');
    $filename = normalizeDownloadFilename($title) . '.txt';

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
    header('Content-Disposition: ' . contentDispositionHeader($filename));
    echo implode("\n", $lines);
}

// ── Auth helpers ──────────────────────────────────────────────
function getSessionUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireAdmin(): void {
    $user = getSessionUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Akses ditolak. Diperlukan hak admin.']);
        exit;
    }
}

function requireLogin(): void {
    $user = getSessionUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Anda harus login terlebih dahulu.']);
        exit;
    }
}

// =============================================================
// CRUD HISTORY — helper untuk mencatat setiap perubahan admin
// =============================================================
function logCrudHistory(string $action, string $tableName, string $recordId, string $detail = ''): void {
    try {
        $pdo  = getPDO();
        $user = getSessionUser();
        $pdo->prepare(
            "INSERT INTO crud_history (admin_id, admin_name, admin_email, action, table_name, record_id, detail)
             VALUES (:admin_id, :admin_name, :admin_email, :action, :table_name, :record_id, :detail)"
        )->execute([
            ':admin_id'    => $user['id']    ?? null,
            ':admin_name'  => $user['name']  ?? 'Unknown',
            ':admin_email' => $user['email'] ?? '',
            ':action'      => $action,
            ':table_name'  => $tableName,
            ':record_id'   => $recordId,
            ':detail'      => $detail,
        ]);
    } catch (\Exception $e) {
        // Logging gagal tidak boleh mengganggu operasi utama
    }
}

// =============================================================
// SEARCH LOG — helper untuk mencatat query pencarian pengunjung
// =============================================================
function logSearchQuery(
    string $type,           // 'basic' | 'advanced'
    string $query,          // teks query utama
    int    $resultCount,    // jumlah hasil
    string $queryDetail = '' // JSON detail untuk pencarian lanjutan
): void {
    try {
        $pdo      = getPDO();
        $user     = getSessionUser();
        $ip       = $_SERVER['HTTP_X_FORWARDED_FOR']
                    ?? $_SERVER['HTTP_X_REAL_IP']
                    ?? $_SERVER['REMOTE_ADDR']
                    ?? '';
        // Ambil IP pertama jika ada beberapa (proxy chain)
        $ip = trim(explode(',', $ip)[0]);
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

        $pdo->prepare(
            "INSERT INTO search_logs
             (search_type, query, query_detail, result_count, visitor_ip, user_agent, user_id, user_name)
             VALUES (:type, :query, :detail, :count, :ip, :ua, :uid, :uname)"
        )->execute([
            ':type'  => $type,
            ':query' => mb_substr($query, 0, 1000),
            ':detail' => $queryDetail ?: null,
            ':count' => $resultCount,
            ':ip'    => $ip,
            ':ua'    => $ua,
            ':uid'   => $user['id']   ?? null,
            ':uname' => $user['name'] ?? '',
        ]);
    } catch (\Exception $e) {
        // Logging gagal tidak boleh mengganggu operasi utama
    }
}

function logUserActivity(string $event, string $eventData = ''): void {
    try {
        $pdo  = getPDO();
        $user = getSessionUser();
        $ip   = $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['HTTP_X_REAL_IP']
                ?? $_SERVER['REMOTE_ADDR']
                ?? '';
        $ip = trim(explode(',', $ip)[0]);
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

        $stmt = $pdo->prepare(
            "INSERT INTO user_activity_log
             (user_id, user_name, user_email, user_role, event, event_data, ip_address, user_agent)
             VALUES (:uid, :uname, :uemail, :urole, :event, :edata, :ip, :ua)"
        );
        $stmt->execute([
            ':uid'    => $user['id']    ?? null,
            ':uname'  => $user['name']  ?? '',
            ':uemail' => $user['email'] ?? '',
            ':urole'  => $user['role']  ?? 'user',
            ':event'  => $event,
            ':edata'  => $eventData !== '' ? $eventData : null,
            ':ip'     => $ip,
            ':ua'     => $ua,
        ]);
    } catch (\Exception $e) {
        // Silently ignore logging errors
    }
}

function ensureDownloadLogsTable(): void {
    try {
        $pdo = getPDO();
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

function logDownloadBook(int $bkid, string $title): void {
    try {
        ensureDownloadLogsTable();
        $pdo = getPDO();
        $user = getSessionUser();
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

// Helper — escape FULLTEXT boolean wildcards safely
function ftEscape(string $q): string {
    // Strip chars that could break boolean mode, keep Arabic/Latin safely
    return preg_replace('/[+\-><()~*"@]+/', ' ', $q);
}

function isPhraseQuery(string $q): bool {
    return preg_match('/^"(.+)"$/u', trim($q)) === 1;
}

function searchPhraseText(string $q): string {
    $q = trim($q);
    if (isPhraseQuery($q)) {
        return trim(substr($q, 1, -1));
    }
    return $q;
}

function booleanSearchTerm(string $q): string {
    $q = trim($q);
    if ($q === '') return '';
    
    // Jika sengaja diapit kutip, jadikan phrase search
    if (isPhraseQuery($q)) {
        return '"' . ftEscape(searchPhraseText($q)) . '"';
    }

    $terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    $parts = [];
    foreach ($terms as $term) {
        $clean = ftEscape($term);
        if ($clean === '') continue;
        
        // Jika kata terlalu pendek (<=2 huruf), jangan paksakan wajib ada (+)
        // karena jika MySQL innodb_ft_min_token_size = 3, kata pendek tidak di-index.
        // Jika dipaksa '+', maka seluruh query boolean akan gagal.
        if (mb_strlen($clean) <= 2) {
            $parts[] = $clean . '*';
        } else {
            $parts[] = '+' . $clean . '*';
        }
    }

    return implode(' ', $parts);
}

function booleanSearchTermForAdvanced(string $q): string {
    return booleanSearchTerm($q);
}

function booleanQueryFromFieldsOr(array $fields): string {
    $parts = [];
    foreach ($fields as $field) {
        $field = trim($field);
        if ($field === '') continue;
        
        if (isPhraseQuery($field)) {
            $parts[] = '"' . ftEscape(searchPhraseText($field)) . '"';
            continue;
        }

        $terms = preg_split('/\s+/u', $field, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($terms as $term) {
            $clean = ftEscape($term);
            if ($clean !== '') {
                $parts[] = $clean . '*';
            }
        }
    }
    return implode(' ', $parts);
}

function booleanSearchQueryFromFields(array $fields): string {
    $parts = [];
    foreach ($fields as $field) {
        $term = booleanSearchTermForAdvanced($field);
        if ($term !== '') {
            $parts[] = $term;
        }
    }
    return implode(' ', $parts);
}

// --- Router ---
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'books':             handleBooks();            break;
        case 'book':              handleBook();             break;
        case 'download_book':     handleDownloadBook();     break;
        case 'content':           handleContent();          break;
        case 'categories':        handleCategories();       break;
        case 'latest':            handleLatest();           break;
        case 'recent_searches':   handleRecentSearches();   break;
        case 'stats':             handleStats();            break;
        // Search — tiga endpoint terpisah untuk parallel fetch
        case 'search_categories':       handleSearchCategories();     break;
        case 'search_books':            handleSearchBooks();          break;
        case 'search_content':          handleSearchContent();        break;
        case 'search_advanced':         handleSearchAdvanced();       break;
        // Per-book progressive search (ringan, dipakai di home)
        case 'search_books_with_content': handleSearchBooksWithContent(); break;
        case 'search_content_in_book':    handleSearchContentInBook();    break;
        // Auth
        case 'auth_me':           handleAuthMe();           break;
        case 'log_activity':       handleLogActivity();      break;
        // Admin — CRUD Kitab
        case 'admin_save_book':    requireAdmin(); handleAdminSaveBook();    break;
        case 'admin_delete_book':  requireAdmin(); handleAdminDeleteBook();  break;
        // Admin — CRUD Kategori
        case 'admin_save_category':   requireAdmin(); handleAdminSaveCategory();   break;
        case 'admin_delete_category': requireAdmin(); handleAdminDeleteCategory(); break;
        // Admin — CRUD Konten Kitab
        case 'admin_save_content':   requireAdmin(); handleAdminSaveContent();   break;
        case 'admin_delete_content': requireAdmin(); handleAdminDeleteContent(); break;
        // Admin — Import Word
        case 'admin_import_book':     requireAdmin(); handleAdminImportBook();     break;
        // Admin — CRUD History
        case 'admin_get_history':     requireAdmin(); handleAdminGetHistory();     break;
        case 'admin_get_activity':    requireAdmin(); handleAdminGetActivity();    break;
        // Admin — Search Logs
        case 'admin_get_search_logs': requireAdmin(); handleAdminGetSearchLogs();  break;
        case 'admin_get_download_logs': requireAdmin(); handleAdminGetDownloadLogs();  break;
        // File Submissions
        // Allow anonymous submissions (require email) — no login required
        case 'submit_file':              handleSubmitFile();              break;
        case 'admin_get_submissions':    requireAdmin(); handleAdminGetSubmissions();    break;
        case 'admin_review_submission':  requireAdmin(); handleAdminReviewSubmission();  break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown action.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// =============================================================
// 1. BOOKS — paginated catalog with optional category filter
// =============================================================
function handleBooks(): void {
    $pdo    = getPDO();
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

// =============================================================
// 2. BOOK DETAIL — single kitab by bkid
// =============================================================
function handleBook(): void {
    $pdo  = getPDO();
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

// =============================================================
// DOWNLOAD — helper: bangun satu PhpWord object untuk satu juz
// =============================================================

/**
 * Bangun PhpWord untuk satu juz (atau seluruh kitab bila hanya 1 juz).
 *
 * @param array  $book       Metadata kitab (title, author, cat_name, iso)
 * @param array  $pageMeta   Baris konten juz ini: [['juz'=>N,'page'=>X,'content'=>'...']]
 * @param int    $juzNumber  Nomor juz (1-based)
 * @param int    $totalJuz   Total juz kitab ini
 * @param bool   $isArabic   Apakah kitab berbahasa Arab
 * @return \PhpOffice\PhpWord\PhpWord
 */
function buildJuzDocx(
    array $book,
    array $pageMeta,
    int   $juzNumber,
    int   $totalJuz,
    bool  $isArabic
): \PhpOffice\PhpWord\PhpWord {
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $phpWord->setDefaultFontName($isArabic ? 'Traditional Arabic' : 'Arial');
    $phpWord->setDefaultFontSize(12);

    // ── Section ──────────────────────────────────────────────
    $sectionStyle = [
        'pageSizeW'   => 12240,
        'pageSizeH'   => 15840,
        'orientation' => 'portrait',
    ];
    if ($isArabic) {
        $sectionStyle['marginLeft']  = 1080;
        $sectionStyle['marginRight'] = 1440;
    }
    $section = $phpWord->addSection($sectionStyle);

    // ── Paragraph & font styles ───────────────────────────────
    $pStyleBase = $isArabic
        ? ['alignment' => 'right', 'bidi' => true]
        : ['alignment' => 'left'];

    $fontTitle   = ['name' => $isArabic ? 'Traditional Arabic' : 'Arial', 'size' => 18, 'bold' => true];
    $fontMeta    = ['name' => $isArabic ? 'Traditional Arabic' : 'Arial', 'size' => 11, 'italic' => true];
    $fontContent = ['name' => $isArabic ? 'Traditional Arabic' : 'Arial', 'size' => $isArabic ? 14 : 12];
    $fontPageHd  = array_merge($fontContent, ['bold' => true]);
    $fontJuzHd   = array_merge($fontContent, ['bold' => true, 'size' => $isArabic ? 16 : 14]);

    // ── Halaman judul ─────────────────────────────────────────
    $titleText = htmlspecialchars($book['title'] ?: 'Kitab tanpa judul', ENT_XML1, 'UTF-8');
    // Tambahkan keterangan juz di judul bila multi-juz
    if ($totalJuz > 1) {
        $juzSuffix = $isArabic
            ? ' — الجزء ' . $juzNumber
            : " — Juz {$juzNumber}";
        $titleText .= htmlspecialchars($juzSuffix, ENT_XML1, 'UTF-8');
    }
    $section->addText($titleText, $fontTitle, $pStyleBase);

    if (!empty($book['author'])) {
        $authorLabel = $isArabic ? 'المؤلف: ' : 'Pengarang: ';
        $section->addText(
            htmlspecialchars($authorLabel . $book['author'], ENT_XML1, 'UTF-8'),
            $fontMeta, $pStyleBase
        );
    }
    if (!empty($book['cat_name'])) {
        $catLabel = $isArabic ? 'التصنيف: ' : 'Kategori: ';
        $section->addText(
            htmlspecialchars($catLabel . $book['cat_name'], ENT_XML1, 'UTF-8'),
            $fontMeta, $pStyleBase
        );
    }
    $section->addTextBreak(1);

    // ── Konten halaman ────────────────────────────────────────
    foreach ($pageMeta as $idx => $row) {
        // Heading halaman
        $pageLabel = $isArabic
            ? 'صفحة ' . $row['page']
            : '--- Halaman ' . $row['page'] . ' ---';
        $section->addText(
            htmlspecialchars($pageLabel, ENT_XML1, 'UTF-8'),
            $fontPageHd, $pStyleBase
        );

        // Konten baris per baris
        $lines = preg_split('/\r\n|\r|\n/', trim($row['content']));
        if ($lines === false || count($lines) === 0) {
            $section->addText('', $fontContent, $pStyleBase);
        } else {
            foreach ($lines as $line) {
                $section->addText(
                    htmlspecialchars($line, ENT_XML1, 'UTF-8'),
                    $fontContent, $pStyleBase
                );
            }
        }

        if ($idx < count($pageMeta) - 1) {
            $section->addPageBreak();
        }
    }

    return $phpWord;
}

// =============================================================
// DOWNLOAD — handler utama
// =============================================================
function handleDownloadBook(): void {
    $pdo = getPDO();
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

    logDownloadBook($id, $book['title'] ?? 'Kitab tanpa judul');

    // Kelompokkan baris per juz: [ juzNumber => [ row, row, ... ] ]
    $byJuz = [];
    foreach ($allRows as $row) {
        $byJuz[(int)$row['juz']][] = $row;
    }
    ksort($byJuz);

    $totalJuz    = count($byJuz);
    $title       = trim($book['title'] ?: 'kitab');
    $baseFilename = normalizeDownloadFilename($title);
    $isArabic    = (bool)preg_match('/\p{Arabic}/u', $title);

    loadComposerAutoloader();
    $hasPhpWord = class_exists('\PhpOffice\PhpWord\PhpWord');

    // ── KASUS 1: Satu juz → download DOCX langsung ───────────
    if ($totalJuz === 1) {
        $pageMeta  = reset($byJuz);
        $juzNumber = (int)array_key_first($byJuz);

        if ($hasPhpWord) {
            $phpWord = buildJuzDocx($book, $pageMeta, $juzNumber, 1, $isArabic);
            $filename = $baseFilename . '.docx';

            header_remove('Content-Type');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: ' . contentDispositionHeader($filename));
            header('Cache-Control: no-store, must-revalidate');

            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
            return;
        }

        // Fallback TXT
        $pages = array_column($pageMeta, 'content');
        fallbackDownloadTxt($book, $pages);
        return;
    }

    // ── KASUS 2: Multi-juz → ZIP berisi N file DOCX ──────────
    if (!class_exists('ZipArchive')) {
        // ZipArchive tidak tersedia: fallback satu TXT gabungan
        fallbackDownloadMultiJuzTxt($book, $byJuz, $baseFilename);
        return;
    }

    // Prefix unik untuk semua temp file sesi ini
    $tmpDir    = sys_get_temp_dir();
    $tmpPrefix = 'maktabah_' . $id . '_' . uniqid('', true);
    $zipPath   = $tmpDir . DIRECTORY_SEPARATOR . $tmpPrefix . '.zip';
    $zip       = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal membuat file ZIP.']);
        return;
    }

    // Catat semua temp DOCX agar bisa dihapus setelah readfile()
    $tempDocxFiles = [];

    foreach ($byJuz as $juzNumber => $pageMeta) {
        if ($hasPhpWord) {
            // Bangun DOCX untuk juz ini, simpan ke temp file
            $phpWord  = buildJuzDocx($book, $pageMeta, $juzNumber, $totalJuz, $isArabic);
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
            $content  = buildJuzTxtContent($book, $pages, $juzNumber, $totalJuz);
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
    header_remove('Content-Type');
    header('Content-Type: application/zip');
    header('Content-Disposition: ' . contentDispositionHeader($zipFilename));
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-store, must-revalidate');

    readfile($zipPath);

    // ── Bersihkan semua temp file setelah dikirim ─────────────
    @unlink($zipPath);
    foreach ($tempDocxFiles as $f) {
        @unlink($f);
    }
}

// =============================================================
// DOWNLOAD — fallback: multi-juz sebagai TXT dalam ZIP
// (bila ZipArchive tidak tersedia, kirim semua dalam satu TXT)
// =============================================================
function buildJuzTxtContent(array $book, array $pages, int $juzNumber, int $totalJuz): string {
    $lines   = [];
    $lines[] = $book['title'] ?: 'Kitab tanpa judul';
    if (!empty($book['author'])) {
        $lines[] = 'Pengarang: ' . $book['author'];
    }
    if ($totalJuz > 1) {
        $lines[] = "Juz: {$juzNumber} dari {$totalJuz}";
    }
    $lines[] = '';
    foreach ($pages as $idx => $content) {
        $lines[] = '--- Halaman ' . ($idx + 1) . ' ---';
        $lines[] = $content;
        $lines[] = '';
    }
    return implode("\n", $lines);
}

function fallbackDownloadMultiJuzTxt(array $book, array $byJuz, string $baseFilename): void {
    // Gabungkan semua juz dalam satu TXT bila tidak ada ZipArchive
    $allPages = [];
    foreach ($byJuz as $pageMeta) {
        foreach ($pageMeta as $row) {
            $allPages[] = $row['content'];
        }
    }
    fallbackDownloadTxt($book, $allPages);
}



// =============================================================
// 3. BOOK CONTENT — one page at a time, juz-aware
//    GET params: bkid, page (posisi dalam juz), juz (nomor juz, default 1)
// =============================================================
function handleContent(): void {
    $pdo  = getPDO();
    $bkid = (int)($_GET['bkid'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $juz  = max(1, (int)($_GET['juz']  ?? 1));

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

// =============================================================
// 5. CATEGORIES
// =============================================================
function handleCategories(): void {
    $pdo  = getPDO();
    $stmt = $pdo->query(
        "SELECT c.id, c.name, c.catord, c.lvl,
                COUNT(b.bkid) AS book_count
         FROM categories c
         LEFT JOIN books b ON b.category_id = c.id
         GROUP BY c.id
         ORDER BY c.catord ASC, c.name ASC"
    );
    echo json_encode(['data' => $stmt->fetchAll()]);
}

// =============================================================
// 6a. SEARCH — KATEGORI  (fast: simple LIKE on small table)
// =============================================================

// =============================================================
// 5a. LATEST — kitab terbaru (untuk halaman beranda)
// =============================================================
function handleLatest(): void {
    $pdo   = getPDO();
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

// =============================================================
// 5b. RECENT SEARCHES — kata kunci pencarian terbaru (publik)
// =============================================================
function handleRecentSearches(): void {
    header('Cache-Control: public, max-age=120');
    $pdo   = getPDO();
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 15)));

    // Ambil query unik terbaru, abaikan yang kosong / terlalu pendek
    $stmt = $pdo->prepare(
        "SELECT query, MAX(created_at) AS last_at
         FROM search_logs
         WHERE LENGTH(TRIM(query)) >= 2
         GROUP BY LOWER(TRIM(query))
         ORDER BY last_at DESC
         LIMIT :lim"
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    echo json_encode(['data' => array_map(fn($r) => $r['query'], $rows)]);
}

// =============================================================
// 5c. STATS — statistik total kitab, kategori, pencarian, kunjungan
// =============================================================
function handleStats(): void {
    $pdo = getPDO();
    
    // Total kitab — hanya hitung buku yang memiliki konten
    $totalBooks = (int)$pdo->query("SELECT COUNT(DISTINCT bkid) FROM book_content")->fetchColumn();
    
    // Total kategori
    $totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    
    // Total pencarian
    $totalSearches = (int)$pdo->query("SELECT COUNT(*) FROM search_logs")->fetchColumn();
    
    // Total kunjungan
    $totalVisits = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_log")->fetchColumn();
    
    echo json_encode([
        'total_books'       => $totalBooks,
        'total_categories'  => $totalCategories,
        'total_searches'    => $totalSearches,
        'total_visits'      => $totalVisits
    ]);
}

// =============================================================
// 5d. AUTH ME — kembalikan session user saat ini
// =============================================================
function handleAuthMe(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $user = getSessionUser();
    if ($user) {
        echo json_encode(['loggedIn' => true, 'user' => $user]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
}

function handleLogActivity(): void {
    $req   = getJsonRequest();
    $event = trim($req['event'] ?? '');
    $data  = $req['data']  ?? null;

    $allowed = ['visit', 'menu_click', 'login', 'logout', 'register'];
    if ($event === '' || !in_array($event, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid activity event.']);
        return;
    }

    $detail = null;
    if (is_array($data) || is_object($data)) {
        $detail = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } elseif ($data !== null) {
        $detail = (string)$data;
    }

    logUserActivity($event, (string)($detail ?? ''));
    echo json_encode(['success' => true]);
}

function handleSearchCategories(): void {
    header('Cache-Control: public, max-age=120');
    $pdo       = getPDO();
    $qRaw      = trim($_GET['q'] ?? '');
    $q         = searchPhraseText($qRaw);
    if (strlen($q) < 2) { echo json_encode(['data' => []]); return; }

    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        "SELECT c.id, c.name, COUNT(b.bkid) AS book_count
         FROM categories c
         LEFT JOIN books b ON b.category_id = c.id
         WHERE c.name LIKE :lk
         GROUP BY c.id
         ORDER BY book_count DESC
         LIMIT 20"
    );
    $stmt->execute([':lk' => $like]);
    echo json_encode(['data' => $stmt->fetchAll()]);
}

// =============================================================
// 6b. SEARCH — JUDUL / PENGARANG  (fulltext + cache)
// =============================================================
function handleSearchBooks(): void {
    header('Cache-Control: public, max-age=120');
    $pdo   = getPDO();
    $qRaw  = trim($_GET['q'] ?? '');
    $q     = searchPhraseText($qRaw);
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;
    $isFirstPage = ($page === 1); // Log hanya halaman pertama

    if (strlen($q) < 2) {
        echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
        return;
    }

    $hash       = 'books:' . hash('sha256', strtolower($qRaw));
    $qStar      = booleanSearchTerm($qRaw);
    $like       = '%' . $q . '%';
    $phraseLike = preg_match('/\s+/u', $q) ? $like : null;

    // --- Cache hit (page 1 only) ---
    if ($page === 1) {
        $cs = $pdo->prepare(
            "SELECT results_json, result_count FROM search_cache
             WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
        );
        $cs->execute([':h' => $hash]);
        if ($row = $cs->fetch()) {
            $all = json_decode($row['results_json'], true) ?? [];
            if ($isFirstPage) logSearchQuery('basic', $qRaw, (int)$row['result_count']);
            echo json_encode([
                'data'        => array_slice($all, 0, $limit),
                'total'       => (int)$row['result_count'],
                'page'        => 1,
                'total_pages' => (int)ceil($row['result_count'] / $limit),
                'cached'      => true,
            ]);
            return;
        }
    }

    // --- Query ---
    $stmt = $pdo->prepare(
        "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name,
                MATCH(b.title) AGAINST (:q1 IN BOOLEAN MODE) AS rel
         FROM books b
         WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
         ORDER BY rel DESC, b.title ASC
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':q1',  $qStar, PDO::PARAM_STR);
    $stmt->bindValue(':q2',  $qStar, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll();

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM books
         WHERE MATCH(title) AGAINST (:q IN BOOLEAN MODE)"
    );
    $countStmt->execute([':q' => $qStar]);
    $total = (int)$countStmt->fetchColumn();

    // --- Cache store ---
    if ($page === 1 && $total > 0) {
        $pdo->prepare(
            "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
             VALUES (:h, :qt, :rj, :rc, DATE_ADD(NOW(), INTERVAL 1 HOUR))
             ON DUPLICATE KEY UPDATE results_json=VALUES(results_json),
             result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
        )->execute([':h' => $hash, ':qt' => $q, ':rj' => json_encode($books), ':rc' => $total]);
    }

    // --- Log pencarian (hanya halaman pertama) ---
    if ($isFirstPage) logSearchQuery('basic', $qRaw, $total);

    echo json_encode([
        'data'        => $books,
        'total'       => $total,
        'page'        => $page,
        'total_pages' => (int)ceil($total / $limit),
    ]);
}

// =============================================================
// 6c. SEARCH — ISI KITAB  (2-step optimised, fulltext only)
//
//  Step 1 — GROUP BY bkid dengan FULLTEXT: hasilkan daftar bkid
//            terurut relevansi, tanpa menyentuh tabel books.
//  Step 2 — JOIN ke books + ambil cuplikan halaman terbaik via
//            window function ROW_NUMBER (MariaDB 10.2+).
//            Semua parameter di-bind untuk keamanan (no injection).
// =============================================================
function handleSearchContent(): void {
    header('Cache-Control: public, max-age=120');
    $pdo   = getPDO();
    $qRaw  = trim($_GET['q'] ?? '');
    $q     = searchPhraseText($qRaw);
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;

    if (strlen($q) < 2) {
        echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
        return;
    }

    $qStar = booleanSearchTerm($qRaw);
    $hash  = 'content:' . hash('sha256', strtolower($qRaw));

    // --- Cache hit ---
    if ($page === 1) {
        $cs = $pdo->prepare(
            "SELECT results_json, result_count FROM search_cache
             WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
        );
        $cs->execute([':h' => $hash]);
        if ($row = $cs->fetch()) {
            $all = json_decode($row['results_json'], true) ?? [];
            echo json_encode([
                'data'        => array_slice($all, 0, $limit),
                'total'       => (int)$row['result_count'],
                'page'        => 1,
                'total_pages' => (int)ceil($row['result_count'] / $limit),
                'cached'      => true,
            ]);
            return;
        }
    }

    // --- Step 1: Scan FULLTEXT pada book_content saja (tidak JOIN) ---
    $step1 = $pdo->prepare(
        "SELECT bkid, page, MATCH(content) AGAINST (:q1 IN BOOLEAN MODE) AS rel
         FROM book_content
         WHERE MATCH(content) AGAINST (:q2 IN BOOLEAN MODE)
         ORDER BY rel DESC, bkid ASC, page ASC
         LIMIT :lim OFFSET :off"
    );
    $step1->bindValue(':q1',  $qStar, PDO::PARAM_STR);
    $step1->bindValue(':q2',  $qStar, PDO::PARAM_STR);
    $step1->bindValue(':lim', $limit, PDO::PARAM_INT);
    $step1->bindValue(':off', $offset, PDO::PARAM_INT);
    $step1->execute();
    $topRows = $step1->fetchAll();

    if (empty($topRows)) {
        echo json_encode(['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0]);
        return;
    }

    $pairConds  = [];
    $pairParams = [];
    foreach ($topRows as $i => $r) {
        $bk = ':bk' . $i;
        $pg = ':pg' . $i;
        $pairConds[]   = "(bc.bkid = $bk AND bc.page = $pg)";
        $pairParams[$bk] = (int)$r['bkid'];
        $pairParams[$pg] = (int)$r['page'];
    }

    // --- Step 2: Best snippet per bkid ---
    $step2Sql = "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page,
                        bc.content AS snippet,
                        b.title, b.author, b.pages, b.category_name
                 FROM book_content bc
                 JOIN books b ON b.bkid = bc.bkid
                 WHERE " . implode(' OR ', $pairConds);

    $step2 = $pdo->prepare($step2Sql);
    foreach ($pairParams as $k => $v) $step2->bindValue($k, $v, PDO::PARAM_INT);
    $step2->execute();

    $byKey = [];
    foreach ($step2->fetchAll() as $r) {
        $byKey[$r['bkid'] . '_' . $r['match_page']] = $r;
    }

    $terms = preg_split('/\s+/u', searchPhraseText($q), -1, PREG_SPLIT_NO_EMPTY);
    $rows = [];
    foreach ($topRows as $r) {
        $k = $r['bkid'] . '_' . $r['page'];
        if (!isset($byKey[$k])) continue;
        $row = $byKey[$k];
        $row['snippet'] = extractSmartSnippet((string)($row['snippet'] ?? ''), $terms);
        $rows[] = $row;
    }

    // --- Count total (cached separately, expensive on huge tables) ---
    $countHash = 'cnt_content:' . hash('sha256', strtolower($q));
    $ccRow = $pdo->prepare(
        "SELECT result_count FROM search_cache WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
    );
    $ccRow->execute([':h' => $countHash]);
    if ($countCached = $ccRow->fetchColumn()) {
        $total = (int)$countCached;
    } else {
        $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM book_content
         WHERE MATCH(content) AGAINST (:q IN BOOLEAN MODE)"
    );
    $countStmt->execute([':q' => $qStar]);
        $total = (int)$countStmt->fetchColumn();
        // Cache the count for 2 hours
        $pdo->prepare(
            "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
             VALUES (:h, :qt, '[]', :rc, DATE_ADD(NOW(), INTERVAL 2 HOUR))
             ON DUPLICATE KEY UPDATE result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
        )->execute([':h' => $countHash, ':qt' => 'cnt:' . $q, ':rc' => $total]);
    }

    // Cache page-1 results
    if ($page === 1 && !empty($rows)) {
        $pdo->prepare(
            "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
             VALUES (:h, :qt, :rj, :rc, DATE_ADD(NOW(), INTERVAL 1 HOUR))
             ON DUPLICATE KEY UPDATE results_json=VALUES(results_json),
             result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
        )->execute([':h' => $hash, ':qt' => $q, ':rj' => json_encode($rows), ':rc' => $total]);
    }

    echo json_encode([
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'total_pages' => (int)ceil($total / $limit),
    ]);
}

// =============================================================
// 6e. LIST BOOKS WITH CONTENT — untuk progressive per-book search
//     Mengembalikan semua buku yang punya konten, urut abjad.
//     Sangat ringan: hanya baca tabel books dengan sub-select EXISTS.
// =============================================================
function handleSearchBooksWithContent(): void {
    header('Cache-Control: public, max-age=3600');
    $pdo   = getPDO();

    // Ambil daftar kitab yang punya konten, urut abjad
    // Gunakan EXISTS agar tidak perlu JOIN/GROUP BY
    $stmt = $pdo->query(
        "SELECT b.bkid, b.title, b.author, b.category_name
         FROM books b
         WHERE EXISTS (
             SELECT 1 FROM book_content bc WHERE bc.bkid = b.bkid LIMIT 1
         )
         ORDER BY b.title ASC"
    );
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['data' => $books, 'total' => count($books)]);
}

// =============================================================
// 6f. SEARCH CONTENT IN BOOK — cari di satu kitab saja
//     Sangat ringan: FULLTEXT + filter bkid = satu kitab.
//     Dikembalikan: ada tidaknya hasil + snippet terbaik.
// =============================================================
function handleSearchContentInBook(): void {
    header('Cache-Control: public, max-age=300');
    $pdo  = getPDO();
    $bkid = (int)($_GET['bkid'] ?? 0);
    $qRaw = trim($_GET['q'] ?? '');
    $q    = searchPhraseText($qRaw);

    if ($bkid <= 0 || strlen($q) < 2) {
        echo json_encode(['found' => false, 'data' => []]);
        return;
    }

    $qStar = booleanSearchTerm($qRaw);

    // Ambil halaman terbaik dalam kitab ini (top 3, ringan karena sudah di-filter bkid)
    $stmt = $pdo->prepare(
        "SELECT bc.juz AS match_juz, bc.page AS match_page,
                bc.content AS snippet,
                MATCH(bc.content) AGAINST (:q1 IN BOOLEAN MODE) AS rel
         FROM book_content bc
         WHERE bc.bkid = :bkid
           AND MATCH(bc.content) AGAINST (:q2 IN BOOLEAN MODE)
         ORDER BY rel DESC
         LIMIT 3"
    );
    $stmt->bindValue(':q1',   $qStar, PDO::PARAM_STR);
    $stmt->bindValue(':q2',   $qStar, PDO::PARAM_STR);
    $stmt->bindValue(':bkid', $bkid,  PDO::PARAM_INT);
    $stmt->execute();
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pages)) {
        echo json_encode(['found' => false, 'data' => []]);
        return;
    }

    // Ambil metadata kitab
    $bmeta = $pdo->prepare(
        "SELECT bkid, title, author, category_name FROM books WHERE bkid = :bkid LIMIT 1"
    );
    $bmeta->execute([':bkid' => $bkid]);
    $book = $bmeta->fetch(PDO::FETCH_ASSOC);

    $terms = preg_split('/\s+/u', searchPhraseText($qRaw), -1, PREG_SPLIT_NO_EMPTY);
    $results = [];
    foreach ($pages as $page) {
        $results[] = [
            'bkid'          => $bkid,
            'title'         => $book['title']        ?? '',
            'author'        => $book['author']       ?? '',
            'category_name' => $book['category_name'] ?? '',
            'match_juz'     => (int)$page['match_juz'],
            'match_page'    => (int)$page['match_page'],
            'snippet'       => extractSmartSnippet((string)($page['snippet'] ?? ''), $terms),
        ];
    }

    echo json_encode(['found' => true, 'data' => $results]);
}

// =============================================================
// Helper: Extract smart snippet that includes all search terms
// =============================================================
function extractSmartSnippet($content, $terms, $maxLength = 350) {
    if (empty($content)) return '';
    
    // Clean content
    $cleanContent = preg_replace('/\s+/', ' ', str_replace(["\n", "\r", "\t"], ' ', $content));
    $cleanContent = substr($cleanContent, 0, 2000); // Limit to prevent memory issues
    
    if (empty($terms)) {
        return substr($cleanContent, 0, $maxLength);
    }
    
    // Find positions of all search terms (case-insensitive)
    $positions = [];
    foreach ($terms as $term) {
        $term = trim($term);
        if (empty($term)) continue;
        
        // Remove quotes if present
        $term = preg_replace('/^"|"$/', '', $term);
        if (empty($term)) continue;
        
        // Find all positions of this term
        $lowerContent = strtolower($cleanContent);
        $lowerTerm = strtolower($term);
        $offset = 0;
        while (($pos = strpos($lowerContent, $lowerTerm, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 1;
        }
    }
    
    if (empty($positions)) {
        // No terms found, return beginning
        return substr($cleanContent, 0, $maxLength);
    }
    
    // Sort positions and find optimal snippet window
    sort($positions);
    $firstPos = reset($positions);
    $lastPos = end($positions);
    
    // Calculate snippet start: go back a bit from first term
    $snippetStart = max(0, $firstPos - 50);
    
    // Calculate snippet end: go forward from last term to include it
    $snippetEnd = min(strlen($cleanContent), $lastPos + 200);
    
    // Adjust length to not exceed maxLength
    $snippetLength = $snippetEnd - $snippetStart;
    if ($snippetLength > $maxLength) {
        // Prefer to keep all terms, but truncate if needed
        $snippetEnd = min($snippetEnd, $snippetStart + $maxLength);
    }
    
    $snippet = substr($cleanContent, $snippetStart, $snippetEnd - $snippetStart);
    
    // Trim to word boundary
    if (strlen($snippet) > 300) {
        $lastSpace = strrpos($snippet, ' ');
        if ($lastSpace > 0 && $lastSpace < strlen($snippet) - 10) {
            $snippet = substr($snippet, 0, $lastSpace);
        }
    }
    
    return trim($snippet);
}

// =============================================================
// 6d. SEARCH ADVANCED — page-level konten dengan banyak kolom + kategori
//
//  Strategi 2-langkah untuk performa maksimal:
//  • Jalur CEPAT (all_cats / tanpa filter kategori):
//    Step 1 — Scan FULLTEXT pada book_content saja (tanpa JOIN),
//             ambil top-N bkid+page berdasarkan relevansi.
//    Step 2 — JOIN ke books hanya untuk N baris hasil tersebut.
//    COUNT  — Tanpa JOIN, langsung dari book_content.
//  • Jalur STANDAR (ada filter kategori):
//    Gunakan JOIN + filter category_id seperti semula,
//    namun LEFT(content,400) saja yang diambil.
// =============================================================
function handleSearchAdvanced(): void {
    header('Cache-Control: public, max-age=120');
    $pdo    = getPDO();
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 12;
    $offset = ($page - 1) * $limit;

    $fields = [];
    for ($i = 1; $i <= 5; $i++) {
        $val = trim((string)($_GET['q' . $i] ?? ''));
        if ($val !== '') $fields[] = $val;
    }

    if (empty($fields)) {
        jsonResponse(['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0]);
        return;
    }

    $allCats = ($_GET['all_cats'] ?? '0') === '1';
    $cats    = [];
    if (!$allCats && isset($_GET['cats']) && $_GET['cats'] !== '') {
        foreach (explode(',', (string)$_GET['cats']) as $cid) {
            $cid = (int)trim($cid);
            if ($cid > 0) $cats[] = $cid;
        }
    }
    $samePage    = ($_GET['same_page'] ?? '1') !== '0';
    $noCatFilter = $allCats || empty($cats);   // true = lewati filter kategori

    // --- Cache key ---
    $cacheKey = 'adv4:' . hash('sha256', json_encode([
        'f' => $fields, 'c' => $cats, 'a' => $allCats, 's' => $samePage, 'p' => $page,
    ]));

    // --- Cache hit ---
    try {
        $cs = $pdo->prepare(
            "SELECT results_json, result_count FROM search_cache
             WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
        );
        $cs->execute([':h' => $cacheKey]);
        if ($row = $cs->fetch()) {
            jsonResponse([
                'data'        => json_decode($row['results_json'], true) ?? [],
                'total'       => (int)$row['result_count'],
                'page'        => $page,
                'total_pages' => (int)ceil($row['result_count'] / $limit),
                'cached'      => true,
            ]);
            return;
        }
    } catch (\Exception $e) { /* ignore */ }

    // --- Build FULLTEXT conditions (kolom bare `content`, tanpa alias tabel) ---
    $ftParams     = [];
    $ftConditions = [];  // menggunakan `content` tanpa alias

    if ($samePage) {
        foreach ($fields as $idx => $field) {
            $boolTerm = booleanSearchTermForAdvanced($field);
            if ($boolTerm === '') continue;
            $k = ':ft' . $idx;
            $ftParams[$k]   = $boolTerm;
            $ftConditions[] = "MATCH(content) AGAINST ($k IN BOOLEAN MODE)";
        }
    } else {
        $combined = booleanQueryFromFieldsOr($fields);
        if ($combined !== '') {
            $ftParams[':ft0'] = $combined;
            $ftConditions[]   = "MATCH(content) AGAINST (:ft0 IN BOOLEAN MODE)";
        }
    }

    if (empty($ftConditions)) {
        jsonResponse(['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0]);
        return;
    }

    $ftWhere  = '(' . implode(' AND ', $ftConditions) . ')';          // bare table
    $allFtRel = booleanQueryFromFieldsOr($fields) ?: '+*';

    $rows  = [];
    $total = 0;

    try {
        if ($noCatFilter) {
            // ══════════════════════════════════════════════════════
            // JALUR CEPAT — Tanpa JOIN pada scan utama
            // ══════════════════════════════════════════════════════

            // Step 1: Scan FULLTEXT pada book_content saja (tidak JOIN)
            //         → hanya menggunakan FULLTEXT index, sangat cepat
            $s1Params          = $ftParams;
            $s1Params[':rel']  = $allFtRel;

            $step1Sql = "SELECT bkid, juz, page,
                                MATCH(content) AGAINST (:rel IN BOOLEAN MODE) AS relevance
                         FROM book_content
                         WHERE $ftWhere
                         ORDER BY relevance DESC, bkid ASC, juz ASC, page ASC
                         LIMIT :lim OFFSET :off";

            $s1 = $pdo->prepare($step1Sql);
            foreach ($s1Params as $k => $v) $s1->bindValue($k, $v, PDO::PARAM_STR);
            $s1->bindValue(':lim', $limit, PDO::PARAM_INT);
            $s1->bindValue(':off', $offset, PDO::PARAM_INT);
            $s1->execute();
            $topRows = $s1->fetchAll();

            if (!empty($topRows)) {
                // Step 2: Ambil metadata + snippet hanya untuk baris terpilih (JOIN minimal)
                $pairConds  = [];
                $pairParams = [];
                foreach ($topRows as $i => $r) {
                    $bk = ':bk' . $i;
                    $jz = ':jz' . $i;
                    $pg = ':pg' . $i;
                    $pairConds[]   = "(bc.bkid = $bk AND bc.juz = $jz AND bc.page = $pg)";
                    $pairParams[$bk] = (int)$r['bkid'];
                    $pairParams[$jz] = (int)$r['juz'];
                    $pairParams[$pg] = (int)$r['page'];
                }

                $step2Sql = "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page,
                                    bc.content AS content,
                                    b.title, b.author, b.category_name
                             FROM book_content bc
                             JOIN books b ON b.bkid = bc.bkid
                             WHERE " . implode(' OR ', $pairConds);

                $s2 = $pdo->prepare($step2Sql);
                foreach ($pairParams as $k => $v) $s2->bindValue($k, $v, PDO::PARAM_INT);
                $s2->execute();

                $byKey = [];
                foreach ($s2->fetchAll() as $r) {
                    $byKey[$r['bkid'] . '_' . $r['match_juz'] . '_' . $r['match_page']] = $r;
                }

                // Kembalikan urutan relevansi dari Step 1
                foreach ($topRows as $r) {
                    $k = $r['bkid'] . '_' . $r['juz'] . '_' . $r['page'];
                    if (!isset($byKey[$k])) continue;
                    $row             = $byKey[$k];
                    $row['snippet']  = extractSmartSnippet((string)($row['content'] ?? ''), $fields);
                    unset($row['content']);
                    $rows[] = $row;
                }
            }

            // COUNT — tanpa JOIN (hanya book_content + FULLTEXT index)
            $countKey = 'advcnt4:' . hash('sha256', json_encode([
                'f' => $fields, 'c' => [], 's' => $samePage,
            ]));
            $cc = $pdo->prepare(
                "SELECT result_count FROM search_cache WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
            );
            $cc->execute([':h' => $countKey]);
            if ($cnt = $cc->fetchColumn()) {
                $total = (int)$cnt;
            } else {
                $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE $ftWhere");
                foreach ($ftParams as $k => $v) $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
                $cntStmt->execute();
                $total = (int)$cntStmt->fetchColumn();
                $pdo->prepare(
                    "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
                     VALUES (:h, :qt, '[]', :rc, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                     ON DUPLICATE KEY UPDATE result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
                )->execute([':h' => $countKey, ':qt' => 'cnt:adv3', ':rc' => $total]);
            }

        } else {
            // ══════════════════════════════════════════════════════
            // JALUR STANDAR — Ada filter kategori, pakai JOIN
            // ══════════════════════════════════════════════════════

            // Kondisi FULLTEXT untuk JOIN query (alias bc.content)
            $ftConditionsAlias = array_map(
                fn($c) => str_replace('MATCH(content)', 'MATCH(bc.content)', $c),
                $ftConditions
            );
            $ftWhereAlias = '(' . implode(' AND ', $ftConditionsAlias) . ')';

            $catPlaceholders = [];
            $catParams       = [];
            foreach ($cats as $i => $cid) {
                $k = ':cat' . $i;
                $catPlaceholders[] = $k;
                $catParams[$k]     = $cid;
            }
            $fullWhere = $ftWhereAlias . ' AND b.category_id IN (' . implode(',', $catPlaceholders) . ')';

            $params          = array_merge($ftParams, $catParams);
            $params[':rel']  = $allFtRel;
            $params[':lim']  = $limit;
            $params[':off']  = $offset;

            $sql = "SELECT bc.bkid, b.title, b.author, b.category_name,
                           bc.juz AS match_juz, bc.page AS match_page,
                           bc.content AS content,
                           MATCH(bc.content) AGAINST (:rel IN BOOLEAN MODE) AS relevance
                    FROM book_content bc
                    JOIN books b ON b.bkid = bc.bkid
                    WHERE $fullWhere
                    ORDER BY relevance DESC, b.title ASC, bc.page ASC
                    LIMIT :lim OFFSET :off";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $rawRows = $stmt->fetchAll();

            $rows = array_map(function ($row) use ($fields) {
                $row['snippet'] = extractSmartSnippet((string)($row['content'] ?? ''), $fields);
                unset($row['content'], $row['relevance']);
                return $row;
            }, $rawRows);

            // COUNT dengan JOIN + filter kategori
            $countKey = 'advcnt3:' . hash('sha256', json_encode([
                'f' => $fields, 'c' => $cats, 's' => $samePage,
            ]));
            $cc = $pdo->prepare(
                "SELECT result_count FROM search_cache WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
            );
            $cc->execute([':h' => $countKey]);
            if ($cnt = $cc->fetchColumn()) {
                $total = (int)$cnt;
            } else {
                $cntParams = array_merge($ftParams, $catParams);
                $cntStmt   = $pdo->prepare(
                    "SELECT COUNT(*) FROM book_content bc JOIN books b ON b.bkid = bc.bkid WHERE $fullWhere"
                );
                foreach ($cntParams as $k => $v) {
                    $cntStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $cntStmt->execute();
                $total = (int)$cntStmt->fetchColumn();
                $pdo->prepare(
                    "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
                     VALUES (:h, :qt, '[]', :rc, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                     ON DUPLICATE KEY UPDATE result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
                )->execute([':h' => $countKey, ':qt' => 'cnt:adv3', ':rc' => $total]);
            }
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Query error: ' . $e->getMessage()], 500);
        return;
    }

    // --- Simpan hasil di cache (30 menit) ---
    try {
        $pdo->prepare(
            "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
             VALUES (:h, :qt, :rj, :rc, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
             ON DUPLICATE KEY UPDATE results_json=VALUES(results_json),
             result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
        )->execute([
            ':h'  => $cacheKey,
            ':qt' => implode(' | ', $fields),
            ':rj' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ':rc' => $total,
        ]);
    } catch (\Exception $e) { /* ignore */ }

    // --- Log pencarian lanjutan (hanya halaman pertama) ---
    if ($page === 1) {
        logSearchQuery('advanced', implode(' | ', $fields), $total, json_encode([
            'fields'   => $fields,
            'cats'     => $cats,
            'all_cats' => $allCats,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    jsonResponse([
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'total_pages' => (int)ceil($total / $limit),
    ]);
}

// =============================================================
// 6-legacy. SEARCH (deprecated — kept for safety, not used)
// =============================================================
function handleSearch(): void {
    $pdo      = getPDO();
    $q        = trim($_GET['q'] ?? '');
    $bookPage = max(1, (int)($_GET['book_page']    ?? 1));
    $contPage = max(1, (int)($_GET['content_page'] ?? 1));
    $limit    = 12;

    $empty = [
        'query'      => $q,
        'categories' => [],
        'books'      => ['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0],
        'content'    => ['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0],
    ];

    if (strlen($q) < 2) { echo json_encode($empty); return; }

    $like  = '%' . $q . '%';
    $qStar = $q . '*';

    // 1. Categories
    $stmtCat = $pdo->prepare(
        "SELECT c.id, c.name, COUNT(b.bkid) AS book_count
         FROM categories c
         LEFT JOIN books b ON b.category_id = c.id
         WHERE c.name LIKE :lk
         GROUP BY c.id ORDER BY book_count DESC LIMIT 20"
    );
    $stmtCat->execute([':lk' => $like]);
    $categories = $stmtCat->fetchAll();

    // 2. Books by title/author
    $bookOffset = ($bookPage - 1) * $limit;
    $stmtBooks = $pdo->prepare(
        "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name
         FROM books b
         WHERE b.title LIKE :lk OR b.author LIKE :lk2
         ORDER BY b.bkid DESC LIMIT :lim OFFSET :off"
    );
    $stmtBooks->bindValue(':lk',  $like, PDO::PARAM_STR);
    $stmtBooks->bindValue(':lk2', $like, PDO::PARAM_STR);
    $stmtBooks->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmtBooks->bindValue(':off', $bookOffset, PDO::PARAM_INT);
    $stmtBooks->execute();
    $books = $stmtBooks->fetchAll();

    $stmtBooksCount = $pdo->prepare(
        "SELECT COUNT(*) FROM books WHERE title LIKE :lk OR author LIKE :lk2"
    );
    $stmtBooksCount->execute([':lk' => $like, ':lk2' => $like]);
    $booksTotal = (int)$stmtBooksCount->fetchColumn();

    // 3. Content
    $contOffset = ($contPage - 1) * $limit;
    $stmtCont = $pdo->prepare(
        "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page, b.title, b.author, b.category_name,
                bc.content AS snippet
         FROM book_content bc
         JOIN books b ON b.bkid = bc.bkid
         WHERE bc.content LIKE :lk
         ORDER BY bc.bkid DESC, bc.page ASC
         LIMIT :lim OFFSET :off"
    );
    $stmtCont->bindValue(':lk',  $like, PDO::PARAM_STR);
    $stmtCont->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmtCont->bindValue(':off', $contOffset, PDO::PARAM_INT);
    $stmtCont->execute();
    $content = $stmtCont->fetchAll();

    $terms = preg_split('/\s+/u', searchPhraseText($q), -1, PREG_SPLIT_NO_EMPTY);
    $content = array_map(function($row) use ($terms) {
        $row['snippet'] = extractSmartSnippet((string)($row['snippet'] ?? ''), $terms);
        return $row;
    }, $content);

    $stmtContCount = $pdo->prepare(
        "SELECT COUNT(*) FROM book_content WHERE content LIKE :lk"
    );
    $stmtContCount->execute([':lk' => $like]);
    $contTotal = (int)$stmtContCount->fetchColumn();

    echo json_encode([
        'query'      => $q,
        'categories' => $categories,
        'books'      => [
            'data' => $books, 'total' => $booksTotal,
            'page' => $bookPage, 'total_pages' => (int)ceil($booksTotal / $limit),
        ],
        'content'    => [
            'data' => $content, 'total' => $contTotal,
            'page' => $contPage, 'total_pages' => (int)ceil($contTotal / $limit),
        ],
    ]);
}

// =============================================================
// ADMIN — Simpan / Update Kitab
// =============================================================
function handleAdminSaveBook(): void {
    $pdo  = getPDO();
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
        logCrudHistory('UPDATE', 'books', (string)$bkid,
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
        logCrudHistory('CREATE', 'books', (string)$newId,
            "Judul: {$title}" . ($author ? " | Penulis: {$author}" : '') . ($catName ? " | Kategori: {$catName}" : ''));
        echo json_encode(['success' => true, 'bkid' => $newId]);
    }
}

// =============================================================
// ADMIN — Hapus Kitab (cascade ke book_content)
// =============================================================
function handleAdminDeleteBook(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $bkid = (int)($data['bkid'] ?? 0);
    if (!$bkid) { http_response_code(400); echo json_encode(['error' => 'bkid wajib diisi.']); return; }
    // Ambil judul sebelum dihapus untuk catatan log
    $titleRow = $pdo->prepare("SELECT title, author FROM books WHERE bkid = :bkid LIMIT 1");
    $titleRow->execute([':bkid' => $bkid]);
    $bookInfo = $titleRow->fetch();
    $pdo->prepare("DELETE FROM book_content WHERE bkid = :bkid")->execute([':bkid' => $bkid]);
    $pdo->prepare("DELETE FROM books WHERE bkid = :bkid")->execute([':bkid' => $bkid]);
    logCrudHistory('DELETE', 'books', (string)$bkid,
        $bookInfo ? "Judul: {$bookInfo['title']}" . ($bookInfo['author'] ? " | Penulis: {$bookInfo['author']}" : '') : '');
    echo json_encode(['success' => true]);
}

// =============================================================
// ADMIN — Simpan / Update Kategori
// =============================================================
function handleAdminSaveCategory(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id     = (int)($data['id']   ?? 0);
    $name   = trim($data['name']  ?? '');
    $ord    = (int)($data['catord'] ?? 0);
    $level  = (int)($data['lvl']  ?? 0);
    if (!$name) { http_response_code(400); echo json_encode(['error' => 'Nama kategori wajib diisi.']); return; }

    if ($id) {
        $pdo->prepare("UPDATE categories SET name=:name, catord=:ord, level=:lvl WHERE id=:id")
            ->execute([':name' => $name, ':ord' => $ord, ':lvl' => $level, ':id' => $id]);
        logCrudHistory('UPDATE', 'categories', (string)$id,
            "Nama: {$name} | Urutan: {$ord} | Level: {$level}");
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        $pdo->prepare("INSERT INTO categories (name, catord, level) VALUES (:name, :ord, :lvl)")
            ->execute([':name' => $name, ':ord' => $ord, ':lvl' => $level]);
        $newCatId = (int)$pdo->lastInsertId();
        logCrudHistory('CREATE', 'categories', (string)$newCatId,
            "Nama: {$name} | Urutan: {$ord} | Level: {$level}");
        echo json_encode(['success' => true, 'id' => $newCatId]);
    }
}

// =============================================================
// ADMIN — Hapus Kategori
// =============================================================
function handleAdminDeleteCategory(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID wajib diisi.']); return; }
    $nameRow = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
    $nameRow->execute([':id' => $id]);
    $catName = $nameRow->fetchColumn() ?: '';
    $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id' => $id]);
    logCrudHistory('DELETE', 'categories', (string)$id, "Nama: {$catName}");
    echo json_encode(['success' => true]);
}

// =============================================================
// ADMIN — Simpan / Update Isi Kitab (satu halaman)
// =============================================================
function handleAdminSaveContent(): void {
    $pdo  = getPDO();
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
        logCrudHistory('CREATE', 'book_content', "bkid:{$bkid}|juz:{$juz}|page:{$page}",
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
        logCrudHistory('UPDATE', 'book_content', "bkid:{$bkid}|juz:{$juz}|page:{$page}",
            "Edit halaman {$page} juz {$juz} pada kitab bkid={$bkid}");
    }
    echo json_encode(['success' => true, 'juz' => $juz]);
}

// =============================================================
// ADMIN — Hapus Halaman Isi Kitab
// =============================================================
function handleAdminDeleteContent(): void {
    $pdo  = getPDO();
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
    logCrudHistory('DELETE', 'book_content', "bkid:{$bkid}|juz:{$juz}|page:{$page}",
        "Hapus halaman {$page} juz {$juz} pada kitab bkid={$bkid}");
    echo json_encode(['success' => true]);
}

// =============================================================
// ADMIN — Import Kitab dari .doc / .docx
// =============================================================
function handleAdminImportBook(): void {
    $pdo = getPDO();

    // --- Bersihkan orphan bkid=0 dari import sebelumnya yang gagal ---
    try {
        $pdo->exec("DELETE FROM book_content WHERE bkid = 0");
        $pdo->exec("DELETE FROM books WHERE bkid = 0");
    } catch (\Exception $e) { /* ignore */ }

    $json = getJsonRequest();
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

        logCrudHistory('IMPORT', 'books', (string)$bkid,
            "Impor kitab: {$title} | Penulis: {$author} | Halaman: " . count($pages)
        );

        echo json_encode(['success' => true, 'bkid' => $bkid, 'pages' => count($pages)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Import gagal: ' . $e->getMessage()]);
    }
}

// =============================================================
// ADMIN — GET CRUD History (paginated + filtered)
// =============================================================
function handleAdminGetHistory(): void {
    $pdo  = getPDO();
    $req  = getJsonRequest();
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

// =============================================================
// ADMIN — GET Visitor Activity (paginated + filtered + stats)
// =============================================================
function handleAdminGetActivity(): void {
    $pdo  = getPDO();
    $req  = getJsonRequest();
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

// =============================================================
// ADMIN — GET Visitor Activity (paginated + filtered + stats)
function handleAdminGetSearchLogs(): void {
    $pdo  = getPDO();
    $req  = getJsonRequest();
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

// =============================================================
//  ADMIN — GET DOWNLOAD LOGS
// =============================================================
function handleAdminGetDownloadLogs(): void {
    $pdo  = getPDO();
    $req  = getJsonRequest();
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

// =============================================================
//  FILE SUBMISSIONS
// =============================================================

function handleSubmitFile(): void {
    $user = getSessionUser();
    $pdo  = getPDO();

    $fileName    = trim($_POST['file_name']     ?? '');
    $fileType    = trim($_POST['file_type']     ?? '');
    $categoryId  = intval($_POST['category_id'] ?? 0);
    $description = trim($_POST['description']   ?? '');
    $submitterEmail = trim($_POST['submitter_email'] ?? '');

    if ($fileName === '') {
        http_response_code(400); echo json_encode(['error' => 'Nama file wajib diisi.']); return;
    }
    if (!in_array($fileType, ['bahsul_masail', 'kitab'], true)) {
        http_response_code(400); echo json_encode(['error' => 'Tipe file tidak valid.']); return;
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['file']['error'] ?? -1;
        http_response_code(400); echo json_encode(['error' => "Upload gagal (kode: $errCode)."]); return;
    }

    $allowedMime = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMime, true)) {
        http_response_code(400); echo json_encode(['error' => 'Format tidak didukung. Gunakan PDF atau Word.']); return;
    }
    if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
        http_response_code(400); echo json_encode(['error' => 'Ukuran file maksimal 20 MB.']); return;
    }

    // If user not logged in, require a valid email
    if (!$user) {
        if ($submitterEmail === '' || filter_var($submitterEmail, FILTER_VALIDATE_EMAIL) === false) {
            http_response_code(400); echo json_encode(['error' => 'Email pengirim wajib diisi dan harus valid.']); return;
        }
    }

    $categoryName = '';
    if ($categoryId > 0) {
        $cat = $pdo->prepare("SELECT name FROM categories WHERE id = :id");
        $cat->execute([':id' => $categoryId]);
        $row = $cat->fetch();
        $categoryName = $row['name'] ?? '';
    }

    $uploadDir = __DIR__ . '/uploads/submissions/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $ext      = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $safeName = preg_replace('/[^a-z0-9_\-]/i', '_', $fileName);
    $saveAs   = uniqid('sub_', true) . '_' . $safeName . '.' . $ext;
    $destPath = $uploadDir . $saveAs;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
        http_response_code(500); echo json_encode(['error' => 'Gagal menyimpan file di server.']); return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO file_submissions
         (user_id, user_name, user_email, file_name, file_type, category_id, category_name,
          file_url, file_size, mime_type, description)
         VALUES
         (:user_id, :user_name, :user_email, :file_name, :file_type, :category_id, :category_name,
          :file_url, :file_size, :mime_type, :description)"
    );
    $stmt->execute([
        ':user_id'       => $user['id'] ?? 0,
        ':user_name'     => $user['name'] ?? '',
        ':user_email'    => $user['email'] ?? $submitterEmail,
        ':file_name'     => $fileName,
        ':file_type'     => $fileType,
        ':category_id'   => $categoryId ?: null,
        ':category_name' => $categoryName,
        ':file_url'      => '/uploads/submissions/' . $saveAs,
        ':file_size'     => filesize($destPath),
        ':mime_type'     => $mimeType,
        ':description'   => $description,
    ]);

    echo json_encode(['success' => true, 'message' => 'Kiriman berhasil dikirim dan sedang menunggu review admin.']);
}

function handleAdminGetSubmissions(): void {
    $pdo    = getPDO();
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

function handleAdminReviewSubmission(): void {
    $admin  = getSessionUser();
    $pdo    = getPDO();
    $req    = getJsonRequest();
    $id     = intval($req['id']            ?? 0);
    $action = trim($req['review_action']   ?? '');
    $note   = trim($req['note']            ?? '');

    if ($id <= 0 || !in_array($action, ['approve','reject'], true)) {
        http_response_code(400); echo json_encode(['error' => 'Parameter tidak valid.']); return;
    }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare(
        "UPDATE file_submissions
         SET status = :status, reviewed_by = :reviewed_by, reviewer_name = :reviewer_name,
             review_note = :note, reviewed_at = NOW()
         WHERE id = :id"
    );
    $stmt->execute([
        ':status'        => $newStatus,
        ':reviewed_by'   => $admin['id'],
        ':reviewer_name' => $admin['name'],
        ':note'          => $note,
        ':id'            => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404); echo json_encode(['error' => 'Kiriman tidak ditemukan.']); return;
    }

    logCrudHistory($action === 'approve' ? 'APPROVE_SUBMISSION' : 'REJECT_SUBMISSION', 'file_submissions', (string)$id, $note);
    echo json_encode(['success' => true, 'status' => $newStatus]);
}
