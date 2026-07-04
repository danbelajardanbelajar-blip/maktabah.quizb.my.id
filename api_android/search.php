<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Config\Database;
use App\Helpers\SearchHelper;

$pdo      = Database::getConnection();
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 5;

$empty = [
    'query'      => $q,
    'books'      => ['data' => []],
    'content'    => ['data' => [], 'next_page' => null],
];

if (strlen($q) < 2) {
    echo json_encode($empty);
    return;
}

$like  = '%' . $q . '%';

$books = [];
// 1. Books (Hanya di halaman 1)
if ($page === 1) {
    $stmtBooks = $pdo->prepare(
        "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name
         FROM books b
         WHERE b.title LIKE :lk OR b.author LIKE :lk2
         ORDER BY b.bkid DESC LIMIT 10" // Batasi maksimal 10 kitab teratas
    );
    $stmtBooks->bindValue(':lk',  $like, PDO::PARAM_STR);
    $stmtBooks->bindValue(':lk2', $like, PDO::PARAM_STR);
    $stmtBooks->execute();
    $books = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Content (Menggunakan LIKE tanpa COUNT(*) agar cepat, limit + 1 untuk deteksi next page)
$contOffset = ($page - 1) * $limit;
$fetchLimit = $limit + 1;

// Eksekusi query
$stmtCont = $pdo->prepare(
    "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page, b.title, b.author, b.category_name,
            bc.content AS snippet
     FROM book_content bc
     JOIN books b ON b.bkid = bc.bkid
     WHERE bc.content LIKE :lk
     LIMIT :lim OFFSET :off"
);
$stmtCont->bindValue(':lk',  $like, PDO::PARAM_STR);
$stmtCont->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$stmtCont->bindValue(':off', $contOffset, PDO::PARAM_INT);
$stmtCont->execute();
$contentRows = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

$hasNextPage = false;
if (count($contentRows) > $limit) {
    $hasNextPage = true;
    array_pop($contentRows); // Buang elemen ke-13
}

$terms = preg_split('/\s+/u', SearchHelper::searchPhraseText($q), -1, PREG_SPLIT_NO_EMPTY);
$content = array_map(function($row) use ($terms) {
    $row['snippet'] = SearchHelper::extractSmartSnippet((string)($row['snippet'] ?? ''), $terms);
    return $row;
}, $contentRows);

// Response
echo json_encode([
    'query'   => $q,
    'books'   => [
        'data' => $books
    ],
    'content' => [
        'data' => $content, 
        'next_page' => $hasNextPage ? ($page + 1) : null
    ],
]);
