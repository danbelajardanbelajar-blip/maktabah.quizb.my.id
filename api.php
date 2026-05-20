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
    if (isPhraseQuery($q)) {
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
    return '+' . ftEscape($q) . '*';
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

    if (strlen($q) < 2) {
        echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
        return;
    }

    $hash       = 'books:' . hash('sha256', strtolower($qRaw));
    $qStar      = booleanSearchTerm($qRaw);
    $like       = '%' . $q . '%';
    $phraseLike = isPhraseQuery($qRaw) ? $like : null;

    // --- Cache hit (page 1 only) ---
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
// 6d. SEARCH ADVANCED — page-level konten dengan banyak kolom + kategori
// =============================================================
function handleSearchAdvanced(): void {
    header('Cache-Control: public, max-age=120');
    $pdo    = getPDO();
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 12;
    $offset = ($page - 1) * $limit;

    $fields = [];
    for ($i = 1; $i <= 5; $i += 1) {
        $val = trim((string)($_GET['q' . $i] ?? ''));
        if ($val !== '') {
            $fields[] = $val;
        }
    }

    if (empty($fields)) {
        echo json_encode(['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0]);
        return;
    }

    $cats = [];
    if (isset($_GET['cats']) && $_GET['cats'] !== '') {
        foreach (explode(',', (string)$_GET['cats']) as $catId) {
            $catId = (int) trim($catId);
            if ($catId > 0) {
                $cats[] = $catId;
            }
        }
    }

    $params = [];
    $whereConditions = [];
    
    // Build LIKE conditions for all search fields
    foreach ($fields as $idx => $field) {
        $key = ':like' . $idx;
        $params[$key] = '%' . $field . '%';
        $whereConditions[] = "bc.content LIKE $key";
    }
    
    $whereClause = count($whereConditions) > 0 
        ? "(" . implode(" AND ", $whereConditions) . ")" 
        : "1";
    
    // Add category filter
    if (!empty($cats)) {
        $catPlaceholders = [];
        foreach ($cats as $idx => $catId) {
            $key = ':cat' . $idx;
            $catPlaceholders[] = $key;
            $params[$key] = $catId;
        }
        $whereClause .= ' AND b.category_id IN (' . implode(',', $catPlaceholders) . ')';
    }

    $sql = "SELECT bc.bkid, b.title, b.author, b.pages, b.category_name,
                bc.page AS match_page,
                LEFT(REPLACE(REPLACE(bc.content, '\n', ' '), '\r', ' '), 280) AS snippet
         FROM book_content bc
         JOIN books b ON b.bkid = bc.bkid
         WHERE $whereClause
         ORDER BY b.title ASC, bc.page ASC
         LIMIT :lim OFFSET :off";
    
    $params[':lim'] = $limit;
    $params[':off'] = $offset;
    
    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query error: ' . $e->getMessage()]);
        return;
    }

    // Count total
    $countSql = "SELECT COUNT(*) FROM book_content bc 
                 JOIN books b ON b.bkid = bc.bkid
                 WHERE $whereClause";
    
    $countParams = [];
    foreach ($params as $key => $value) {
        if ($key !== ':lim' && $key !== ':off') {
            $countParams[$key] = $value;
        }
    }
    
    try {
        $countStmt = $pdo->prepare($countSql);
        foreach ($countParams as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
    } catch (Exception $e) {
        $total = 0;
    }

    echo json_encode([
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
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'id wajib diisi.']); return; }
    // Ambil nama kategori untuk catatan log
    $catRow = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
    $catRow->execute([':id' => $id]);
    $catNameLog = $catRow->fetchColumn() ?: '';
    $pdo->prepare("UPDATE books SET category_id = NULL, category_name = '' WHERE category_id = :id")
        ->execute([':id' => $id]);
    $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id' => $id]);
    logCrudHistory('DELETE', 'categories', (string)$id,
        $catNameLog ? "Nama: {$catNameLog}" : '');
    echo json_encode(['success' => true]);
}

// =============================================================
// ADMIN — Simpan Konten Halaman
// =============================================================
function handleAdminSaveContent(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $bkid    = (int)($data['bkid']    ?? 0);
    $page    = (int)($data['page']    ?? 0);
    $content = $data['content'] ?? '';
    if (!$bkid || !$page) { http_response_code(400); echo json_encode(['error' => 'bkid dan page wajib diisi.']); return; }

    // Cek apakah halaman sudah ada (UPDATE) atau baru (CREATE)
    $existsRow = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid = :bkid AND page = :page");
    $existsRow->execute([':bkid' => $bkid, ':page' => $page]);
    $isUpdate = (int)$existsRow->fetchColumn() > 0;

    $pdo->prepare(
        "INSERT INTO book_content (bkid, page, content)
         VALUES (:bkid, :page, :content)
         ON DUPLICATE KEY UPDATE content = VALUES(content)"
    )->execute([':bkid' => $bkid, ':page' => $page, ':content' => $content]);

    // Sync halaman count di tabel books
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid = :bkid");
    $cnt->execute([':bkid' => $bkid]);
    $pdo->prepare("UPDATE books SET pages = :pages WHERE bkid = :bkid")
        ->execute([':pages' => (int)$cnt->fetchColumn(), ':bkid' => $bkid]);

    logCrudHistory(
        $isUpdate ? 'UPDATE' : 'CREATE',
        'book_content',
        "bkid:{$bkid}|page:{$page}",
        "Kitab ID: {$bkid} | Halaman: {$page}"
    );
    echo json_encode(['success' => true]);
}

// =============================================================
// ADMIN — Hapus Konten Halaman
// =============================================================
function handleAdminDeleteContent(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $bkid = (int)($data['bkid'] ?? 0);
    $page = (int)($data['page'] ?? 0);
    if (!$bkid || !$page) { http_response_code(400); echo json_encode(['error' => 'bkid dan page wajib diisi.']); return; }

    $pdo->prepare("DELETE FROM book_content WHERE bkid = :bkid AND page = :page")
        ->execute([':bkid' => $bkid, ':page' => $page]);

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE bkid = :bkid");
    $cnt->execute([':bkid' => $bkid]);
    $pdo->prepare("UPDATE books SET pages = :pages WHERE bkid = :bkid")
        ->execute([':pages' => (int)$cnt->fetchColumn(), ':bkid' => $bkid]);

    logCrudHistory('DELETE', 'book_content', "bkid:{$bkid}|page:{$page}",
        "Kitab ID: {$bkid} | Halaman: {$page}");
    echo json_encode(['success' => true]);
}

// =============================================================
// ADMIN — Import Kitab dari Word (bulk insert)
// =============================================================
function handleAdminImportBook(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $title   = trim($data['title']   ?? '');
    $author  = trim($data['author']  ?? '');
    $catId   = (int)($data['category_id'] ?? 0);
    $iso     = $data['iso'] ?? 'ar';
    $pages   = $data['pages'] ?? [];   // array of strings (satu per halaman)

    if (!$title)        { http_response_code(400); echo json_encode(['error' => 'Judul wajib diisi.']); return; }
    if (empty($pages))  { http_response_code(400); echo json_encode(['error' => 'Tidak ada halaman untuk diimpor.']); return; }

    $catName = '';
    if ($catId) {
        $cs = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
        $cs->execute([':id' => $catId]);
        $catName = $cs->fetchColumn() ?: '';
    }

    $pdo->beginTransaction();
    try {
        // Buat record kitab
        $stmtBook = $pdo->prepare(
            "INSERT INTO books (title, author, category_id, category_name, iso, pages)
             VALUES (:title, :author, :catid, :catname, :iso, :pages)"
        );
        $stmtBook->execute([
            ':title'   => $title,
            ':author'  => $author,
            ':catid'   => $catId ?: null,
            ':catname' => $catName,
            ':iso'     => $iso,
            ':pages'   => count($pages),
        ]);
        $bkid = (int)$pdo->lastInsertId();

        // Insert semua halaman
        $stmtPage = $pdo->prepare(
            "INSERT INTO book_content (bkid, page, content)
             VALUES (:bkid, :page, :content)"
        );
        foreach ($pages as $i => $pageText) {
            $stmtPage->execute([
                ':bkid'    => $bkid,
                ':page'    => $i + 1,
                ':content' => $pageText,
            ]);
        }

        $pdo->commit();
        logCrudHistory('IMPORT', 'books', (string)$bkid,
            "Judul: {$title}" . ($author ? " | Penulis: {$author}" : '') . ($catName ? " | Kategori: {$catName}" : '') . " | " . count($pages) . " halaman diimpor");
        echo json_encode(['success' => true, 'bkid' => $bkid, 'pages_imported' => count($pages)]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// =============================================================
// ADMIN — Ambil CRUD History (paginated + filter)
// =============================================================
function handleAdminGetHistory(): void {
    $pdo    = getPDO();
    $page   = max(1, (int)($_GET['page']   ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $action    = $_GET['action_filter'] ?? '';
    $tableFil  = $_GET['table_filter']  ?? '';
    $adminFil  = $_GET['admin_filter']  ?? '';

    $where  = [];
    $params = [];

    if ($action && in_array($action, ['CREATE','UPDATE','DELETE','IMPORT'], true)) {
        $where[]  = 'h.action = :action';
        $params[':action'] = $action;
    }
    if ($tableFil) {
        $where[]  = 'h.table_name = :table_name';
        $params[':table_name'] = $tableFil;
    }
    if ($adminFil) {
        $where[]  = '(h.admin_name LIKE :admin OR h.admin_email LIKE :admin)';
        $params[':admin'] = '%' . $adminFil . '%';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $pdo->prepare("SELECT COUNT(*) FROM crud_history h $whereSQL");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT h.id, h.admin_id, h.admin_name, h.admin_email,
                h.action, h.table_name, h.record_id, h.detail,
                h.created_at
         FROM crud_history h
         $whereSQL
         ORDER BY h.created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'total'   => $totalCount,
        'page'    => $page,
        'limit'   => $limit,
        'pages'   => (int)ceil($totalCount / $limit),
    ]);
}
