<?php
namespace SEO\Helpers;

class MetaGenerator {
    private $pdo;
    private $path;
    private $host;

    public function __construct() {
        try {
            if (class_exists('\App\Config\Database')) {
                $this->pdo = \App\Config\Database::getConnection();
            }
        } catch (\Exception $e) {
            $this->pdo = null;
        }
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->host = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'maktabah.quizb.my.id');
    }

    public function generateTags() {
        $html = "";
        
        $canonical = $this->host . $this->path;
        $title = "Perpustakaan Digital Islam — Al-Maktabah As-Sunniyyah";
        $description = "Perpustakaan digital Islam memuat ribuan kitab salaf, referensi Islam, dan kitab kuning.";
        $image = $this->host . "/favicon.png";
        $type = "website";

        // Determine context
        if (preg_match('/^\/baca\/([0-9]+)$/', $this->path, $matches)) {
            $bookId = $matches[1];
            $book = $this->getBookData($bookId);
            if ($book) {
                $title = "Baca Kitab " . htmlspecialchars($book['bkname']) . " — Al-Maktabah As-Sunniyyah";
                $description = "Baca kitab kuning " . htmlspecialchars($book['bkname']) . " karya " . htmlspecialchars($book['author']) . ". Tersedia gratis di Maktabah Digital kami.";
                $type = "article";
            }
        } elseif (preg_match('/^\/kategori\/([0-9]+)$/', $this->path, $matches)) {
            $catId = $matches[1];
            $cat = $this->getCategoryData($catId);
            if ($cat) {
                $title = "Kategori: " . htmlspecialchars($cat['name']) . " — Al-Maktabah As-Sunniyyah";
                $description = "Koleksi kitab Islam dalam kategori " . htmlspecialchars($cat['name']) . ". Temukan berbagai referensi Islam di sini.";
            }
        }

        // Canonical (only add if not query strings, to avoid duplicate issues)
        $html .= "<link rel=\"canonical\" href=\"{$canonical}\" />\n";

        // Generate OpenGraph
        $html .= "<meta property=\"og:site_name\" content=\"Al-Maktabah As-Sunniyyah\" />\n";
        $html .= "<meta property=\"og:title\" content=\"{$title}\" />\n";
        $html .= "<meta property=\"og:description\" content=\"{$description}\" />\n";
        $html .= "<meta property=\"og:url\" content=\"{$canonical}\" />\n";
        $html .= "<meta property=\"og:type\" content=\"{$type}\" />\n";
        $html .= "<meta property=\"og:image\" content=\"{$image}\" />\n";

        // Generate Twitter Card
        $html .= "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
        $html .= "<meta name=\"twitter:title\" content=\"{$title}\" />\n";
        $html .= "<meta name=\"twitter:description\" content=\"{$description}\" />\n";
        $html .= "<meta name=\"twitter:image\" content=\"{$image}\" />\n";

        // SEO Keywords
        $html .= "<meta name=\"keywords\" content=\"Perpustakaan Digital Islam, Maktabah Digital, Koleksi Kitab Islam, Kitab Kuning, Referensi Islam, Kitab Salaf\" />\n";

        return $html;
    }

    private function getBookData($id) {
        if (!$this->pdo) return null;
        try {
            $stmt = $this->pdo->prepare("SELECT bkId, bkname, author FROM books WHERE bkId = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) { return null; }
    }

    private function getCategoryData($id) {
        if (!$this->pdo) return null;
        try {
            $stmt = $this->pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) { return null; }
    }
}
