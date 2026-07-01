<?php
// bulk_generate_toc.php
require_once __DIR__ . '/app/bootstrap.php';

use App\Config\Database;

$action = $_GET['action'] ?? '';

// API Endpoints
if ($action === 'get_books') {
    header('Content-Type: application/json');
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT bkid, title FROM books ORDER BY bkid ASC");
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $books]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'generate_single') {
    header('Content-Type: application/json');
    $bkid = (int)($_GET['bkid'] ?? 0);
    if (!$bkid) {
        echo json_encode(['success' => false, 'error' => 'No book ID provided']);
        exit;
    }

    try {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        // Delete existing TOC
        $del = $pdo->prepare("DELETE FROM book_toc WHERE bkid = ?");
        $del->execute([$bkid]);

        // Get content
        $stmt = $pdo->prepare("SELECT page, juz, content FROM book_content WHERE bkid = ? ORDER BY juz ASC, page ASC, id ASC");
        $stmt->execute([$bkid]);
        $pages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $insertedCount = 0;
        $ins = $pdo->prepare("INSERT INTO book_toc (bkid, title, juz, page, level) VALUES (?, ?, ?, ?, ?)");

        foreach ($pages as $p) {
            $normalized = str_replace(['\r', '\n'], ["\r", "\n"], $p['content']);
            $lines = preg_split('/[\r\n]+/', $normalized);
            foreach ($lines as $line) {
                $line = trim($line);
                if (mb_strlen($line) >= 3 && mb_strlen($line) <= 80) {
                    if (strpos($line, '.') === false && strpos($line, '،') === false && strpos($line, '؟') === false && strpos($line, '@') === false) {
                        if (preg_match('/[a-zA-Z\p{Arabic}]{2,}/u', $line) && !preg_match('/^[-_@\s]*ص?\s*\d+\s*[-_@\s]*$/u', $line)) {
                            if (preg_match('/^(كتاب|باب|فصل|مقدمة|خاتمة|المبحث|المطلب|القسم|تنبيه|فائدة|مسألة)/u', $line) || 
                                (mb_strlen($line) <= 60 && strpos($line, ' ') !== false && mb_substr($line, -1) !== ':')) {
                                
                                $title = mb_substr($line, 0, 200);
                                $level = preg_match('/^(كتاب|باب)/u', $line) ? 1 : 2;
                                $ins->execute([$bkid, $title, $p['juz'], $p['page'], $level]);
                                $insertedCount++;
                            }
                        }
                    }
                }
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'inserted' => $insertedCount]);
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Generate TOC - Al-Maktabah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .gold-gradient { background: linear-gradient(135deg, #d4af37 0%, #aa8c2c 100%); }
    </style>
</head>
<body class="min-h-screen text-slate-800 flex items-center justify-center p-4">
    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200">
        <div class="gold-gradient p-6 text-white text-center">
            <i data-lucide="list-tree" class="w-12 h-12 mx-auto mb-3 opacity-90"></i>
            <h1 class="text-2xl font-bold tracking-wide">Bulk Generate Daftar Isi</h1>
            <p class="text-white/80 mt-1">Memproses seluruh kitab secara massal tanpa batas timeout server.</p>
        </div>
        
        <div class="p-8">
            <div id="setup-panel" class="text-center">
                <p class="text-slate-600 mb-6 text-lg">Sistem siap mengambil data semua kitab di database dan menjalankan pemindaian judul bab (TOC) otomatis secara berurutan.</p>
                <button id="start-btn" onclick="startBulkProcess()" class="bg-slate-900 hover:bg-slate-800 text-white font-medium py-3 px-8 rounded-xl transition-all shadow-lg shadow-slate-900/20 flex items-center gap-2 mx-auto">
                    <i data-lucide="play" class="w-5 h-5"></i> Mulai Proses Sekarang
                </button>
            </div>

            <div id="progress-panel" class="hidden">
                <div class="flex justify-between items-end mb-2">
                    <h3 class="font-semibold text-slate-800">Progres Keseluruhan</h3>
                    <span id="progress-text" class="text-sm font-medium text-slate-500">0 / 0 Kitab</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-4 mb-6 overflow-hidden border border-slate-200">
                    <div id="progress-bar" class="gold-gradient h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 mb-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="animate-spin rounded-full h-5 w-5 border-2 border-slate-400 border-t-[#d4af37]" id="current-spinner"></div>
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500 hidden" id="current-done"></i>
                        <span class="text-sm font-medium text-slate-700">Sedang memproses:</span>
                    </div>
                    <div id="current-book-name" class="text-lg font-semibold text-slate-900 truncate">-</div>
                    <div id="current-book-status" class="text-sm text-slate-500 mt-1">Menunggu...</div>
                </div>

                <div class="h-48 overflow-y-auto bg-slate-900 rounded-xl p-4 text-xs font-mono text-emerald-400 shadow-inner" id="log-container">
                    <div>[SYSTEM] Menunggu instruksi mulai...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        let books = [];
        let currentIndex = 0;
        let isProcessing = false;

        function logMsg(msg, isError = false) {
            const container = document.getElementById('log-container');
            const el = document.createElement('div');
            el.className = isError ? 'text-rose-400' : 'text-emerald-400';
            const time = new Date().toLocaleTimeString();
            el.textContent = `[${time}] ${msg}`;
            container.appendChild(el);
            container.scrollTop = container.scrollHeight;
        }

        async function startBulkProcess() {
            if (isProcessing) return;
            isProcessing = true;

            document.getElementById('setup-panel').classList.add('hidden');
            document.getElementById('progress-panel').classList.remove('hidden');
            
            logMsg('Mengambil daftar kitab dari server...');
            
            try {
                const res = await fetch('?action=get_books');
                const json = await res.json();
                
                if (!json.success) throw new Error(json.error);
                
                books = json.data;
                logMsg(`Berhasil mengambil ${books.length} kitab.`);
                
                if (books.length === 0) {
                    logMsg('Tidak ada kitab di database.');
                    setFinished();
                    return;
                }

                updateProgressUI();
                processNextBook();

            } catch (err) {
                logMsg('Gagal: ' + err.message, true);
                isProcessing = false;
            }
        }

        async function processNextBook() {
            if (currentIndex >= books.length) {
                logMsg('Selesai! Seluruh kitab telah diproses.');
                setFinished();
                return;
            }

            const book = books[currentIndex];
            document.getElementById('current-book-name').textContent = book.title;
            document.getElementById('current-book-status').textContent = `Menganalisis konten kitab...`;
            logMsg(`Memproses: ${book.title} (ID: ${book.bkid})`);

            try {
                const res = await fetch(`?action=generate_single&bkid=${book.bkid}`);
                const json = await res.json();

                if (json.success) {
                    logMsg(`Sukses: Ditemukan ${json.inserted} bab/subbab.`);
                    document.getElementById('current-book-status').textContent = `Selesai: ${json.inserted} daftar isi tersimpan.`;
                } else {
                    logMsg(`Error pada ID ${book.bkid}: ${json.error}`, true);
                    document.getElementById('current-book-status').textContent = `Gagal!`;
                }
            } catch (err) {
                logMsg(`Fetch Error pada ID ${book.bkid}: ${err.message}`, true);
                document.getElementById('current-book-status').textContent = `Koneksi Terputus!`;
            }

            currentIndex++;
            updateProgressUI();
            
            setTimeout(processNextBook, 100);
        }

        function updateProgressUI() {
            const pct = books.length > 0 ? Math.round((currentIndex / books.length) * 100) : 0;
            document.getElementById('progress-bar').style.width = pct + '%';
            document.getElementById('progress-text').textContent = `${currentIndex} / ${books.length} Kitab (${pct}%)`;
        }

        function setFinished() {
            isProcessing = false;
            document.getElementById('current-spinner').classList.add('hidden');
            document.getElementById('current-done').classList.remove('hidden');
            document.getElementById('current-book-name').textContent = 'Proses Massal Selesai!';
            document.getElementById('current-book-status').textContent = 'Anda bisa menutup halaman ini.';
            document.getElementById('progress-bar').classList.add('!bg-emerald-500', '!bg-none');
        }
    </script>
</body>
</html>
