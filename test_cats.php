<?php
require __DIR__ . '/app/bootstrap.php';
$db = \App\Config\Database::getConnection();
$stmt = $db->query('SELECT * FROM categories');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
