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
    if ($q === '') {
        return '';
    }
    if (isPhraseQuery($q) || preg_match('/\s+/u', $q)) {
        return '"' . ftEscape(searchPhraseText($q)) . '"';
    }

    $terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    $parts = [];
    foreach ($terms as $term) {
        $term = ftEscape($term);
        if ($term === '') {
            continue;
        }
        $parts[] = '+' . $term . '*';
    }

    return $parts ? implode(' ', $parts) : ftEscape($q) . '*';
}

function booleanSearchTermForAdvanced(string $q): string {
    $q = trim($q);
    if ($q === '') {
        return '';
    }
    if (preg_match('/\s+/u', $q)) {
        return '+"' . ftEscape($q) . '"';
    }
    return '+' . ftEscape($q) . '*';
}

function booleanQueryFromFieldsOr(array $fields): string {
    $parts = [];
    foreach ($fields as $field) {
        $field = trim($field);
        if ($field === '') {
            continue;
        }
        if (preg_match('/\s+/u', $field)) {
            $parts[] = '"' . ftEscape($field) . '"';
        } else {
            $parts[] = ftEscape($field) . '*';
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
        // Search — tiga endpoint terpisah untuk parallel fetch
        case 'search_categories': handleSearchCategories(); break;
        case 'search_books':      handleSearchBooks();      break;
        case 'search_content':    handleSearchContent();    break;
        case 'search_advanced':   handleSearchAdvanced();   break;
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

    // count available content pages
    $cp = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid = :id");
    $cp->execute([':id' => $id]);
    $book['content_pages'] = (int)$cp->fetchColumn();

    echo json_encode(['data' => $book]);
}

function handleDownloadBook(): void {
    $pdo = getPDO();
    $id  = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid book id.']);
        return;
    }

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

    $contentStmt = $pdo->prepare(
        "SELECT content FROM book_content WHERE bkid = :id ORDER BY page ASC"
    );
    $contentStmt->execute([':id' => $id]);
    $pages = $contentStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($pages)) {
        http_response_code(404);
        echo json_encode(['error' => 'Konten kitab tidak tersedia.']);
        return;
    }

    $title = trim($book['title'] ?: 'kitab');
    $safeName = preg_replace('/[^a-z0-9\-_\.]/i', '_', $title);
    $filename = substr($safeName, 0, 100) ?: 'kitab';
    $filename .= '.txt';

    $lines = [];
    $lines[] = $book['title'] ?: 'Kitab tanpa judul';
    if ($book['author']) {
        $lines[] = 'Pengarang: ' . $book['author'];
    }
    if ($book['cat_name']) {
        $lines[] = 'Kategori: ' . $book['cat_name'];
    }
    $lines[] = '';
    foreach ($pages as $idx => $content) {
        $lines[] = "--- Halaman " . ($idx + 1) . " ---";
        $lines[] = $content;
        if ($idx < count($pages) - 1) {
            $lines[] = '';
        }
    }

    $body = implode("\n", $lines);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $body;
}

// =============================================================
// 3. BOOK CONTENT — one page at a time from book_content
// =============================================================
function handleContent(): void {
    $pdo  = getPDO();
    $bkid = (int)($_GET['bkid'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));

    if ($bkid <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid bkid.']); return; }

    // Get the Nth page row ordered by page number
    $stmt = $pdo->prepare(
        "SELECT bc.page, bc.content
         FROM book_content bc
         WHERE bc.bkid = :bkid
         ORDER BY bc.page ASC
         LIMIT 1 OFFSET :off"
    );
    $stmt->bindValue(':bkid', $bkid, PDO::PARAM_INT);
    $stmt->bindValue(':off',  $page - 1, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();

    // total pages in content table
    $ct = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid = :bkid");
    $ct->execute([':bkid' => $bkid]);
    $total = (int)$ct->fetchColumn();

    echo json_encode([
        'bkid'        => $bkid,
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
// 5b. AUTH ME — kembalikan session user saat ini
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
    if ($phraseLike !== null) {
        $stmt = $pdo->prepare(
            "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name,
                    MATCH(b.title) AGAINST (:q1 IN BOOLEAN MODE) AS rel
             FROM books b
             WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
                OR b.title LIKE :ph
                OR b.author LIKE :ph
             ORDER BY rel DESC, b.title ASC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':q1',  $qStar, PDO::PARAM_STR);
        $stmt->bindValue(':q2',  $qStar, PDO::PARAM_STR);
        $stmt->bindValue(':ph',  $phraseLike, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare(
            "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name,
                    MATCH(b.title) AGAINST (:q1 IN BOOLEAN MODE) AS rel
             FROM books b
             WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
                OR b.title  LIKE :lk
                OR b.author LIKE :la
             ORDER BY rel DESC, b.title ASC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':q1',  $qStar, PDO::PARAM_STR);
        $stmt->bindValue(':q2',  $qStar, PDO::PARAM_STR);
        $stmt->bindValue(':lk',  $like,  PDO::PARAM_STR);
        $stmt->bindValue(':la',  $like,  PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll();

    if ($phraseLike !== null) {
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM books
             WHERE MATCH(title) AGAINST (:q IN BOOLEAN MODE)
                OR title LIKE :ph OR author LIKE :ph"
        );
        $countStmt->execute([':q' => $qStar, ':ph' => $phraseLike]);
    } else {
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM books
             WHERE MATCH(title) AGAINST (:q IN BOOLEAN MODE)
                OR title LIKE :lk OR author LIKE :la"
        );
        $countStmt->execute([':q' => $qStar, ':lk' => $like, ':la' => $like]);
    }
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

    // --- Step 1: Top bkids via fulltext GROUP BY (uses FULLTEXT index, very fast) ---
    $step1 = $pdo->prepare(
        "SELECT bkid, MAX(MATCH(content) AGAINST (:q1 IN BOOLEAN MODE)) AS rel
         FROM book_content
         WHERE MATCH(content) AGAINST (:q2 IN BOOLEAN MODE)
         GROUP BY bkid
         ORDER BY rel DESC
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

    $bkidInts = array_map('intval', array_column($topRows, 'bkid'));
    $relMap   = array_column($topRows, 'rel', 'bkid');
    $inClause = implode(',', $bkidInts); // safe — all ints

    // --- Step 2: Best snippet per bkid using window function (all params bound) ---
    $step2 = $pdo->prepare(
        "SELECT b.bkid, b.title, b.author, b.pages, b.category_name,
                s.match_page, s.snippet
         FROM books b
         JOIN (
             SELECT bkid, match_page, LEFT(content_text, 300) AS snippet
             FROM (
                 SELECT bc.bkid,
                        bc.page  AS match_page,
                        bc.content AS content_text,
                        ROW_NUMBER() OVER (
                            PARTITION BY bc.bkid
                            ORDER BY MATCH(bc.content) AGAINST (:q3 IN BOOLEAN MODE) DESC
                        ) AS rn
                 FROM book_content bc
                 WHERE bc.bkid IN ($inClause)
                   AND MATCH(bc.content) AGAINST (:q4 IN BOOLEAN MODE)
             ) ranked
             WHERE rn = 1
         ) s ON s.bkid = b.bkid
         WHERE b.bkid IN ($inClause)"
    );
    $step2->bindValue(':q3', $qStar, PDO::PARAM_STR);
    $step2->bindValue(':q4', $qStar, PDO::PARAM_STR);
    $step2->execute();
    $rows = $step2->fetchAll();

    // Restore relevance order from step 1
    usort($rows, fn($a, $b) =>
        ($relMap[$b['bkid']] ?? 0) <=> ($relMap[$a['bkid']] ?? 0)
    );

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
        "SELECT COUNT(DISTINCT bkid) FROM book_content
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
    $cacheKey = 'adv3:' . hash('sha256', json_encode([
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

            $step1Sql = "SELECT bkid, page,
                                MATCH(content) AGAINST (:rel IN BOOLEAN MODE) AS relevance
                         FROM book_content
                         WHERE $ftWhere
                         ORDER BY relevance DESC, bkid ASC, page ASC
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
                    $pg = ':pg' . $i;
                    $pairConds[]   = "(bc.bkid = $bk AND bc.page = $pg)";
                    $pairParams[$bk] = (int)$r['bkid'];
                    $pairParams[$pg] = (int)$r['page'];
                }

                $step2Sql = "SELECT bc.bkid, bc.page AS match_page,
                                    LEFT(bc.content, 400) AS content,
                                    b.title, b.author, b.category_name
                             FROM book_content bc
                             JOIN books b ON b.bkid = bc.bkid
                             WHERE " . implode(' OR ', $pairConds);

                $s2 = $pdo->prepare($step2Sql);
                foreach ($pairParams as $k => $v) $s2->bindValue($k, $v, PDO::PARAM_INT);
                $s2->execute();

                $byKey = [];
                foreach ($s2->fetchAll() as $r) {
                    $byKey[$r['bkid'] . '_' . $r['match_page']] = $r;
                }

                // Kembalikan urutan relevansi dari Step 1
                foreach ($topRows as $r) {
                    $k = $r['bkid'] . '_' . $r['page'];
                    if (!isset($byKey[$k])) continue;
                    $row             = $byKey[$k];
                    $row['snippet']  = extractSmartSnippet((string)($row['content'] ?? ''), $fields);
                    unset($row['content']);
                    $rows[] = $row;
                }
            }

            // COUNT — tanpa JOIN (hanya book_content + FULLTEXT index)
            $countKey = 'advcnt3:' . hash('sha256', json_encode([
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
                           bc.page AS match_page,
                           LEFT(bc.content, 400) AS content,
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
        "SELECT bc.bkid, bc.page AS match_page, b.title, b.author, b.category_name,
                LEFT(bc.content, 300) AS snippet
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
    $content = trim($data['content']  ?? '');
    $isNew   = (bool)($data['is_new'] ?? false);

    if (!$bkid || !$page) {
        http_response_code(400); echo json_encode(['error' => 'bkid dan page wajib diisi.']); return;
    }

    if ($isNew) {
        // Cek halaman sudah ada
        $check = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid=:bkid AND page=:page");
        $check->execute([':bkid' => $bkid, ':page' => $page]);
        if ((int)$check->fetchColumn() > 0) {
            http_response_code(409); echo json_encode(['error' => "Halaman {$page} sudah ada."]); return;
        }
        $pdo->prepare("INSERT INTO book_content (bkid, page, content) VALUES (:bkid, :page, :content)")
            ->execute([':bkid' => $bkid, ':page' => $page, ':content' => $content]);
        // Update jumlah halaman di tabel books
        $pdo->prepare("UPDATE books SET pages = (SELECT COUNT(*) FROM book_content WHERE bkid=:bkid) WHERE bkid=:bkid2")
            ->execute([':bkid' => $bkid, ':bkid2' => $bkid]);
        logCrudHistory('CREATE', 'book_content', "bkid:{$bkid}|page:{$page}",
            "Tambah halaman {$page} pada kitab bkid={$bkid}");
    } else {
        $pdo->prepare("UPDATE book_content SET content=:content WHERE bkid=:bkid AND page=:page")
            ->execute([':content' => $content, ':bkid' => $bkid, ':page' => $page]);
        logCrudHistory('UPDATE', 'book_content', "bkid:{$bkid}|page:{$page}",
            "Edit halaman {$page} pada kitab bkid={$bkid}");
    }
    echo json_encode(['success' => true]);
}

// =============================================================
// ADMIN — Hapus Halaman Isi Kitab
// =============================================================
function handleAdminDeleteContent(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $bkid = (int)($data['bkid'] ?? 0);
    $page = (int)($data['page'] ?? 0);
    if (!$bkid || !$page) {
        http_response_code(400); echo json_encode(['error' => 'bkid dan page wajib diisi.']); return;
    }
    $pdo->prepare("DELETE FROM book_content WHERE bkid=:bkid AND page=:page")
        ->execute([':bkid' => $bkid, ':page' => $page]);
    // Recalculate pages
    $pdo->prepare("UPDATE books SET pages=(SELECT COUNT(*) FROM book_content WHERE bkid=:bkid) WHERE bkid=:bkid2")
        ->execute([':bkid' => $bkid, ':bkid2' => $bkid]);
    logCrudHistory('DELETE', 'book_content', "bkid:{$bkid}|page:{$page}",
        "Hapus halaman {$page} pada kitab bkid={$bkid}");
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
