<?php
// =============================================================
//  Al-Maktabah As-Sunniyyah — Google OAuth 2.0 Handler
//  Endpoint: /auth.php?action=login|callback|logout|me
// =============================================================

require_once __DIR__ . '/koneksi.php';

session_start();

// ── Konfigurasi Google OAuth — isi dengan data dari Google Cloud Console ──
define('GOOGLE_CLIENT_ID',     'GOOGLE_CLIENT_ID_ANDA');
define('GOOGLE_CLIENT_SECRET', 'GOOGLE_CLIENT_SECRET_ANDA');
define('GOOGLE_REDIRECT_URI',  'https://lib.quizb.my.id/auth.php?action=callback');

// URL base untuk redirect setelah login/logout
define('APP_BASE_URL', 'https://lib.quizb.my.id');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':    handleLogin();    break;
    case 'callback': handleCallback(); break;
    case 'logout':   handleLogout();   break;
    case 'me':       handleMe();       break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action.']);
}

// =============================================================
//  1. LOGIN — redirect ke Google consent screen
// =============================================================
function handleLogin(): void {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
        'access_type'   => 'online',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// =============================================================
//  2. CALLBACK — tukar code dengan token, simpan user ke DB
// =============================================================
function handleCallback(): void {
    // Validasi state (CSRF protection)
    $state = $_GET['state'] ?? '';
    if (!isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
        http_response_code(403);
        die('Invalid state. Kemungkinan serangan CSRF.');
    }
    unset($_SESSION['oauth_state']);

    $code = $_GET['code'] ?? '';
    if (!$code) {
        header('Location: ' . APP_BASE_URL . '/?error=no_code');
        exit;
    }

    // Tukar authorization code → access token
    $tokenRes = httpPost('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    if (!isset($tokenRes['access_token'])) {
        header('Location: ' . APP_BASE_URL . '/?error=token_failed');
        exit;
    }

    // Ambil data profil user dari Google
    $userInfo = httpGet(
        'https://www.googleapis.com/oauth2/v3/userinfo',
        $tokenRes['access_token']
    );

    if (!isset($userInfo['sub'])) {
        header('Location: ' . APP_BASE_URL . '/?error=userinfo_failed');
        exit;
    }

    // Simpan/update user ke database
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "INSERT INTO users (google_id, email, name, picture, role)
         VALUES (:gid, :email, :name, :pic, 'user')
         ON DUPLICATE KEY UPDATE
           email      = VALUES(email),
           name       = VALUES(name),
           picture    = VALUES(picture),
           last_login = CURRENT_TIMESTAMP"
    );
    $stmt->execute([
        ':gid'   => $userInfo['sub'],
        ':email' => $userInfo['email'] ?? '',
        ':name'  => $userInfo['name']  ?? '',
        ':pic'   => $userInfo['picture'] ?? '',
    ]);

    // Ambil data user lengkap (termasuk role)
    $userRow = $pdo->prepare("SELECT * FROM users WHERE google_id = :gid LIMIT 1");
    $userRow->execute([':gid' => $userInfo['sub']]);
    $user = $userRow->fetch();

    // Simpan ke session
    $_SESSION['user'] = [
        'id'      => $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'picture' => $user['picture'],
        'role'    => $user['role'],
    ];

    // Redirect ke dashboard
    header('Location: ' . APP_BASE_URL . '/dashboard');
    exit;
}

// =============================================================
//  3. LOGOUT
// =============================================================
function handleLogout(): void {
    session_destroy();
    header('Location: ' . APP_BASE_URL . '/');
    exit;
}

// =============================================================
//  4. ME — kembalikan data user saat ini sebagai JSON
// =============================================================
function handleMe(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (isset($_SESSION['user'])) {
        echo json_encode(['loggedIn' => true, 'user' => $_SESSION['user']]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
}

// =============================================================
//  Helpers HTTP
// =============================================================
function httpPost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body ?: '{}', true) ?? [];
}

function httpGet(string $url, string $accessToken): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body ?: '{}', true) ?? [];
}
