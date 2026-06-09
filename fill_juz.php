<?php
// ============================================================
//  fill_juz.php — CLI version (safe incremental)
//  Jalankan: php fill_juz.php [--debug] [--dry-run] [--reset] [--delay=50]
//
//  PERBAIKAN dari versi lama:
//    ✓ Setiap kitab di-commit secara TERPISAH — tidak ada lock besar
//    ✓ Progress disimpan ke file, bisa dilanjutkan bila terputus
//    ✓ Delay antar kitab agar server tidak kewalahan
//    ✓ Tidak ada global transaction yang memblokir tabel
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan via terminal: php fill_juz.php\n");
}

set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/koneksi.php';

// ── ANSI helpers ─────────────────────────────────────────────
function clr(string $code, string $text): string {
    return "\033[{$code}m{$text}\033[0m";
}
function ok(string $s): void   { echo clr('32', '✓') . " {$s}\n"; flush(); }
function info(string $s): void { echo clr('36', 'ℹ') . " {$s}\n"; flush(); }
function warn(string $s): void { echo clr('33', '⚠') . " {$s}\n"; flush(); }
function err(string $s): void  { echo clr('31', '✗') . " {$s}\n"; flush(); }
function head(string $s): void {
    echo "\n" . clr('1;37', $s) . "\n" . str_repeat('─', 60) . "\n";
    flush();
}
function dim(string $s): string { return clr('90', $s); }

function progressBar(int $done, int $total, int $width = 40): string {
    $pct  = $total > 0 ? $done / $total : 1;
    $fill = (int)round($pct * $width);
    $bar  = str_repeat('█', $fill) . str_repeat('░', $width - $fill);
    $pctLabel = str_pad((int)round($pct * 100), 3) . '%';
    return clr('32', $bar) . " {$pctLabel} ({$done}/{$total})";
}

function normalizePage(mixed $page): ?int {
    if ($page === null) return null;
    $page = trim((string)$page);
    if ($page === '') return null;
    if (preg_match('/^(\d+)/', $page, $m)) return (int)$m[1];
    return null;
}

function parseArgs(array $argv): array {
    $opts = [];
    foreach ($argv as $arg) {
        if (!str_starts_with($arg, '--')) continue;
        $parts = explode('=', substr($arg, 2), 2);
        $opts[$parts[0]] = $parts[1] ?? true;
    }
    return $opts;
}

// ── Argumen ───────────────────────────────────────────────────
$args   = parseArgs($argv);
$debug  = isset($args['debug']);
$dryRun = isset($args['dry-run']) || isset($args['dryrun']);
$reset  = isset($args['reset']);
// Delay dalam milidetik antar kitab (default 50ms = 50000µs)
$delayMs = max(0, (int)($args['delay'] ?? 50));

// File progress — menyimpan index kitab terakhir yang berhasil diproses
$progressFile = __DIR__ . '/fill_juz_progress.json';

// ── Header ────────────────────────────────────────────────────
head('fill_juz.php — Pengisian Kolom Juz (Aman & Bertahap)');
echo dim('Waktu mulai : ' . date('Y-m-d H:i:s')) . "\n";
echo dim('Mode        : ' . ($dryRun ? 'DRY-RUN (tidak menulis)' : 'LIVE')) . "\n";
echo dim('Delay       : ' . $delayMs . ' ms per kitab') . "\n\n";

if ($dryRun) warn('Dry-run aktif: TIDAK ada perubahan di database.');
if ($debug)  info('Debug aktif: tampilkan detail per kitab.');

// ── Koneksi ───────────────────────────────────────────────────
$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Nonaktifkan autocommit — kita kontrol sendiri per kitab
$pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1); // pastikan autocommit ON (kita pakai beginTransaction per kitab)

try {
    $pdo->exec('SET SESSION wait_timeout=28800');
    $pdo->exec('SET SESSION interactive_timeout=28800');
    info('Session timeout diset ke 8 jam.');
} catch (Throwable $e) {
    warn('Gagal set timeout sesi: ' . $e->getMessage());
}

// ── Cek kolom juz ────────────────────────────────────────────
head('LANGKAH 1: Cek kolom juz');
$col = $pdo->query("SHOW COLUMNS FROM book_content LIKE 'juz'")->fetch();
if (!$col) {
    err('Kolom `juz` tidak ada. Tambahkan dulu:');
    err('  ALTER TABLE book_content ADD COLUMN juz SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER page;');
    exit(1);
}
ok('Kolom `juz` ditemukan.');

// ── Ambil daftar kitab ────────────────────────────────────────
head('LANGKAH 2: Ambil daftar kitab');
$bkids      = $pdo->query('SELECT DISTINCT bkid FROM book_content ORDER BY bkid ASC')->fetchAll(PDO::FETCH_COLUMN);
$totalKitab = count($bkids);
info("Total kitab: {$totalKitab}");

if ($totalKitab === 0) {
    err('Tidak ada data di book_content.');
    exit(1);
}

// ── Progress resume ───────────────────────────────────────────
$startIndex = 0;
if ($reset && file_exists($progressFile)) {
    unlink($progressFile);
    info('Progress file dihapus (--reset). Mulai dari awal.');
}
if (file_exists($progressFile) && !$dryRun) {
    $prog = json_decode(file_get_contents($progressFile), true);
    if (isset($prog['last_done_index'])) {
        $startIndex = (int)$prog['last_done_index'] + 1;
        $lastBkid   = $prog['last_bkid'] ?? '?';
        warn("Melanjutkan dari index {$startIndex} (setelah bkid={$lastBkid}).");
        warn("Gunakan --reset untuk mulai dari awal.");
    }
} else {
    info('Mulai dari awal.');
}

if ($startIndex >= $totalKitab) {
    ok("Semua {$totalKitab} kitab sudah diproses sebelumnya.");
    ok("Gunakan --reset untuk memproses ulang.");
    exit(0);
}

info('Kitab yang akan diproses: ' . ($totalKitab - $startIndex));

// ── Statement siap ────────────────────────────────────────────
// SELECT tanpa lock (READ tanpa FOR UPDATE)
$selectStmt = $pdo->prepare(
    'SELECT id, page, juz FROM book_content WHERE bkid = ? ORDER BY id ASC'
);
// Update satu kitab sekaligus pakai CASE WHEN — jauh lebih efisien dari loop UPDATE satu baris
// (dibangun dinamis per kitab)

// ── Statistik ─────────────────────────────────────────────────
$doneKitab = 0;
$doneRows  = 0;
$multiJuz  = 0;
$changed   = 0;
$errors    = [];

head('LANGKAH 3: Proses per kitab (commit per kitab)');

for ($idx = $startIndex; $idx < $totalKitab; $idx++) {
    $bkid = $bkids[$idx];

    try {
        // 1. Baca semua baris kitab ini (TANPA transaksi — cukup SELECT biasa)
        $selectStmt->execute([$bkid]);
        $rows  = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
        $count = count($rows);

        if ($count === 0) {
            $doneKitab++;
            continue;
        }

        // 2. Hitung juz per baris
        $currentJuz = 1;
        $prevPage   = null;
        $highPage   = 0;
        $maxJuz     = 1;

        // Kumpulkan id → juz yang perlu diubah
        $toUpdate = []; // [juz => [id, id, ...]]

        foreach ($rows as $row) {
            $pageValue = normalizePage($row['page']);

            if (
                $prevPage   !== null &&
                $pageValue  !== null &&
                $pageValue  <  $prevPage &&
                $highPage   >= 3
            ) {
                $currentJuz++;
                $highPage = $pageValue;
            } elseif ($pageValue !== null && $pageValue > $highPage) {
                $highPage = $pageValue;
            }

            if ((int)$row['juz'] !== $currentJuz) {
                $toUpdate[$currentJuz][] = (int)$row['id'];
            }

            $prevPage = $pageValue;
            $maxJuz   = max($maxJuz, $currentJuz);
        }

        $updatedRows = array_sum(array_map('count', $toUpdate));

        // 3. Simpan ke DB — satu transaksi KECIL hanya untuk kitab ini
        if (!$dryRun && $updatedRows > 0) {
            $pdo->beginTransaction();
            try {
                foreach ($toUpdate as $juzVal => $ids) {
                    // UPDATE ... WHERE id IN (...) per juz value
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare(
                        "UPDATE book_content SET juz = ? WHERE bkid = ? AND id IN ({$placeholders})"
                    );
                    $stmt->execute(array_merge([$juzVal, $bkid], $ids));
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e; // lempar ke catch luar
            }
        }

        // 4. Log
        if ($maxJuz > 1) {
            ok(sprintf(
                'bkid=%-6s → max_juz=%d | rows=%d | updated=%d',
                $bkid, $maxJuz, $count, $updatedRows
            ));
            $multiJuz++;
        } elseif ($debug) {
            info(sprintf(
                'bkid=%-6s → max_juz=1 | rows=%d | updated=%d',
                $bkid, $count, $updatedRows
            ));
        }

        if ($debug && $updatedRows > 0) {
            $sample = array_slice($rows, 0, 5);
            foreach ($sample as $r) {
                $p = normalizePage($r['page']);
                echo dim(sprintf(
                    '    id=%d  page=%-6s  normalized=%-4s  old_juz=%d',
                    $r['id'], $r['page'], $p ?? 'NULL', $r['juz']
                )) . "\n";
            }
        }

        $doneKitab++;
        $doneRows += $count;
        $changed  += $updatedRows;

        // 5. Simpan progress setelah tiap kitab berhasil
        if (!$dryRun) {
            file_put_contents($progressFile, json_encode([
                'last_done_index' => $idx,
                'last_bkid'       => $bkid,
                'done_kitab'      => $doneKitab,
                'done_rows'       => $doneRows,
                'changed'         => $changed,
                'multi_juz'       => $multiJuz,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]));
        }

        // 6. Tampilkan progress tiap 25 kitab
        if ($doneKitab % 25 === 0) {
            echo "\n" . progressBar($idx + 1, $totalKitab) . "\n\n";
        }

        // 7. Delay agar server napas
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

    } catch (Throwable $e) {
        $errors[$bkid] = $e->getMessage();
        err("bkid={$bkid}: " . $e->getMessage());
        // Lanjut ke kitab berikutnya — tidak berhenti total
    }
}

// ── Progress bar akhir ────────────────────────────────────────
echo "\n" . progressBar($totalKitab, $totalKitab) . "\n";

// ── Verifikasi sampel ─────────────────────────────────────────
head('VERIFIKASI (20 sampel kitab dengan max_juz > 1)');
try {
    $verifyRows = $pdo->query(
        'SELECT bkid, COUNT(*) AS pages, MAX(juz) AS max_juz
         FROM book_content
         GROUP BY bkid
         HAVING max_juz > 1
         ORDER BY max_juz DESC, bkid ASC
         LIMIT 20'
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($verifyRows)) {
        warn('Tidak ada kitab multi-juz terdeteksi di database.');
    } else {
        printf("%-10s %-10s %-10s\n", 'bkid', 'pages', 'max_juz');
        echo str_repeat('─', 34) . "\n";
        foreach ($verifyRows as $row) {
            printf("%-10s %-10d %-10d\n", $row['bkid'], $row['pages'], $row['max_juz']);
        }
    }
} catch (Throwable $e) {
    warn('Verifikasi gagal: ' . $e->getMessage());
}

// ── Ringkasan ─────────────────────────────────────────────────
head('SELESAI');
ok("Kitab diproses      : {$doneKitab}");
ok("Baris total         : {$doneRows}");
info("Baris diupdate      : {$changed}");
info("Kitab multi-juz     : {$multiJuz}");

if (!empty($errors)) {
    warn('Error pada ' . count($errors) . ' kitab:');
    foreach ($errors as $bkid => $msg) {
        err("  bkid={$bkid}: {$msg}");
    }
}

if (!$dryRun && $doneKitab === $totalKitab - $startIndex) {
    // Semua selesai, hapus file progress
    if (file_exists($progressFile)) {
        unlink($progressFile);
        info('File progress dihapus (proses selesai sempurna).');
    }
}

echo dim('Waktu selesai: ' . date('Y-m-d H:i:s')) . "\n";
