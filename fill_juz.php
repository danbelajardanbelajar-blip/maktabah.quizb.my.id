<?php
// ============================================================
//  fill_juz.php — Isi kolom `juz` pada tabel book_content
//  Jalankan via browser: https://yourdomain.com/fill_juz.php
//  Proses dicicil per-kitab agar tidak timeout
// ============================================================

// Keamanan: hanya bisa diakses dari IP server atau login admin
// (hapus baris berikut jika sudah pastikan aman)
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    // Uncomment untuk membatasi akses:
    // http_response_code(403); die('Forbidden');
}

set_time_limit(300);   // 5 menit maksimal
ignore_user_abort(true);

require_once __DIR__ . '/koneksi.php';

// Output langsung ke browser (streaming)
header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');
ob_implicit_flush(true);
if (ob_get_level()) ob_end_flush();

function out(string $msg): void {
    echo $msg . "\n";
    flush();
}

out("=== fill_juz.php — Pengisi kolom juz ===");
out("Waktu mulai: " . date('Y-m-d H:i:s'));
out("");

try {
    $pdo = getPDO();

    // ── LANGKAH 1: Cek / tambah kolom juz ──────────────────
    out("[ 1/4 ] Memeriksa kolom juz...");
    $cols = $pdo->query("SHOW COLUMNS FROM book_content LIKE 'juz'")->fetchAll();
    if (empty($cols)) {
        // Tambah kolom saja dulu, index menyusul setelah data terisi
        $pdo->exec(
            "ALTER TABLE book_content
             ADD COLUMN juz SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER page"
        );
        out("        ✓ Kolom juz berhasil ditambahkan.");
    } else {
        out("        ℹ Kolom juz sudah ada, lanjut ke pengisian data.");
    }

    // ── LANGKAH 2: Ambil semua bkid yang unik ───────────────
    out("");
    out("[ 2/4 ] Mengambil daftar kitab...");
    $bkids = $pdo
        ->query("SELECT DISTINCT bkid FROM book_content ORDER BY bkid ASC")
        ->fetchAll(PDO::FETCH_COLUMN);
    $totalKitab = count($bkids);
    out("        Ditemukan {$totalKitab} kitab.");

    // ── LANGKAH 3: Proses per-kitab ─────────────────────────
    out("");
    out("[ 3/4 ] Mengisi juz per kitab...");

    $stmtSelect = $pdo->prepare(
        "SELECT id, page FROM book_content WHERE bkid = ? ORDER BY id ASC"
    );
    $stmtUpdate = $pdo->prepare(
        "UPDATE book_content SET juz = ? WHERE id = ?"
    );

    $doneKitab  = 0;
    $totalRows  = 0;
    $multiJuzKitab = 0;

    foreach ($bkids as $bkid) {
        $stmtSelect->execute([$bkid]);
        $rows = $stmtSelect->fetchAll();

        $juz      = 1;
        $prevPage = -1;

        // Kumpulkan update per juz agar bisa di-batch
        // update[juzNum] = [id1, id2, ...]
        $byJuz = [];

        foreach ($rows as $row) {
            // Deteksi reset halaman → juz baru
            if ($prevPage >= 0 && (int)$row['page'] <= $prevPage) {
                $juz++;
            }
            $byJuz[$juz][] = (int)$row['id'];
            $prevPage = (int)$row['page'];
        }

        $maxJuz = max(array_keys($byJuz));
        if ($maxJuz > 1) {
            $multiJuzKitab++;
            out("        bkid={$bkid} → {$maxJuz} juz (" . count($rows) . " halaman)");
        }

        // UPDATE per kelompok juz (batch IN clause)
        foreach ($byJuz as $juzNum => $ids) {
            if ($juzNum === 1 && count($byJuz) === 1) {
                // Kitab satu juz — nilai default sudah 1, skip update jika mau hemat waktu
                // Tapi tetap update untuk memastikan konsistensi
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$juzNum], $ids);
            $pdo->prepare(
                "UPDATE book_content SET juz = ? WHERE id IN ({$placeholders})"
            )->execute($params);
        }

        $doneKitab++;
        $totalRows += count($rows);

        // Progress setiap 20 kitab
        if ($doneKitab % 20 === 0) {
            out("        Progress: {$doneKitab}/{$totalKitab} kitab, {$totalRows} baris diproses...");
        }
    }

    out("        ✓ Selesai: {$doneKitab} kitab, {$totalRows} baris, {$multiJuzKitab} kitab memiliki >1 juz.");

    // ── LANGKAH 4: Tambah index (jika belum ada) ────────────
    out("");
    out("[ 4/4 ] Menambah index...");
    $indexCheck = $pdo->query(
        "SHOW INDEX FROM book_content WHERE Key_name = 'idx_bkid_juz_page'"
    )->fetchAll();

    if (empty($indexCheck)) {
        $pdo->exec(
            "ALTER TABLE book_content
             ADD INDEX idx_bkid_juz_page (bkid, juz, page)"
        );
        out("        ✓ Index idx_bkid_juz_page berhasil ditambahkan.");
    } else {
        out("        ℹ Index sudah ada, dilewati.");
    }

    // ── Ringkasan ────────────────────────────────────────────
    out("");
    out("=== SELESAI ===");
    out("Waktu selesai : " . date('Y-m-d H:i:s'));
    out("Total kitab   : {$doneKitab}");
    out("Total baris   : {$totalRows}");
    out("Kitab multi-juz: {$multiJuzKitab}");
    out("");
    out("Langkah berikutnya:");
    out("  1. Hapus file ini dari server (fill_juz.php)");
    out("  2. Upload api.php dan app.js yang sudah diperbarui");

} catch (PDOException $e) {
    out("");
    out("✗ ERROR: " . $e->getMessage());
}
