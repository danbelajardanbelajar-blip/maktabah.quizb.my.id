<?php
/**
 * api_import_json.php
 * Endpoint import massal kitab dari file JSON satu-per-satu.
 * Pattern: kumpulkan $result, bersihkan buffer, output JSON satu kali di akhir.
 */

// 1. Tangkap SEMUA output sebelum JSON kita — termasuk warning PHP, notice, dll.
ob_start();

// 2. Sembunyikan error dari output, tapi tetap log ke server error_log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 3. Naikkan limit resource untuk file kitab besar
@set_time_limit(300);
@ini_set('memory_limit', '256M');

// 4. Shutdown handler — tangkap Fatal Error, kembalikan JSON valid
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // Buang apapun yang sudah tertulis
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'status'  => 'error',
            'message' => 'PHP Fatal: ' . $err['message']
                       . ' — ' . basename($err['file']) . ':' . $err['line'],
        ]);
    }
});

// 5. Satu fungsi untuk kirim respons dan keluar — selalu bersih
function sendJson(array $data): void
{
    // Buang semua output buffer yang mungkin berisi garbage
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Require Database ─────────────────────────────────────────────────────────
require_once __DIR__ . '/app/Config/Database.php';

// ── Validasi Method ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['status' => 'error', 'message' => 'Method not allowed']);
}

// ── Validasi Upload File ──────────────────────────────────────────────────────
if (!isset($_FILES['json_file'])) {
    $cl = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($cl > 0 && empty($_FILES) && empty($_POST)) {
        $max = ini_get('post_max_size');
        sendJson(['status' => 'error', 'message' => "File terlalu besar — melebihi post_max_size ({$max})"]);
    }
    sendJson(['status' => 'error', 'message' => 'Tidak ada file yang diunggah (json_file missing)']);
}

if ($_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['json_file']['error'];
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'File melebihi upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_FORM_SIZE  => 'File melebihi MAX_FILE_SIZE form',
        UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap, silakan coba lagi',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file dipilih',
        UPLOAD_ERR_NO_TMP_DIR => 'Server tidak punya direktori temp',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis ke disk server',
        UPLOAD_ERR_EXTENSION  => 'Upload diblokir ekstensi PHP',
    ];
    sendJson(['status' => 'error', 'message' => $msgs[$code] ?? "Upload error kode: $code"]);
}

// ── Baca File ─────────────────────────────────────────────────────────────────
$fileTmpPath = $_FILES['json_file']['tmp_name'];
$fileName    = $_FILES['json_file']['name'];
$fileSize    = (int)$_FILES['json_file']['size'];

if ($fileSize === 0) {
    sendJson(['status' => 'error', 'message' => "File kosong (0 bytes): $fileName"]);
}

$jsonRaw = file_get_contents($fileTmpPath);
if ($jsonRaw === false) {
    sendJson(['status' => 'error', 'message' => 'Gagal membaca file dari temp dir server']);
}

// Hapus UTF-8 BOM kalau ada
$jsonRaw = ltrim($jsonRaw, "\xEF\xBB\xBF");

// ── Parse JSON ────────────────────────────────────────────────────────────────
$data = json_decode($jsonRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJson([
        'status'  => 'error',
        'message' => 'JSON tidak valid: ' . json_last_error_msg() . " — file: $fileName",
    ]);
}

if (!is_array($data)) {
    sendJson(['status' => 'error', 'message' => "JSON bukan object: $fileName"]);
}

// ── Validasi Struktur ─────────────────────────────────────────────────────────
// Support format: {buku/halaman} atau {book/contents}
$buku    = $data['buku']     ?? $data['book']     ?? null;
$halaman = $data['halaman']  ?? $data['contents'] ?? null;

if (!$buku || !is_array($buku)) {
    sendJson(['status' => 'error', 'message' => "Node 'buku'/'book' tidak ditemukan: $fileName"]);
}
if (!$halaman || !is_array($halaman)) {
    sendJson(['status' => 'error', 'message' => "Node 'halaman'/'contents' tidak ditemukan: $fileName"]);
}
if (count($halaman) === 0) {
    sendJson(['status' => 'error', 'message' => "Array halaman kosong: $fileName"]);
}

$catId  = (isset($_POST['cat_id']) && $_POST['cat_id'] !== '') ? (int)$_POST['cat_id'] : null;
$title  = trim((string)($buku['title']  ?? 'Kitab Tanpa Judul'));
$author = trim((string)($buku['author'] ?? 'Anonim'));
$pages  = $halaman;
$tocs   = $data['daftar_isi'] ?? $data['tocs'] ?? $data['toc'] ?? [];

if ($title === '') {
    $title = pathinfo($fileName, PATHINFO_FILENAME);
}

// ── Transaksi Database ────────────────────────────────────────────────────────
try {
    $mysql = \App\Config\Database::getConnection();
    $mysql->beginTransaction();

    // Lookup category_name dari tabel categories (sesuai struktur tabel books)
    $catName = '';
    if ($catId) {
        $cs = $mysql->prepare('SELECT name FROM categories WHERE id = :id LIMIT 1');
        $cs->execute([':id' => $catId]);
        $catName = (string)($cs->fetchColumn() ?: '');
    }

    // Insert book — kolom sesuai schema: category_id, category_name, pages
    $stmt = $mysql->prepare(
        'INSERT INTO books (title, author, category_id, category_name, pages, created_at) VALUES (?, ?, ?, ?, 0, NOW())'
    );
    $stmt->execute([$title, $author, $catId ?: null, $catName]);
    $bkid = (int)$mysql->lastInsertId();


    // Insert pages
    $stmtContent = $mysql->prepare(
        'INSERT INTO book_content (bkid, page, juz, content) VALUES (?, ?, ?, ?)'
    );
    $pagesCount = 0;
    $juzMap     = [];

    foreach ($pages as $idx => $p) {
        $pageNum = isset($p['page']) ? (int)$p['page'] : ($idx + 1);
        $juzNum  = isset($p['juz'])  ? (int)$p['juz']  : 1;
        $text    = (string)($p['text'] ?? '');
        $stmtContent->execute([$bkid, $pageNum, $juzNum, $text]);
        $pagesCount++;
        $juzMap[$juzNum] = true;
    }
    $juzCount = count($juzMap);

    // Insert TOC
    $autoTocRun = false;
    if (!empty($tocs) && is_array($tocs)) {
        $stmtToc = $mysql->prepare(
            'INSERT INTO book_toc (bkid, title, level, page, juz) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($tocs as $t) {
            $stmtToc->execute([
                $bkid,
                mb_substr((string)($t['title'] ?? ''), 0, 200),
                (int)($t['level'] ?? 1),
                (int)($t['page']  ?? 1),
                (int)($t['juz']   ?? 1),
            ]);
        }
    } else {
        // Auto-generate TOC dari pola konten Arab
        $autoTocRun = true;
        $stmtToc    = $mysql->prepare(
            'INSERT INTO book_toc (bkid, title, level, page, juz) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($pages as $idx => $p) {
            $content = (string)($p['text'] ?? '');
            $lines   = preg_split('/[\r\n]+/', $content);
            foreach ($lines as $line) {
                $line = trim($line);
                $len  = mb_strlen($line);
                if ($len < 3 || $len > 80)                                              continue;
                if (mb_strpos($line, '.') !== false
                    || mb_strpos($line, '،') !== false
                    || mb_strpos($line, '؟') !== false
                    || mb_strpos($line, '@') !== false)                                 continue;
                if (!preg_match('/[a-zA-Z\p{Arabic}]{2,}/u', $line))                   continue;
                if (preg_match('/^[-_@\s]*ص?\s*\d+\s*[-_@\s]*$/u', $line))            continue;

                $isChapter = (bool)preg_match(
                    '/^(كتاب|باب|فصل|مقدمة|خاتمة|المبحث|المطلب|القسم|تنبيه|فائدة|مسألة)/u',
                    $line
                );
                $isShort = ($len <= 60
                    && mb_strpos($line, ' ') !== false
                    && mb_substr($line, -1) !== ':');

                if ($isChapter || $isShort) {
                    $level = preg_match('/^(كتاب|باب)/u', $line) ? 1 : 2;
                    $stmtToc->execute([
                        $bkid,
                        mb_substr($line, 0, 200),
                        $level,
                        isset($p['page']) ? (int)$p['page'] : ($idx + 1),
                        isset($p['juz'])  ? (int)$p['juz']  : 1,
                    ]);
                }
            }
        }
    }

    $mysql->commit();

    sendJson([
        'status'      => 'success',
        'title'       => $title,
        'bkid'        => $bkid,
        'filename'    => $fileName,
        'pages_count' => $pagesCount,
        'juz_count'   => $juzCount,
        'auto_toc'    => $autoTocRun,
    ]);

} catch (\Throwable $e) {
    if (isset($mysql) && $mysql->inTransaction()) {
        try { $mysql->rollBack(); } catch (\Throwable $_) {}
    }
    sendJson([
        'status'  => 'error',
        'message' => 'DB error: ' . $e->getMessage(),
    ]);
}
