import { apiFetch, app, reicons, escHtml, paginationHtml, handleAuthError } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar, autoDir, bindAutoDir } from '../../core/AdminUtils.js';

// ══════════════════════════════════════════════════════════════
//  CRUD HISTORY
// ══════════════════════════════════════════════════════════════
async function renderAdminHistory() {
  if (!adminGuard()) return;
  if (window.innerWidth < 768) {
    app().innerHTML = adminNavBar('/admin/history') + `
      <div class="max-w-2xl mx-auto px-4 py-16 text-center">
        <i data-lucide="monitor" class="w-12 h-12 text-gold/40 mx-auto mb-4"></i>
        <p class="text-primary/50 text-sm">Halaman ini hanya tersedia di tampilan desktop.</p>
      </div>`;
    reicons(); return;
  }

  let _hist = { page: 1, action: '', table: '', admin: '', data: null };

  app().innerHTML = adminNavBar('/admin/history') + `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-primary/8 flex items-center justify-center">
          <i data-lucide="history" class="w-5 h-5 text-primary"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-primary">CRUD History</h1>
          <p class="text-xs text-primary/40">Rekam jejak perubahan data oleh admin</p>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-2xl shadow-card p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[140px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Jenis Aksi</label>
          <select id="hf-action" onchange="histLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold bg-cream/30">
            <option value="">Semua</option>
            <option value="CREATE">CREATE</option>
            <option value="UPDATE">UPDATE</option>
            <option value="DELETE">DELETE</option>
            <option value="IMPORT">IMPORT</option>
          </select>
        </div>
        <div class="flex-1 min-w-[140px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Tabel</label>
          <select id="hf-table" onchange="histLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold bg-cream/30">
            <option value="">Semua</option>
            <option value="books">books</option>
            <option value="book_content">book_content</option>
            <option value="categories">categories</option>
            <option value="users">users</option>
          </select>
        </div>
        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Nama Admin</label>
          <input id="hf-admin" type="text" placeholder="Cari nama admin…" oninput="histFilterDebounce()"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <button onclick="histReset()"
          class="px-4 py-2 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors flex items-center gap-1.5">
          <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reset
        </button>
      </div>

      <!-- Table area -->
      <div id="hist-grid" class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="p-10 text-center text-primary/30 text-sm">Memuat data…</div>
      </div>

      <!-- Pagination -->
      <div id="hist-pager" class="mt-4 flex items-center justify-center gap-2"></div>
    </div>`;

  reicons();

  window.histLoad = async function(p = 1) {
    _hist.page   = p;
    _hist.action = document.getElementById('hf-action')?.value || '';
    _hist.table  = document.getElementById('hf-table')?.value  || '';
    _hist.admin  = document.getElementById('hf-admin')?.value  || '';
    const grid = document.getElementById('hist-grid');
    grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm"><i data-lucide="loader-2" class="w-5 h-5 animate-spin inline-block mr-2"></i>Memuat…</div>';
    reicons();
    try {
      const d = await adminPost('admin_get_history', {
        page: _hist.page, action: _hist.action,
        table_name: _hist.table, admin_name: _hist.admin, per_page: 20
      });
      if (!d.rows?.length) {
        grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm">Tidak ada data.</div>';
        document.getElementById('hist-pager').innerHTML = '';
        return;
      }
      const actionBadge = a => {
        const m = { CREATE:'bg-green-100 text-green-700', UPDATE:'bg-blue-100 text-blue-700',
                    DELETE:'bg-red-100 text-red-700', IMPORT:'bg-purple-100 text-purple-700' };
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold ${m[a]||'bg-gray-100 text-gray-600'}">${escHtml(a)}</span>`;
      };
      grid.innerHTML = `
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-cream/60 border-b border-gold/15">
              <tr>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50 whitespace-nowrap">Waktu</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Aksi</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Tabel</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">ID Record</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Detail</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Admin</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gold/8">
              ${d.rows.map(r => `
                <tr class="hover:bg-cream/30 transition-colors">
                  <td class="px-4 py-3 text-primary/50 whitespace-nowrap text-xs">${escHtml(r.created_at)}</td>
                  <td class="px-4 py-3">${actionBadge(r.action)}</td>
                  <td class="px-4 py-3 font-mono text-xs text-primary/70">${escHtml(r.table_name)}</td>
                  <td class="px-4 py-3 font-mono text-xs text-primary/60">${escHtml(r.record_id)}</td>
                  <td class="px-4 py-3 text-xs text-primary/60 max-w-xs truncate" title="${escHtml(r.detail||'')}">${escHtml(r.detail||'—')}</td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <div class="w-6 h-6 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold shrink-0">
                        ${escHtml((r.admin_name||'?').charAt(0).toUpperCase())}
                      </div>
                      <span class="text-xs text-primary/70 truncate max-w-[120px]">${escHtml(r.admin_name||'—')}</span>
                    </div>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
      // Pagination
      const totalPages = Math.ceil(d.total / 20);
      const pager = document.getElementById('hist-pager');
      if (totalPages <= 1) {
        pager.innerHTML = '';
        return;
      }
      pager.innerHTML = `
        <div class="flex flex-col items-center gap-2">
          <div class="text-xs text-primary/50">Menampilkan halaman ${_hist.page} dari ${totalPages} — total ${d.total} catatan</div>
          ${paginationHtml(_hist.page, totalPages, 'histGotoPage')}
        </div>`;
      reicons();
    } catch(e) {
      grid.innerHTML = `<div class="p-6 text-red-500 text-sm">${escHtml(e.message)}</div>`;
    }
  };

  window.histGotoPage = p => histLoad(p);
  window.histReset = function() {
    document.getElementById('hf-action').value = '';
    document.getElementById('hf-table').value  = '';
    document.getElementById('hf-admin').value  = '';
    histLoad(1);
  };
  let _histTimer;
  window.histFilterDebounce = function() {
    clearTimeout(_histTimer);
    _histTimer = setTimeout(() => histLoad(1), 380);
  };

  await histLoad(1);
}

export { renderAdminHistory };
