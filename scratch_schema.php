<?php
require 'app/bootstrap.php';
$pdo = \App\Config\Database::getConnection();
$stmt = $pdo->query("SHOW CREATE TABLE db_sessions");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
