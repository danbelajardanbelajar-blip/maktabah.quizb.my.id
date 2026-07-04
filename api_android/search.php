<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require '../app/bootstrap.php';

use App\Config\Database;
use App\Helpers\SearchHelper;

$pdo      = Database::getConnection();
$qRaw     = trim($_GET['q'] ?? '');
$q        = SearchHelper::searchPhraseText($qRaw);
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 5;

$empty = [
    'query'   => $q,
    'books'   => ['data' => []],
    'content' => ['data' => [], 'next_page' => null]
];

if (strlen($q) < 2) {
    echo json_encode($empty);
    exit;
}

$qStar = SearchHelper::booleanSearchTerm($qRaw);
$books = [];

// 1. Books (Hanya di halaman 1)
if ($page === 1) {
    $stmtBooks = $pdo->prepare(
        "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name,
                MATCH(b.title) AGAINST (:q1 IN BOOLEAN MODE) AS rel
         FROM books b
         WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
         ORDER BY rel DESC, b.title ASC
         LIMIT 10"
    );
    $stmtBooks->bindValue(':q1',  $qStar, PDO::PARAM_STR);
    $stmtBooks->bindValue(':q2',  $qStar, PDO::PARAM_STR);
    $stmtBooks->execute();
    $books = $stmtBooks->fetchAll();
}

$contents = [];
// 2. Contents (dengan LIMIT dan OFFSET, menggunakan 2-step FULLTEXT query)
$contOffset = ($page - 1) * $limit;
$fetchLimit = $limit + 1; // Ambil 1 ekstra untuk mengecek ketersediaan halaman berikutnya

// Step 1: Scan FULLTEXT pada book_content saja
$step1 = $pdo->prepare(
    "SELECT bkid, page, MATCH(content) AGAINST (:q1 IN BOOLEAN MODE) AS rel
     FROM book_content
     WHERE MATCH(content) AGAINST (:q2 IN BOOLEAN MODE)
     ORDER BY rel DESC, bkid ASC, page ASC
     LIMIT :lim OFFSET :off"
);
$step1->bindValue(':q1',  $qStar, PDO::PARAM_STR);
$step1->bindValue(':q2',  $qStar, PDO::PARAM_STR);
$step1->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$step1->bindValue(':off', $contOffset, PDO::PARAM_INT);
$step1->execute();
$topRows = $step1->fetchAll();

$nextPage = null;
if (count($topRows) > $limit) {
    $nextPage = $page + 1;
    array_pop($topRows); // Buang item ekstra
}

if (!empty($topRows)) {
    $pairConds  = [];
    $pairParams = [];
    foreach ($topRows as $i => $r) {
        $bk = ':bk' . $i;
        $pg = ':pg' . $i;
        $pairConds[]   = "(bc.bkid = $bk AND bc.page = $pg)";
        $pairParams[$bk] = (int)$r['bkid'];
        $pairParams[$pg] = (int)$r['page'];
    }

    // Step 2: JOIN ke tabel books berdasarkan hasil step 1 yang sangat kecil (limit 5)
    $step2Sql = "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page, b.title, b.author, b.category_name,
                        bc.content AS snippet
                 FROM book_content bc
                 JOIN books b ON b.bkid = bc.bkid
                 WHERE " . implode(' OR ', $pairConds);

    $step2 = $pdo->prepare($step2Sql);
    foreach ($pairParams as $k => $v) {
        $step2->bindValue($k, $v, PDO::PARAM_INT);
    }
    $step2->execute();
    $step2Results = $step2->fetchAll();

    // Map by key agar urutannya (Relevansi) tetap sama seperti topRows (Step 1)
    $byKey = [];
    foreach ($step2Results as $r) {
        $byKey[$r['bkid'] . '_' . $r['match_page']] = $r;
    }

    foreach ($topRows as $tr) {
        $key = $tr['bkid'] . '_' . $tr['page'];
        if (isset($byKey[$key])) {
            $row = $byKey[$key];
            $row['snippet'] = SearchHelper::extractSmartSnippet($row['snippet'], $q);
            $contents[] = $row;
        }
    }
}

echo json_encode([
    'query'   => $q,
    'books'   => [
        'data' => $books
    ],
    'content' => [
        'data' => $contents,
        'next_page' => $nextPage
    ]
]);
