<?php
namespace SEO;

class SitemapHandler {
    
    public static function handleRequest($path) {
        require_once __DIR__ . '/../app/bootstrap.php';
        
        $pdo = null;
        try {
            $pdo = \App\Config\Database::getConnection();
        } catch (\Exception $e) { }

        $host = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'maktabah.quizb.my.id');
        
        header("Content-Type: application/xml; charset=utf-8");
        
        if ($path === '/sitemap.xml') {
            echo self::generateIndex($host);
        } elseif ($path === '/sitemap-kitab.xml') {
            echo self::generateBooks($pdo, $host);
        } elseif ($path === '/sitemap-kategori.xml') {
            echo self::generateCategories($pdo, $host);
        } elseif ($path === '/sitemap-image.xml') {
            echo self::generateImages($host);
        } else {
            // fallback
            echo self::generateIndex($host);
        }
    }

    private static function generateIndex($host) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $sitemaps = [
            $host . '/sitemap-kitab.xml',
            $host . '/sitemap-kategori.xml',
            $host . '/sitemap-image.xml'
        ];
        
        $date = date('Y-m-d\TH:i:sP');
        foreach ($sitemaps as $url) {
            $xml .= "  <sitemap>\n";
            $xml .= "    <loc>{$url}</loc>\n";
            $xml .= "    <lastmod>{$date}</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }
        
        $xml .= '</sitemapindex>';
        return $xml;
    }

    private static function generateBooks($pdo, $host) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        if ($pdo) {
            $stmt = $pdo->query("SELECT bkId FROM books ORDER BY bkId DESC");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $xml .= "  <url>\n";
                $xml .= "    <loc>{$host}/baca/{$row['bkId']}</loc>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>0.8</priority>\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private static function generateCategories($pdo, $host) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        if ($pdo) {
            $stmt = $pdo->query("SELECT id FROM categories ORDER BY id ASC");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $xml .= "  <url>\n";
                $xml .= "    <loc>{$host}/kategori/{$row['id']}</loc>\n";
                $xml .= "    <changefreq>monthly</changefreq>\n";
                $xml .= "    <priority>0.6</priority>\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private static function generateImages($host) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        
        // Homepage favicon image as basic
        $xml .= "  <url>\n";
        $xml .= "    <loc>{$host}/</loc>\n";
        $xml .= "    <image:image>\n";
        $xml .= "      <image:loc>{$host}/favicon.png</image:loc>\n";
        $xml .= "      <image:title>Al-Maktabah As-Sunniyyah</image:title>\n";
        $xml .= "    </image:image>\n";
        $xml .= "  </url>\n";

        $xml .= '</urlset>';
        return $xml;
    }
}
