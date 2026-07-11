<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=quic1934_maktabah;charset=utf8mb4", 'quic1934_zenhkm', '03Maret1990', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $stmt = $pdo->query('SELECT COUNT(*) FROM search_dictionary');
    echo 'COUNT: ' . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
