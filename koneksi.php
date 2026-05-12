<?php
// =============================================================
//  Al-Maktabah As-Sunniyyah — Database Connection (PDO)
// =============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'quic1934_maktabah');
define('DB_USER', 'quic1934_maktabah');
define('DB_PASS', '[MASUKKAN_PASSWORD_ANDA_DI_SINI]');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed.']);
            exit;
        }
    }
    return $pdo;
}
