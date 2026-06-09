<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class BookModel {
    public static function getBooks(int $page, int $limit, ?int $catId) {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $limit;
        
        $where  = $catId !== null ? 'WHERE b.category_id = :cat' : '';
        $params = $catId !== null ? [':cat' => $catId] : [];

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM books b $where");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $sql  = "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name
                 FROM books b $where
                 ORDER BY b.bkid DESC
                 LIMIT :lim OFFSET :off";
        $stmt = $pdo->prepare($sql);
        if ($catId !== null) $stmt->bindValue(':cat', $catId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':off',  $offset, PDO::PARAM_INT);
        $stmt->execute();
        $books = $stmt->fetchAll();

        return [
            'data'        => $books,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ];
    }

    public static function getBookById(int $id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT b.*, c.name AS cat_name
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             WHERE b.bkid = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $book = $stmt->fetch();

        if (!$book) return null;

        $juzStmt = $pdo->prepare(
            "SELECT juz, COUNT(*) AS pages
             FROM book_content
             WHERE bkid = :id
             GROUP BY juz
             ORDER BY juz ASC"
        );
        $juzStmt->execute([':id' => $id]);
        $juzRows = $juzStmt->fetchAll();

        $juzList = array_map(fn($r) => [
            'juz'   => (int)$r['juz'],
            'pages' => (int)$r['pages'],
        ], $juzRows);

        $book['juz_list']      = $juzList;
        $book['total_juz']     = count($juzList);
        $book['content_pages'] = array_sum(array_column($juzList, 'pages'));

        return $book;
    }
}
