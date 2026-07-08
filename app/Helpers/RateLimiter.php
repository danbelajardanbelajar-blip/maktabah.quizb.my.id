<?php

namespace App\Helpers;

use App\Config\Database;
use PDO;

/**
 * RateLimiter — IP-based Rate Limiting via Database
 *
 * Menggunakan tabel `rate_limits` yang di-auto-create jika belum ada.
 * Pendekatan sliding window: hitung hit dalam X detik terakhir.
 *
 * Cocok untuk shared hosting tanpa Redis/Memcached.
 */
class RateLimiter {

    /**
     * Cek apakah request masih dalam batas.
     * Jika melewati batas, langsung kirim HTTP 429 dan exit.
     *
     * @param string $key          Identifier unik (misal: "search", "submit")
     * @param int    $maxHits      Jumlah maksimal request yang diizinkan
     * @param int    $windowSecs   Jendela waktu dalam detik (misal: 60)
     * @param string $ip           IP address (default: ambil otomatis)
     */
    public static function check(
        string $key,
        int    $maxHits,
        int    $windowSecs,
        string $ip = ''
    ): void {
        if ($ip === '') {
            $ip = self::getIp();
        }

        try {
            $pdo = Database::getConnection();
            self::ensureTable($pdo);
            self::cleanup($pdo); // bersihkan entry lama secara periodik

            $windowStart = date('Y-m-d H:i:s', time() - $windowSecs);
            $identifier  = $key . ':' . $ip;

            // Hitung hit dalam window
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM rate_limits
                 WHERE identifier = :id AND hit_at >= :ws"
            );
            $stmt->execute([':id' => $identifier, ':ws' => $windowStart]);
            $count = (int)$stmt->fetchColumn();

            if ($count >= $maxHits) {
                // Hitung kapan window reset
                $retryAfter = $windowSecs;
                http_response_code(429);
                header('Content-Type: application/json; charset=utf-8');
                header('Retry-After: ' . $retryAfter);
                echo json_encode([
                    'error'       => 'Terlalu banyak permintaan. Silakan coba lagi dalam beberapa saat.',
                    'retry_after' => $retryAfter,
                ]);
                exit;
            }

            // Catat hit baru
            $pdo->prepare(
                "INSERT INTO rate_limits (identifier, hit_at) VALUES (:id, NOW())"
            )->execute([':id' => $identifier]);

        } catch (\Throwable $e) {
            // Jika tabel belum ada atau DB error, biarkan request lewat
            // (fail open: lebih baik melayani daripada memblokir semua)
            error_log('[RateLimiter] ' . $e->getMessage());
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function getIp(): string {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
           ?? $_SERVER['HTTP_X_REAL_IP']
           ?? $_SERVER['REMOTE_ADDR']
           ?? '0.0.0.0';
        return trim(explode(',', $ip)[0]);
    }

    private static function ensureTable(PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rate_limits (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255)    NOT NULL,
                hit_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_identifier_hit (identifier, hit_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /**
     * Hapus entri yang lebih lama dari 1 jam (cleanup ringan, 1% chance).
     * Menghindari tabel tumbuh tak terbatas tanpa perlu cron job.
     */
    private static function cleanup(PDO $pdo): void {
        if (mt_rand(1, 100) === 1) {
            $pdo->exec("DELETE FROM rate_limits WHERE hit_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        }
    }
}
