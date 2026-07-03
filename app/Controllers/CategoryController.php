<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use PDO;
use Exception;

class CategoryController {
    public function handleCategories(): void {
        $pdo  = Database::getConnection();
        
        // Coba ambil jumlah kitab per kategori lebih efisien
        $counts = [];
        try {
            $stmtCounts = $pdo->query("SELECT category_id, COUNT(bkid) as c FROM books GROUP BY category_id");
            $counts = $stmtCounts->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\Exception $e) {}

        // Ambil semua kategori
        $stmt = $pdo->query(
            "SELECT id, name, catord, lvl
             FROM categories
             ORDER BY catord ASC, name ASC"
        );
        $categories = $stmt->fetchAll();
        
        // Gabungkan
        foreach ($categories as &$c) {
            $c['book_count'] = isset($counts[$c['id']]) ? (int)$counts[$c['id']] : 0;
        }

        echo json_encode(['data' => $categories]);
    }

}
