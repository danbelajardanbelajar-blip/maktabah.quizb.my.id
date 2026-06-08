<?php
// ============================================================
// copy_content.php — copy data dari book_content_old ke book_content
// Jalankan di terminal:
//   php copy_content.php [--force] [--dry-run]
//
// Struktur sumber book_content_old:
//   id, bkid, page, content, created_at
// Struktur target book_content:
//   id, bkid, page, juz, content, created_at
//
// Kolom `juz` tidak disalin secara eksplisit karena sudah punya default 1.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan via terminal: php copy_content.php\n");
}

set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/koneksi.php';

function clr(string $code, string $text): string {
    return "\033[{$code}m{$text}\033[0m";
}
function ok(string $s): void   { echo clr('32', '✓') . " {$s}\n"; }
function info(string $s): void { echo clr('36', 'ℹ') . " {$s}\n"; }
function warn(string $s): void { echo clr('33', '⚠') . " {$s}\n"; }
function err(string $s): void  { echo clr('31', '✗') . " {$s}\n"; }
function head(string $s): void { echo "\n" . clr('1;37', $s) . "\n" . str_repeat('─', 56) . "\n"; }
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

$args = parseArgs($argv);
$force = isset($args['force']);
$dryRun = isset($args['dry-run']) || isset($args['dryrun']);

head('copy_content.php — Salin book_content_old ke book_content');
info('Waktu mulai: ' . date('Y-m-d H:i:s'));
if ($force) {
    warn('Mode force: target akan dikosongkan sebelum salin jika sudah berisi data.');
}
if ($dryRun) {
    warn('Mode dry-run: tidak akan melakukan perubahan di database.');
}

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $sourceExists = (bool)$pdo->query("SHOW TABLES LIKE 'book_content_old'")->fetchColumn();
    $targetExists = (bool)$pdo->query("SHOW TABLES LIKE 'book_content'")->fetchColumn();

    if (!$sourceExists) {
        err('Tabel sumber `book_content_old` tidak ditemukan.');
        exit(1);
    }
    if (!$targetExists) {
        err('Tabel target `book_content` tidak ditemukan.');
        exit(1);
    }

    $sourceCount = (int)$pdo->query('SELECT COUNT(*) FROM book_content_old')->fetchColumn();
    $targetCount = (int)$pdo->query('SELECT COUNT(*) FROM book_content')->fetchColumn();

    info('Jumlah baris di book_content_old: ' . $sourceCount);
    info('Jumlah baris di book_content: ' . $targetCount);

    if ($sourceCount === 0) {
        err('Tidak ada baris untuk disalin dari book_content_old.');
        exit(1);
    }

    if ($targetCount > 0 && !$force) {
        err('Tabel book_content sudah berisi data. Gunakan --force untuk mengosongkannya terlebih dahulu.');
        exit(1);
    }

    if ($dryRun) {
        ok('Dry-run selesai. Tidak ada data yang diubah.');
        exit(0);
    }

    $pdo->beginTransaction();

    if ($targetCount > 0 && $force) {
        info('Mengosongkan tabel book_content sebelum menyalin...');
        $pdo->exec('TRUNCATE TABLE book_content');
        ok('Tabel book_content dikosongkan.');
    }

    info('Menyalin data...');
    $insertSql = <<<SQL
INSERT INTO book_content (id, bkid, page, content, created_at)
SELECT id, bkid, page, content, created_at
FROM book_content_old
SQL;
    $inserted = $pdo->exec($insertSql);
    if ($inserted === false) {
        throw new RuntimeException('Eksekusi INSERT gagal.');
    }

    $pdo->commit();

    ok('Salin selesai. Baris disalin: ' . $inserted);
    info('Periksa tabel book_content untuk memastikan data sudah masuk dan kolom juz bernilai 1.');
    info('Waktu selesai: ' . date('Y-m-d H:i:s'));
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err('ERROR: ' . $e->getMessage());
    exit(1);
}
