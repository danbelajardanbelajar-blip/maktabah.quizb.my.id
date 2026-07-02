<?php
// import_json_ui.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Multiple JSON - Maktabah QuizB</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f4f7f6; color: #333; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h1 { font-size: 26px; color: #2c3e50; margin-bottom: 10px; }
        p { color: #555; line-height: 1.6; }
        
        .upload-area { border: 2px dashed #3498db; padding: 50px 20px; text-align: center; border-radius: 10px; margin: 30px 0; background: #ebf5fb; transition: background 0.3s; }
        .upload-area:hover { background: #d6eaf8; }
        .upload-area input { display: none; }
        .upload-area label { background: #3498db; color: #fff; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: background 0.3s; box-shadow: 0 2px 5px rgba(52, 152, 219, 0.4); }
        .upload-area label:hover { background: #2980b9; }
        
        #fileCount { margin-top: 20px; font-weight: bold; color: #2c3e50; font-size: 16px; }
        
        #btnStart { display: none; background: #27ae60; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 15px; font-size: 16px; box-shadow: 0 2px 5px rgba(39, 174, 96, 0.4); transition: background 0.3s; }
        #btnStart:hover { background: #219653; }
        #btnStart:disabled { background: #95a5a6; cursor: not-allowed; box-shadow: none; }
        
        .progress-container { width: 100%; background: #e0e0e0; border-radius: 8px; margin: 25px 0 10px; overflow: hidden; display: none; height: 24px; }
        .progress-bar { width: 0%; height: 100%; background: #2ecc71; transition: width 0.4s ease; }
        #progressText { text-align: center; font-weight: bold; display: none; margin-bottom: 25px; color: #2c3e50; }
        
        #log-area { max-height: 350px; overflow-y: auto; background: #2c3e50; color: #ecf0f1; padding: 15px; font-family: 'Courier New', Courier, monospace; border-radius: 8px; margin-top: 20px; font-size: 13px; line-height: 1.5; }
        .log-item { margin-bottom: 8px; border-bottom: 1px solid #34495e; padding-bottom: 8px; }
        .log-item:last-child { border-bottom: none; }
        .log-item.error { color: #e74c3c; font-weight: bold; }
        .log-item.success { color: #2ecc71; }
    </style>
</head>
<body>
<div class="container">
    <h1>Import Kitab (Format JSON)</h1>
    <p>Silakan pilih banyak file <code>.json</code> hasil ekspor dari aplikasi desktop Maktabah Syamilah. Sistem akan mengunggah dan memprosesnya secara bergiliran (AJAX Chunking) agar tidak membebani server dan mencegah <em>timeout</em>.</p>
    
    <div class="upload-area">
        <label for="jsonFiles">Pilih File JSON</label>
        <input type="file" id="jsonFiles" multiple accept=".json">
        <div id="fileCount">Belum ada file terpilih.</div>
        <button id="btnStart">Mulai Import</button>
    </div>

    <div class="progress-container" id="progressContainer">
        <div class="progress-bar" id="progressBar"></div>
    </div>
    <div id="progressText">0 / 0 terselesaikan</div>

    <div id="log-area"></div>
</div>

<script>
    const fileInput = document.getElementById('jsonFiles');
    const fileCount = document.getElementById('fileCount');
    const btnStart = document.getElementById('btnStart');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logArea = document.getElementById('log-area');

    let filesQueue = [];
    let totalFiles = 0;
    let completedFiles = 0;
    let isUploading = false;

    fileInput.addEventListener('change', (e) => {
        if (isUploading) return;
        
        filesQueue = Array.from(e.target.files);
        totalFiles = filesQueue.length;
        if (totalFiles > 0) {
            fileCount.textContent = totalFiles + " file terpilih siap diimpor.";
            btnStart.style.display = 'inline-block';
        } else {
            fileCount.textContent = "Belum ada file terpilih.";
            btnStart.style.display = 'none';
        }
    });

    function logMessage(msg, type = 'info') {
        const div = document.createElement('div');
        div.className = 'log-item';
        if (type === 'error') div.classList.add('error');
        if (type === 'success') div.classList.add('success');
        
        const timestamp = new Date().toLocaleTimeString('id-ID');
        div.textContent = `[${timestamp}] ${msg}`;
        
        logArea.appendChild(div);
        logArea.scrollTop = logArea.scrollHeight;
    }

    function updateProgress() {
        let percent = (completedFiles / totalFiles) * 100;
        progressBar.style.width = percent + '%';
        progressText.textContent = completedFiles + " / " + totalFiles + " file terselesaikan (" + Math.round(percent) + "%)";
    }

    async function uploadNextFile() {
        if (filesQueue.length === 0) {
            isUploading = false;
            logMessage("=== PROSES IMPORT SELESAI ===", "success");
            btnStart.style.display = 'none';
            fileInput.value = "";
            fileCount.textContent = "Import selesai. Silakan pilih file baru jika ingin menambah.";
            return;
        }

        const file = filesQueue.shift();
        logMessage("Memproses: " + file.name + "...");

        const formData = new FormData();
        formData.append('json_file', file);

        try {
            const response = await fetch('api_import_json.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error("HTTP error " + response.status);
            }

            const data = await response.json();
            
            if (data.status === 'success') {
                logMessage("BERHASIL: " + file.name + " (" + data.title + ") -> ID: " + data.bkid, "success");
            } else {
                logMessage("GAGAL (" + file.name + "): " + data.message, "error");
            }
        } catch (error) {
            logMessage("ERROR (" + file.name + "): " + error.message, "error");
        }

        completedFiles++;
        updateProgress();
        
        // Jeda sedikit agar tidak membebani server
        setTimeout(uploadNextFile, 500);
    }

    btnStart.addEventListener('click', () => {
        if (isUploading) return;
        if (totalFiles === 0) return;

        isUploading = true;
        btnStart.disabled = true;
        btnStart.textContent = "Sedang Memproses...";
        fileInput.disabled = true;
        
        completedFiles = 0;
        progressContainer.style.display = 'block';
        progressText.style.display = 'block';
        logArea.innerHTML = '';
        logMessage("=== MEMULAI IMPORT " + totalFiles + " FILE ===");
        
        updateProgress();
        uploadNextFile();
    });
</script>
</body>
</html>
