<?php

namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
            $db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'quic1934_maktabah';
            $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'quic1934_zenhkm';
            $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Database connection failed.']);
                exit;
            }
        }
        return self::$pdo;
    }
}
