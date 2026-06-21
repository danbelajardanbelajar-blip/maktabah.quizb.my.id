<?php
require_once __DIR__ . '/app/bootstrap.php';

header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$baseUrl = 'https://maktabah.quizb.my.id';

// Halaman Statis Utama
$staticPages = [
    '/',
    '/advanced-search',
    '/about',
    '/privacy',
    '/history',
    '/request-kitab'
];

foreach ($staticPages as $page) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($baseUrl . $page) . '</loc>' . "\n";
    echo '    <changefreq>daily</changefreq>' . "\n";
    echo '    <priority>0.8</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Halaman Dinamis (Kitab)
try {
    global $pdo;
    $stmt = $pdo->query("SELECT bkid FROM books");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($baseUrl . '/kitab?id=' . $row['bkid']) . '</loc>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.6</priority>' . "\n";
        echo '  </url>' . "\n";
    }
} catch (Exception $e) {
    // Abaikan jika terjadi error (tidak menghentikan load sitemap statis)
}

echo '</urlset>' . "\n";
