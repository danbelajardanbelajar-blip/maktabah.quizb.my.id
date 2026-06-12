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
                  <td class="px-4 py-3 text-xs text-primary/70 max-w-[240px] truncate" title="${escHtml(r.book_title)}">#${escHtml(String(r.bkid))} — ${escHtml(r.book_title)}</td>
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
