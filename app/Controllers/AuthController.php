<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use PDO;
use Exception;

class AuthController {
    public function handleAuthMe(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $user = AuthHelper::getSessionUser();
        if ($user) {
            echo json_encode(['loggedIn' => true, 'user' => $user]);
        } else {
            echo json_encode(['loggedIn' => false]);
        }
    }

    public function handleLogActivity(): void {
        $req   = ResponseHelper::getJsonRequest();
        $event = trim($req['event'] ?? '');
        $data  = $req['data']  ?? null;
    
        $allowed = ['visit', 'menu_click', 'login', 'logout', 'register'];
        if ($event === '' || !in_array($event, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid activity event.']);
            return;
        }
    
        $detail = null;
        if (is_array($data) || is_object($data)) {
            $detail = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } elseif ($data !== null) {
            $detail = (string)$data;
        }
    
        AuthHelper::logUserActivity($event, (string)($detail ?? ''));
        echo json_encode(['success' => true]);
    }

}
