<?php
// ============================================================
//  fill_juz.php — Isi kolom `juz` pada tabel book_content
//  Jalankan via browser: https://yourdomain.com/fill_juz.php
//  Proses dicicil 10% per klik, lanjutkan dengan tombol
// ============================================================

// Keamanan: hanya bisa diakses dari IP server atau login admin
// (hapus baris berikut jika sudah pastikan aman)
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    // Uncomment untuk membatasi akses:
    // http_response_code(403); die('Forbidden');
}

require_once __DIR__ . '/koneksi.php';

header('Content-Type: text/html; charset=utf-8');

$chunkPercent = 5;
$startIndex = max(0, (int)($_GET['start'] ?? 0));
$messages = [];
$details = [];
$error = '';
$nextStart = null;
$finished = false;
$summary = [];

try {
    $pdo = getPDO();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $messages[] = 'Waktu menjalankan: ' . date('Y-m-d H:i:s');

    // Pastikan kolom juz ada
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

    $bkidCounts = $pdo
        ->query('SELECT bkid, COUNT(*) AS cnt FROM book_content GROUP BY bkid ORDER BY bkid ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    $totalKitab = count($bkidCounts);
    $messages[] = "Total kitab: {$totalKitab}";

    if ($totalRows === 0) {
        $finished = true;
        $summary[] = 'Tabel `book_content` kosong, tidak ada yang diproses.';
    } elseif ($startIndex >= $totalKitab) {
        $finished = true;
        $summary[] = 'Semua kitab sudah diproses.';
    } else {
        $stmtSelect = $pdo->prepare('SELECT id, page FROM book_content WHERE bkid = ? ORDER BY id ASC');

        $processedRows = 0;
        $processedKitab = 0;
        $multiJuzKitab = 0;
        $currentIndex = $startIndex;

        while ($currentIndex < $totalKitab && $processedRows < $chunkTarget) {
            $bkid = $bkidCounts[$currentIndex]['bkid'];
            $countRows = (int)$bkidCounts[$currentIndex]['cnt'];

            $stmtSelect->execute([$bkid]);
            $rows = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

            $juz = 1;
            $prevPage = -1;
            $byJuz = [];

            foreach ($rows as $row) {
                if ($prevPage >= 0 && (int)$row['page'] <= $prevPage) {
                    $juz++;
                }
                $byJuz[$juz][] = (int)$row['id'];
                $prevPage = (int)$row['page'];
            }

            $maxJuz = max(array_keys($byJuz));
            if ($maxJuz > 1) {
                $multiJuzKitab++;
            }

            foreach ($byJuz as $juzNum => $ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge([$juzNum], $ids);
                $pdo->prepare("UPDATE book_content SET juz = ? WHERE id IN ({$placeholders})")->execute($params);
            }

            $processedRows += $countRows;
            $processedKitab++;
            $details[] = sprintf(
                'Kitab %d/%d: bkid=%s, baris=%d, juz=%d',
                $currentIndex + 1,
                $totalKitab,
                htmlspecialchars($bkid, ENT_QUOTES, 'UTF-8'),
                $countRows,
                $maxJuz
            );

            $currentIndex++;
        }

        $nextStart = $currentIndex;
        $summary[] = "Diproses: {$processedRows} baris dari start kitab ke {$startIndex} ({$processedKitab} kitab).";
        if ($currentIndex < $totalKitab) {
            $summary[] = 'Batch selesai, klik Lanjutkan untuk meneruskan proses.';
        } else {
            $finished = true;
            $summary[] = 'Semua kitab selesai diproses.';
        }

        if ($processedKitab > 0) {
            $summary[] = "Kitab multi-juz yang diproses di batch ini: {$multiJuzKitab}.";
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
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>fill_juz.php — Proses 10%</title>
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
    <h1>fill_juz.php — Proses 10% per klik</h1>
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
            <h2>Detail Kitab</h2>
            <pre><?= htmlspecialchars(implode("\n", $details), ENT_QUOTES, 'UTF-8') ?></pre>
        <?php endif; ?>

        <?php if ($finished): ?>
            <p class="ok"><strong>Selesai:</strong> Semua baris sudah terproses.</p>
            <p>Setelah verifikasi, hapus file ini dari server.</p>
        <?php elseif ($nextStart !== null): ?>
            <a class="button" href="?start=<?= $nextStart ?>">Lanjutkan batch berikutnya</a>
            <p>Batch berikutnya akan mulai dari kitab ke-<?= $nextStart + 1 ?>.</p>
        <?php endif; ?>
    </div>
</body>
</html>
