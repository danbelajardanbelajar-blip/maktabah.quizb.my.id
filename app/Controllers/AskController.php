<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\ResponseHelper;
use App\Services\AIService;
use PDO;
use Exception;

class AskController {
    public function handleAsk(): void {
        header('Cache-Control: no-cache, must-revalidate');
        
        $pdo  = Database::getConnection();
        $qRaw = trim($_POST['q'] ?? $_GET['q'] ?? '');
        $q    = SearchHelper::searchPhraseText($qRaw);
        
        if (strlen($q) < 5) {
            ResponseHelper::json(['status' => 'error', 'message' => 'Pertanyaan terlalu pendek.']);
            return;
        }

        // Gunakan pencarian teks natural karena pengguna mengetik kalimat panjang
        $qClean = SearchHelper::ftEscape($qRaw);
        $limit = 5; // Ambil 5 snippet terbaik

        try {
            // Langkah 1: Cari snippet paling relevan di database
            $step1 = $pdo->prepare(
                "SELECT bkid, page, MATCH(content) AGAINST (:q1 IN NATURAL LANGUAGE MODE) AS rel
                 FROM book_content
                 WHERE MATCH(content) AGAINST (:q2 IN NATURAL LANGUAGE MODE)
                 ORDER BY rel DESC, bkid ASC, page ASC
                 LIMIT :lim"
            );
            $step1->bindValue(':q1',  $qClean, PDO::PARAM_STR);
            $step1->bindValue(':q2',  $qClean, PDO::PARAM_STR);
            $step1->bindValue(':lim', $limit, PDO::PARAM_INT);
            $step1->execute();
            $topRows = $step1->fetchAll();

            $contextData = [];
            
            if (!empty($topRows)) {
                $pairConds  = [];
                $pairParams = [];
                foreach ($topRows as $i => $r) {
                    $bk = ':bk' . $i;
                    $pg = ':pg' . $i;
                    $pairConds[]   = "(bc.bkid = $bk AND bc.page = $pg)";
                    $pairParams[$bk] = (int)$r['bkid'];
                    $pairParams[$pg] = (int)$r['page'];
                }

                $step2Sql = "SELECT bc.bkid, bc.juz AS match_juz, bc.page AS match_page,
                                    bc.content AS snippet,
                                    b.title
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

                foreach ($topRows as $r) {
                    $k = $r['bkid'] . '_' . $r['page'];
                    if (!isset($byKey[$k])) continue;
                    
                    $row = $byKey[$k];
                    // Clean snippet a bit, remove very long whitespaces
                    $row['snippet'] = preg_replace('/\s+/', ' ', $row['snippet']);
                    $contextData[] = $row;
                }
            }

            // Langkah 2: Kirim ke AI (Gemini)
            $aiService = new AIService();
            $aiResponse = $aiService->askGemini($qRaw, $contextData);
            
            // Format referensi untuk dikirim ke frontend
            $references = [];
            foreach ($contextData as $ctx) {
                $references[] = [
                    'bkid'  => $ctx['bkid'],
                    'title' => $ctx['title'],
                    'juz'   => $ctx['match_juz'],
                    'page'  => $ctx['match_page']
                ];
            }

            ResponseHelper::json([
                'status' => 'success',
                'answer' => $aiResponse,
                'references' => $references
            ]);

        } catch (Exception $e) {
            ResponseHelper::json(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }
}
