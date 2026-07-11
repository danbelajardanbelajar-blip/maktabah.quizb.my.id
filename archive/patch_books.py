import re
import os

books_path = "c:\\Users\\zenhk\\OneDrive\\Documents\\GitHub\\maktabah.quizb.my.id\\js\\views\\admin\\Books.js"

with open(books_path, "r", encoding="utf-8") as f:
    content = f.read()

# Define start and end markers
start_marker = "// IMPORT BOK (JSON CHUNKS) MODAL\n// =========================================="
end_marker = "// ==========================================\n// TOC Management"

# Ensure markers exist
if start_marker not in content:
    print("Start marker not found")
    exit(1)
if end_marker not in content:
    print("End marker not found")
    exit(1)

# Find positions
start_pos = content.find(start_marker)
end_pos = content.find(end_marker)

new_logic = """// IMPORT BOK (JSON CHUNKS) MODAL
// ==========================================
let _bokImp = { file: null, title: '', author: '', catId: '', pages: [], toc: [], currentPage: 0, bkid: null };

window.openImportBokModal = async () => {
  $('#form-import-bok')?.reset();
  _bokImp = { file: null, title: '', author: '', catId: '', pages: [], toc: [], currentPage: 0, bkid: null };
  _setBokImportStep(1);
  _renderBokImportStep1();
  $('#import-bok-modal')?.classList.remove('hidden');

  // Load categories after modal opens so _renderBokImportStep1 is ready
  let sel = document.getElementById('bok-category');
  if (sel) {
    sel.innerHTML = '<option value="">-- Pilih Kategori --</option>';
    try {
      const r = await apiFetch({ action: 'categories' });
      if (r && r.data) {
        // Find select again since it might have been re-rendered
        let sel2 = document.getElementById('bok-category');
        if (sel2) {
            r.data.forEach(c => {
            sel2.innerHTML += `<option value="${c.id}">${escHtml(c.name)}</option>`;
            });
        }
      }
    } catch (e) {
      console.error("Failed to load categories for bok modal", e);
    }
  }
};

window.closeImportBokModal = () => {
  $('#import-bok-modal')?.classList.add('hidden');
};

function _setBokImportStep(step) {
  const d1 = document.getElementById('bok-step1-dot');
  const l1 = document.getElementById('bok-step1-lbl');
  const d2 = document.getElementById('bok-step2-dot');
  const l2 = document.getElementById('bok-step2-lbl');
  if (!d1) return;
  if (step === 1) {
    d1.className = 'w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs shrink-0';
    l1.className = 'font-semibold text-primary';
    d2.className = 'w-6 h-6 rounded-full bg-cream-dark text-primary/30 flex items-center justify-center font-bold text-xs shrink-0';
    l2.className = 'text-primary/30';
  } else {
    d1.className = 'w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-xs shrink-0';
    l1.className = 'text-primary/60';
    d2.className = 'w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs shrink-0';
    l2.className = 'font-semibold text-primary';
  }
}

function _renderBokImportStep1() {
  const body = document.getElementById('bok-import-body');
  if (!body) return;
  body.innerHTML = `
    <div class="py-4">
      <p class="text-sm text-primary/60 text-center mb-6 leading-relaxed">
        Unggah file JSON hasil konversi Bok Converter. Sistem akan memproses data dalam potongan (chunk) agar stabil.
      </p>
      
      <div class="bg-cream p-4 rounded-xl text-xs text-primary/80 mb-6 space-y-2">
        <p><strong>Catatan:</strong></p>
        <p>Proses import kitab memakan waktu yang cukup lama. Harap jangan menutup jendela saat proses berlangsung.</p>
      </div>
      
      <form id="form-import-bok" onsubmit="event.preventDefault(); window.submitImportBok();">
        <div class="mb-4">
          <label class="block text-sm font-semibold text-primary mb-2">Kategori (Opsional)</label>
          <select id="bok-category" class="w-full bg-white border border-cream-dark rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-gold">
            <option value="">-- Pilih Kategori --</option>
          </select>
        </div>
        
        <div class="mb-6">
          <label class="block text-sm font-semibold text-primary mb-2">File Kitab (.json)</label>
          <input type="file" id="bok-file" accept=".json" required
            class="w-full text-sm text-primary/60 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-colors">
        </div>

        <div id="bok-import-loading" class="hidden flex-col justify-center my-4 space-y-2 w-full">
          <div class="text-sm font-semibold text-primary text-center">Membaca file...</div>
        </div>

        <div class="flex justify-end gap-3" id="bok-import-actions">
          <button type="button" onclick="closeImportBokModal()" class="px-5 py-2.5 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">Batal</button>
          <button type="submit" id="bok-submit-btn" class="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm flex items-center gap-2">
            <i data-lucide="scan-text" class="w-4 h-4"></i> Proses & Pratinjau
          </button>
        </div>
      </form>
    </div>
  `;
  reicons();
}

window.submitImportBok = async () => {
  let fileInput = document.getElementById('bok-file');
  if (!fileInput.files || fileInput.files.length === 0) {
    alert("Pilih file .json terlebih dahulu.");
    return;
  }
  
  const file = fileInput.files[0];
  const btn = document.getElementById('bok-submit-btn');
  
  if (btn) { btn.disabled = true; btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Memproses...'; reicons(); }
  document.getElementById('bok-import-loading').classList.remove('hidden');
  document.getElementById('bok-import-actions').classList.add('hidden');

  const reader = new FileReader();
  reader.onload = (e) => {
    try {
      const jsonStr = e.target.result;
      const data = JSON.parse(jsonStr);
      
      if (!data.buku) throw new Error("Format JSON tidak dikenali. Pastikan ini hasil Bok Converter.");
      if (!data.halaman || !Array.isArray(data.halaman) || data.halaman.length === 0) throw new Error("Tidak ada halaman yang ditemukan dalam JSON.");

      _bokImp.title = data.buku.title || file.name.replace(/\.[^/.]+$/, "");
      _bokImp.author = data.buku.author || '';
      // Read current value of category
      _bokImp.catId = document.getElementById('bok-category')?.value || '';
      _bokImp.pages = data.halaman;
      _bokImp.toc = data.daftar_isi || [];
      _bokImp.currentPage = 0;

      _setBokImportStep(2);
      _renderBokImportStep2();
    } catch (err) {
      alert("Gagal mem-parsing JSON: " + err.message);
      if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="scan-text" class="w-4 h-4"></i> Proses & Pratinjau'; reicons(); }
      document.getElementById('bok-import-loading').classList.add('hidden');
      document.getElementById('bok-import-actions').classList.remove('hidden');
    }
  };
  
  reader.onerror = () => {
    alert("Gagal membaca file lokal.");
    if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="scan-text" class="w-4 h-4"></i> Proses & Pratinjau'; reicons(); }
    document.getElementById('bok-import-loading').classList.add('hidden');
    document.getElementById('bok-import-actions').classList.remove('hidden');
  };
  
  reader.readAsText(file);
};

function _renderBokImportStep2() {
  const total = _bokImp.pages.length;
  const pg    = _bokImp.currentPage;
  const wordCount = _bokImp.pages[pg]?.text?.split(/\s+/).filter(Boolean).length || 0;

  // Pagination for sidebar to avoid freezing DOM with 10k items
  const perPage = 200;
  const totalSidebarPages = Math.ceil(total / perPage);
  const currentSidebarPage = Math.floor(pg / perPage);
  const startIdx = currentSidebarPage * perPage;
  const endIdx = Math.min(startIdx + perPage, total);
  
  document.getElementById('bok-import-body').innerHTML = `
    <div class="space-y-4 pt-2">

      <!-- Summary bar -->
      <div class="bg-cream rounded-xl px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center shrink-0">
            <i data-lucide="file-check" class="w-4 h-4 text-gold"></i>
          </div>
          <div>
            <div class="text-sm font-bold text-primary">${escHtml(_bokImp.title)}</div>
            <div class="text-xs text-primary/50">${total} halaman terdeteksi · JSON</div>
          </div>
        </div>
        <button onclick="window._bokImpBack()" class="text-xs text-primary/40 hover:text-primary transition-colors flex items-center gap-1">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Ubah
        </button>
      </div>

      <!-- Page navigator + preview -->
      <div class="grid grid-cols-[110px_1fr] md:grid-cols-[140px_1fr] gap-3" style="min-height:340px;">

        <!-- Sidebar halaman -->
        <div class="bg-white rounded-xl border border-cream-dark overflow-y-auto flex flex-col" style="max-height:420px;">
          <div class="px-3 py-2 bg-cream/50 border-b border-cream-dark text-xs font-semibold text-primary/40">Hal.</div>
          <div id="bok-imp-page-nav" class="flex-1 overflow-y-auto">
            ${Array.from({length: endIdx - startIdx}, (_, i) => {
              const absIdx = startIdx + i;
              const juz = _bokImp.pages[absIdx].juz;
              const hal = _bokImp.pages[absIdx].page;
              return \`
                <button onclick="window._bokImpGoPage(\${absIdx})"
                  class="w-full px-2 md:px-3 py-2 text-left text-[11px] transition-colors flex flex-col
                    \${absIdx === pg ? 'bg-primary text-white' : 'hover:bg-cream/60 text-primary/60'}">
                  <div class="\${absIdx === pg ? 'font-bold' : ''}">ID: \${absIdx + 1}</div>
                  <div class="\${absIdx === pg ? 'text-white/70' : 'text-primary/40'}">Juz \${juz}, Hal \${hal}</div>
                </button>\`;
            }).join('')}
          </div>
          ${totalSidebarPages > 1 ? `
          <div class="flex justify-between items-center p-1 border-t border-cream-dark bg-gray-50 shrink-0">
            <button onclick="window._bokImpGoSidebar(\${currentSidebarPage - 1})" \${currentSidebarPage === 0 ? 'disabled' : ''} class="p-1 rounded bg-cream text-primary/50 disabled:opacity-30 hover:bg-cream-dark"><i data-lucide="chevron-left" class="w-3 h-3"></i></button>
            <span class="text-[10px] text-primary/50 font-medium">\${currentSidebarPage + 1}/\${totalSidebarPages}</span>
            <button onclick="window._bokImpGoSidebar(\${currentSidebarPage + 1})" \${currentSidebarPage >= totalSidebarPages - 1 ? 'disabled' : ''} class="p-1 rounded bg-cream text-primary/50 disabled:opacity-30 hover:bg-cream-dark"><i data-lucide="chevron-right" class="w-3 h-3"></i></button>
          </div>` : ''}
        </div>

        <!-- Editor halaman -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <span class="text-[11px] md:text-xs font-semibold text-primary/50">
              Halaman <span class="text-primary font-bold">${pg+1}</span> dari ${total}
              <span class="text-primary/30 ml-1">(${wordCount} kata) · Juz ${_bokImp.pages[pg]?.juz}, Hal ${_bokImp.pages[pg]?.page}</span>
            </span>
            <div class="flex items-center gap-1">
              <button onclick="window._bokImpGoPage(${pg-1})" ${pg===0?'disabled':''} class="p-1.5 rounded-lg text-primary/40 hover:bg-cream-dark disabled:opacity-25 transition-colors">
                <i data-lucide="chevron-up" class="w-3.5 h-3.5"></i>
              </button>
              <button onclick="window._bokImpGoPage(${pg+1})" ${pg>=total-1?'disabled':''} class="p-1.5 rounded-lg text-primary/40 hover:bg-cream-dark disabled:opacity-25 transition-colors">
                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
              </button>
            </div>
          </div>
          <textarea id="bok-imp-page-text" dir="auto" rows="14"
            class="w-full px-4 py-3 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15 resize-y leading-relaxed font-arabic"
            style="min-height:260px;">${escHtml(_bokImp.pages[pg]?.text||'')}</textarea>
          <div class="flex justify-end">
            <button onclick="window._bokImpSavePage()"
              class="px-4 py-1.5 rounded-xl border border-gold/30 text-xs text-primary/60 hover:bg-cream-dark transition-colors flex items-center gap-1">
              <i data-lucide="check" class="w-3 h-3 text-gold"></i> Simpan edit halaman ini
            </button>
          </div>
        </div>
      </div>

      <!-- Progress import -->
      <div id="bok-imp-progress-wrap" class="hidden mt-4">
        <div class="flex items-center justify-between text-xs text-primary/50 mb-1.5">
          <span id="bok-imp-progress-status">Menginisialisasi...</span>
          <span id="bok-imp-progress-lbl" class="font-bold">0%</span>
        </div>
        <div class="w-full bg-cream-dark rounded-full h-2 overflow-hidden">
          <div id="bok-imp-progress-bar" class="bg-gold h-2 rounded-full transition-all duration-300" style="width:0%"></div>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-4 pt-4 border-t border-cream-dark">
        <button id="bok-imp-cancel-btn" onclick="window.closeImportBokModal()" class="px-5 py-2.5 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">
          Batal
        </button>
        <button onclick="window._bokImpConfirm()" id="bok-imp-confirm-btn" class="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm flex items-center gap-2">
          <i data-lucide="cloud-upload" class="w-4 h-4"></i> Konfirmasi & Import (${total} halaman)
        </button>
      </div>
    </div>
  `;
  reicons();
}

window._bokImpBack = function() {
  _setBokImportStep(1);
  _renderBokImportStep1();
};

window._bokImpGoPage = function(idx) {
  const ta = document.getElementById('bok-imp-page-text');
  if (ta) _bokImp.pages[_bokImp.currentPage].text = ta.value;
  _bokImp.currentPage = idx;
  _renderBokImportStep2();
};

window._bokImpGoSidebar = function(sidebarPage) {
  const perPage = 200;
  window._bokImpGoPage(sidebarPage * perPage);
};

window._bokImpSavePage = function() {
  const ta = document.getElementById('bok-imp-page-text');
  if (ta) {
    _bokImp.pages[_bokImp.currentPage].text = ta.value;
    adminToast('Perubahan halaman disimpan');
  }
};

window._bokImpConfirm = async function() {
  const ta = document.getElementById('bok-imp-page-text');
  if (ta) _bokImp.pages[_bokImp.currentPage].text = ta.value;

  const btn = document.getElementById('bok-imp-confirm-btn');
  const cancelBtn = document.getElementById('bok-imp-cancel-btn');
  const progWrap = document.getElementById('bok-imp-progress-wrap');
  const progBar = document.getElementById('bok-imp-progress-bar');
  const progLbl = document.getElementById('bok-imp-progress-lbl');
  const progStatus = document.getElementById('bok-imp-progress-status');

  if (btn) btn.disabled = true;
  if (cancelBtn) cancelBtn.disabled = true;
  if (progWrap) progWrap.classList.remove('hidden');

  const updateProgress = (pct, text) => {
    if (progBar) progBar.style.width = pct + '%';
    if (progLbl) progLbl.innerText = pct + '%';
    if (text && progStatus) progStatus.innerText = text;
  };

  try {
    // 1. INIT
    updateProgress(5, 'Menginisialisasi kitab...');
    const initRes = await fetch('/api.php?action=admin_import_json_init', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token'), 'Content-Type': 'application/json' },
      body: JSON.stringify({
        title: _bokImp.title,
        author: _bokImp.author,
        category_id: _bokImp.catId
      })
    });
    const initData = await initRes.json();
    if (!initData.success || !initData.bkid) throw new Error(initData.error || "Gagal menginisialisasi kitab.");
    let bkid = initData.bkid;

    // 2. CHUNKS (Pages)
    const chunkSize = 100;
    const pages = _bokImp.pages;
    const totalChunks = Math.ceil(pages.length / chunkSize);
    
    for (let i = 0; i < totalChunks; i++) {
      let chunk = pages.slice(i * chunkSize, (i + 1) * chunkSize);
      let p = 5 + Math.round(((i + 1) / totalChunks) * 80);
      updateProgress(p, `Mengimpor teks (Bagian ${i + 1}/${totalChunks})...`);
      
      const chunkRes = await fetch('/api.php?action=admin_import_json_chunk', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token'), 'Content-Type': 'application/json' },
        body: JSON.stringify({ bkid: bkid, pages: chunk })
      });
      const chunkData = await chunkRes.json();
      if (!chunkData.success) throw new Error(chunkData.error || "Gagal upload chunk.");
    }
    
    // 3. CHUNKS (TOC)
    if (_bokImp.toc && _bokImp.toc.length > 0) {
      const tocSize = 500;
      const tocs = _bokImp.toc;
      const totalTocChunks = Math.ceil(tocs.length / tocSize);
      
      for (let i = 0; i < totalTocChunks; i++) {
        let chunk = tocs.slice(i * tocSize, (i + 1) * tocSize);
        let p = 85 + Math.round(((i + 1) / totalTocChunks) * 10);
        updateProgress(p, `Mengimpor daftar isi (${i + 1}/${totalTocChunks})...`);
        
        const tocRes = await fetch('/api.php?action=admin_import_json_toc_chunk', {
          method: 'POST',
          headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token'), 'Content-Type': 'application/json' },
          body: JSON.stringify({ bkid: bkid, tocs: chunk })
        });
        const tocData = await tocRes.json();
        if (!tocData.success) throw new Error(tocData.error || "Gagal upload toc chunk.");
      }
    }
    
    // 4. FINISH
    updateProgress(98, 'Menyelesaikan proses...');
    const finishRes = await fetch('/api.php?action=admin_import_json_finish', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token'), 'Content-Type': 'application/json' },
      body: JSON.stringify({ bkid: bkid })
    });
    const finishData = await finishRes.json();
    if (!finishData.success) throw new Error(finishData.error || "Gagal menyelesaikan proses.");
    
    updateProgress(100, 'Selesai!');
    adminToast(`✅ ${_bokImp.pages.length} halaman berhasil diimpor!`);
    
    setTimeout(() => {
      closeImportBokModal();
      window.loadBooks();
      window.gotoContent(bkid);
    }, 500);
    
  } catch (err) {
    alert("Gagal mengimpor: " + err.message);
    if (btn) btn.disabled = false;
    if (cancelBtn) cancelBtn.disabled = false;
    // Don't hide progress wrap so user sees where it failed
    if (progStatus) progStatus.innerText = "Gagal: " + err.message;
    if (progBar) progBar.classList.replace('bg-gold', 'bg-red-500');
  }
};
"""

# Stitch together
final_content = content[:start_pos] + new_logic + "\n" + content[end_pos:]

with open(books_path, "w", encoding="utf-8") as f:
    f.write(final_content)

print("Successfully updated Books.js")
