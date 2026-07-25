import re

with open('app/Controllers/AskController.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace part 1: add caching logic
new_logic = """        try {
            // [CACHING] Cek history jika pertanyaan sudah pernah ditanyakan sebelumnya
            $logCheck = $pdo->prepare("SELECT response FROM ask_logs WHERE question = ? ORDER BY id DESC LIMIT 1");
            $logCheck->execute([$qRaw]);
            $cachedLog = $logCheck->fetch(PDO::FETCH_ASSOC);

            $contextData = $this->fetchContextData($pdo, $qRaw, $limit);
            
            // [NEW LOGIC] Jika pencarian awal kosong, coba terjemahkan ke bahasa berlawanan dan cari lagi
            $aiService = new AIService();
            if (empty($contextData)) {
                $translatedQuery = $aiService->translateToSearchKeywords($qRaw);
                if (!empty($translatedQuery) && mb_strtolower(trim($translatedQuery), 'UTF-8') !== mb_strtolower(trim($qRaw), 'UTF-8')) {
                    $contextData = $this->fetchContextData($pdo, $translatedQuery, $limit);
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
            }"""

content = re.sub(
    r'        try \{\s*\$contextData = \$this->fetchContextData.*?\$aiResponse = \$aiService->askGemini\(\$qRaw, \$contextData\);',
    new_logic,
    content,
    flags=re.DOTALL
)

# Replace part 2: wrap logging with if (!$isCached)
log_logic = """            // Catat Log ke database HANYA jika bukan dari cache
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
            }"""

content = re.sub(
    r'            // Catat Log ke database\s*try \{.*?\/\/ Abaikan error logging agar tidak merusak response ke user\s*\}',
    log_logic,
    content,
    flags=re.DOTALL
)

with open('app/Controllers/AskController.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Done patching AskController.php")
