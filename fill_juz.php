<?php
// ============================================================
//  fill_juz.php  v3 — Batch auto-refresh, tahan timeout
//  Setiap request hanya kerjakan SATU langkah kecil,
//  lalu halaman otomatis lanjut ke langkah berikutnya.
//
//  STEP 0 = cek / tambah kolom juz (tanpa index, ALGORITHM=INSTANT)
//  STEP 1 = isi data juz per-kitab  (offset=N per request)
//  STEP 2 = tambah index
//  STEP 3 = selesai
// ============================================================

set_time_limit(55);        // maks 55 detik per request
ignore_user_abort(true);

require_once __DIR__ . '/koneksi.php';

// ── Baca state dari URL ────────────────────────────────────────
$step   = max(0, (int)($_GET['step']   ?? 0));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$batchSize = 5;   // proses N kitab per request (kecil agar aman)

// ── Helper output ──────────────────────────────────────────────
$logs = [];
function out(string $msg): void {
    global $logs;
    $logs[] = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function outOk(string $msg):  void { global $logs; $logs[] = '<span style="color:#2d8a4e">✓ ' . htmlspecialchars($msg) . '</span>'; }
function outErr(string $msg): void { global $logs; $logs[] = '<span style="color:#c0392b">✗ ' . htmlspecialchars($msg) . '</span>'; }
function outInfo(string $msg):void { global $logs; $logs[] = '<span style="color:#2471a3">ℹ ' . htmlspecialchars($msg) . '</span>'; }

// ── Koneksi ────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = getPDO();
    // Naikkan timeout sesi agar ALTER tidak di-interrupt
    $pdo->exec("SET SESSION wait_timeout       = 300");
    $pdo->exec("SET SESSION interactive_timeout = 300");
    $pdo->exec("SET SESSION net_read_timeout    = 300");
    $pdo->exec("SET SESSION net_write_timeout   = 300");
} catch (Throwable $e) {
    // Lanjut meski timeout settings gagal (hak terbatas)
}

// ══════════════════════════════════════════════════════════════
//  STEP 0 — Tambah kolom juz (tanpa index dulu)
// ══════════════════════════════════════════════════════════════
if ($step === 0) {
    out("[ STEP 0 ] Memeriksa kolom juz…");

    $cols = $pdo->query("SHOW COLUMNS FROM book_content LIKE 'juz'")->fetchAll();
    if (!empty($cols)) {
        outInfo("Kolom juz sudah ada, lewati ke STEP 1.");
        $nextUrl = "?step=1&offset=0";
    } else {
        // Coba ALGORITHM=INSTANT dulu (MySQL 8.0+ / MariaDB 10.4+) → sangat cepat
        $ok = false;
        try {
            $pdo->exec(
                "ALTER TABLE book_content
                 ADD COLUMN juz SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER page,
                 ALGORITHM=INSTANT"
            );
            $ok = true;
            outOk("Kolom juz ditambahkan (INSTANT).");
        } catch (Throwable $e1) {
            outInfo("INSTANT tidak didukung, coba INPLACE…");
            try {
                $pdo->exec(
                    "ALTER TABLE book_content
                     ADD COLUMN juz SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER page,
                     ALGORITHM=INPLACE, LOCK=NONE"
                );
                $ok = true;
                outOk("Kolom juz ditambahkan (INPLACE).");
            } catch (Throwable $e2) {
                outInfo("INPLACE gagal, coba ALTER biasa…");
                try {
                    $pdo->exec(
                        "ALTER TABLE book_content
                         ADD COLUMN juz SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER page"
                    );
                    $ok = true;
                    outOk("Kolom juz ditambahkan.");
                } catch (Throwable $e3) {
                    outErr("Gagal menambah kolom: " . $e3->getMessage());
                    outErr("Silakan tambahkan kolom manual di phpMyAdmin:");
                    out("<code style='background:#f5f5f5;padding:4px 8px;border-radius:4px;font-size:12px;display:block;margin:4px 0'>ALTER TABLE book_content ADD COLUMN juz SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER page;</code>");
                    out("Setelah kolom ditambah manual, akses: <a href='?step=1&offset=0'>Lanjutkan ke Step 1</a>");
                }
            }
        }

        $nextUrl = $ok ? "?step=1&offset=0" : null;
    }
}

// ══════════════════════════════════════════════════════════════
//  STEP 1 — Isi data juz per-kitab (batching)
// ══════════════════════════════════════════════════════════════
elseif ($step === 1) {
    out("[ STEP 1 ] Mengisi data juz… (batch offset={$offset})");

    // Ambil semua bkid
    $bkids = $pdo->query(
        "SELECT DISTINCT bkid FROM book_content ORDER BY bkid ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
    $total = count($bkids);

    if ($offset >= $total) {
        outOk("Semua {$total} kitab selesai diproses.");
        $nextUrl = "?step=2&offset=0";
    } else {
        $batch = array_slice($bkids, $offset, $batchSize);

        $stmtSel = $pdo->prepare(
            "SELECT id, page FROM book_content WHERE bkid = ? ORDER BY id ASC"
        );
        $stmtUpd = $pdo->prepare(
            "UPDATE book_content SET juz = ? WHERE id IN ({PLACEHOLDER})"
        );

        $done = 0;
        foreach ($batch as $bkid) {
            $stmtSel->execute([$bkid]);
            $rows = $stmtSel->fetchAll();

            $juz      = 1;
            $prevPage = -1;
            $byJuz    = [];   // [juzNum => [id, ...]]

            foreach ($rows as $row) {
                if ($prevPage >= 0 && (int)$row['page'] <= $prevPage) {
                    $juz++;
                }
                $byJuz[$juz][] = (int)$row['id'];
                $prevPage = (int)$row['page'];
            }

            // UPDATE per kelompok juz
            foreach ($byJuz as $juzNum => $ids) {
                $ph  = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE book_content SET juz = ? WHERE id IN ($ph)")
                    ->execute(array_merge([$juzNum], $ids));
            }

            $maxJuz = !empty($byJuz) ? max(array_keys($byJuz)) : 1;
            if ($maxJuz > 1) {
                out("  bkid={$bkid} → {$maxJuz} juz (" . count($rows) . " hal.)");
            }
            $done++;
        }

        $newOffset = $offset + $done;
        $pct = round($newOffset / $total * 100);
        outOk("Selesai {$newOffset}/{$total} kitab ({$pct}%)");
        $nextUrl = "?step=1&offset={$newOffset}";
    }
}

// ══════════════════════════════════════════════════════════════
//  STEP 2 — Tambah index
// ══════════════════════════════════════════════════════════════
elseif ($step === 2) {
    out("[ STEP 2 ] Menambahkan index…");

    $exists = $pdo->query(
        "SHOW INDEX FROM book_content WHERE Key_name = 'idx_bkid_juz_page'"
    )->fetchAll();

    if (!empty($exists)) {
        outInfo("Index sudah ada.");
    } else {
        try {
            $pdo->exec(
                "ALTER TABLE book_content
                 ADD INDEX idx_bkid_juz_page (bkid, juz, page)"
            );
            outOk("Index idx_bkid_juz_page ditambahkan.");
        } catch (Throwable $e) {
            outErr("Index gagal: " . $e->getMessage() . " (tidak fatal, data tetap benar)");
        }
    }

    $nextUrl = "?step=3";
}

// ══════════════════════════════════════════════════════════════
//  STEP 3 — Selesai
// ══════════════════════════════════════════════════════════════
elseif ($step === 3) {
    // Statistik akhir
    $stat = $pdo->query(
        "SELECT bkid, MAX(juz) AS max_juz FROM book_content
         GROUP BY bkid HAVING max_juz > 1 ORDER BY max_juz DESC"
    )->fetchAll();

    outOk("=== SELESAI! Semua langkah berhasil. ===");
    out("Kitab dengan >1 juz: <strong>" . count($stat) . "</strong>");
    foreach ($stat as $r) {
        out("  bkid={$r['bkid']} → {$r['max_juz']} juz");
    }
    out("");
    out("<strong>Langkah selanjutnya:</strong>");
    out("1. Hapus file ini dari server (<code>fill_juz.php</code>)");
    out("2. Upload <code>api.php</code> dan <code>app.js</code> yang sudah diperbarui");
    $nextUrl = null;
}

// ── Hitung total bkid untuk progress bar (step 1) ─────────────
$totalBkid = 0;
if ($step === 1) {
    $totalBkid = (int)$pdo->query(
        "SELECT COUNT(DISTINCT bkid) FROM book_content"
    )->fetchColumn();
}

// ── Auto-redirect ──────────────────────────────────────────────
$autoRefreshMs = isset($nextUrl) ? 600 : 0; // 0.6 detik jeda sebelum lanjut

?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>fill_juz — Al-Maktabah</title>
<?php if (isset($nextUrl)): ?>
<meta http-equiv="refresh" content="1;url=<?= htmlspecialchars($nextUrl) ?>">
<?php endif; ?>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', sans-serif; background: #f4f6f8; margin: 0; padding: 20px; color: #1c1c1e; }
  .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08); max-width: 680px; margin: 0 auto; padding: 28px 32px; }
  h1 { font-size: 1.2rem; color: #1a3a2a; margin: 0 0 4px; }
  .sub { font-size: .85rem; color: #888; margin-bottom: 20px; }
  .log { background: #f9f9f9; border: 1px solid #e8e4d9; border-radius: 10px; padding: 14px 16px;
         font-size: .82rem; line-height: 1.8; max-height: 360px; overflow-y: auto; font-family: monospace; }
  .log div { margin: 1px 0; }
  code { background: #f0ece0; padding: 2px 6px; border-radius: 4px; font-size: .8rem; }
  .progress-bar { background: #e8e4d9; border-radius: 999px; height: 10px; margin: 16px 0 6px; overflow: hidden; }
  .progress-fill { background: linear-gradient(90deg, #1a3a2a, #c9a84c); height: 100%; border-radius: 999px; transition: width .4s; }
  .pct { font-size: .8rem; color: #888; text-align: right; }
  .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #c9a84c; border-top-color: transparent; border-radius: 50%; animation: spin .7s linear infinite; vertical-align: middle; margin-right: 6px; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .btn { display: inline-block; margin-top: 16px; padding: 10px 22px; background: #1a3a2a; color: #fff; border-radius: 10px; text-decoration: none; font-size: .9rem; font-weight: 600; }
  .done-banner { background: #eafaf1; border: 1px solid #2ecc71; border-radius: 10px; padding: 14px 18px; margin-top: 16px; font-size: .9rem; color: #1e8449; font-weight: 600; }
</style>
</head>
<body>
<div class="card">
  <h1>🔧 fill_juz.php — Al-Maktabah As-Sunniyyah</h1>
  <div class="sub">Mengisi kolom <code>juz</code> pada tabel <code>book_content</code> secara otomatis</div>

  <?php
  // Progress bar (step 1)
  if ($step === 1 && $totalBkid > 0):
    $pct = min(100, round($offset / $totalBkid * 100));
  ?>
  <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
  <div class="pct"><?= $pct ?>% (<?= $offset ?> / <?= $totalBkid ?> kitab)</div>
  <?php endif; ?>

  <div class="log">
  <?php foreach ($logs as $line): ?>
    <div><?= $line ?></div>
  <?php endforeach; ?>
  <?php if (isset($nextUrl)): ?>
    <div><span class="spinner"></span><em>Melanjutkan otomatis…</em></div>
  <?php endif; ?>
  </div>

  <?php if ($step === 3): ?>
    <div class="done-banner">✅ Proses selesai! Hapus file ini dari server, lalu upload file yang sudah diperbarui.</div>
  <?php elseif (isset($nextUrl)): ?>
    <p style="font-size:.82rem;color:#888;margin-top:12px">
      Halaman akan otomatis lanjut. Jika tidak, <a href="<?= htmlspecialchars($nextUrl) ?>">klik di sini</a>.
    </p>
  <?php else: ?>
    <a class="btn" href="?step=0">↺ Mulai Ulang</a>
  <?php endif; ?>

  <p style="font-size:.75rem;color:#bbb;margin-top:20px">
    Step: <?= $step ?> | Offset: <?= $offset ?> | <?= date('H:i:s') ?>
  </p>
</div>
</body>
</html>
