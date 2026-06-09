<?php

namespace App\Helpers;

use App\Config\Database;

class AuthHelper {
    public static function getSessionUser(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public static function requireAdmin(): void {
        $user = self::getSessionUser();
        if (!$user || $user['role'] !== 'admin') {
            ResponseHelper::json(['error' => 'Akses ditolak. Diperlukan hak admin.'], 403);
            exit;
        }
    }

    public static function requireLogin(): void {
        $user = self::getSessionUser();
        if (!$user) {
            ResponseHelper::json(['error' => 'Anda harus login terlebih dahulu.'], 401);
            exit;
        }
    }

    public static function logCrudHistory(string $action, string $tableName, string $recordId, string $detail = ''): void {
        try {
            $pdo  = Database::getConnection();
            $user = self::getSessionUser();
            $pdo->prepare(
                "INSERT INTO crud_history (admin_id, admin_name, admin_email, action, table_name, record_id, detail)
                 VALUES (:admin_id, :admin_name, :admin_email, :action, :table_name, :record_id, :detail)"
            )->execute([
                ':admin_id'    => $user['id']    ?? null,
                ':admin_name'  => $user['name']  ?? 'Unknown',
                ':admin_email' => $user['email'] ?? '',
                ':action'      => $action,
                ':table_name'  => $tableName,
                ':record_id'   => $recordId,
                ':detail'      => $detail,
            ]);
        } catch (\Exception $e) {}
    }

    public static function logUserActivity(string $event, string $eventData = ''): void {
        try {
            $pdo  = Database::getConnection();
            $user = self::getSessionUser();
            $ip   = $_SERVER['HTTP_X_FORWARDED_FOR']
                    ?? $_SERVER['HTTP_X_REAL_IP']
                    ?? $_SERVER['REMOTE_ADDR']
                    ?? '';
            $ip = trim(explode(',', $ip)[0]);
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

            $stmt = $pdo->prepare(
                "INSERT INTO user_activity_log
                 (user_id, user_name, user_email, user_role, event, event_data, ip_address, user_agent)
                 VALUES (:uid, :uname, :uemail, :urole, :event, :edata, :ip, :ua)"
            );
            $stmt->execute([
                ':uid'    => $user['id']    ?? null,
                ':uname'  => $user['name']  ?? '',
                ':uemail' => $user['email'] ?? '',
                ':urole'  => $user['role']  ?? 'user',
                ':event'  => $event,
                ':edata'  => $eventData !== '' ? $eventData : null,
                ':ip'     => $ip,
                ':ua'     => $ua,
            ]);
        } catch (\Exception $e) {}
    }
}
