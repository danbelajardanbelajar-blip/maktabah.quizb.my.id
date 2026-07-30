<?php
require_once __DIR__.'/app/bootstrap.php';
$pdo = \App\Config\Database::getConnection();
$stmt = $pdo->query('SELECT title, COUNT(*) as c FROM books GROUP BY title HAVING c > 1 LIMIT 10');
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($duplicates);
