<?php

namespace App\Helpers;

/**
 * CsrfHelper — CSRF Token Protection
 *
 * Pola: Double Submit + Session Token
 * - Token disimpan di $_SESSION['csrf_token']
 * - Dikirim client via header X-CSRF-Token (AJAX) atau hidden field (form)
 * - Validasi dilakukan di api.php untuk semua request POST
 */
class CsrfHelper {

    private const SESSION_KEY = 'csrf_token';

    /**
     * Kembalikan token CSRF aktif.
     * Jika belum ada di session, buat token baru.
     */
    public static function getToken(): string {
        if (empty($_SESSION[self::SESSION_KEY])) {
            self::generate();
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Buat token baru dan simpan ke session.
     */
    public static function generate(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;
        return $token;
    }

    /**
     * Validasi token dari request.
     * Mengecek header X-CSRF-Token (AJAX) atau _csrf (form POST).
     *
     * @return bool true = valid, false = invalid / tidak ada
     */
    public static function validate(): bool {
        $session = $_SESSION[self::SESSION_KEY] ?? '';
        if ($session === '') {
            return false;
        }

        // Cek header AJAX terlebih dahulu (cara utama dari SPA)
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($headerToken !== '') {
            return hash_equals($session, $headerToken);
        }

        // Fallback: cek POST field _csrf (untuk form HTML biasa)
        $postToken = $_POST['_csrf'] ?? '';
        if ($postToken !== '') {
            return hash_equals($session, $postToken);
        }

        return false;
    }

    /**
     * Validasi dan langsung kirim 403 + exit jika gagal.
     * Digunakan di api.php untuk satu baris perlindungan.
     */
    public static function requireValid(): void {
        if (!self::validate()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'CSRF token tidak valid atau sudah kedaluwarsa. Silakan muat ulang halaman.']);
            exit;
        }
    }
}
