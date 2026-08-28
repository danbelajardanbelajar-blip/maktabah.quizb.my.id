export async function renderAdminExport() {
  const content = document.getElementById('admin-content');
  if (!content) return;

  content.innerHTML = `
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i data-lucide="database" class="w-6 h-6 text-green-700"></i>
          Export Database
        </h2>
        <p class="text-gray-500 text-sm mt-1">Export kategori dan kitab ke format SQLite (maktabah.db)</p>
      </div>
      <button id="btn-export-selected" class="bg-green-700 hover:bg-green-800 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-lg shadow-green-900/20 transition-all opacity-50 cursor-not-allowed" disabled>
        <i data-lucide="download" class="w-4 h-4"></i>
        Export Terpilih
      </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-4 border-b border-gray-100 flex items-center gap-4 bg-gray-50/50">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" id="check-all" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
          <span class="text-sm font-medium text-gray-700">Pilih Semua</span>
        </label>
      </div>
      <div id="export-list" class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto p-4">
        <div class="py-12 text-center text-gray-400">
          <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-3"></i>
          <p>Memuat data...</p>
        </div>
      </div>
    </div>
  `;

  if (window.lucide) window.lucide.createIcons();
  
  await loadExportData();
}

async function loadExportData() {
  const listEl = document.getElementById('export-list');
  const checkAll = document.getElementById('check-all');
  const btnExport = document.getElementById('btn-export-selected');
  
  try {
    const res = await fetch('/api.php?action=export_data');
    const json = await res.json();
    
    if (json.status !== 'success') {
      listEl.innerHTML = `<div class="text-red-500 p-4 text-center">Gagal memuat data.</div>`;
      return;
    }
    
    const categories = json.data;
    
    if (categories.length === 0) {
      listEl.innerHTML = `<div class="p-4 text-center text-gray-500">Tidak ada data.</div>`;
      return;
    }
    
    listEl.innerHTML = categories.map(cat => `
      <div class="py-3">
        <label class="flex items-center gap-2 cursor-pointer mb-2">
          <input type="checkbox" class="cat-check w-4 h-4 text-green-600 rounded border-gray-300" data-cat-id="${cat.id}">
          <span class="font-bold text-gray-800 text-sm">${cat.name} (${cat.books.length} kitab)</span>
        </label>
        <div class="pl-6 flex flex-col gap-1">
          ${cat.books.map(b => `
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" class="book-check w-3.5 h-3.5 text-green-600 rounded border-gray-300" data-book-id="${b.bkid}" data-cat-id="${cat.id}">
              <span class="text-sm text-gray-600">${b.title}</span>
            </label>
          `).join('')}
        </div>
      </div>
    `).join('');
    
    // Logic for checkboxes
    const catChecks = document.querySelectorAll('.cat-check');
    const bookChecks = document.querySelectorAll('.book-check');
    
    function updateBtn() {
      const selected = document.querySelectorAll('.book-check:checked').length;
      if (selected > 0) {
        btnExport.disabled = false;
        btnExport.classList.remove('opacity-50', 'cursor-not-allowed');
      } else {
        btnExport.disabled = true;
        btnExport.classList.add('opacity-50', 'cursor-not-allowed');
      }
    }
    
    checkAll.addEventListener('change', e => {
      const checked = e.target.checked;
      catChecks.forEach(c => c.checked = checked);
      bookChecks.forEach(b => b.checked = checked);
      updateBtn();
    });
    
    catChecks.forEach(c => {
      c.addEventListener('change', e => {
        const catId = e.target.dataset.catId;
        const checked = e.target.checked;
        document.querySelectorAll(`.book-check[data-cat-id="${catId}"]`).forEach(b => {
          b.checked = checked;
        });
        updateBtn();
      });
    });
    
    bookChecks.forEach(b => {
      b.addEventListener('change', () => {
        const catId = b.dataset.catId;
        const allBooksInCat = document.querySelectorAll(`.book-check[data-cat-id="${catId}"]`);
        const checkedBooksInCat = document.querySelectorAll(`.book-check[data-cat-id="${catId}"]:checked`);
        const catCheck = document.querySelector(`.cat-check[data-cat-id="${catId}"]`);
        
        catCheck.checked = allBooksInCat.length === checkedBooksInCat.length;
        updateBtn();
      });
    });
    
    // Export action
    btnExport.addEventListener('click', async () => {
      const selectedBooks = Array.from(document.querySelectorAll('.book-check:checked')).map(b => b.dataset.bookId);
      
      const originalText = btnExport.innerHTML;
      btnExport.disabled = true;
      btnExport.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Memproses...';
      if (window.lucide) window.lucide.createIcons();
      
      try {
        const res = await fetch('/api.php?action=do_export', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ book_ids: selectedBooks })
        });
        
        const data = await res.json();
        if (data.status === 'success') {
          window.location.href = data.url;
        } else {
          alert('Gagal mengekspor: ' + data.message);
        }
      } catch (err) {
        alert('Terjadi kesalahan koneksi.');
      }
      
      btnExport.innerHTML = originalText;
      updateBtn();
      if (window.lucide) window.lucide.createIcons();
    });
    
  } catch (e) {
    listEl.innerHTML = `<div class="text-red-500 p-4 text-center">Gagal memuat data.</div>`;
  }
}
