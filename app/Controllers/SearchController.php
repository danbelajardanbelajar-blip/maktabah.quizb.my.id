<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use PDO;
use Exception;

class SearchController {
    public function handleSearchCategories(): void {
        header('Cache-Control: public, max-age=120');
        $pdo       = Database::getConnection();
        $qRaw      = trim($_GET['q'] ?? '');
        $q         = SearchHelper::searchPhraseText($qRaw);
        if (strlen($q) < 2) { echo json_encode(['data' => []]); return; }
    
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare(
            "SELECT c.id, c.name, COUNT(b.bkid) AS book_count
             FROM categories c
             LEFT JOIN books b ON b.category_id = c.id
             WHERE c.name LIKE :lk
             GROUP BY c.id
             ORDER BY book_count DESC
             LIMIT 20"
        );
        $stmt->execute([':lk' => $like]);
        echo json_encode(['data' => $stmt->fetchAll()]);
    }

    public function handleSearchBooks(): void {
        header('Cache-Control: public, max-age=120');
        $pdo   = Database::getConnection();
        $qRaw  = trim($_GET['q'] ?? '');
        $q     = SearchHelper::searchPhraseText($qRaw);
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;
        $isFirstPage = ($page === 1); // Log hanya halaman pertama
    
        if (strlen($q) < 2) {
            echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
            return;
        }
    
        $hash       = 'books:' . hash('sha256', strtolower($qRaw));
        $qStar      = SearchHelper::booleanSearchTerm($qRaw);
        $like       = '%' . $q . '%';
        $phraseLike = preg_match('/\s+/u', $q) ? $like : null;
    
        // --- Cache hit (page 1 only) ---
        if ($page === 1) {
            $cs = $pdo->prepare(
                "SELECT results_json, result_count FROM search_cache
                 WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
            );
            $cs->execute([':h' => $hash]);
            if ($row = $cs->fetch()) {
                $all = json_decode($row['results_json'], true) ?? [];
                if ($isFirstPage) SearchHelper::logSearchQuery('basic', $qRaw, (int)$row['result_count']);
                echo json_encode([
                    'data'        => array_slice($all, 0, $limit),
                    'total'       => (int)$row['result_count'],
                    'page'        => 1,
                    'total_pages' => (int)ceil($row['result_count'] / $limit),
                    'cached'      => true,
                ]);
                return;
            }
        }
    
        // --- Query ---
        $stmt = $pdo->prepare(
            "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name,
                    MATCH(b.title) AGAINST (:q1 IN BOOLEAN MODE) AS rel
             FROM books b
             WHERE MATCH(b.title) AGAINST (:q2 IN BOOLEAN MODE)
             ORDER BY rel DESC, b.title ASC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':q1',  $qStar, PDO::PARAM_STR);
        $stmt->bindValue(':q2',  $qStar, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $books = $stmt->fetchAll();
    
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM books
             WHERE MATCH(title) AGAINST (:q IN BOOLEAN MODE)"
        );
        $countStmt->execute([':q' => $qStar]);
        $total = (int)$countStmt->fetchColumn();
    
        // --- Cache store ---
        if ($page === 1 && $total > 0) {
            $pdo->prepare(
                "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
                 VALUES (:h, :qt, :rj, :rc, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                 ON DUPLICATE KEY UPDATE results_json=VALUES(results_json),
                 result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
            )->execute([':h' => $hash, ':qt' => $q, ':rj' => json_encode($books), ':rc' => $total]);
        }
    
        // --- Log pencarian (hanya halaman pertama) ---
        if ($isFirstPage) SearchHelper::logSearchQuery('basic', $qRaw, $total);
    
        echo json_encode([
            'data'        => $books,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int)ceil($total / $limit),
        ]);
    }

    public function handleSearchContent(): void {
        header('Cache-Control: public, max-age=120');
        $pdo   = Database::getConnection();
        $qRaw  = trim($_GET['q'] ?? '');
        $q     = SearchHelper::searchPhraseText($qRaw);
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;
    
        if (strlen($q) < 2) {
            echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
            return;
        }
    
        $qStar = SearchHelper::booleanSearchTerm($qRaw);
        $hash  = 'content:' . hash('sha256', strtolower($qRaw));
    
        // --- Cache hit ---
        if ($page === 1) {
            $cs = $pdo->prepare(
                "SELECT results_json, result_count FROM search_cache
                 WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
            );
            $cs->execute([':h' => $hash]);
            if ($row = $cs->fetch()) {
                $all = json_decode($row['results_json'], true) ?? [];
                echo json_encode([
                    'data'        => array_slice($all, 0, $limit),
                    'total'       => (int)$row['result_count'],
                    'page'        => 1,
                    'total_pages' => (int)ceil($row['result_count'] / $limit),
                    'cached'      => true,
                ]);
                return;
            }
        }
    
        // --- Step 1: Scan FULLTEXT pada book_content saja (tidak JOIN) ---
        $step1 = $pdo->prepare(
            "SELECT bkid, page, MATCH(content) AGAINST (:q1 IN BOOLEAN MODE) AS rel
             FROM book_content
             WHERE MATCH(content) AGAINST (:q2 IN BOOLEAN MODE)
             ORDER BY rel DESC, bkid ASC, page ASC
             LIMIT :lim OFFSET :off"
        );
        $step1->bindValue(':q1',  $qStar, PDO::PARAM_STR);
        $step1->bindValue(':q2',  $qStar, PDO::PARAM_STR);
        $step1->bindValue(':lim', $limit, PDO::PARAM_INT);
        $step1->bindValue(':off', $offset, PDO::PARAM_INT);
        $step1->execute();
        $topRows = $step1->fetchAll();
    
        if (empty($topRows)) {
            echo json_encode(['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0]);
            return;
        }
    
        $pairConds  = [];
        $pairParams = [];
        foreach ($topRows as $i => $r) {
            $bk = ':bk' . $i;
            $pg = ':pg' . $i;
            $pairConds[]   = "(bc.bkid = $bk AND bc.page = $pg)";
            $pairParams[$bk] = (int)$r['bkid'];
            $pairParams[$pg] = (int)$r['page'];
        }
    
        // --- Step 2: Best snippet per bkid ---
        $step2Sql = "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page,
                            bc.content AS snippet,
                            b.title, b.author, b.pages, b.category_name
                     FROM book_content bc
                     JOIN books b ON b.bkid = bc.bkid
                     WHERE " . implode(' OR ', $pairConds);
    
        $step2 = $pdo->prepare($step2Sql);
        foreach ($pairParams as $k => $v) $step2->bindValue($k, $v, PDO::PARAM_INT);
        $step2->execute();
    
        $byKey = [];
        foreach ($step2->fetchAll() as $r) {
            $byKey[$r['bkid'] . '_' . $r['match_page']] = $r;
        }
    
        $terms = preg_split('/\s+/u', SearchHelper::searchPhraseText($q), -1, PREG_SPLIT_NO_EMPTY);
        $rows = [];
        foreach ($topRows as $r) {
            $k = $r['bkid'] . '_' . $r['page'];
            if (!isset($byKey[$k])) continue;
            $row = $byKey[$k];
            $row['snippet'] = SearchHelper::extractSmartSnippet((string)($row['snippet'] ?? ''), $terms);
            $rows[] = $row;
        }
    
        // --- Count total (cached separately, expensive on huge tables) ---
        $countHash = 'cnt_content:' . hash('sha256', strtolower($q));
        $ccRow = $pdo->prepare(
            "SELECT result_count FROM search_cache WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
        );
        $ccRow->execute([':h' => $countHash]);
        if ($countCached = $ccRow->fetchColumn()) {
            $total = (int)$countCached;
        } else {
            $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM book_content
             WHERE MATCH(content) AGAINST (:q IN BOOLEAN MODE)"
        );
        $countStmt->execute([':q' => $qStar]);
            $total = (int)$countStmt->fetchColumn();
            // Cache the count for 2 hours
            $pdo->prepare(
                "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
                 VALUES (:h, :qt, '[]', :rc, DATE_ADD(NOW(), INTERVAL 2 HOUR))
                 ON DUPLICATE KEY UPDATE result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
            )->execute([':h' => $countHash, ':qt' => 'cnt:' . $q, ':rc' => $total]);
        }
    
        // Cache page-1 results
        if ($page === 1 && !empty($rows)) {
            $pdo->prepare(
                "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
                 VALUES (:h, :qt, :rj, :rc, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                 ON DUPLICATE KEY UPDATE results_json=VALUES(results_json),
                 result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
            )->execute([':h' => $hash, ':qt' => $q, ':rj' => json_encode($rows), ':rc' => $total]);
        }
    
        echo json_encode([
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int)ceil($total / $limit),
        ]);
    }

    public function handleSearchAdvanced(): void {
        header('Cache-Control: public, max-age=120');
        $pdo    = Database::getConnection();
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 12;
        $offset = ($page - 1) * $limit;
    
        $fields = [];
        for ($i = 1; $i <= 5; $i++) {
            $val = trim((string)($_GET['q' . $i] ?? ''));
            if ($val !== '') $fields[] = $val;
        }
    
        if (empty($fields)) {
            ResponseHelper::json(['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0]);
            return;
        }
    
        $allCats = ($_GET['all_cats'] ?? '0') === '1';
        $cats    = [];
        if (!$allCats && isset($_GET['cats']) && $_GET['cats'] !== '') {
            foreach (explode(',', (string)$_GET['cats']) as $cid) {
                $cid = (int)trim($cid);
                if ($cid > 0) $cats[] = $cid;
            }
        }
        $samePage    = ($_GET['same_page'] ?? '1') !== '0';
        $noCatFilter = $allCats || empty($cats);   // true = lewati filter kategori
    
        // --- Cache key ---
        $cacheKey = 'adv6:' . hash('sha256', json_encode([
            'f' => $fields, 'c' => $cats, 'a' => $allCats, 's' => $samePage, 'p' => $page,
        ]));
    
        // --- Cache hit ---
        try {
            $cs = $pdo->prepare(
                "SELECT results_json, result_count FROM search_cache
                 WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
            );
            $cs->execute([':h' => $cacheKey]);
            if ($row = $cs->fetch()) {
                ResponseHelper::json([
                    'data'        => json_decode($row['results_json'], true) ?? [],
                    'total'       => (int)$row['result_count'],
                    'page'        => $page,
                    'total_pages' => (int)ceil($row['result_count'] / $limit),
                    'cached'      => true,
                ]);
                return;
            }
        } catch (\Exception $e) { /* ignore */ }
    
        // --- Build FULLTEXT conditions (kolom bare `content`, tanpa alias tabel) ---
        $ftParams     = [];
        $ftConditions = [];  // menggunakan `content` tanpa alias
    
        if ($samePage) {
            foreach ($fields as $idx => $field) {
                $boolTerm = SearchHelper::booleanSearchTerm($field);
                if ($boolTerm === '') continue;
                $k = ':ft' . $idx;
                $ftParams[$k]   = $boolTerm;
                $ftConditions[] = "MATCH(content) AGAINST ($k IN BOOLEAN MODE)";
            }
        } else {
            $combined = SearchHelper::booleanQueryFromFieldsOr($fields);
            if ($combined !== '') {
                $ftParams[':ft0'] = $combined;
                $ftConditions[]   = "MATCH(content) AGAINST (:ft0 IN BOOLEAN MODE)";
            }
        }
    
        if (empty($ftConditions)) {
            ResponseHelper::json(['data' => [], 'total' => 0, 'page' => $page, 'total_pages' => 0]);
            return;
        }
    
        $ftWhere  = '(' . implode(' AND ', $ftConditions) . ')';          // bare table
        $allFtRel = SearchHelper::booleanQueryFromFieldsOr($fields) ?: '+*';
    
        $rows  = [];
        $total = 0;
    
        try {
            if ($noCatFilter) {
                // ══════════════════════════════════════════════════════
                // JALUR CEPAT — Tanpa JOIN pada scan utama
                // ══════════════════════════════════════════════════════
    
                // Step 1: Scan FULLTEXT pada book_content saja (tidak JOIN)
                //         → hanya menggunakan FULLTEXT index, sangat cepat
                $s1Params          = $ftParams;
                $s1Params[':rel']  = $allFtRel;
    
                $step1Sql = "SELECT id,
                                    MATCH(content) AGAINST (:rel IN BOOLEAN MODE) AS relevance
                             FROM book_content
                             WHERE $ftWhere
                             ORDER BY relevance DESC, id ASC
                             LIMIT :lim OFFSET :off";
    
                $s1 = $pdo->prepare($step1Sql);
                foreach ($s1Params as $k => $v) $s1->bindValue($k, $v, PDO::PARAM_STR);
                $s1->bindValue(':lim', $limit, PDO::PARAM_INT);
                $s1->bindValue(':off', $offset, PDO::PARAM_INT);
                $s1->execute();
                $topRows = $s1->fetchAll();
    
                if (!empty($topRows)) {
                    // Step 2: Ambil metadata + snippet hanya untuk baris terpilih (JOIN minimal)
                    $ids = array_map(fn($r) => (int)$r['id'], $topRows);
                    $inIds = implode(',', $ids);
    
                    $step2Sql = "SELECT bc.id AS match_id, bc.bkid, bc.juz AS match_juz, bc.page AS match_page,
                                        bc.content AS content,
                                        b.title, b.author, b.category_name
                                 FROM book_content bc
                                 JOIN books b ON b.bkid = bc.bkid
                                 WHERE bc.id IN ($inIds)";
    
                    $s2 = $pdo->query($step2Sql);
    
                    $byKey = [];
                    foreach ($s2->fetchAll() as $r) {
                        $byKey[$r['match_id']] = $r;
                    }
    
                    // Kembalikan urutan relevansi dari Step 1
                    foreach ($topRows as $r) {
                        $k = $r['id'];
                        if (!isset($byKey[$k])) continue;
                        $row             = $byKey[$k];
                        $row['snippet']  = SearchHelper::extractSmartSnippet((string)($row['content'] ?? ''), $fields);
                        unset($row['content']);
                        $rows[] = $row;
                    }
                }
    
                // COUNT — tanpa JOIN (hanya book_content + FULLTEXT index)
                $countKey = 'advcnt6:' . hash('sha256', json_encode([
                    'f' => $fields, 'c' => [], 's' => $samePage,
                ]));
                $cc = $pdo->prepare(
                    "SELECT result_count FROM search_cache WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
                );
                $cc->execute([':h' => $countKey]);
                if ($cnt = $cc->fetchColumn()) {
                    $total = (int)$cnt;
                } else {
                    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM book_content WHERE $ftWhere");
                    foreach ($ftParams as $k => $v) $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
                    $cntStmt->execute();
                    $total = (int)$cntStmt->fetchColumn();
                    $pdo->prepare(
                        "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
                         VALUES (:h, :qt, '[]', :rc, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                         ON DUPLICATE KEY UPDATE result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
                    )->execute([':h' => $countKey, ':qt' => 'cnt:adv3', ':rc' => $total]);
                }
    
            } else {
                // ══════════════════════════════════════════════════════
                // JALUR STANDAR — Ada filter kategori, pakai JOIN
                // ══════════════════════════════════════════════════════
    
                // Kondisi FULLTEXT untuk JOIN query (alias bc.content)
                $ftConditionsAlias = array_map(
                    fn($c) => str_replace('MATCH(content)', 'MATCH(bc.content)', $c),
                    $ftConditions
                );
                $ftWhereAlias = '(' . implode(' AND ', $ftConditionsAlias) . ')';
    
                $catPlaceholders = [];
                $catParams       = [];
                foreach ($cats as $i => $cid) {
                    $k = ':cat' . $i;
                    $catPlaceholders[] = $k;
                    $catParams[$k]     = $cid;
                }
                $fullWhere = $ftWhereAlias . ' AND b.category_id IN (' . implode(',', $catPlaceholders) . ')';
    
                $params          = array_merge($ftParams, $catParams);
                $params[':rel']  = $allFtRel;
                $params[':lim']  = $limit;
                $params[':off']  = $offset;
    
                $sql = "SELECT bc.id AS match_id, bc.bkid, b.title, b.author, b.category_name,
                               bc.juz AS match_juz, bc.page AS match_page,
                               bc.content AS content,
                               MATCH(bc.content) AGAINST (:rel IN BOOLEAN MODE) AS relevance
                        FROM book_content bc
                        JOIN books b ON b.bkid = bc.bkid
                        WHERE $fullWhere
                        ORDER BY relevance DESC, bc.id ASC
                        LIMIT :lim OFFSET :off";
    
                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmt->execute();
                $rawRows = $stmt->fetchAll();
    
                $rows = array_map(function ($row) use ($fields) {
                    $row['snippet'] = SearchHelper::extractSmartSnippet((string)($row['content'] ?? ''), $fields);
                    unset($row['content'], $row['relevance']);
                    return $row;
                }, $rawRows);
    
                // COUNT dengan JOIN + filter kategori
                $countKey = 'advcnt3:' . hash('sha256', json_encode([
                    'f' => $fields, 'c' => $cats, 's' => $samePage,
                ]));
                $cc = $pdo->prepare(
                    "SELECT result_count FROM search_cache WHERE query_hash = :h AND expires_at > NOW() LIMIT 1"
                );
                $cc->execute([':h' => $countKey]);
                if ($cnt = $cc->fetchColumn()) {
                    $total = (int)$cnt;
                } else {
                    $cntParams = array_merge($ftParams, $catParams);
                    $cntStmt   = $pdo->prepare(
                        "SELECT COUNT(*) FROM book_content bc JOIN books b ON b.bkid = bc.bkid WHERE $fullWhere"
                    );
                    foreach ($cntParams as $k => $v) {
                        $cntStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                    }
                    $cntStmt->execute();
                    $total = (int)$cntStmt->fetchColumn();
                    $pdo->prepare(
                        "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
                         VALUES (:h, :qt, '[]', :rc, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                         ON DUPLICATE KEY UPDATE result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
                    )->execute([':h' => $countKey, ':qt' => 'cnt:adv3', ':rc' => $total]);
                }
            }
        } catch (Exception $e) {
            ResponseHelper::json(['error' => 'Query error: ' . $e->getMessage()], 500);
            return;
        }
    
        // --- Simpan hasil di cache (30 menit) ---
        try {
            $pdo->prepare(
                "INSERT INTO search_cache (query_hash, query_text, results_json, result_count, expires_at)
                 VALUES (:h, :qt, :rj, :rc, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                 ON DUPLICATE KEY UPDATE results_json=VALUES(results_json),
                 result_count=VALUES(result_count), expires_at=VALUES(expires_at)"
            )->execute([
                ':h'  => $cacheKey,
                ':qt' => implode(' | ', $fields),
                ':rj' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                ':rc' => $total,
            ]);
        } catch (\Exception $e) { /* ignore */ }
    
        // --- Log pencarian lanjutan (hanya halaman pertama) ---
        if ($page === 1) {
            SearchHelper::logSearchQuery('advanced', implode(' | ', $fields), $total, json_encode([
                'fields'   => $fields,
                'cats'     => $cats,
                'all_cats' => $allCats,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        }
    
        ResponseHelper::json([
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int)ceil($total / $limit),
        ]);
    }

    public function handleSearchBooksWithContent(): void {
        header('Cache-Control: public, max-age=3600');
        $pdo   = Database::getConnection();
    
        // Ambil daftar kitab yang punya konten, urut abjad
        // Gunakan EXISTS agar tidak perlu JOIN/GROUP BY
        $stmt = $pdo->query(
            "SELECT b.bkid, b.title, b.author, b.category_name
             FROM books b
             WHERE EXISTS (
                 SELECT 1 FROM book_content bc WHERE bc.bkid = b.bkid LIMIT 1
             )
             ORDER BY b.title ASC"
        );
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $books, 'total' => count($books)]);
    }

    public function handleSearchContentInBook(): void {
        header('Cache-Control: public, max-age=300');
        $pdo  = Database::getConnection();
        $bkid = (int)($_GET['bkid'] ?? 0);
        $qRaw = trim($_GET['q'] ?? '');
        $q    = SearchHelper::searchPhraseText($qRaw);
    
        if ($bkid <= 0 || strlen($q) < 2) {
            echo json_encode(['found' => false, 'data' => []]);
            return;
        }
    
        $qStar = SearchHelper::booleanSearchTerm($qRaw);
    
        // Ambil halaman terbaik dalam kitab ini (top 3, ringan karena sudah di-filter bkid)
        $stmt = $pdo->prepare(
            "SELECT bc.id AS match_id, bc.juz AS match_juz, bc.page AS match_page,
                    bc.content AS snippet,
                    MATCH(bc.content) AGAINST (:q1 IN BOOLEAN MODE) AS rel
             FROM book_content bc
             WHERE bc.bkid = :bkid
               AND MATCH(bc.content) AGAINST (:q2 IN BOOLEAN MODE)
             ORDER BY rel DESC
             LIMIT 3"
        );
        $stmt->bindValue(':q1',   $qStar, PDO::PARAM_STR);
        $stmt->bindValue(':q2',   $qStar, PDO::PARAM_STR);
        $stmt->bindValue(':bkid', $bkid,  PDO::PARAM_INT);
        $stmt->execute();
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        if (empty($pages)) {
            echo json_encode(['found' => false, 'data' => []]);
            return;
        }
    
        // Ambil metadata kitab
        $bmeta = $pdo->prepare(
            "SELECT bkid, title, author, category_name FROM books WHERE bkid = :bkid LIMIT 1"
        );
        $bmeta->execute([':bkid' => $bkid]);
        $book = $bmeta->fetch(PDO::FETCH_ASSOC);
    
        $terms = preg_split('/\s+/u', SearchHelper::searchPhraseText($qRaw), -1, PREG_SPLIT_NO_EMPTY);
        $results = [];
        foreach ($pages as $page) {
            $results[] = [
                'match_id'      => (int)$page['match_id'],
                'bkid'          => $bkid,
                'title'         => $book['title']        ?? '',
                'author'        => $book['author']       ?? '',
                'category_name' => $book['category_name'] ?? '',
                'match_juz'     => (int)$page['match_juz'],
                'match_page'    => (int)$page['match_page'],
                'snippet'       => SearchHelper::extractSmartSnippet((string)($page['snippet'] ?? ''), $terms),
            ];
        }
    
        echo json_encode(['found' => true, 'data' => $results]);
    }

    public function handleSearch(): void {
        $pdo      = Database::getConnection();
        $q        = trim($_GET['q'] ?? '');
        $bookPage = max(1, (int)($_GET['book_page']    ?? 1));
        $contPage = max(1, (int)($_GET['content_page'] ?? 1));
        $limit    = 12;
    
        $empty = [
            'query'      => $q,
            'categories' => [],
            'books'      => ['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0],
            'content'    => ['data' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0],
        ];
    
        if (strlen($q) < 2) { echo json_encode($empty); return; }
    
        $like  = '%' . $q . '%';
        $qStar = $q . '*';
    
        // 1. Categories
        $stmtCat = $pdo->prepare(
            "SELECT c.id, c.name, COUNT(b.bkid) AS book_count
             FROM categories c
             LEFT JOIN books b ON b.category_id = c.id
             WHERE c.name LIKE :lk
             GROUP BY c.id ORDER BY book_count DESC LIMIT 20"
        );
        $stmtCat->execute([':lk' => $like]);
        $categories = $stmtCat->fetchAll();
    
        // 2. Books by title/author
        $bookOffset = ($bookPage - 1) * $limit;
        $stmtBooks = $pdo->prepare(
            "SELECT b.bkid, b.title, b.author, b.pages, b.iso, b.category_id, b.category_name
             FROM books b
             WHERE b.title LIKE :lk OR b.author LIKE :lk2
             ORDER BY b.bkid DESC LIMIT :lim OFFSET :off"
        );
        $stmtBooks->bindValue(':lk',  $like, PDO::PARAM_STR);
        $stmtBooks->bindValue(':lk2', $like, PDO::PARAM_STR);
        $stmtBooks->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmtBooks->bindValue(':off', $bookOffset, PDO::PARAM_INT);
        $stmtBooks->execute();
        $books = $stmtBooks->fetchAll();
    
        $stmtBooksCount = $pdo->prepare(
            "SELECT COUNT(*) FROM books WHERE title LIKE :lk OR author LIKE :lk2"
        );
        $stmtBooksCount->execute([':lk' => $like, ':lk2' => $like]);
        $booksTotal = (int)$stmtBooksCount->fetchColumn();
    
        // 3. Content
        $contOffset = ($contPage - 1) * $limit;
        $stmtCont = $pdo->prepare(
            "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page, b.title, b.author, b.category_name,
                    bc.content AS snippet
             FROM book_content bc
             JOIN books b ON b.bkid = bc.bkid
             WHERE bc.content LIKE :lk
             ORDER BY bc.bkid DESC, bc.page ASC
             LIMIT :lim OFFSET :off"
        );
        $stmtCont->bindValue(':lk',  $like, PDO::PARAM_STR);
        $stmtCont->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmtCont->bindValue(':off', $contOffset, PDO::PARAM_INT);
        $stmtCont->execute();
        $content = $stmtCont->fetchAll();
    
        $terms = preg_split('/\s+/u', SearchHelper::searchPhraseText($q), -1, PREG_SPLIT_NO_EMPTY);
        $content = array_map(function($row) use ($terms) {
            $row['snippet'] = SearchHelper::extractSmartSnippet((string)($row['snippet'] ?? ''), $terms);
            return $row;
        }, $content);
    
        $stmtContCount = $pdo->prepare(
            "SELECT COUNT(*) FROM book_content WHERE content LIKE :lk"
        );
        $stmtContCount->execute([':lk' => $like]);
        $contTotal = (int)$stmtContCount->fetchColumn();
    
        echo json_encode([
            'query'      => $q,
            'categories' => $categories,
            'books'      => [
                'data' => $books, 'total' => $booksTotal,
                'page' => $bookPage, 'total_pages' => (int)ceil($booksTotal / $limit),
            ],
            'content'    => [
                'data' => $content, 'total' => $contTotal,
                'page' => $contPage, 'total_pages' => (int)ceil($contTotal / $limit),
            ],
        ]);
    }

    public function handleRecentSearches(): void {
        header('Cache-Control: public, max-age=120');
        $pdo   = Database::getConnection();
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 15)));
    
        // Ambil query unik terbaru, abaikan yang kosong / terlalu pendek
        $stmt = $pdo->prepare(
            "SELECT query, MAX(created_at) AS last_at
             FROM search_logs
             WHERE LENGTH(TRIM(query)) >= 2
             GROUP BY LOWER(TRIM(query))
             ORDER BY last_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
    
        $rows = $stmt->fetchAll();
        echo json_encode(['data' => array_map(fn($r) => $r['query'], $rows)]);
    }

}
