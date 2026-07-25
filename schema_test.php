<?php
require 'app/bootstrap.php';
$pdo = \App\Config\Database::getConnection();
$stmt = $pdo->query('SHOW CREATE TABLE categories');
$res = $stmt->fetch(PDO::FETCH_ASSOC);
echo $res['Create Table'];
