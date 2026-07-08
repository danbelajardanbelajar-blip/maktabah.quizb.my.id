<?php
// =============================================================
//  Al-Maktabah As-Sunniyyah — System Bootstrap & Loader
// =============================================================

// Simple PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

// Setup Database Session Handler
if (session_status() === PHP_SESSION_NONE) {
    try {
        $pdo = \App\Config\Database::getConnection();
        $sessionHandler = new \App\Helpers\DatabaseSessionHandler($pdo);
        session_set_save_handler($sessionHandler, true);
    } catch (\Throwable $e) {
        error_log('[Bootstrap] Failed to initialize Database Session Handler: ' . $e->getMessage());
        // Fallback to default session handler if DB fails
    }

    session_start();
}

// Auto-login check (Remember Me)
\App\Helpers\AuthHelper::checkRememberMe();

// Pastikan CSRF token selalu ada di session setelah login/session start
// Token dipakai oleh SPA (dikirim via header X-CSRF-Token) dan divalidasi di api.php
\App\Helpers\CsrfHelper::getToken();
