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
        $pdo = Database::getConnection();
        
        // Total kitab — hanya hitung buku yang memiliki konten
        $totalBooks = (int)$pdo->query("SELECT COUNT(DISTINCT bkid) FROM book_content")->fetchColumn();
        
        // Total kategori
        $totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        
        // Total pencarian
        $totalSearches = (int)$pdo->query("SELECT COUNT(*) FROM search_logs")->fetchColumn();
        
        // Total kunjungan
        $totalVisits = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_log")->fetchColumn();
        
        // Sedang Online (distinct IPs in last 5 minutes)
        $onlineUsers = (int)$pdo->query("SELECT COUNT(DISTINCT ip_address) FROM user_activity_log WHERE created_at >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
        // Pastikan nilainya minimal 1 (dirinya sendiri)
        if ($onlineUsers < 1) $onlineUsers = 1;
        
        echo json_encode([
            'total_books'       => $totalBooks,
            'total_categories'  => $totalCategories,
            'total_searches'    => $totalSearches,
            'total_visits'      => $totalVisits,
            'online_users'      => $onlineUsers
        ]);
    }

}
