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

        $limit = 25; // Ambil 25 halaman terbaik agar konteks lebih luas dan akurat

        try {
            $contextData = $this->fetchContextData($pdo, $qRaw, $limit);
            
            // [NEW LOGIC] Jika pencarian awal kosong, coba terjemahkan ke bahasa berlawanan dan cari lagi
            $aiService = new AIService();
            if (empty($contextData)) {
                $translatedQuery = $aiService->translateToSearchKeywords($qRaw);
                if (!empty($translatedQuery) && mb_strtolower(trim($translatedQuery), 'UTF-8') !== mb_strtolower(trim($qRaw), 'UTF-8')) {
                    $contextData = $this->fetchContextData($pdo, $translatedQuery, $limit);
                }
            }

            // Langkah 2: Kirim ke AI (Gemini)
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

            // Catat Log ke database
            try {
                $user = $_SESSION['user'] ?? null;
                $userId = $user ? $user['id'] : null;
                $userName = $user ? $user['name'] : '';
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                $logStmt = $pdo->prepare("INSERT INTO ask_logs (question, response, visitor_ip, user_id, user_name) VALUES (?, ?, ?, ?, ?)");
                $logStmt->execute([$qRaw, $aiResponse, $ip, $userId, $userName]);

                // [REALTIME NOTIFIKASI]
                $uNameStr = !empty($userName) ? $userName : 'Anonim';
                $msgText = "Tanya AI Maktabah: '" . mb_substr($qRaw, 0, 50) . "' oleh {$uNameStr}";
                
                $notifyUrl = 'https://tahajjud.quizb.my.id/api_notify.php';
                $postData = http_build_query([
                    'secret' => 'QUIZB_NOTIFY_SECRET_99',
                    'message' => $msgText
                ]);
                
                $ch = curl_init($notifyUrl);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);

            } catch (Exception $logEx) {
                // Abaikan error logging agar tidak merusak response ke user
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

    private function fetchContextData(PDO $pdo, string $qRaw, int $limit): array {
        // Bersihkan tanda baca khusus agar tidak mengganggu sintaks BOOLEAN MySQL
        $qClean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $qRaw);
        // Pecah kata dan ambil yang panjangnya > 2 (hapus kata hubung pendek)
        $qWords = array_filter(explode(' ', $qClean), function($w) { return mb_strlen($w, 'UTF-8') > 2; });
        
        if (empty($qWords)) {
            $qBool = SearchHelper::ftEscape($qRaw); 
        } else {
            // Ambil maksimal 4 kata pertama agar pencarian tidak terlalu ketat (hasil 0) jika kalimatnya sangat panjang
            $qWords = array_slice(array_values($qWords), 0, 4);
            $qBool = '+' . implode('* +', $qWords) . '*';
        }

        $step1 = $pdo->prepare(
            "SELECT bkid, page, MATCH(content) AGAINST (:q1 IN BOOLEAN MODE) AS rel
             FROM book_content
             WHERE MATCH(content) AGAINST (:q2 IN BOOLEAN MODE)
             ORDER BY rel DESC, bkid ASC, page ASC
             LIMIT :lim"
        );
        $step1->bindValue(':q1',  $qBool, PDO::PARAM_STR);
        $step1->bindValue(':q2',  $qBool, PDO::PARAM_STR);
        $step1->bindValue(':lim', $limit, PDO::PARAM_INT);
        $step1->execute();
        $topRows = $step1->fetchAll();

        // Fallback pencarian
        if (empty($topRows) && strpos($qBool, '+') !== false && count($qWords) > 1) {
            $sortedWords = $qWords;
            usort($sortedWords, function($a, $b) {
                return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
            });
            
            $qBoolFallback = '+' . $sortedWords[0] . '*';
            for ($i = 1; $i < count($sortedWords); $i++) {
                $qBoolFallback .= ' ' . $sortedWords[$i] . '*';
            }
            
            $step1->bindValue(':q1',  $qBoolFallback, PDO::PARAM_STR);
            $step1->bindValue(':q2',  $qBoolFallback, PDO::PARAM_STR);
            $step1->execute();
            $topRows = $step1->fetchAll();
        }

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
                $row['snippet'] = preg_replace('/\s+/', ' ', $row['snippet']);
                $contextData[] = $row;
            }
        }
        
        return $contextData;
    }

    public function handleRecentQuestions(): void {
        header('Cache-Control: public, max-age=120');
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
    
        $data = \App\Helpers\CacheHelper::remember('recent_questions_' . $limit, 600, function() use ($limit) {
            $pdo   = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT question 
                 FROM ask_logs 
                 WHERE LENGTH(TRIM(question)) >= 5
                 GROUP BY question
                 ORDER BY MAX(id) DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
        
            $rows = $stmt->fetchAll();
            return array_map(fn($r) => [
                'query' => $r['question']
            ], $rows);
        });

        echo json_encode(['data' => $data]);
    }
}
