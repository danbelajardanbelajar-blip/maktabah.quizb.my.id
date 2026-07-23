<?php
require 'app/Config/Database.php';
$pdo = \App\Config\Database::getConnection();

// Check if التهجد exists
$stmt = $pdo->prepare("SELECT count(*) FROM book_content WHERE content LIKE '%التهجد%'");
$stmt->execute();
echo "Pages with التهجد: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->prepare("SELECT count(*) FROM book_content WHERE MATCH(content) AGAINST ('التهجد' IN BOOLEAN MODE)");
$stmt->execute();
echo "Pages with MATCH التهجد: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->prepare("SELECT count(*) FROM book_content WHERE content LIKE '%حكم%'");
$stmt->execute();
echo "Pages with حكم: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->prepare("SELECT count(*) FROM book_content WHERE content LIKE '%صلاة%'");
$stmt->execute();
echo "Pages with صلاة: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->prepare("SELECT count(*) FROM book_content WHERE MATCH(content) AGAINST ('+حكم* +صلاة* +التهجد*' IN BOOLEAN MODE)");
$stmt->execute();
echo "Pages with ALL THREE (boolean): " . $stmt->fetchColumn() . "\n";
