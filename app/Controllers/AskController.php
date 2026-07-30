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

        $retry = (int)($_POST['retry'] ?? $_GET['retry'] ?? 0);
        $limit = $retry > 0 ? 50 : 25; // Ambil 25 (atau 50 jika retry) halaman terbaik agar konteks lebih luas dan akurat

        try {
            // [CACHING] Cek history jika pertanyaan sudah pernah ditanyakan sebelumnya
            $logCheck = $pdo->prepare("SELECT response FROM ask_logs WHERE question = ? ORDER BY id DESC LIMIT 1");
            $logCheck->execute([$qRaw]);
            $cachedLog = $logCheck->fetch(PDO::FETCH_ASSOC);

            $contextData = $this->fetchContextData($pdo, $qRaw, $limit, $retry);
            
            // [NEW LOGIC] Jika pencarian awal kosong, coba terjemahkan ke bahasa berlawanan dan cari lagi
            $aiService = new AIService();
            if (empty($contextData)) {
                $translatedQuery = $aiService->translateToSearchKeywords($qRaw);
                if (!empty($translatedQuery) && mb_strtolower(trim($translatedQuery), 'UTF-8') !== mb_strtolower(trim($qRaw), 'UTF-8')) {
                    $contextData = $this->fetchContextData($pdo, $translatedQuery, $limit, $retry);
                }
            }

            // Gunakan jawaban dari log jika ada, agar instan dan tanpa loading panjang
            $isCached = false;
            if ($cachedLog && !empty($cachedLog['response'])) {
                $aiResponse = $cachedLog['response'];
                $isCached = true;
            } else {
                // Langkah 2: Kirim ke AI (Gemini)
                $aiResponse = $aiService->askGemini($qRaw, $contextData);
            }
            
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

            // Catat Log ke database HANYA jika bukan dari cache
            if (!$isCached) {
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

    private function fetchContextData(PDO $pdo, string $qRaw, int $limit, int $retry = 0): array {
        // Bersihkan tanda baca khusus agar tidak mengganggu sintaks BOOLEAN MySQL
        $qClean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $qRaw);
        // Filter stop-words (kata tanya) agar pencarian boolean tidak terlalu ketat mencari kata tanya
        $stopWords = ['siapa', 'apa', 'kapan', 'dimana', 'bagaimana', 'kenapa', 'mengapa', 'apakah', 'berapa'];
        
        // Pecah kata dan ambil yang panjangnya > 2 (hapus kata hubung pendek) serta bukan stop-word
        $qWords = array_filter(explode(' ', $qClean), function($w) use ($stopWords) { 
            return mb_strlen($w, 'UTF-8') > 2 && !in_array(mb_strtolower($w, 'UTF-8'), $stopWords); 
        });
        
        if (empty($qWords)) {
            $qBool = SearchHelper::ftEscape($qRaw); 
        } else {
            // Ambil maksimal 4 kata pertama agar pencarian tidak terlalu ketat (hasil 0) jika kalimatnya sangat panjang
            $qWords = array_slice(array_values($qWords), 0, 4);
            $qBool = '+' . implode(' +', $qWords);
        }

        if ($retry > 0) {
            // Mode luas: BOOLEAN MODE yang dilonggarkan (menghindari timeout NATURAL LANGUAGE MODE)
            // Hanya mewajibkan 1 kata terpanjang, sisanya opsional (TANPA wildcard * agar query tidak timeout)
            $qBoolBroad = '';
            if (!empty($qWords)) {
                $sortedWords = $qWords;
                usort($sortedWords, function($a, $b) {
                    return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
                });
                
                $qBoolBroad = '+' . $sortedWords[0];
                for ($i = 1; $i < count($sortedWords); $i++) {
                    $qBoolBroad .= ' ' . $sortedWords[$i];
                }
            } else {
                $qBoolBroad = SearchHelper::ftEscape($qRaw);
            }

            $step1 = $pdo->prepare(
                "SELECT bkid, page, MATCH(content) AGAINST (:q1 IN BOOLEAN MODE) AS rel
                 FROM book_content
                 WHERE MATCH(content) AGAINST (:q2 IN BOOLEAN MODE)
                 ORDER BY rel DESC, bkid ASC, page ASC
                 LIMIT :lim"
            );
            $step1->bindValue(':q1', $qBoolBroad, PDO::PARAM_STR);
            $step1->bindValue(':q2', $qBoolBroad, PDO::PARAM_STR);
            $step1->bindValue(':lim', $limit, PDO::PARAM_INT);
            $step1->execute();
            $topRows = $step1->fetchAll();
        } else {
            // Mode ketat: BOOLEAN MODE
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
        }

        // Fallback pencarian (hanya jika mode ketat/retry 0 dan kosong)
        if ($retry === 0 && empty($topRows) && strpos($qBool, '+') !== false && count($qWords) > 1) {
            $sortedWords = $qWords;
            usort($sortedWords, function($a, $b) {
                return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
            });
            
            // Hapus wildcard * agar pencarian fallback tidak menyebabkan max_statement_time exceeded
            $qBoolFallback = '+' . $sortedWords[0];
            for ($i = 1; $i < count($sortedWords); $i++) {
                $qBoolFallback .= ' ' . $sortedWords[$i];
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
    
        // Abaikan parameter refresh untuk ini karena query berat pada tabel besar
        $refresh = $_GET['refresh'] ?? null;
        unset($_GET['refresh']);

        $data = \App\Helpers\CacheHelper::remember('recent_questions_' . $limit, 600, function() use ($limit) {
            $pdo   = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT question 
                 FROM ask_logs 
                 WHERE LENGTH(TRIM(question)) >= 5
                 ORDER BY id DESC LIMIT 200"
            );
            $stmt->execute();
        
            $rows = $stmt->fetchAll();
            $unique = [];
            $result = [];
            foreach ($rows as $r) {
                $lq = strtolower(trim($r['question']));
                if (!isset($unique[$lq])) {
                    $unique[$lq] = true;
                    $result[] = ['query' => $r['question']];
                    if (count($result) == $limit) break;
                }
            }
            return $result;
        });
        
        if ($refresh !== null) $_GET['refresh'] = $refresh;

        echo json_encode(['data' => $data]);
    }
}
