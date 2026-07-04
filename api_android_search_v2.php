<?php
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');
header('Content-Type: application/json');

require_once __DIR__ . '/app/bootstrap.php';

use App\Config\Database;
use App\Helpers\SearchHelper;

try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$qRaw     = trim($_GET['q'] ?? '');
$q        = SearchHelper::searchPhraseText($qRaw);
$bookPage = max(1, (int)($_GET['book_page']    ?? 1));
$contPage = max(1, (int)($_GET['content_page'] ?? 1));
$limit    = 12;

$empty = [
    'query'      => $q,
    'categories' => [],
    'books'      => ['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0],
    'content'    => ['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0],
];

if (strlen($q) < 2) { 
    echo json_encode($empty); 
    exit; 
}

$like  = '%' . $q . '%';

// 1. Books by title/author
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
$books = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

$stmtBooksCount = $pdo->prepare(
    "SELECT COUNT(*) FROM books WHERE title LIKE :lk OR author LIKE :lk2"
);
$stmtBooksCount->execute([':lk' => $like, ':lk2' => $like]);
$booksTotal = (int)$stmtBooksCount->fetchColumn();

// 2. Content (Optimized with LIKE since FULLTEXT may fail for short words or missing indexes)
$contOffset = ($contPage - 1) * $limit;
$stmtCont = $pdo->prepare(
    "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page, b.title, b.author, b.category_name,
            bc.content AS snippet
     FROM book_content bc
     JOIN books b ON b.bkid = bc.bkid
     WHERE bc.content LIKE :q
     ORDER BY bc.bkid DESC, bc.page ASC
     LIMIT :lim OFFSET :off"
);
$stmtCont->bindValue(':q',  $like, PDO::PARAM_STR);
$stmtCont->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmtCont->bindValue(':off', $contOffset, PDO::PARAM_INT);
$stmtCont->execute();
$contentRows = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

$terms = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
$content = array_map(function($row) use ($terms) {
    $row['snippet'] = SearchHelper::extractSmartSnippet((string)($row['snippet'] ?? ''), $terms);
    return $row;
}, $contentRows);

$stmtContCount = $pdo->prepare(
    "SELECT COUNT(*) FROM book_content WHERE content LIKE :q"
);
$stmtContCount->execute([':q' => $like]);
$contTotal = (int)$stmtContCount->fetchColumn();

// Response
echo json_encode([
    'query'        => $q,
    'books'        => [
        'data' => $books, 'total' => $booksTotal,
        'page' => $bookPage, 'total_pages' => (int)ceil($booksTotal / $limit),
    ],
    'content'      => [
        'data' => $content, 'total' => $contTotal,
        'page' => $contPage, 'total_pages' => (int)ceil($contTotal / $limit),
    ],
]);
