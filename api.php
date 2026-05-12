<?php
// =============================================================
//  Al-Maktabah As-Sunniyyah — REST-like JSON API
// =============================================================

require_once __DIR__ . '/koneksi.php';

// --- CORS & Headers ---
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');

// Helper — escape FULLTEXT boolean wildcards safely
function ftEscape(string $q): string {
    // Strip chars that could break boolean mode, keep Arabic/Latin safely
    return preg_replace('/[+\-><()~*"@]+/', ' ', $q);
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
    $pdo  = getPDO();
    $q    = trim($_GET['q'] ?? '');
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
    $q     = trim($_GET['q'] ?? '');
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;

    if (strlen($q) < 2) {
        echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
        return;
    }

    $hash  = 'books:' . hash('sha256', strtolower($q));
    $qStar = ftEscape($q) . '*';
    $like  = '%' . $q . '%';

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
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll();

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM books
         WHERE MATCH(title) AGAINST (:q IN BOOLEAN MODE)
            OR title LIKE :lk OR author LIKE :la"
    );
    $countStmt->execute([':q' => $qStar, ':lk' => $like, ':la' => $like]);
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
    $q     = trim($_GET['q'] ?? '');
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;

    if (strlen($q) < 2) {
        echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
        return;
    }

    $qStar = ftEscape($q) . '*';
    $hash  = 'content:' . hash('sha256', strtolower($q));

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
    // Build parameterized placeholders for the MATCH() calls in subquery
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

