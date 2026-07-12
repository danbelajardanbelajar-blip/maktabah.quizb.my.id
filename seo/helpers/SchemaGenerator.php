<?php
namespace SEO\Helpers;

class SchemaGenerator {
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

    public function generateSchemas() {
        $schemas = [];
        
        // 1. Organization Schema
        $schemas[] = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => "Al-Maktabah As-Sunniyyah",
            "url" => $this->host,
            "logo" => $this->host . "/favicon.png",
            "description" => "Perpustakaan digital Islam memuat ribuan kitab salaf."
        ];

        // 2. WebSite & SearchAction Schema
        $schemas[] = [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => "Al-Maktabah As-Sunniyyah",
            "url" => $this->host,
            "potentialAction" => [
                "@type" => "SearchAction",
                "target" => $this->host . "/?q={search_term_string}",
                "query-input" => "required name=search_term_string"
            ]
        ];

        // Determine specific page schema
        if (preg_match('/^\/baca\/([0-9]+)$/', $this->path, $matches)) {
            $bookId = $matches[1];
            $book = $this->getBookData($bookId);
            if ($book) {
                // Book Schema
                $schemas[] = [
                    "@context" => "https://schema.org",
                    "@type" => "Book",
                    "name" => $book['bkname'],
                    "author" => [
                        "@type" => "Person",
                        "name" => $book['author']
                    ],
                    "publisher" => [
                        "@type" => "Organization",
                        "name" => "Al-Maktabah As-Sunniyyah"
                    ],
                    "inLanguage" => "ar", // assuming arabic/indonesian
                    "url" => $this->host . $this->path
                ];
                // Breadcrumb Schema
                $schemas[] = $this->getBreadcrumbSchema([
                    ["name" => "Beranda", "item" => $this->host],
                    ["name" => $book['bkname'], "item" => $this->host . $this->path]
                ]);
            }
        } elseif (preg_match('/^\/kategori\/([0-9]+)$/', $this->path, $matches)) {
            $catId = $matches[1];
            $cat = $this->getCategoryData($catId);
            if ($cat) {
                // CollectionPage Schema
                $schemas[] = [
                    "@context" => "https://schema.org",
                    "@type" => "CollectionPage",
                    "name" => "Kategori: " . $cat['name'],
                    "description" => "Koleksi kitab Islam dalam kategori " . $cat['name'],
                    "url" => $this->host . $this->path
                ];
                // Breadcrumb Schema
                $schemas[] = $this->getBreadcrumbSchema([
                    ["name" => "Beranda", "item" => $this->host],
                    ["name" => "Kategori", "item" => $this->host . "/#categories"],
                    ["name" => $cat['name'], "item" => $this->host . $this->path]
                ]);
            }
        }

        $html = "";
        foreach ($schemas as $schema) {
            $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $html .= "<script type=\"application/ld+json\">\n{$json}\n</script>\n";
        }

        return $html;
    }

    private function getBreadcrumbSchema($itemListElement) {
        $elements = [];
        $position = 1;
        foreach ($itemListElement as $item) {
            $elements[] = [
                "@type" => "ListItem",
                "position" => $position,
                "name" => $item['name'],
                "item" => $item['item']
            ];
            $position++;
        }

        return [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => $elements
        ];
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
