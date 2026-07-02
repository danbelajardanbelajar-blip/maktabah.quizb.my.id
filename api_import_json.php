<?php
// api_import_json.php
// Tangkap semua output sebelum header dikirim
ob_start();

// Nonaktifkan display errors ke output, tapi log ke error_log server
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Naikkan limit untuk file kitab besar
@set_time_limit(300);         // 5 menit
@ini_set('memory_limit', '256M');

// Shutdown handler — tangkap Fatal Error dan kembalikan JSON bersih
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'status'  => 'error',
            'message' => 'PHP Fatal Error: ' . $err['message'] . ' in ' . basename($err['file']) . ':' . $err['line']
        ]);
    }
});

header('Content-Type: application/json');

// Pastikan tidak ada namespace autoloader dari app
require_once __DIR__ . '/app/Config/Database.php';

// ── Validasi Method ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// ── Validasi File Upload ─────────────────────────────────────
if (!isset($_FILES['json_file'])) {
    // Cek apakah file terlalu besar sehingga PHP membuang semua superglobal
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 0 && empty($_FILES) && empty($_POST)) {
        $maxPost = ini_get('post_max_size');
        ob_end_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => "File terlalu besar: melebihi post_max_size ({$maxPost}). Coba split file menjadi lebih kecil."
        ]);
    } else {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada file yang diunggah.']);
    }
    exit;
}

if ($_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['json_file']['error'];
    $errMsgs = [
        UPLOAD_ERR_INI_SIZE   => 'File melebihi upload_max_filesize di php.ini (' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_FORM_SIZE  => 'File melebihi MAX_FILE_SIZE yang ditentukan di form',
        UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian, coba lagi',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih',
        UPLOAD_ERR_NO_TMP_DIR => 'Server tidak punya direktori temp (hubungi hosting)',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk server',
        UPLOAD_ERR_EXTENSION  => 'Upload diblokir oleh ekstensi PHP server',
    ];
    $msg = $errMsgs[$errCode] ?? 'Upload error tidak dikenal (kode: ' . $errCode . ')';
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// ── Baca & Parse File JSON ───────────────────────────────────
$fileTmpPath = $_FILES['json_file']['tmp_name'];
$fileName    = $_FILES['json_file']['name'];
$fileSize    = $_FILES['json_file']['size'];

if ($fileSize === 0) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'File kosong (0 bytes): ' . $fileName]);
    exit;
}

$jsonData = file_get_contents($fileTmpPath);
if ($jsonData === false) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Gagal membaca file upload dari server temp.']);
    exit;
}

// Hapus BOM jika ada
$jsonData = ltrim($jsonData, "\xEF\xBB\xBF");

$data = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Format JSON tidak valid: ' . json_last_error_msg() . ' — di file: ' . $fileName
    ]);
    exit;
}

if (!is_array($data)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'JSON bukan object/array yang valid: ' . $fileName]);
    exit;
}

// ── Validasi Struktur ────────────────────────────────────────
// Support dua format: {buku, halaman} atau {book, contents}
$buku   = $data['buku']    ?? $data['book']     ?? null;
$halaman = $data['halaman'] ?? $data['contents'] ?? null;

if (!$buku) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'JSON tidak punya node "buku" atau "book": ' . $fileName]);
    exit;
}
if (!$halaman || !is_array($halaman)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'JSON tidak punya node "halaman" atau "contents" (array): ' . $fileName]);
    exit;
}
if (count($halaman) === 0) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Array halaman kosong di file: ' . $fileName]);
    exit;
}

$catId  = isset($_POST['cat_id']) && $_POST['cat_id'] !== '' ? (int)$_POST['cat_id'] : null;
$title  = $buku['title']  ?? 'Kitab Tanpa Judul';
$author = $buku['author'] ?? 'Anonim';
$pages  = $halaman;
$tocs   = $data['daftar_isi'] ?? $data['tocs'] ?? $data['toc'] ?? [];

// ── Transaksi Database ───────────────────────────────────────
try {
    $mysql = \App\Config\Database::getConnection();
    $mysql->beginTransaction();

    // Insert Book
    if ($catId) {
        $stmt = $mysql->prepare("INSERT INTO books (title, author, cat_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$title, $author, $catId]);
    } else {
        $stmt = $mysql->prepare("INSERT INTO books (title, author, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$title, $author]);
    }
    $bkid = $mysql->lastInsertId();

    // Insert Pages
    $insertContent = $mysql->prepare(
        "INSERT INTO book_content (bkid, page, juz, content) VALUES (?, ?, ?, ?)"
    );
    $pagesCount = 0;
    $juzMap     = [];

    foreach ($pages as $p) {
        $pageNum = isset($p['page']) ? (int)$p['page'] : ($pagesCount + 1);
        $juzNum  = isset($p['juz'])  ? (int)$p['juz']  : 1;
        $text    = $p['text'] ?? '';

        $insertContent->execute([$bkid, $pageNum, $juzNum, $text]);
        $pagesCount++;
        $juzMap[$juzNum] = true;
    }
    $juzCount = count($juzMap);

    // Insert TOC
    $autoTocRun = false;
    if (!empty($tocs)) {
        $insertToc = $mysql->prepare(
            "INSERT INTO book_toc (bkid, title, level, page, juz) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($tocs as $t) {
            $insertToc->execute([
                $bkid,
                $t['title'] ?? '',
                $t['level'] ?? 1,
                $t['page']  ?? 1,
                $t['juz']   ?? 1,
            ]);
        }
    } else {
        // Auto generate TOC dari pola konten
        $autoTocRun = true;
        $insertToc  = $mysql->prepare(
            "INSERT INTO book_toc (bkid, title, level, page, juz) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($pages as $p) {
            $content = $p['text'] ?? '';
            $lines   = preg_split('/[\r\n]+/', $content);
            foreach ($lines as $line) {
                $line = trim($line);
                $len  = mb_strlen($line);
                if ($len < 3 || $len > 80) continue;
                if (strpos($line, '.') !== false
                    || strpos($line, '،') !== false
                    || strpos($line, '؟') !== false
                    || strpos($line, '@') !== false) continue;
                if (!preg_match('/[a-zA-Z\p{Arabic}]{2,}/u', $line)) continue;
                if (preg_match('/^[-_@\s]*ص?\s*\d+\s*[-_@\s]*$/u', $line)) continue;

                $isChapter = preg_match('/^(كتاب|باب|فصل|مقدمة|خاتمة|المبحث|المطلب|القسم|تنبيه|فائدة|مسألة)/u', $line);
                $isShort   = ($len <= 60 && strpos($line, ' ') !== false && mb_substr($line, -1) !== ':');

                if ($isChapter || $isShort) {
                    $level = preg_match('/^(كتاب|باب)/u', $line) ? 1 : 2;
                    $insertToc->execute([
                        $bkid,
                        mb_substr($line, 0, 200),
                        $level,
                        $p['page'] ?? 1,
                        $p['juz']  ?? 1,
                    ]);
                }
            }
        }
    }

    $mysql->commit();

    // Bersihkan output buffer sebelum kirim JSON success
    ob_end_clean();
    echo json_encode([
        'status'      => 'success',
        'title'       => $title,
        'bkid'        => (int)$bkid,
        'filename'    => $fileName,
        'pages_count' => $pagesCount,
        'juz_count'   => $juzCount,
        'auto_toc'    => $autoTocRun,
    ]);

} catch (\Exception $e) {
    if (isset($mysql) && $mysql->inTransaction()) {
        $mysql->rollBack();
    }
    ob_end_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
