<?php
require 'app/bootstrap.php';
$pdo = App\Config\Database::getConnection();
$cols = $pdo->query('DESCRIBE users')->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $col) echo $col['Field'] . "\n";
