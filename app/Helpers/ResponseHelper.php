<?php

namespace App\Helpers;

class ResponseHelper {
    public static function json(array $data, int $status = 200): void {
        http_response_code($status);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false) {
            $payload = json_encode(['error' => 'JSON encoding failed'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        echo $payload;
    }

    public static function getJsonRequest(): array {
        static $json = null;
        if ($json !== null) {
            return $json;
        }
        $input = file_get_contents('php://input');
        if ($input === false || trim($input) === '') {
            return $json = [];
        }
        $decoded = json_decode($input, true);
        return $json = is_array($decoded) ? $decoded : [];
    }
}
