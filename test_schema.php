<?php
require 'koneksi.php';
$pdo = getPDO();
print_r($pdo->query('SHOW COLUMNS FROM book_content')->fetchAll(PDO::FETCH_ASSOC));
