<?php
// ============================================================
// copy_ulang.php — Salin data dari book_content_old ke book_content
// Dilengkapi fitur Auto-Resume jika terputus di tengah jalan.
// ============================================================

if (php_sapi_name() !== 'cli') {
    die("Error: Script ini harus dijalankan melalui Terminal/SSH.\n");
}

set_time_limit(0);
ini_set('memory_limit', '1024M');
require_once __DIR__ . '/koneksi.php';

// Konfigurasi Nama Tabel
$tabelSumber = 'book_content_old';
$tabelTujuan = 'book_content';

function logMsg($msg) { 
    echo "[" . date('H:i:s') . "] " . $msg . "\n"; 
}

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    die("Koneksi gagal: " . $e->getMessage() . "\n");
}

logMsg("=== MEMULAI PROSES COPY DATA ===");

// 1. Ambil daftar kolom dari tabel SUMBER (book_content_old)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM $tabelSumber")->fetchAll(PDO::FETCH_COLUMN);
    // Hapus kolom 'juz' dari daftar jika tidak sengaja terambil dari tabel sumber
    $cols = array_diff($cols, ['juz']);
    $colNames = implode(', ', $cols);
} catch (Throwable $e) {
    die("Error: Tabel sumber ($tabelSumber) tidak ditemukan. Pastikan nama tabel benar.\n");
}

// 2. Cari titik awal (Resume) berdasarkan data yang sudah masuk di tabel TUJUAN
$lastIdNew = (int)$pdo->query("SELECT MAX(id) FROM $tabelTujuan")->fetchColumn();

if ($lastIdNew > 0) {
    $minId = $lastIdNew + 1;
    logMsg("Melanjutkan copy data dari ID: $minId...");
} else {
    $minId = (int)$pdo->query("SELECT MIN(id) FROM $tabelSumber")->fetchColumn();
    logMsg("Memulai dari awal. Min ID: $minId");
}

$maxId = (int)$pdo->query("SELECT MAX(id) FROM $tabelSumber")->fetchColumn();

// Jika proses ternyata sudah selesai
if ($minId > $maxId) {
    logMsg("Semua data dari $tabelSumber sudah berada di $tabelTujuan.");
    logMsg("=== PROCESS FINISHED ===");
    exit;
}

// 3. Proses penyalinan secara bertahap (Batching)
$chunkSize = 5000;
$copied = 0;

for ($i = $minId; $i <= $maxId; $i += $chunkSize) {
    $endId = $i + $chunkSize - 1;
    
    // Gunakan INSERT IGNORE untuk keamanan data ganda
    $sql = "INSERT IGNORE INTO $tabelTujuan ($colNames)
            SELECT $colNames FROM $tabelSumber
            WHERE id BETWEEN $i AND $endId";
            
    $pdo->exec($sql);
    
    $copied += $chunkSize;
    
    // Cetak log progress setiap 50.000 baris data
    if ($copied % 50000 === 0 || $i >= $maxId) {
        $pct = round(($endId / $maxId) * 100);
        $pct = $pct > 100 ? 100 : $pct; 
        logMsg("Progress: Tercopy s/d ID $endId ($pct%)");
    }
    
    // Jeda 200 milidetik agar CPU/RAM server tetap stabil
    usleep(200000); 
}

logMsg("=== PROSES COPY SELESAI SEPENUHNYA ===");
?>