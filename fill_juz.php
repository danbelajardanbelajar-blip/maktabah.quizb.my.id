<?php
// ============================================================
//  fill_juz.php — Isi kolom `juz` pada tabel book_content
//  Jalankan via browser: https://yourdomain.com/fill_juz.php
//  Proses dicicil 5% per klik, bisa lanjut di tengah kitab
// ============================================================

// Keamanan: hanya bisa diakses dari IP server atau login admin
// (hapus baris berikut jika sudah pastikan aman)
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    // Uncomment untuk membatasi akses:
    // http_response_code(403); die('Forbidden');
}

require_once __DIR__ . '/koneksi.php';

header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ignore_user_abort(true);

$chunkPercent = 5;
$currentIndex = max(0, (int)($_GET['index'] ?? 0));
$lastId = max(0, (int)($_GET['last_id'] ?? 0));
$juz = max(1, (int)($_GET['juz'] ?? 1));
$prevPage = isset($_GET['prev_page']) ? (int)$_GET['prev_page'] : -1;

$messages = [];
$details = [];
$error = '';
$nextUrl = '';
$finished = false;
$summary = [];

try {
    $pdo = getPDO();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $messages[] = 'Waktu menjalankan: ' . date('Y-m-d H:i:s');

    $checkColumn = $pdo->query("SHOW COLUMNS FROM book_content LIKE 'juz'")->fetchAll();
    if (empty($checkColumn)) {
        $pdo->exec(
            "ALTER TABLE book_content
             ADD COLUMN juz SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER page"
        );
        $messages[] = 'Kolom `juz` berhasil ditambahkan.';
    } else {
        $messages[] = 'Kolom `juz` sudah ada.';
    }

    $totalRows = (int)$pdo->query('SELECT COUNT(*) FROM book_content')->fetchColumn();
    $chunkTarget = max(1, (int)ceil($totalRows * $chunkPercent / 100));
    $messages[] = "Total baris book_content: {$totalRows}";
    $messages[] = "Target proses per klik: {$chunkTarget} baris ({$chunkPercent}% dari total)";

    $bkids = $pdo
        ->query('SELECT DISTINCT bkid FROM book_content ORDER BY bkid ASC')
        ->fetchAll(PDO::FETCH_COLUMN);
    $totalKitab = count($bkids);
    $messages[] = "Total kitab: {$totalKitab}";

    if ($totalRows === 0) {
        $finished = true;
        $summary[] = 'Tabel `book_content` kosong, tidak ada yang diproses.';
    } elseif ($currentIndex >= $totalKitab) {
        $finished = true;
        $summary[] = 'Semua kitab sudah diproses.';
    } else {
        $stmtUpdate = $pdo->prepare('UPDATE book_content SET juz = ? WHERE id = ?');

        $processedRows = 0;
        $processedKitab = 0;
        $multiJuzKitab = 0;
        $startIndex = $currentIndex;

        $details[] = sprintf(
            'Mulai dari kitab ke %d: bkid=%s, last_id=%d, juz=%d, prev_page=%d',
            $currentIndex + 1,
            htmlspecialchars($bkids[$currentIndex], ENT_QUOTES, 'UTF-8'),
            $lastId,
            $juz,
            $prevPage
        );

        while ($currentIndex < $totalKitab && $processedRows < $chunkTarget) {
            $currentBkid = $bkids[$currentIndex];
            $remaining = $chunkTarget - $processedRows;
            $sql = sprintf(
                'SELECT id, page FROM book_content WHERE bkid = ? AND id > ? ORDER BY id ASC LIMIT %d',
                $remaining
            );
            $stmtSelect = $pdo->prepare($sql);
            $stmtSelect->execute([$currentBkid, $lastId]);
            $rows = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                $currentIndex++;
                $lastId = 0;
                $juz = 1;
                $prevPage = -1;
                continue;
            }

            foreach ($rows as $row) {
                $page = (int)$row['page'];
                if ($prevPage >= 0 && $page <= $prevPage) {
                    $juz++;
                }

                $stmtUpdate->execute([$juz, (int)$row['id']]);
                $processedRows++;
                $lastId = (int)$row['id'];
                $prevPage = $page;

                $details[] = sprintf(
                    'bkid=%s id=%d page=%d juz=%d',
                    htmlspecialchars($currentBkid, ENT_QUOTES, 'UTF-8'),
                    (int)$row['id'],
                    $page,
                    $juz
                );

                if ($processedRows >= $chunkTarget) {
                    break;
                }
            }

            if (count($rows) < $remaining) {
                $currentIndex++;
                $lastId = 0;
                $juz = 1;
                $prevPage = -1;
            }
        }

        $nextUrl = sprintf(
            '?index=%d&last_id=%d&juz=%d&prev_page=%d',
            $currentIndex,
            $lastId,
            $juz,
            $prevPage
        );

        $summary[] = sprintf(
            'Diproses %d baris pada %d kitab dimulai dari indeks %d.',
            $processedRows,
            max(0, $currentIndex - $startIndex + ($lastId === 0 ? 0 : 1)),
            $startIndex
        );

        if ($currentIndex >= $totalKitab) {
            $finished = true;
            $summary[] = 'Semua kitab selesai diproses.';
        } else {
            $summary[] = 'Batch selesai; klik Lanjutkan untuk batch berikutnya.';
            $summary[] = sprintf('Selanjutnya mulai dari kitab ke %d.', $currentIndex + 1);
        }

        if ($processedRows > 0) {
            $summary[] = "Kitab multi-juz yang diproses di batch ini: {$multiJuzKitab}.";
        }
    }

    if ($finished) {
        $indexCheck = $pdo->query("SHOW INDEX FROM book_content WHERE Key_name = 'idx_bkid_juz_page'")->fetchAll();
        if (empty($indexCheck)) {
            $pdo->exec("ALTER TABLE book_content ADD INDEX idx_bkid_juz_page (bkid, juz, page)");
            $summary[] = 'Index `idx_bkid_juz_page` berhasil ditambahkan.';
        } else {
            $summary[] = 'Index `idx_bkid_juz_page` sudah ada.';
        }
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>fill_juz.php — Proses 5% per klik</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; line-height: 1.6; }
        .box { background:#f8f9fa; border:1px solid #dfe3e6; padding:16px; border-radius:8px; }
        .error { color:#a00; }
        .ok { color:#064; }
        .button { display:inline-block; margin-top:16px; padding:12px 18px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; }
        .button:hover { background:#0056b3; }
        pre { background:#282c34; color:#f8f8f2; padding:12px; border-radius:8px; overflow:auto; }
    </style>
</head>
<body>
    <h1>fill_juz.php — Proses 5% per klik</h1>
    <div class="box">
        <p>Jalankan ulang halaman ini setiap kali ingin meneruskan batch berikutnya.</p>
        <?php if ($error): ?>
            <p class="error"><strong>ERROR:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($messages): ?>
            <ul>
                <?php foreach ($messages as $msg): ?>
                    <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($summary): ?>
            <h2>Ringkasan</h2>
            <ul>
                <?php foreach ($summary as $line): ?>
                    <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($details): ?>
            <h2>Detail</h2>
            <pre><?= htmlspecialchars(implode("\n", $details), ENT_QUOTES, 'UTF-8') ?></pre>
        <?php endif; ?>

        <?php if ($finished): ?>
            <p class="ok"><strong>Selesai:</strong> Semua baris sudah terproses.</p>
            <p>Setelah verifikasi, hapus file ini dari server.</p>
        <?php elseif ($nextUrl): ?>
            <a class="button" href="<?= htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') ?>">Lanjutkan batch berikutnya</a>
        <?php endif; ?>
    </div>
</body>
</html>
