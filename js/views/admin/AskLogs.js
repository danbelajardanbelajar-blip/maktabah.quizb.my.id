import { apiFetch, app, reicons, escHtml, paginationHtml, handleAuthError } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar } from '../../core/AdminUtils.js';

// ══════════════════════════════════════════════════════════════
//  LOG TANYA AI
// ══════════════════════════════════════════════════════════════
async function renderAdminAskLogs() {
  if (!adminGuard()) return;

  let _al = { page: 1, query: '', date: '' };

  app().innerHTML = adminNavBar('/admin/ask-logs') + `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-primary/8 flex items-center justify-center">
          <i data-lucide="bot" class="w-5 h-5 text-primary"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-primary">Log Tanya AI</h1>
          <p class="text-xs text-primary/40">Riwayat pertanyaan kepada AI</p>
        </div>
      </div>

      <!-- Stats row -->
      <div id="al-stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6"></div>

      <!-- Filter bar -->
      <div class="bg-white rounded-2xl shadow-card p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Pencarian</label>
          <input id="alf-query" type="text" placeholder="Cari pertanyaan atau IP…" oninput="alFilterDebounce()"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <div class="flex-1 min-w-[150px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Tanggal</label>
          <input id="alf-date" type="date" onchange="alLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <button onclick="alReset()"
          class="px-4 py-2 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors flex items-center gap-1.5">
          <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reset
        </button>
      </div>

      <!-- Table -->
      <div id="al-grid" class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="p-10 text-center text-primary/30 text-sm">Memuat data…</div>
      </div>

      <!-- Pagination -->
      <div id="al-pager" class="mt-4 flex items-center justify-center gap-2"></div>
    </div>`;

  reicons();

  window.alLoad = async function(p = 1) {
    _al.page  = p;
    _al.query = document.getElementById('alf-query')?.value || '';
    _al.date  = document.getElementById('alf-date')?.value  || '';
    const grid = document.getElementById('al-grid');
    grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm"><i data-lucide="loader-2" class="w-5 h-5 animate-spin inline-block mr-2"></i>Memuat…</div>';
    reicons();
    try {
      const d = await adminPost('admin_get_ask_logs', {
        page: _al.page,
        query: _al.query, date: _al.date, per_page: 25
      });

      // Stats
      const stats = d.stats || {};
      const statsEl = document.getElementById('al-stats');
      if (statsEl) {
        statsEl.innerHTML = [
          { icon: 'calendar',      label: 'Hari Ini',      val: stats.today   || 0, color: 'text-blue-600',   bg: 'bg-blue-50'   },
          { icon: 'calendar-days', label: 'Minggu Ini',    val: stats.week    || 0, color: 'text-green-600',  bg: 'bg-green-50'  },
          { icon: 'list',          label: 'Total Semua',   val: d.total       || 0, color: 'text-primary',    bg: 'bg-cream/60'  },
          { icon: 'users',         label: 'Unik (Query)',  val: stats.unique  || 0, color: 'text-purple-600', bg: 'bg-purple-50' },
        ].map(s => `
          <div class="bg-white rounded-2xl shadow-card p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl ${s.bg} flex items-center justify-center shrink-0">
              <i data-lucide="${s.icon}" class="w-5 h-5 ${s.color}"></i>
            </div>
            <div>
              <div class="text-xs text-primary/40 font-medium">${s.label}</div>
              <div class="text-xl font-bold text-primary">${s.val.toLocaleString()}</div>
            </div>
          </div>`).join('');
      }

      // Table
      if (!d.rows?.length) {
        grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm">Tidak ada data log AI.</div>';
        document.getElementById('al-pager').innerHTML = '';
        return;
      }

      grid.innerHTML = `
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-cream/60 border-b border-gold/15">
              <tr>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50 whitespace-nowrap">Waktu</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Pertanyaan & Jawaban AI</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">IP</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">User</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gold/8">
              ${d.rows.map(r => {
                return `<tr class="hover:bg-cream/30 transition-colors">
                  <td class="px-4 py-3 text-primary/50 whitespace-nowrap text-xs align-top">${escHtml(r.created_at)}</td>
                  <td class="px-4 py-3 max-w-lg align-top">
                    <div class="font-bold text-primary text-sm mb-2"><span class="text-xs text-primary/50 uppercase mr-1">Q:</span> ${escHtml(r.question)}</div>
                    <div class="text-primary/70 text-xs mt-1 whitespace-pre-wrap max-h-32 overflow-y-auto pr-2 bg-gray-50 p-2 rounded-lg border border-gray-100">${escHtml(r.response || '')}</div>
                  </td>
                  <td class="px-4 py-3 font-mono text-xs text-primary/50 align-top">${escHtml(r.visitor_ip||'—')}</td>
                  <td class="px-4 py-3 text-xs text-primary/60 align-top">${escHtml(r.user_name||'Tamu')}</td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>`;

      // Pagination
      const totalPages = Math.ceil(d.total / 25);
      const pager = document.getElementById('al-pager');
      if (totalPages <= 1) { pager.innerHTML = ''; reicons(); return; }
      let btns = '';
      if (_al.page > 1) btns += `<button onclick="alLoad(${_al.page-1})" class="px-3 py-1.5 rounded-lg bg-white border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">‹</button>`;
      const start = Math.max(1, _al.page-2), end = Math.min(totalPages, _al.page+2);
      for (let i = start; i <= end; i++) {
        btns += `<button onclick="alLoad(${i})"
          class="w-8 h-8 rounded-lg text-sm font-semibold transition-colors
          ${i === _al.page ? 'bg-primary text-white' : 'bg-white border border-gold/25 text-primary/60 hover:bg-cream-dark'}">${i}</button>`;
      }
      if (_al.page < totalPages) btns += `<button onclick="alLoad(${_al.page+1})" class="px-3 py-1.5 rounded-lg bg-white border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">›</button>`;
      pager.innerHTML = `<div class="flex gap-1.5 flex-wrap justify-center">${btns}</div>`;
      reicons();
    } catch(e) {
      if (handleAuthError(e)) return;
      grid.innerHTML = `<div class="p-6 text-red-500 text-sm">${escHtml(e.message)}</div>`;
    }
  };

  window.alReset = function() {
    document.getElementById('alf-query').value = '';
    document.getElementById('alf-date').value  = '';
    alLoad(1);
  };
  let _alTimer;
  window.alFilterDebounce = function() {
    clearTimeout(_alTimer);
    _alTimer = setTimeout(() => alLoad(1), 380);
  };

  await alLoad(1);
}

export { renderAdminAskLogs };
