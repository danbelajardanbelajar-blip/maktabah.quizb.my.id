<?php

namespace App\Helpers;

use App\Config\Database;

class SearchHelper {
    public static function logSearchQuery(string $type, string $query, int $resultCount, string $queryDetail = ''): void {
        try {
            $pdo      = Database::getConnection();
            $user     = AuthHelper::getSessionUser();
            $ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            $ip = trim(explode(',', $ip)[0]);
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

            $pdo->prepare(
                "INSERT INTO search_logs
                 (search_type, query, query_detail, result_count, visitor_ip, user_agent, user_id, user_name)
                 VALUES (:type, :query, :detail, :count, :ip, :ua, :uid, :uname)"
            )->execute([
                ':type'  => $type,
                ':query' => mb_substr($query, 0, 1000),
                ':detail' => $queryDetail ?: null,
                ':count' => $resultCount,
                ':ip'    => $ip,
                ':ua'    => $ua,
                ':uid'   => $user['id']   ?? null,
                ':uname' => $user['name'] ?? '',
            ]);
        } catch (\Exception $e) {}
    }

    public static function ftEscape(string $q): string {
        return preg_replace('/[+\-><()~*"@]+/', ' ', $q);
    }

    public static function isPhraseQuery(string $q): bool {
        return preg_match('/^"(.+)"$/u', trim($q)) === 1;
    }

    public static function searchPhraseText(string $q): string {
        $q = trim($q);
        if (self::isPhraseQuery($q)) {
            return trim(substr($q, 1, -1));
        }
        return $q;
    }

    public static function booleanSearchTerm(string $q): string {
        $q = trim($q);
        if ($q === '') return '';
        
        if (self::isPhraseQuery($q)) {
            return '"' . self::ftEscape(self::searchPhraseText($q)) . '"';
        }

        if (strpos($q, ' ') !== false) {
            return '"' . self::ftEscape($q) . '"';
        }

        $clean = self::ftEscape($q);
        if ($clean === '') return '';
        
        if (mb_strlen($clean) <= 2) {
            return $clean . '*';
        } else {
            return '+' . $clean . '*';
        }
    }

    public static function booleanQueryFromFieldsOr(array $fields): string {
        $parts = [];
        foreach ($fields as $field) {
            $field = trim($field);
            if ($field === '') continue;
            
            if (self::isPhraseQuery($field)) {
                $parts[] = '"' . self::ftEscape(self::searchPhraseText($field)) . '"';
                continue;
            }

            if (strpos($field, ' ') !== false) {
                $parts[] = '"' . self::ftEscape($field) . '"';
                continue;
            }

            $clean = self::ftEscape($field);
            if ($clean !== '') {
                $parts[] = $clean . '*';
            }
        }
        return implode(' ', $parts);
    }

    public static function booleanSearchQueryFromFields(array $fields): string {
        $parts = [];
        foreach ($fields as $field) {
            $term = self::booleanSearchTerm($field);
            if ($term !== '') {
                $parts[] = $term;
            }
        }
        return implode(' ', $parts);
    }

    public static function extractSmartSnippet($content, $terms, $maxLength = 350) {
        if (empty($content)) return '';
        
        $cleanContent = preg_replace('/\s+/', ' ', str_replace(["\n", "\r", "\t"], ' ', $content));
        $cleanContent = mb_substr($cleanContent, 0, 2000);
        
        if (empty($terms)) {
            return mb_substr($cleanContent, 0, $maxLength);
        }
        
        $diacritics = '[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}]*';
        $positions = [];
        
        foreach ($terms as $term) {
            $term = trim($term);
            if (empty($term)) continue;
            
            $term = preg_replace('/^"|"$/', '', $term);
            if (empty($term)) continue;
            
            // Hapus semua harokat dari kata kunci yang diinputkan pengguna
            $term = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}]/u', '', $term);
            if (empty($term)) continue;
            
            $patternChars = [];
            $chars = preg_split('//u', $term, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($chars as $char) {
                if (preg_match('/\s/u', $char)) {
                    $patternChars[] = '\s+';
                } else {
                    $patternChars[] = preg_quote($char, '/') . $diacritics;
                }
            }
            $pattern = '/' . implode('', $patternChars) . '/ui';
            
            if (preg_match_all($pattern, $cleanContent, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $positions[] = $match[1];
                }
            }
        }
        
        if (empty($positions)) {
            return mb_substr($cleanContent, 0, $maxLength);
        }
        
        sort($positions);
        $firstPos = reset($positions);
        $lastPos = end($positions);
        
        $snippetStart = max(0, $firstPos - 100);
        $snippetEnd = min(strlen($cleanContent), $lastPos + 400);
        
        $maxBytes = $maxLength * 2;
        $snippetLength = $snippetEnd - $snippetStart;
        if ($snippetLength > $maxBytes) {
            $snippetEnd = min($snippetEnd, $snippetStart + $maxBytes);
        }
        
        $snippet = mb_strcut($cleanContent, $snippetStart, $snippetEnd - $snippetStart);
        
        if (strlen($snippet) > ($maxBytes * 0.8)) {
            $lastSpace = strrpos($snippet, ' ');
            if ($lastSpace > 0 && $lastSpace < strlen($snippet) - 10) {
                $snippet = substr($snippet, 0, $lastSpace);
            }
        }
        
        return trim($snippet);
    }

    public static function utf8_levenshtein(string $str1, string $str2): int {
        $str1 = mb_strtolower($str1, 'UTF-8');
        $str2 = mb_strtolower($str2, 'UTF-8');
        $len1 = mb_strlen($str1, 'UTF-8');
        $len2 = mb_strlen($str2, 'UTF-8');
        
        if ($len1 == 0) return $len2;
        if ($len2 == 0) return $len1;
        
        $prevCol = range(0, $len2);
        for ($i = 0; $i < $len1; $i++) {
            $col = [$i + 1];
            $char1 = mb_substr($str1, $i, 1, 'UTF-8');
            for ($j = 0; $j < $len2; $j++) {
                $char2 = mb_substr($str2, $j, 1, 'UTF-8');
                $cost = ($char1 === $char2) ? 0 : 1;
                $col[] = min($col[$j] + 1, $prevCol[$j + 1] + 1, $prevCol[$j] + $cost);
            }
            $prevCol = $col;
        }
        return $prevCol[$len2];
    }

    public static function getDidYouMean(string $query): ?string {
        // Hanya cek jika panjang kata memadai
        $len = mb_strlen($query, 'UTF-8');
        if ($len < 3 || $len > 30) return null;

        // Cek jika mengandung spasi (frasa), kita bisa memecah tiap kata
        if (strpos($query, ' ') !== false) {
            $words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
            $suggestions = [];
            $changed = false;
            foreach ($words as $w) {
                if (mb_strlen($w, 'UTF-8') >= 3) {
                    $sug = self::getDidYouMeanSingleWord($w);
                    if ($sug && mb_strtolower($sug, 'UTF-8') !== mb_strtolower($w, 'UTF-8')) {
                        $suggestions[] = $sug;
                        $changed = true;
                    } else {
                        $suggestions[] = $w;
                    }
                } else {
                    $suggestions[] = $w;
                }
            }
            return $changed ? implode(' ', $suggestions) : null;
        }

        $sug = self::getDidYouMeanSingleWord($query);
        return ($sug && mb_strtolower($sug, 'UTF-8') !== mb_strtolower($query, 'UTF-8')) ? $sug : null;
    }

    private static function getDidYouMeanSingleWord(string $word): ?string {
        $pdo = Database::getConnection();
        $len = mb_strlen($word, 'UTF-8');
        $minLen = max(3, $len - 2);
        $maxLen = $len + 2;

        try {
            // Ambil kandidat kata yang memiliki huruf awal yang sama (sangat cepat karena menggunakan index primary key)
            // Ini akan memisahkan kata Arab dan Latin secara natural serta menemukan kata dengan frekuensi kecil
            $firstChar = mb_substr($word, 0, 1, 'UTF-8');
            $stmt = $pdo->prepare("SELECT word, frequency FROM search_dictionary WHERE word LIKE :prefix AND CHAR_LENGTH(word) BETWEEN :minl AND :maxl ORDER BY frequency DESC LIMIT 2000");
            $stmt->execute([':prefix' => $firstChar . '%', ':minl' => $minLen, ':maxl' => $maxLen]);
            
            $bestMatch = null;
            $minDist = -1;
            $bestFreq = 0;

            foreach ($stmt->fetchAll() as $row) {
                $w = $row['word'];
                // Jika isian adalah Latin, bisa pakai levenshtein() bawaan PHP yang lebih cepat
                // Jika Arab, pakai utf8_levenshtein
                if (preg_match('/^[a-zA-Z0-9]+$/', $word) && preg_match('/^[a-zA-Z0-9]+$/', $w)) {
                    $dist = levenshtein(strtolower($word), strtolower($w));
                } else {
                    $dist = self::utf8_levenshtein($word, $w);
                }

                // Toleransi typo: maksimal 2 karakter beda, atau 1 karakter untuk kata pendek
                $maxDist = ($len <= 4) ? 1 : 2;
                
                if ($dist <= $maxDist) {
                    if ($minDist === -1 || $dist < $minDist || ($dist === $minDist && $row['frequency'] > $bestFreq)) {
                        $minDist = $dist;
                        $bestMatch = $w;
                        $bestFreq = $row['frequency'];
                    }
                }
            }
            return $minDist >= 0 ? $bestMatch : null;
        } catch (\Exception $e) {
            return null; // Abaikan jika tabel belum ada atau error
        }
    }

    public static function syncContentToDictionary(string $content): void {
        if (empty(trim($content))) return;

        try {
            $pdo = Database::getConnection();
            $text = strip_tags($content);
            $text = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $text);
            $words = preg_split('/\s+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
            
            $wordCounts = [];
            foreach ($words as $w) {
                if (mb_strlen($w, 'UTF-8') < 3) continue;
                if (is_numeric($w)) continue;

                if (!isset($wordCounts[$w])) {
                    $wordCounts[$w] = 1;
                } else {
                    $wordCounts[$w]++;
                }
            }

            if (empty($wordCounts)) return;

            $insertStmt = $pdo->prepare("INSERT INTO search_dictionary (word, frequency) VALUES (:w, :f) ON DUPLICATE KEY UPDATE frequency = frequency + VALUES(frequency)");
            
            $pdo->beginTransaction();
            foreach ($wordCounts as $w => $f) {
                $insertStmt->execute([':w' => $w, ':f' => $f]);
            }
            $pdo->commit();
        } catch (\Exception $e) {
            // Abaikan jika error
        }
    }
}

