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
    if (isPhraseQuery($q)) {
        return '"' . ftEscape(searchPhraseText($q)) . '"';
    }
    if (preg_match('/\s+/u', $q)) {
        return '"' . ftEscape($q) . '"';
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
    return $parts ? implode(' ', $parts) : '';
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
        foreach (explode(',', $_GET['cats']) as $catId) {
            $catId = (int) trim($catId);
            if ($catId > 0) {
                $cats[] = $catId;
            }
        }
    }

    $qStar = booleanSearchQueryFromFields($fields);
    if ($qStar === '') {
        echo json_encode(['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0]);
        return;
    }

    $where = 'WHERE MATCH(bc.content) AGAINST (:q IN BOOLEAN MODE)';
    $params = [':q' => $qStar];
    if (!empty($cats)) {
        $placeholders = implode(',', array_fill(0, count($cats), '?'));
        $where .= " AND b.category_id IN ($placeholders)";
        $params = array_merge($params, $cats);
    }

    $stmt = $pdo->prepare(
        "SELECT bc.bkid, b.title, b.author, b.pages, b.category_name,
                bc.page AS match_page,
                LEFT(REPLACE(bc.content, '\n', ' '), 280) AS snippet,
                MATCH(bc.content) AGAINST (:q IN BOOLEAN MODE) AS rel
         FROM book_content bc
         JOIN books b ON b.bkid = bc.bkid
         $where
         ORDER BY rel DESC, b.title ASC, bc.page ASC
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':q', $qStar, PDO::PARAM_STR);
    foreach ($cats as $idx => $catId) {
        $stmt->bindValue($idx + 1, $catId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $countSql = "SELECT COUNT(*) FROM book_content bc JOIN books b ON b.bkid = bc.bkid $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindValue(':q', $qStar, PDO::PARAM_STR);
    foreach ($cats as $idx => $catId) {
        $countStmt->bindValue($idx + 1, $catId, PDO::PARAM_INT);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

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

    $like = '%' . $q . '%';
    $qStar = $q . '*';

    // ── 1. KATEGORI ──────────────────────────────────────────
    $stmtCat = $pdo->prepare(
        "SELECT c.id, c.name, COUNT(b.bkid) AS book_count
         FROM categories c
         LEFT JOIN books b ON b.category_id = c.id
         WHERE c.name LIKE :lk
         GROUP BY c.id
         ORDER BY book_count DESC
         LIMIT 20"
    );
    $stmtCat->execute([':lk' => $like]);
    $categories = $stmtCat->fetchAll();

    // ── 2. JUDUL / PENGARANG KITAB ───────────────────────────
    $bookOffset = ($bookPage - 1) * $limit;

    $stmtBooks = $pdo->prepare(
        "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name,
                MATCH(b.title) AGAINST (:q1 IN BOOLEAN MODE) AS rel
         FROM books b
         WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
            OR b.title  LIKE :lk
            OR b.author LIKE :la
         ORDER BY rel DESC, b.title ASC
         LIMIT :lim OFFSET :off"
    );
    $stmtBooks->bindValue(':q1',  $qStar, PDO::PARAM_STR);
    $stmtBooks->bindValue(':q2',  $qStar, PDO::PARAM_STR);
    $stmtBooks->bindValue(':lk',  $like,  PDO::PARAM_STR);
    $stmtBooks->bindValue(':la',  $like,  PDO::PARAM_STR);
    $stmtBooks->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmtBooks->bindValue(':off', $bookOffset, PDO::PARAM_INT);
    $stmtBooks->execute();
    $books = $stmtBooks->fetchAll();

    $stmtBooksCount = $pdo->prepare(
        "SELECT COUNT(*) FROM books
         WHERE MATCH(title) AGAINST (:q IN BOOLEAN MODE)
            OR title LIKE :lk OR author LIKE :la"
    );
    $stmtBooksCount->execute([':q' => $qStar, ':lk' => $like, ':la' => $like]);
    $booksTotal = (int)$stmtBooksCount->fetchColumn();

    // -- cache buku (opsional, tetap berguna) --
    if ($bookPage === 1 && $booksTotal > 0) {
        $hash = hash('sha256', 'books:' . strtolower($q));
        $pdo->prepare(
            "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
             VALUES (:h, :qt, :rj, :rc, DATE_ADD(NOW(), INTERVAL 1 HOUR))
             ON DUPLICATE KEY UPDATE results_json=VALUES(results_json),
             result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
        )->execute([':h' => $hash, ':qt' => $q, ':rj' => json_encode($books), ':rc' => $booksTotal]);
    }

    // ── 3. ISI KITAB (book_content) ──────────────────────────
    $contOffset = ($contPage - 1) * $limit;

    // Ambil buku unik yang isinya cocok, sertakan cuplikan halaman pertama yang cocok
    $stmtContent = $pdo->prepare(
        "SELECT b.bkid, b.title, b.author, b.pages, b.category_name,
                (SELECT bc2.page
                 FROM book_content bc2
                 WHERE bc2.bkid = b.bkid
                   AND (MATCH(bc2.content) AGAINST (:qs1 IN BOOLEAN MODE) OR bc2.content LIKE :ls1)
                 ORDER BY MATCH(bc2.content) AGAINST (:qs2 IN BOOLEAN MODE) DESC
                 LIMIT 1) AS match_page,
                (SELECT LEFT(bc2.content, 260)
                 FROM book_content bc2
                 WHERE bc2.bkid = b.bkid
                   AND (MATCH(bc2.content) AGAINST (:qs3 IN BOOLEAN MODE) OR bc2.content LIKE :ls2)
                 ORDER BY MATCH(bc2.content) AGAINST (:qs4 IN BOOLEAN MODE) DESC
                 LIMIT 1) AS snippet
         FROM books b
         WHERE EXISTS (
             SELECT 1 FROM book_content bc
             WHERE bc.bkid = b.bkid
               AND (MATCH(bc.content) AGAINST (:q IN BOOLEAN MODE) OR bc.content LIKE :lk)
         )
         ORDER BY b.title ASC
         LIMIT :lim OFFSET :off"
    );
    $stmtContent->bindValue(':qs1', $qStar, PDO::PARAM_STR);
    $stmtContent->bindValue(':qs2', $qStar, PDO::PARAM_STR);
    $stmtContent->bindValue(':qs3', $qStar, PDO::PARAM_STR);
    $stmtContent->bindValue(':qs4', $qStar, PDO::PARAM_STR);
    $stmtContent->bindValue(':ls1', $like,  PDO::PARAM_STR);
    $stmtContent->bindValue(':ls2', $like,  PDO::PARAM_STR);
    $stmtContent->bindValue(':q',   $qStar, PDO::PARAM_STR);
    $stmtContent->bindValue(':lk',  $like,  PDO::PARAM_STR);
    $stmtContent->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmtContent->bindValue(':off', $contOffset, PDO::PARAM_INT);
    $stmtContent->execute();
    $contentBooks = $stmtContent->fetchAll();

    $stmtContCount = $pdo->prepare(
        "SELECT COUNT(DISTINCT bc.bkid) FROM book_content bc
         WHERE MATCH(bc.content) AGAINST (:q IN BOOLEAN MODE) OR bc.content LIKE :lk"
    );
    $stmtContCount->execute([':q' => $qStar, ':lk' => $like]);
    $contTotal = (int)$stmtContCount->fetchColumn();

    // ── Respon ───────────────────────────────────────────────
    echo json_encode([
        'query'      => $q,
        'categories' => $categories,
        'books'      => [
            'data'        => $books,
            'total'       => $booksTotal,
            'page'        => $bookPage,
            'total_pages' => (int)ceil($booksTotal / $limit),
        ],
        'content'    => [
            'data'        => $contentBooks,
            'total'       => $contTotal,
            'page'        => $contPage,
            'total_pages' => (int)ceil($contTotal / $limit),
        ],
    ]);
}

// =============================================================
// 5. LATEST — recent additions for Hero section
// =============================================================
function handleLatest(): void {
    $pdo   = getPDO();
    $limit = min(12, max(1, (int)($_GET['limit'] ?? 8)));
    $stmt  = $pdo->prepare(
        "SELECT bkid, title, author, pages, iso, category_name
         FROM books ORDER BY bkid DESC LIMIT :lim"
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode(['data' => $stmt->fetchAll()]);
}

// =============================================================
//  AUTH — kembalikan data user session saat ini
// =============================================================
function handleAuthMe(): void {
    header('Cache-Control: no-store');
    $user = getSessionUser();
    if ($user) {
        echo json_encode(['loggedIn' => true, 'user' => $user]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
}

// =============================================================
//  ADMIN — CRUD KITAB
// =============================================================

/** Tambah atau edit kitab. POST body: title, author, pages, iso, category_id, [bkid] */
function handleAdminSaveBook(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $title      = trim($data['title']       ?? '');
    $author     = trim($data['author']      ?? '');
    $pages      = (int)($data['pages']      ?? 0);
    $iso        = trim($data['iso']         ?? 'ar');
    $categoryId = (int)($data['category_id'] ?? 0);
    $bkid       = isset($data['bkid']) ? (int)$data['bkid'] : 0;

    if (!$title) {
        http_response_code(400);
        echo json_encode(['error' => 'Judul kitab tidak boleh kosong.']);
        return;
    }

    // Ambil nama kategori
    $catName = '';
    if ($categoryId > 0) {
        $cs = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
        $cs->execute([':id' => $categoryId]);
        $catName = (string)($cs->fetchColumn() ?: '');
    }

    if ($bkid > 0) {
        // UPDATE
        $stmt = $pdo->prepare(
            "UPDATE books SET title=:t, author=:a, pages=:p, iso=:i,
                              category_id=:cid, category_name=:cname
             WHERE bkid=:id"
        );
        $stmt->execute([
            ':t'     => $title,
            ':a'     => $author,
            ':p'     => $pages,
            ':i'     => $iso,
            ':cid'   => $categoryId ?: null,
            ':cname' => $catName,
            ':id'    => $bkid,
        ]);
        echo json_encode(['success' => true, 'bkid' => $bkid, 'action' => 'updated']);
    } else {
        // INSERT
        $stmt = $pdo->prepare(
            "INSERT INTO books (title, author, pages, iso, category_id, category_name)
             VALUES (:t, :a, :p, :i, :cid, :cname)"
        );
        $stmt->execute([
            ':t'     => $title,
            ':a'     => $author,
            ':p'     => $pages,
            ':i'     => $iso,
            ':cid'   => $categoryId ?: null,
            ':cname' => $catName,
        ]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'bkid' => $newId, 'action' => 'created']);
    }
}

/** Hapus kitab beserta seluruh isinya */
function handleAdminDeleteBook(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $bkid = (int)($data['bkid'] ?? 0);

    if ($bkid <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'bkid tidak valid.']);
        return;
    }

    $pdo->prepare("DELETE FROM book_content WHERE bkid = :id")->execute([':id' => $bkid]);
    $pdo->prepare("DELETE FROM books WHERE bkid = :id")->execute([':id' => $bkid]);

    echo json_encode(['success' => true, 'deleted_bkid' => $bkid]);
}

// =============================================================
//  ADMIN — CRUD KATEGORI
// =============================================================

/** Tambah atau edit kategori. POST body: name, catord, lvl, [id] */
function handleAdminSaveCategory(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $name   = trim($data['name']   ?? '');
    $catord = (int)($data['catord'] ?? 0);
    $lvl    = (int)($data['lvl']    ?? 1);
    $id     = isset($data['id']) ? (int)$data['id'] : 0;

    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Nama kategori tidak boleh kosong.']);
        return;
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE categories SET name=:n, catord=:o, lvl=:l WHERE id=:id")
            ->execute([':n' => $name, ':o' => $catord, ':l' => $lvl, ':id' => $id]);
        // Sync category_name di tabel books
        $pdo->prepare("UPDATE books SET category_name=:n WHERE category_id=:id")
            ->execute([':n' => $name, ':id' => $id]);
        echo json_encode(['success' => true, 'id' => $id, 'action' => 'updated']);
    } else {
        $pdo->prepare("INSERT INTO categories (name, catord, lvl) VALUES (:n, :o, :l)")
            ->execute([':n' => $name, ':o' => $catord, ':l' => $lvl]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'action' => 'created']);
    }
}

/** Hapus kategori (kitab dalam kategori menjadi tidak berkategori) */
function handleAdminDeleteCategory(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID kategori tidak valid.']);
        return;
    }

    // Lepas relasi di books dulu
    $pdo->prepare("UPDATE books SET category_id=NULL, category_name='' WHERE category_id=:id")
        ->execute([':id' => $id]);
    $pdo->prepare("DELETE FROM categories WHERE id=:id")->execute([':id' => $id]);

    echo json_encode(['success' => true, 'deleted_id' => $id]);
}

// =============================================================
//  ADMIN — CRUD KONTEN KITAB
// =============================================================

/** Simpan/update halaman konten. POST body: bkid, page, content */
function handleAdminSaveContent(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $bkid    = (int)($data['bkid']    ?? 0);
    $page    = (int)($data['page']    ?? 0);
    $content = $data['content'] ?? '';

    if ($bkid <= 0 || $page <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'bkid dan page harus valid.']);
        return;
    }

    // Cek kitab ada
    $bs = $pdo->prepare("SELECT bkid FROM books WHERE bkid=:id LIMIT 1");
    $bs->execute([':id' => $bkid]);
    if (!$bs->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Kitab tidak ditemukan.']);
        return;
    }

    $pdo->prepare(
        "INSERT INTO book_content (bkid, page, content)
         VALUES (:b, :p, :c)
         ON DUPLICATE KEY UPDATE content = VALUES(content)"
    )->execute([':b' => $bkid, ':p' => $page, ':c' => $content]);

    // Update kolom pages di books agar sesuai jumlah konten
    $pdo->prepare(
        "UPDATE books SET pages = (SELECT COUNT(*) FROM book_content WHERE bkid=:b) WHERE bkid=:b2"
    )->execute([':b' => $bkid, ':b2' => $bkid]);

    echo json_encode(['success' => true, 'bkid' => $bkid, 'page' => $page]);
}

/** Hapus satu halaman konten. POST body: bkid, page */
function handleAdminDeleteContent(): void {
    $pdo  = getPDO();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $bkid = (int)($data['bkid'] ?? 0);
    $page = (int)($data['page'] ?? 0);

    if ($bkid <= 0 || $page <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'bkid dan page harus valid.']);
        return;
    }

    $pdo->prepare("DELETE FROM book_content WHERE bkid=:b AND page=:p")
        ->execute([':b' => $bkid, ':p' => $page]);

    // Sinkronisasi jumlah halaman
    $pdo->prepare(
        "UPDATE books SET pages = (SELECT COUNT(*) FROM book_content WHERE bkid=:b) WHERE bkid=:b2"
    )->execute([':b' => $bkid, ':b2' => $bkid]);

    echo json_encode(['success' => true, 'deleted' => ['bkid' => $bkid, 'page' => $page]]);
}

