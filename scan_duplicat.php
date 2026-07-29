<?php
require_once __DIR__ . '/app/bootstrap.php';
use App\Config\Database;

@set_time_limit(600);
@ini_set('memory_limit', '512M');

$pdo = Database::getConnection();

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ids'])) {
    $deletedCount = 0;
    foreach ($_POST['delete_ids'] as $delId) {
        $delId = (int)$delId;
        if ($delId > 0) {
            $pdo->prepare("DELETE FROM book_toc WHERE bkid = ?")->execute([$delId]);
            $pdo->prepare("DELETE FROM book_content WHERE bkid = ?")->execute([$delId]);
            $pdo->prepare("DELETE FROM books WHERE bkid = ?")->execute([$delId]);
            $deletedCount++;
        }
    }
    $msg = "<div style='color:green; font-weight:bold; margin-bottom: 20px; padding:10px; background:#dcfce7; border-radius:5px;'>Berhasil menghapus $deletedCount kitab duplikat secara permanen!</div>";
}

// 1. Cari kandidat duplikat berdasarkan judul dan jumlah halaman (Sangat Cepat)
$stmt = $pdo->query("SELECT title, pages, GROUP_CONCAT(bkid) as bkids FROM books GROUP BY title, pages HAVING COUNT(bkid) > 1 LIMIT 100");
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$confirmedDuplicates = [];

// 2. Format kandidat menjadi confirmed duplicates (Kriteria Size dihapus)
foreach ($candidates as $cand) {
    $bkids = explode(',', $cand['bkids']);
    if (count($bkids) > 1) {
        $confirmedDuplicates[] = [
            'title' => $cand['title'],
            'pages' => $cand['pages'],
            'size'  => 0, // Tidak dihitung
            'bkids' => $bkids
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner Duplikasi Kitab - Maktabah</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f3f4f6; color: #1f2937; }
        .container { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 1000px; margin: auto; }
        h2 { color: #166534; margin-top: 0; }
        .criteria { background: #fef3c7; border-left: 4px solid #d97706; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th, td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
        th { background: #166534; color: white; font-weight: 600; }
        .btn { padding: 10px 20px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn:hover { background: #b91c1c; }
        .btn-refresh { background: #2563eb; margin-right: 10px; text-decoration: none; display: inline-block; }
        .btn-refresh:hover { background: #1d4ed8; }
        .group-header { background: #f9fafb; font-weight: bold; color: #374151; }
        .bkid-row:hover { background: #f3f4f6; }
        .bkid-row label { display: block; cursor: pointer; width: 100%; }
        .checkbox-cell { text-align: center; width: 80px; }
        input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .keep-badge { font-size: 11px; background: #16a34a; color: white; padding: 2px 6px; border-radius: 10px; margin-left: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Scanner Duplikasi Kitab Maktabah</h2>
    <div class="criteria">
        <strong>Mencari kitab dengan 2 kriteria kesamaan ketat:</strong><br>
        1. Kesamaan Nama / Judul Buku<br>
        2. Kesamaan Jumlah Halaman
    </div>
    
    <?= $msg ?? '' ?>

    <?php if (empty($confirmedDuplicates)): ?>
        <div style="text-align: center; padding: 40px;">
            <svg style="width:64px;height:64px;color:#16a34a;margin:auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <h3 style="color: #16a34a;">Aman! Tidak ada kitab duplikat ditemukan.</h3>
            <p>Database saat ini bersih dari duplikasi berdasarkan 2 kriteria tersebut (Limit periksa: 100 grup).</p>
            <a href="scan_duplicat.php" class="btn btn-refresh">Scan Ulang</a>
        </div>
    <?php else: ?>
        <form method="POST" onsubmit="return confirm('PERINGATAN: Kitab yang dicentang akan DIHAPUS PERMANEN beserta isi dan daftar isinya. Anda yakin?');">
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell">Pilih (Hapus)</th>
                        <th>ID Kitab</th>
                        <th>Judul Kitab</th>
                        <th>Halaman</th>
                        <th>Size (Dihapus)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($confirmedDuplicates as $index => $dup): ?>
                        <tr class="group-header">
                            <td colspan="5">Grup Duplikat #<?= $index + 1 ?>: <span style="color:#166534;"><?= htmlspecialchars($dup['title']) ?></span></td>
                        </tr>
                        <?php 
                        // ID pertama tidak dicentang (untuk di-keep), sisanya dicentang otomatis
                        foreach ($dup['bkids'] as $i => $id): 
                            $isChecked = ($i > 0) ? 'checked' : ''; 
                            $isKeep = ($i === 0) ? '<span class="keep-badge">Simpan</span>' : '';
                        ?>
                        <tr class="bkid-row">
                            <td class="checkbox-cell">
                                <input type="checkbox" name="delete_ids[]" value="<?= $id ?>" id="chk_<?= $id ?>" <?= $isChecked ?>>
                            </td>
                            <td><label for="chk_<?= $id ?>"><strong><?= $id ?></strong> <?= $isKeep ?></label></td>
                            <td><label for="chk_<?= $id ?>"><?= htmlspecialchars($dup['title']) ?></label></td>
                            <td><label for="chk_<?= $id ?>"><?= $dup['pages'] ?></label></td>
                            <td><label for="chk_<?= $id ?>">-</label></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="scan_duplicat.php" class="btn btn-refresh" style="padding: 10px 20px;">Refresh Data</a>
                <button type="submit" class="btn">Hapus Kitab Tercentang</button>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
