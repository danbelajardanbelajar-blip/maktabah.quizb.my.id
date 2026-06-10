<?php
// =============================================================
//  Al-Maktabah As-Sunniyyah — API Entry Point (Front Controller)
// =============================================================

header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');
session_start();

// Simple PSR-4 Autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Router;

// Initialize Router
$router = new Router();

// Books
$router->add('books', 'BookController', 'handleBooks');
$router->add('book', 'BookController', 'handleBook');
$router->add('download_book', 'BookController', 'handleDownloadBook');
$router->add('content', 'BookController', 'handleContent');
$router->add('latest', 'BookController', 'handleLatest');

// Categories
$router->add('categories', 'CategoryController', 'handleCategories');

// Search
$router->add('search_categories', 'SearchController', 'handleSearchCategories');
$router->add('search_books', 'SearchController', 'handleSearchBooks');
$router->add('search_content', 'SearchController', 'handleSearchContent');
$router->add('search_advanced', 'SearchController', 'handleSearchAdvanced');
$router->add('search_books_with_content', 'SearchController', 'handleSearchBooksWithContent');
$router->add('search_content_in_book', 'SearchController', 'handleSearchContentInBook');
$router->add('search', 'SearchController', 'handleSearch');
$router->add('recent_searches', 'SearchController', 'handleRecentSearches');
$router->add('search_recommendations', 'SearchController', 'handleSearchRecommendations');

// Auth
$router->add('auth_me', 'AuthController', 'handleAuthMe');
$router->add('log_activity', 'AuthController', 'handleLogActivity');

// Admin
$router->add('admin_save_book', 'AdminController', 'handleAdminSaveBook');
$router->add('admin_delete_book', 'AdminController', 'handleAdminDeleteBook');
$router->add('admin_save_category', 'AdminController', 'handleAdminSaveCategory');
$router->add('admin_delete_category', 'AdminController', 'handleAdminDeleteCategory');
$router->add('admin_save_content', 'AdminController', 'handleAdminSaveContent');
$router->add('admin_delete_content', 'AdminController', 'handleAdminDeleteContent');
$router->add('admin_import_book', 'AdminController', 'handleAdminImportBook');
$router->add('admin_get_history', 'AdminController', 'handleAdminGetHistory');
$router->add('admin_get_activity', 'AdminController', 'handleAdminGetActivity');
$router->add('admin_get_search_logs', 'AdminController', 'handleAdminGetSearchLogs');
$router->add('admin_get_download_logs', 'AdminController', 'handleAdminGetDownloadLogs');
$router->add('admin_get_submissions', 'AdminController', 'handleAdminGetSubmissions');
$router->add('admin_review_submission', 'AdminController', 'handleAdminReviewSubmission');
$router->add('admin_get_submission_content', 'AdminController', 'handleAdminGetSubmissionContent');
$router->add('admin_delete_submission', 'AdminController', 'handleAdminDeleteSubmission');

// Submissions
$router->add('submit_file', 'SubmissionController', 'handleSubmitFile');

// Stats
$router->add('stats', 'StatsController', 'handleStats');

// Global Composer autoloader handling
function getComposerAutoloadPath(): ?string {
    $candidates = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/vendor/autoload.php',
        dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/vendor/autoload.php',
    ];
    foreach ($candidates as $path) {
        if ($path && is_file($path)) return $path;
    }
    return null;
}

function loadComposerAutoloader(): bool {
    static $loaded = null;
    if ($loaded !== null) return $loaded;
    $autoload = getComposerAutoloadPath();
    if ($autoload) {
        require_once $autoload;
        return $loaded = true;
    }
    return $loaded = false;
}

// Dispatch
$action = $_GET['action'] ?? '';
try {
    $router->handleRequest($action);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
