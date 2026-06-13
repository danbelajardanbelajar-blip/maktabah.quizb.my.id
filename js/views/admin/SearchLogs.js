import { apiFetch, app, reicons, escHtml, paginationHtml, handleAuthError } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar, autoDir, bindAutoDir } from '../../core/AdminUtils.js';

// ══════════════════════════════════════════════════════════════
//  LOG PENCARIAN
// ══════════════════════════════════════════════════════════════
async function renderAdminSearchLogs() {
  if (!adminGuard()) return;

  let _sl = { page: 1, type: '', query: '', date: '' };

  app().innerHTML = adminNavBar('/admin/search-logs') + `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-primary/8 flex items-center justify-center">
          <i data-lucide="search" class="w-5 h-5 text-primary"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-primary">Log Pencarian</h1>
          <p class="text-xs text-primary/40">Riwayat pencarian seluruh pengunjung</p>
        </div>
      </div>

      <!-- Stats row -->
      <div id="sl-stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6"></div>

      <!-- Filter bar -->
      <div class="bg-white rounded-2xl shadow-card p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[130px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Jenis Pencarian</label>
          <select id="slf-type" onchange="slLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold bg-cream/30">
            <option value="">Semua</option>
            <option value="basic">Biasa</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Kata Kunci</label>
          <input id="slf-query" type="text" placeholder="Cari query…" oninput="slFilterDebounce()"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <div class="flex-1 min-w-[150px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Tanggal</label>
          <input id="slf-date" type="date" onchange="slLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <button onclick="slReset()"
          class="px-4 py-2 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors flex items-center gap-1.5">
          <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reset
        </button>
      </div>

      <!-- Top queries -->
      <div id="sl-topq" class="mb-6"></div>

      <!-- Table -->
      <div id="sl-grid" class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="p-10 text-center text-primary/30 text-sm">Memuat data…</div>
      </div>

      <!-- Pagination -->
      <div id="sl-pager" class="mt-4 flex items-center justify-center gap-2"></div>
    </div>`;

  reicons();

  window.slLoad = async function(p = 1) {
    _sl.page  = p;
    _sl.type  = document.getElementById('slf-type')?.value  || '';
    _sl.query = document.getElementById('slf-query')?.value || '';
    _sl.date  = document.getElementById('slf-date')?.value  || '';
    const grid = document.getElementById('sl-grid');
    grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm"><i data-lucide="loader-2" class="w-5 h-5 animate-spin inline-block mr-2"></i>Memuat…</div>';
    reicons();
    try {
      const d = await adminPost('admin_get_search_logs', {
        page: _sl.page, search_type: _sl.type,
        query: _sl.query, date: _sl.date, per_page: 25
      });

      // Stats
      const stats = d.stats || {};
      const statsEl = document.getElementById('sl-stats');
      if (statsEl) {
        statsEl.innerHTML = [
          { icon: 'calendar',      label: 'Hari Ini',      val: stats.today   || 0, color: 'text-blue-600',   bg: 'bg-blue-50'   },
          { icon: 'calendar-days', label: 'Minggu Ini',    val: stats.week    || 0, color: 'text-green-600',  bg: 'bg-green-50'  },
          { icon: 'list',          label: 'Total Semua',   val: d.total       || 0, color: 'text-primary',    bg: 'bg-cream/60'  },
          { icon: 'trending-up',   label: 'Unik (query)',  val: stats.unique  || 0, color: 'text-purple-600', bg: 'bg-purple-50' },
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

      // Top queries
      const topqEl = document.getElementById('sl-topq');
      if (topqEl && d.top_queries?.length && !_sl.query && !_sl.type && !_sl.date) {
        topqEl.innerHTML = `
          <div class="bg-white rounded-2xl shadow-card p-5">
            <div class="text-xs font-semibold text-primary/50 uppercase tracking-wider mb-3">Top Pencarian</div>
            <div class="flex flex-wrap gap-2">
              ${d.top_queries.map(q => `
                <div class="inline-flex items-center rounded-full bg-cream hover:bg-cream-dark border border-gold/15 transition-colors overflow-hidden">
                  <button onclick="document.getElementById('slf-query').value=${JSON.stringify(q.query).replace(/"/g, '&quot;')};slLoad(1)"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs text-primary/70 font-medium border-r border-gold/15">
                    <i data-lucide="search" class="w-3 h-3 text-gold/60"></i>
                    ${escHtml(q.query)}
                    <span class="text-primary/35">${q.cnt}×</span>
                  </button>
                  <button onclick="slDeleteQuery(${JSON.stringify(q.query).replace(/"/g, '&quot;')}, event)" class="px-2 py-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus query ini dari log">
                    <i data-lucide="x" class="w-3 h-3"></i>
                  </button>
                </div>`).join('')}
            </div>
          </div>`;
        reicons();
      } else if (topqEl) {
        topqEl.innerHTML = '';
      }

      // Table
      if (!d.rows?.length) {
        grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm">Tidak ada data pencarian.</div>';
        document.getElementById('sl-pager').innerHTML = '';
        return;
      }

      const typeBadge = t => t === 'advanced'
        ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-700">Advanced</span>'
        : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Biasa</span>';

      grid.innerHTML = `
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-cream/60 border-b border-gold/15">
              <tr>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50 whitespace-nowrap">Waktu</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Jenis</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Query</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50 text-right">Hasil</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">IP</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">User</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Browser</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-primary/50">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gold/8">
              ${d.rows.map(r => {
                const ua = r.user_agent || '';
                let browserShort = '—';
                if (ua.includes('wv') || (ua.includes('Android') && ua.includes('Version/4.0'))) {
                  browserShort = 'Android App';
                } else if (ua.includes('Edg/')) {
                  browserShort = 'Edge';
                } else if (ua.includes('OPR/') || ua.includes('Opera')) {
                  browserShort = 'Opera';
                } else if (ua.includes('SamsungBrowser')) {
                  browserShort = 'Samsung Internet';
                } else if (ua.includes('Chrome')) {
                  browserShort = ua.includes('Mobile') ? 'Chrome Mobile' : 'Chrome Desktop';
                } else if (ua.includes('Firefox')) {
                  browserShort = ua.includes('Mobile') ? 'Firefox Mobile' : 'Firefox Desktop';
                } else if (ua.includes('Safari') && !ua.includes('Chrome')) {
                  browserShort = ua.includes('Mobile') ? 'Safari Mobile' : 'Safari Desktop';
                } else if (ua.length > 0) {
                  browserShort = ua.slice(0, 18);
                }
                return `<tr class="hover:bg-cream/30 transition-colors">
                  <td class="px-4 py-3 text-primary/50 whitespace-nowrap text-xs">${escHtml(r.created_at)}</td>
                  <td class="px-4 py-3">${typeBadge(r.search_type)}</td>
                  <td class="px-4 py-3 max-w-xs">
                    <div class="font-medium text-primary text-xs truncate" title="${escHtml(r.query)}">${escHtml(r.query)}</div>
                    ${r.query_detail ? `<div class="text-primary/35 text-xs truncate mt-0.5">${escHtml(r.query_detail)}</div>` : ''}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold
                      ${+r.result_count > 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-500'}">
                      ${escHtml(String(r.result_count))}
                    </span>
                  </td>
                  <td class="px-4 py-3 font-mono text-xs text-primary/50">${escHtml(r.visitor_ip||'—')}</td>
                  <td class="px-4 py-3 text-xs text-primary/60">${escHtml(r.user_name||'Tamu')}</td>
                  <td class="px-4 py-3 text-xs text-primary/40">${escHtml(browserShort)}</td>
                  <td class="px-4 py-3 text-right">
                    <button onclick="slDelete(${r.id})" class="p-1.5 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition-colors" title="Hapus">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>`;

      // Pagination
      const totalPages = Math.ceil(d.total / 25);
      const pager = document.getElementById('sl-pager');
      if (totalPages <= 1) { pager.innerHTML = ''; reicons(); return; }
      let btns = '';
      if (_sl.page > 1) btns += `<button onclick="slLoad(${_sl.page-1})" class="px-3 py-1.5 rounded-lg bg-white border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">‹</button>`;
      const start = Math.max(1, _sl.page-2), end = Math.min(totalPages, _sl.page+2);
      for (let i = start; i <= end; i++) {
        btns += `<button onclick="slLoad(${i})"
          class="w-8 h-8 rounded-lg text-sm font-semibold transition-colors
          ${i === _sl.page ? 'bg-primary text-white' : 'bg-white border border-gold/25 text-primary/60 hover:bg-cream-dark'}">${i}</button>`;
      }
      if (_sl.page < totalPages) btns += `<button onclick="slLoad(${_sl.page+1})" class="px-3 py-1.5 rounded-lg bg-white border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">›</button>`;
      pager.innerHTML = `<div class="flex gap-1.5 flex-wrap justify-center">${btns}</div>`;
      reicons();
    } catch(e) {
      if (handleAuthError(e)) return;
      grid.innerHTML = `<div class="p-6 text-red-500 text-sm">${escHtml(e.message)}</div>`;
    }
  };

  window.slReset = function() {
    document.getElementById('slf-type').value  = '';
    document.getElementById('slf-query').value = '';
    document.getElementById('slf-date').value  = '';
    slLoad(1);
  };
  let _slTimer;
  window.slFilterDebounce = function() {
    clearTimeout(_slTimer);
    _slTimer = setTimeout(() => slLoad(1), 380);
  };

  window.slDelete = async function(id) {
    if (!confirm('Yakin ingin menghapus log pencarian ini?')) return;
    try {
      const res = await adminPost('admin_delete_search_log', { id });
      if (res.success) { adminToast('Log dihapus', 'success'); slLoad(_sl.page); }
      else adminToast(res.error || 'Gagal menghapus log', 'error');
    } catch(e) { adminToast(e.message, 'error'); }
  };

  window.slDeleteQuery = async function(query, e) {
    e.stopPropagation();
    if (!confirm(`Yakin ingin menghapus semua riwayat pencarian untuk kata "${query}"?\nIni akan menghilangkannya dari Pencarian Terpopuler.`)) return;
    try {
      const res = await adminPost('admin_delete_search_log', { query });
      if (res.success) { adminToast(`Semua log "${query}" dihapus`, 'success'); slLoad(1); }
      else adminToast(res.error || 'Gagal menghapus log', 'error');
    } catch(e) { adminToast(e.message, 'error'); }
  };

  await slLoad(1);
}

export { renderAdminSearchLogs };
