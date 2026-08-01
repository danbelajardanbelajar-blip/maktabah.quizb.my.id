<?php
require_once __DIR__ . '/app/bootstrap.php';

try {
    $pdo = App\Config\Database::getConnection();

    $types = [
        'category' => 'Kategori',
        'basic' => 'Judul',
        'advanced' => 'Pencarian Lanjut',
        'scholarium_pdf' => 'PDF Scholarium',
        'archive_org' => 'Archive.org',
        'content' => 'Isi Kitab',
        'content_in_book' => 'Isi Dalam Kitab',
        'scholarium' => 'Isi Kitab Tersebar'
    ];

    echo "<h1>Seeding Data Riwayat Pencarian...</h1>";
    echo "<ul>";

    foreach ($types as $type => $label) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM search_logs WHERE search_type = ?");
        $stmt->execute([$type]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            echo "<li>Membuat dummy data untuk <b>$label</b>... ";
            
            // Buat 10 log: 7 sukses, 3 gagal
            $ins = $pdo->prepare("INSERT INTO search_logs (search_type, query, query_detail, result_count, visitor_ip, user_agent, user_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND() * 14) DAY)");
            
            // Sukses
            for ($i=1; $i<=7; $i++) {
                $ins->execute([
                    $type, 
                    "Pencarian Sukses $i", 
                    '', 
                    rand(1, 50), 
                    '127.0.0.1', 
                    'System/1.0', 
                    'Admin'
                ]);
            }
            
            // Gagal
            for ($i=1; $i<=3; $i++) {
                $ins->execute([
                    $type, 
                    "Pencarian Gagal $i", 
                    '', 
                    0, 
                    '127.0.0.1', 
                    'System/1.0', 
                    'Admin'
                ]);
            }
            
            echo "<span style='color:green;'>OK</span></li>";
        } else {
            echo "<li>Data untuk <b>$label</b> sudah ada ($count catatan). Melewati...</li>";
        }
    }
    echo "</ul>";
    echo "<p>Selesai! Silakan cek kembali dashboard Analytics Anda. Jika sudah, Anda bisa menghapus file ini.</p>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
