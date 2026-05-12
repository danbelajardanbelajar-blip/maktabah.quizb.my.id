<?php
// =============================================================
//  Al-Maktabah As-Sunniyyah — REST-like JSON API
// =============================================================

require_once __DIR__ . '/koneksi.php';

// --- CORS & Headers ---
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');

// --- Router ---
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'books':      handleBooks();      break;
        case 'book':       handleBook();       break;
        case 'content':    handleContent();    break;
        case 'categories': handleCategories(); break;
        case 'search':     handleSearch();     break;
        case 'latest':     handleLatest();     break;
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
// 4. SEARCH — fulltext + LIKE fallback, with cache
// =============================================================
function handleSearch(): void {
    $pdo   = getPDO();
    $q     = trim($_GET['q'] ?? '');
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(48, max(1, (int)($_GET['limit'] ?? 24)));

    if (strlen($q) < 2) {
        echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0]);
        return;
    }

    $hash   = hash('sha256', strtolower($q));
    $offset = ($page - 1) * $limit;

    // -- check cache (first page only for simplicity) --
    if ($page === 1) {
        $cs = $pdo->prepare("SELECT results_json, result_count FROM search_cache WHERE query_hash = :h AND expires_at > NOW() LIMIT 1");
        $cs->execute([':h' => $hash]);
        $cached = $cs->fetch();
        if ($cached) {
            $all   = json_decode($cached['results_json'], true);
            $slice = array_slice($all, $offset, $limit);
            echo json_encode([
                'data'        => $slice,
                'total'       => (int)$cached['result_count'],
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int)ceil($cached['result_count'] / $limit),
                'cached'      => true,
            ]);
            return;
        }
    }

    // -- fulltext search --
    $likePct = '%' . $q . '%';
    $sql = "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name,
                   MATCH(b.title) AGAINST (:q1 IN BOOLEAN MODE) AS rel
            FROM books b
            WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
               OR b.title  LIKE :lk
               OR b.author LIKE :la
            ORDER BY rel DESC, b.title ASC
            LIMIT :lim OFFSET :off";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':q1',  $q . '*', PDO::PARAM_STR);
    $stmt->bindValue(':q2',  $q . '*', PDO::PARAM_STR);
    $stmt->bindValue(':lk',  $likePct, PDO::PARAM_STR);
    $stmt->bindValue(':la',  $likePct, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll();

    // count
    $stmtC = $pdo->prepare(
        "SELECT COUNT(*) FROM books
         WHERE MATCH(title) AGAINST (:q IN BOOLEAN MODE)
            OR title LIKE :lk OR author LIKE :la"
    );
    $stmtC->execute([':q' => $q . '*', ':lk' => $likePct, ':la' => $likePct]);
    $total = (int)$stmtC->fetchColumn();

    // -- store cache (first page only) --
    if ($page === 1 && $total > 0) {
        $allStmt = $pdo->prepare(
            "SELECT bkid, title, author, pages, iso, category_id, category_name
             FROM books
             WHERE MATCH(title) AGAINST (:q IN BOOLEAN MODE)
                OR title LIKE :lk OR author LIKE :la
             ORDER BY MATCH(title) AGAINST (:q2 IN BOOLEAN MODE) DESC, title ASC
             LIMIT 500"
        );
        $allStmt->execute([':q' => $q . '*', ':lk' => $likePct, ':la' => $likePct, ':q2' => $q . '*']);
        $allResults = $allStmt->fetchAll();
        $pdo->prepare(
            "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
             VALUES (:h, :qt, :rj, :rc, DATE_ADD(NOW(), INTERVAL 1 HOUR))
             ON DUPLICATE KEY UPDATE results_json=VALUES(results_json), result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
        )->execute([':h' => $hash, ':qt' => $q, ':rj' => json_encode($allResults), ':rc' => $total]);
    }

    echo json_encode([
        'data'        => $books,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => (int)ceil($total / $limit),
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

