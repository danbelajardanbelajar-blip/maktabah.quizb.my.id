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
        $stmt = $pdo->query(
            "SELECT c.id, c.name, c.catord, c.lvl,
                    COUNT(b.bkid) AS book_count
             FROM categories c
             LEFT JOIN books b ON b.category_id = c.id
             GROUP BY c.id
             ORDER BY c.catord ASC, c.name ASC"
        );
        echo json_encode(['data' => $stmt->fetchAll()]);
    }

}
