<?php
// ============================================================
//  fill_juz.php — CLI version
//  Jalankan di terminal:
//    php fill_juz.php [--start=0] [--batch=20] [--debug] [--dry-run]
//
//  Logika deteksi juz:
//    • Urut per bkid berdasarkan id ASC
//    • Juz = 1 untuk baris pertama setiap bkid
//    • Jika page turun setelah nilai >= 3 (misal 3 → 1) → mulai juz baru
//    • Jika page sama di awal (misal 1 → 1) atau reset sebelum 3 → tidak mulai juz baru
//
//  Output: log ke terminal, progress, dan ringkasan akhir.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan via terminal: php fill_juz.php\n");
}

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/koneksi.php';

function clr(string $code, string $text): string {
    return "\033[{$code}m{$text}\033[0m";
}
function ok(string $s): void   { echo clr('32', '✓') . " {$s}\n"; }
function info(string $s): void { echo clr('36', 'ℹ') . " {$s}\n"; }
function warn(string $s): void { echo clr('33', '⚠') . " {$s}\n"; }
function err(string $s): void  { echo clr('31', '✗') . " {$s}\n"; }
function head(string $s): void { echo "\n" . clr('1;37', $s) . "\n" . str_repeat('─', 56) . "\n"; }
function dim(string $s): string { return clr('90', $s); }
function progressBar(int $done, int $total, int $width = 40): string {
    $pct = $total > 0 ? $done / $total : 1;
    $fill = (int)round($pct * $width);
    $bar = str_repeat('█', $fill) . str_repeat('░', $width - $fill);
    $label = str_pad(round($pct * 100), 3) . '% (' . $done . '/' . $total . ')';
    return clr('32', $bar) . ' ' . $label;
}
function parseArgs(array $argv): array {
    $opts = [];
    foreach ($argv as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $parts = explode('=', substr($arg, 2), 2);
        $opts[$parts[0]] = $parts[1] ?? true;
    }
    return $opts;
}
function normalizePage(mixed $page): ?int {
    if ($page === null) {
        return null;
    }
    $page = trim((string)$page);
    if ($page === '') {
        return null;
    }
    if (preg_match('/^(\d+)/', $page, $m)) {
        return (int)$m[1];
    }
    return null;
}

$args = parseArgs($argv);
$startIndex = max(0, (int)($args['start'] ?? $args['start-index'] ?? 0));
$batchSize = max(1, (int)($args['batch'] ?? 20));
$debug = isset($args['debug']);
$dryRun = isset($args['dry-run']) || isset($args['dryrun']);

head('fill_juz.php — Al-Maktabah As-Sunniyyah');
echo dim('Waktu mulai: ' . date('Y-m-d H:i:s')) . "\n";
if ($dryRun) {
    warn('Mode dry-run aktif: tidak akan menulis ke database.');
}
if ($debug) {
    info('Mode debug aktif: menampilkan detail per kitab.');
}

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $pdo->exec('SET SESSION wait_timeout=28800');
    $pdo->exec('SET SESSION interactive_timeout=28800');
    $pdo->exec('SET SESSION net_read_timeout=3600');
    $pdo->exec('SET SESSION net_write_timeout=3600');
    info('Session timeout dinaikkan untuk proses panjang.');
} catch (Throwable $e) {
    warn('Gagal set timeout sesi: ' . $e->getMessage());
}

head('LANGKAH 1: Cek kolom juz');
$col = $pdo->query("SHOW COLUMNS FROM book_content LIKE 'juz'")->fetch();
if (!$col) {
    err('Kolom `juz` tidak ditemukan di tabel book_content. Tambahkan dulu kolom tersebut.');
    err('SQL: ALTER TABLE book_content ADD COLUMN juz SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER page;');
    exit(1);
}
ok('Kolom `juz` ditemukan.');

head('LANGKAH 2: Ambil daftar kitab');
$bkids = $pdo->query('SELECT DISTINCT bkid FROM book_content ORDER BY bkid ASC')->fetchAll(PDO::FETCH_COLUMN);
$totalKitab = count($bkids);
info('Total kitab: ' . $totalKitab);
if ($totalKitab === 0) {
    err('Tidak ditemukan data book_content.');
    exit(1);
}
if ($startIndex >= $totalKitab) {
    err('start index terlalu besar. Total kitab: ' . $totalKitab);
    exit(1);
}

$selectStmt = $pdo->prepare('SELECT id, page, juz FROM book_content WHERE bkid = ? ORDER BY id ASC');
$updateStmt = $pdo->prepare('UPDATE book_content SET juz = ? WHERE id = ?');

$doneKitab = 0;
$doneRows = 0;
$multiJuz = 0;
$changed = 0;
$errors = [];

$pdo->beginTransaction();
for ($idx = $startIndex; $idx < $totalKitab; $idx++) {
    $bkid = $bkids[$idx];
    try {
        $selectStmt->execute([$bkid]);
        $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
        $count = count($rows);
        if ($count === 0) {
            $doneKitab++;
            continue;
        }

        $currentJuz = 1;
        $prevPage = null;
        $highPage = 0;
        $maxJuz = 1;
        $updatedRows = 0;

        foreach ($rows as $row) {
            $pageValue = normalizePage($row['page']);

            if ($prevPage !== null && $pageValue !== null && $pageValue < $prevPage && $highPage >= 3) {
                // Hanya mulai juz baru bila halaman sebelumnya sudah mencapai angka 3 atau lebih.
                // Jika page sama atau reset sebelum 3, tetap di juz yang sama.
                $currentJuz++;
                $highPage = $pageValue;
            } elseif ($pageValue !== null && $pageValue > $highPage) {
                $highPage = $pageValue;
            }

            if ((int)$row['juz'] !== $currentJuz) {
                if (!$dryRun) {
                    $updateStmt->execute([$currentJuz, (int)$row['id']]);
                }
                $updatedRows++;
            }

            $prevPage = $pageValue;
            $maxJuz = max($maxJuz, $currentJuz);
        }

        if ($maxJuz > 1) {
            ok("bkid={$bkid} → max_juz={$maxJuz}, rows={$count}, updated={$updatedRows}");
            $multiJuz++;
        } elseif ($debug) {
            info("bkid={$bkid} → max_juz=1, rows={$count}, updated={$updatedRows}");
        }

        $doneKitab++;
        $doneRows += $count;
        $changed += $updatedRows;

        if ($debug || $updatedRows > 0) {
            $sample = array_slice($rows, 0, 5);
            foreach ($sample as $row) {
                $p = normalizePage($row['page']);
                echo dim(sprintf('  id=%d page=%s normalized=%s old_juz=%d', $row['id'], $row['page'], $p === null ? 'NULL' : $p, $row['juz'])) . "\n";
            }
        }

        if ($doneKitab % $batchSize === 0) {
            if (!$dryRun) {
                $pdo->commit();
                $pdo->beginTransaction();
            }
            echo progressBar($doneKitab, $totalKitab) . "\n";
        }
    } catch (Throwable $e) {
        $errors[$bkid] = $e->getMessage();
        err("bkid={$bkid}: " . $e->getMessage());
    }
}

if (!$dryRun) {
    $pdo->commit();
}

head('VERIFIKASI');
$verify = $pdo->query('SELECT bkid, COUNT(*) AS pages, MAX(juz) AS max_juz FROM book_content GROUP BY bkid ORDER BY bkid ASC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
foreach ($verify as $row) {
    echo sprintf("bkid=%s pages=%d max_juz=%d\n", $row['bkid'], $row['pages'], $row['max_juz']);
}

head('SELESAI');
ok('Kitab diproses: ' . $doneKitab);
ok('Baris total: ' . $doneRows);
info('Total baris diupdate: ' . $changed);
info('Kitab multi-juz terdeteksi: ' . $multiJuz);
if (!empty($errors)) {
    warn('Ditemukan error pada ' . count($errors) . ' kitab:');
    foreach ($errors as $bkid => $msg) {
        err("  bkid={$bkid}: {$msg}");
    }
}
echo dim('Waktu selesai: ' . date('Y-m-d H:i:s')) . "\n";
?>
