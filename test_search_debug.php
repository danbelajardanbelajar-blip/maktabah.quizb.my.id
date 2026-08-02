<?php
require 'app/bootstrap.php';
use App\Config\Database;
use App\Helpers\SearchHelper;

$pdo = Database::getConnection();
$q = "فتح القريب";
$qClean = str_replace(["'", "’"], "", $q);
$words = preg_split('/\s+/u', $qClean);

$andConds = [];
$params = [];
foreach ($words as $i => $w) {
    if (strlen($w) > 0) {
        $andConds[] = "REPLACE(REPLACE(b.title, '''', ''), '’', '') LIKE :lka$i";
        $params[":lka$i"] = "%$w%";
    }
}
$andSql = implode(' AND ', $andConds);

$qStar = SearchHelper::booleanSearchTermAnd($q);
echo "qStar: $qStar\n";
echo "andSql: $andSql\n";
print_r($params);

$sql = "SELECT b.bkid, b.title 
        FROM books b 
        WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
           OR ($andSql)
        LIMIT 10";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':q2', $qStar, PDO::PARAM_STR);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($results) . "\n";
print_r($results);

// Test old OR logic
$likeConds = [];
foreach ($words as $i => $w) {
    if (strlen($w) > 0) {
        $likeConds[] = "REPLACE(REPLACE(b.title, '''', ''), '’', '') LIKE :lk$i";
    }
}
$orSql = implode(' OR ', $likeConds);
$qStarOr = SearchHelper::booleanSearchTermOr($q);
$sqlOr = "SELECT b.bkid, b.title 
        FROM books b 
        WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
           OR ($orSql)
        LIMIT 10";
$stmt = $pdo->prepare($sqlOr);
$stmt->bindValue(':q2', $qStarOr, PDO::PARAM_STR);
foreach ($params as $k => $v) {
    $stmt->bindValue(str_replace('lka', 'lk', $k), $v, PDO::PARAM_STR);
}
$stmt->execute();
$resultsOr = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count OR: " . count($resultsOr) . "\n";
