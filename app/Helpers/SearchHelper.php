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
}
