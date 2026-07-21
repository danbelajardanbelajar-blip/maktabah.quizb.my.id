<?php
// =============================================================
//  Al-Maktabah As-Sunniyyah — API Entry Point (Front Controller)
// =============================================================

require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Router;
use App\Helpers\CsrfHelper;
use App\Helpers\RateLimiter;

// ── CORS ──────────────────────────────────────────────────────────────────────
// GET read-only: boleh diakses dari semua origin (dibutuhkan oleh APK Android
// yang dapat berjalan di domain yang berbeda dari webview).
// POST / mutating: hanya izinkan same-origin — tidak perlu header CORS sama sekali
// karena browser akan block cross-origin POST tanpa header dari server.
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'GET' || $requestMethod === 'HEAD') {
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=60');
} else {
    // Untuk POST/PUT/DELETE: header Cache-Control no-store, tanpa CORS wildcard
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

// Handle preflight OPTIONS request (CORS)
if ($requestMethod === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// Set JSON content type default
header('Content-Type: application/json; charset=utf-8');

// ── Action ────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

// ── CSRF Validation untuk semua POST ─────────────────────────────────────────
//
// Endpoint GET tidak perlu CSRF (read-only, tidak mengubah state).
// Endpoint POST berikut dikecualikan dari CSRF karena dipanggil dari
// konteks khusus (form upload multipart atau inisiasi dari server):
//   - submit_file  → form multipart, token tetap dikirim via POST field _csrf
//   - setup_*      → endpoint internal (sudah diblokir via .htaccess)
//
// Semua POST lain wajib menyertakan header X-CSRF-Token yang valid.
//
$csrfExemptActions = [
    // Endpoint ini hanya boleh GET
];

if ($requestMethod === 'POST' && !in_array($action, $csrfExemptActions, true)) {
    CsrfHelper::requireValid();
}

// ── Rate Limiting ─────────────────────────────────────────────────────────────
//
// Diterapkan per IP address. Batasan cukup longgar agar tidak mengganggu
// penggunaan normal dari mobile/Android maupun desktop.
//
switch ($action) {
    // Pencarian teks — 80 request per menit (cukup untuk real-time search)
    case 'search_books':
    case 'search_categories':
    case 'search_content':
    case 'search':
    case 'search_advanced':
    case 'search_books_with_content':
    case 'search_content_in_book':
    case 'search_scholarium_pdfs':
        RateLimiter::check('search', 80, 60);
        break;

    // Pengiriman konten — 5 request per 5 menit (mencegah spam submission)
    case 'submit_file':
    case 'submit_request':
    case 'submit_feedback':
    case 'user_reply_activity':
        RateLimiter::check('submit', 5, 300);
        break;

    // Log aktivitas — 60 request per menit
    case 'log_activity':
    case 'log_download_scholarium':
        RateLimiter::check('log_activity', 60, 60);
        break;
}

// ── Router ────────────────────────────────────────────────────────────────────
$router = new Router();

// Books
$router->add('books', 'BookController', 'handleBooks');
$router->add('book', 'BookController', 'handleBook');
$router->add('download_book', 'BookController', 'handleDownloadBook');
$router->add('log_download_scholarium', 'BookController', 'handleLogDownloadScholarium');
$router->add('content', 'BookController', 'handleContent');
$router->add('latest', 'BookController', 'handleLatest');
$router->add('popular_books', 'BookController', 'handlePopularBooks');
$router->add('book_toc', 'BookController', 'handleGetBookToc');

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
$router->add('popular_searches', 'SearchController', 'handlePopularSearches');
$router->add('search_recommendations', 'SearchController', 'handleSearchRecommendations');
$router->add('search_scholarium_pdfs', 'SearchController', 'handleSearchScholarium');
$router->add('log_search', 'SearchController', 'handleLogSearch');
$router->add('ask_ai', 'AskController', 'handleAsk');

// Auth
$router->add('auth_me', 'AuthController', 'handleAuthMe');
$router->add('log_activity', 'AuthController', 'handleLogActivity');
$router->add('agree_tos', 'AuthController', 'handleAgreeTos');

// Admin
$router->add('admin_save_book', 'AdminController', 'handleAdminSaveBook');
$router->add('admin_delete_book', 'AdminController', 'handleAdminDeleteBook');
$router->add('admin_save_category', 'AdminController', 'handleAdminSaveCategory');
$router->add('admin_delete_category', 'AdminController', 'handleAdminDeleteCategory');
$router->add('admin_save_content', 'AdminController', 'handleAdminSaveContent');
$router->add('admin_delete_content', 'AdminController', 'handleAdminDeleteContent');
$router->add('admin_import_book', 'AdminController', 'handleAdminImportBook');
$router->add('admin_import_json_init', 'AdminController', 'handleAdminImportJsonInit');
$router->add('admin_import_json_chunk', 'AdminController', 'handleAdminImportJsonChunk');
$router->add('admin_import_json_toc_chunk', 'AdminController', 'handleAdminImportJsonTocChunk');
$router->add('admin_import_json_finish', 'AdminController', 'handleAdminImportJsonFinish');
$router->add('admin_get_toc', 'AdminController', 'handleAdminGetToc');
$router->add('admin_save_toc', 'AdminController', 'handleAdminSaveToc');
$router->add('admin_generate_toc', 'AdminController', 'handleAdminGenerateToc');
$router->add('admin_get_history', 'AdminController', 'handleAdminGetHistory');
$router->add('admin_get_activity', 'AdminController', 'handleAdminGetActivity');
$router->add('admin_get_search_logs', 'AdminController', 'handleAdminGetSearchLogs');
$router->add('admin_get_ask_logs', 'AdminController', 'handleAdminGetAskLogs');
$router->add('admin_delete_search_log', 'AdminController', 'handleAdminDeleteSearchLog');
$router->add('admin_get_download_logs', 'AdminController', 'handleAdminGetDownloadLogs');
$router->add('admin_get_submissions', 'AdminController', 'handleAdminGetSubmissions');
$router->add('admin_review_submission', 'AdminController', 'handleAdminReviewSubmission');
$router->add('admin_get_submission_content', 'AdminController', 'handleAdminGetSubmissionContent');
$router->add('admin_delete_submission', 'AdminController', 'handleAdminDeleteSubmission');
$router->add('admin_get_requests', 'AdminController', 'handleAdminGetRequests');
$router->add('admin_update_request_status', 'AdminController', 'handleAdminUpdateRequestStatus');
$router->add('admin_delete_request', 'AdminController', 'handleAdminDeleteRequest');

// Submissions, Requests & Feedback
$router->add('submit_file', 'SubmissionController', 'handleSubmitFile');
$router->add('submit_request', 'SubmissionController', 'handleSubmitRequest');
$router->add('submit_feedback', 'SubmissionController', 'handleSubmitFeedback');
$router->add('user_reply_activity', 'SubmissionController', 'handleUserReplyActivity');
$router->add('get_my_activities', 'SubmissionController', 'getMyActivities');
$router->add('get_my_notifications', 'SubmissionController', 'getMyNotifications');
$router->add('mark_notification_read', 'SubmissionController', 'markNotificationRead');
$router->add('setup_kitab_requests', 'SubmissionController', 'setupKitabRequests');
$router->add('setup_feedback', 'SubmissionController', 'setupFeedback');

// Admin Feedback handling
$router->add('admin_get_feedbacks', 'AdminController', 'handleAdminGetFeedbacks');
$router->add('admin_update_feedback_status', 'AdminController', 'handleAdminUpdateFeedbackStatus');
$router->add('admin_delete_feedback', 'AdminController', 'handleAdminDeleteFeedback');
$router->add('admin_reply_feedback', 'AdminController', 'handleAdminReplyFeedback');
$router->add('admin_reply_request', 'AdminController', 'handleAdminReplyRequest');
$router->add('admin_reply_submission', 'AdminController', 'handleAdminReplySubmission');

// Stats
$router->add('stats', 'StatsController', 'handleStats');

// ── Global Composer autoloader handling ───────────────────────────────────────
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
try {
    $router->handleRequest($action);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
