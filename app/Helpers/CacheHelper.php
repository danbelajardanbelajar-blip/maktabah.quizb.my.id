<?php

namespace App\Helpers;

class CacheHelper {
    private static function getCacheDir(): string {
        $dir = __DIR__ . '/../../cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback) {
        $cacheFile = self::getCacheDir() . '/' . md5($key) . '.json';
        
        // Cek jika cache masih valid
        if (file_exists($cacheFile)) {
            $mtime = filemtime($cacheFile);
            if (time() - $mtime < $ttlSeconds) {
                $content = file_get_contents($cacheFile);
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
        }
        
        // Eksekusi jika tidak ada cache atau expired
        $data = $callback();
        
        // Simpan ke cache
        file_put_contents($cacheFile, json_encode($data));
        
        return $data;
    }
}
