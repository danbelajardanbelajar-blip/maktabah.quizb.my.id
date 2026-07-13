import { apiFetch, app, reicons, escHtml, paginationHtml, handleAuthError } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar, autoDir, bindAutoDir } from '../../core/AdminUtils.js';

// ══════════════════════════════════════════════════════════════
//  ADMIN — LOG DOWNLOAD KITAB
// ══════════════════════════════════════════════════════════════
async function renderAdminDownloadLogs() {
  if (!adminGuard()) return;

  let _dl = { page: 1, bkid: '', query: '', date: '' };

  app().innerHTML = adminNavBar('/admin/download-logs') + `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-primary/8 flex items-center justify-center">
          <i data-lucide="download" class="w-5 h-5 text-primary"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-primary">Log Download Kitab</h1>
          <p class="text-xs text-primary/40">Rekam IP dan perangkat yang mengunduh kitab.</p>
        </div>
      </div>

      <!-- Stats row -->
      <div id="dl-stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6"></div>

      <!-- Filter bar -->
      <div class="bg-white rounded-2xl shadow-card p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[130px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">ID Kitab</label>
          <input id="dlf-bkid" type="text" placeholder="Masukkan ID kitab"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Cari Judul / User / IP</label>
          <input id="dlf-query" type="text" placeholder="Masukkan kata kunci…" oninput="dlFilterDebounce()"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <div class="flex-1 min-w-[150px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Tanggal</label>
          <input id="dlf-date" type="date" onchange="dlLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <button onclick="dlReset()"
          class="px-4 py-2 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors flex items-center gap-1.5">
          <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reset
        </button>
      </div>

      <!-- Top downloads -->
      <div id="dl-topd" class="mb-6"></div>

      <!-- Table -->
      <div id="dl-grid" class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="p-10 text-center text-primary/30 text-sm">Memuat data…</div>
      </div>

      <div id="dl-pager" class="mt-4 flex items-center justify-center gap-2"></div>
    </div>`;

  reicons();

  window.dlLoad = async function(p = 1) {
    _dl.page  = p;
    _dl.bkid  = document.getElementById('dlf-bkid')?.value.trim() || '';
    _dl.query = document.getElementById('dlf-query')?.value.trim() || '';
    _dl.date  = document.getElementById('dlf-date')?.value  || '';
    const grid = document.getElementById('dl-grid');
    grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm"><i data-lucide="loader-2" class="w-5 h-5 animate-spin inline-block mr-2"></i>Memuat…</div>';
    reicons();
    try {
      const d = await adminPost('admin_get_download_logs', {
        page: _dl.page, per_page: 25,
        bkid: _dl.bkid, query: _dl.query, date: _dl.date
      });

      // Stats
      const stats = d.stats || {};
      const statsEl = document.getElementById('dl-stats');
      if (statsEl) {
        statsEl.innerHTML = [
          { icon: 'calendar',      label: 'Hari Ini',      val: stats.today   || 0, color: 'text-blue-600',   bg: 'bg-blue-50'   },
          { icon: 'calendar-days', label: 'Minggu Ini',    val: stats.week    || 0, color: 'text-green-600',  bg: 'bg-green-50'  },
          { icon: 'list',          label: 'Total Semua',   val: d.total       || 0, color: 'text-primary',    bg: 'bg-cream/60'  },
          { icon: 'trending-up',   label: 'Unik (kitab)',  val: stats.unique  || 0, color: 'text-purple-600', bg: 'bg-purple-50' },
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

      // Top downloads
      const topdEl = document.getElementById('dl-topd');
      if (topdEl && d.top_downloads?.length && !_dl.bkid && !_dl.query && !_dl.date) {
        topdEl.innerHTML = `
          <div class="bg-white rounded-2xl shadow-card p-5">
            <div class="text-xs font-semibold text-primary/50 uppercase tracking-wider mb-3">Top Download</div>
            <div class="flex flex-wrap gap-2">
              ${d.top_downloads.map(q => `
                <button onclick="document.getElementById('dlf-bkid').value=${JSON.stringify(q.bkid)};dlLoad(1)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-cream hover:bg-cream-dark text-xs text-primary/70 font-medium transition-colors border border-gold/15">
                  <i data-lucide="download" class="w-3 h-3 text-gold/60"></i>
                  ${q.source === 'scholarium' ? '<span class="text-[10px] bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded uppercase font-bold tracking-wider">Scholarium</span> ' : ''}${escHtml(q.book_title)}
                  <span class="text-primary/35">${q.cnt}×</span>
                </button>`).join('')}
            </div>
          </div>`;
        reicons();
      } else if (topdEl) {
        topdEl.innerHTML = '';
      }

      // Table
      if (!d.rows?.length) {
        grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm">Tidak ada log download.</div>';
        document.getElementById('dl-pager').innerHTML = '';
        return;
      }

      grid.innerHTML = `
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-cream/60 border-b border-gold/15">
              <tr>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Waktu</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Kitab</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">User / Role</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">IP</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Perangkat / Browser</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gold/8">
              ${d.rows.map(r => `
                <tr class="hover:bg-cream/30 transition-colors">
                  <td class="px-4 py-3 text-primary/50 whitespace-nowrap text-xs">${escHtml(r.created_at)}</td>
                  <td class="px-4 py-3 text-xs text-primary/70 max-w-[240px] truncate" title="${escHtml(r.book_title)}">
                    ${r.source === 'scholarium' ? '<span class="inline-block text-[10px] bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded uppercase font-bold tracking-wider mr-1">Scholarium</span>' : ''}
                    #${escHtml(String(r.bkid))} — ${escHtml(r.book_title)}
                  </td>
                  <td class="px-4 py-3 text-xs text-primary/60">
                    ${escHtml(r.user_name || 'Guest')}<br>
                    <span class="text-primary/40">${escHtml(r.user_email || '—')} · ${escHtml(r.user_role || 'user')}</span>
                  </td>
                  <td class="px-4 py-3 font-mono text-xs text-primary/50 truncate max-w-[140px]">${escHtml(r.ip_address)}</td>
                  <td class="px-4 py-3 text-xs text-primary/60 max-w-[320px] truncate" title="${escHtml(r.user_agent)}">${escHtml(r.user_agent)}</td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;

      const totalPages = Math.ceil(d.total / 25);
      const pager = document.getElementById('dl-pager');
      if (totalPages <= 1) {
        pager.innerHTML = '';
      } else {
        pager.innerHTML = `
          <div class="flex gap-2 flex-wrap justify-center">
            ${paginationHtml(_dl.page, totalPages, 'dlPage')}
          </div>`;
      }
      reicons();
    } catch (e) {
      if (handleAuthError(e)) return;
      grid.innerHTML = `<div class="p-6 text-red-500 text-sm">${escHtml(e.message)}</div>`;
    }
  };

  window.dlPage = p => dlLoad(p);
  window.dlReset = function() {
    document.getElementById('dlf-bkid').value = '';
    document.getElementById('dlf-query').value = '';
    document.getElementById('dlf-date').value = '';
    dlLoad(1);
  };
  let _dlTimer;
  window.dlFilterDebounce = function() {
    clearTimeout(_dlTimer);
    _dlTimer = setTimeout(() => dlLoad(1), 380);
  };

  await dlLoad(1);
}

export { renderAdminDownloadLogs };
