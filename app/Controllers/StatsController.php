<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use PDO;
use Exception;

class StatsController {
    public function handleStats(): void {
        $data = \App\Helpers\CacheHelper::remember('home_stats', 600, function() {
            $pdo = Database::getConnection();
            
            // Total kitab — hanya hitung buku yang memiliki konten
            $totalBooks = (int)$pdo->query("SELECT COUNT(DISTINCT bkid) FROM book_content")->fetchColumn();
            
            // Total kategori
            $totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            
            // Total pencarian
            $totalSearches = (int)$pdo->query("SELECT COUNT(*) FROM search_logs")->fetchColumn();
            
            // Total kunjungan
            $totalVisits = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_log")->fetchColumn();
            
            return [
                'total_books'       => $totalBooks,
                'total_categories'  => $totalCategories,
                'total_searches'    => $totalSearches,
                'total_visits'      => $totalVisits
            ];
        });

        // Sedang Online (distinct IPs in last 5 minutes) - jangan dicache terlalu lama
        $pdo = Database::getConnection();
        $onlineUsers = (int)$pdo->query("SELECT COUNT(DISTINCT ip_address) FROM user_activity_log WHERE created_at >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
        if ($onlineUsers < 1) $onlineUsers = 1;
        
        $data['online_users'] = $onlineUsers;

        echo json_encode($data);
    }

}
