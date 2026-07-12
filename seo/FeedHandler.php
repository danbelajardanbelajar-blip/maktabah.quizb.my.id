<?php
namespace SEO;

class FeedHandler {
    
    public static function handleRequest() {
        $pdo = null;
        try {
            if (class_exists('\App\Config\Database')) {
                $pdo = \App\Config\Database::getConnection();
            }
        } catch (\Exception $e) { }

        $host = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'maktabah.quizb.my.id');
        
        header("Content-Type: application/rss+xml; charset=utf-8");
        
        $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>Al-Maktabah As-Sunniyyah</title>\n";
        $xml .= "    <link>{$host}</link>\n";
        $xml .= "    <description>Perpustakaan digital Islam memuat ribuan kitab salaf.</description>\n";
        $xml .= "    <language>id</language>\n";
        $xml .= "    <atom:link href=\"{$host}/feed.xml\" rel=\"self\" type=\"application/rss+xml\" />\n";

        if ($pdo) {
            $stmt = $pdo->query("SELECT bkId, bkname, author FROM books ORDER BY bkId DESC LIMIT 20");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $itemUrl = $host . "/baca/" . $row['bkId'];
                $xml .= "    <item>\n";
                $xml .= "      <title>" . htmlspecialchars($row['bkname']) . "</title>\n";
                $xml .= "      <link>{$itemUrl}</link>\n";
                $xml .= "      <description>Kitab " . htmlspecialchars($row['bkname']) . " karya " . htmlspecialchars($row['author']) . "</description>\n";
                $xml .= "      <guid>{$itemUrl}</guid>\n";
                $xml .= "    </item>\n";
            }
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>";
        
        echo $xml;
    }
}
