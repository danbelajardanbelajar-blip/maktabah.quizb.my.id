<?php
require 'app/Config/Database.php';
$pdo = \App\Config\Database::getConnection();
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 1");
$u = $stmt->fetch();
var_dump($u['role']);
var_dump($u['role'] === 'admin');
