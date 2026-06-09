<?php
require_once __DIR__ . '/koneksi.php';
$pdo = getPDO();
print_r($pdo->query('SHOW VARIABLES LIKE "innodb_ft_min_token_size"')->fetch(PDO::FETCH_ASSOC));
$query = "SELECT bkid, title FROM books WHERE MATCH(title) AGAINST ('+في*' IN BOOLEAN MODE) LIMIT 5";
print_r($pdo->query($query)->fetchAll(PDO::FETCH_ASSOC));
