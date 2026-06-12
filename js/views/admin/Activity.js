import { apiFetch, app, reicons, escHtml, paginationHtml, handleAuthError } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar, autoDir, bindAutoDir } from '../../core/AdminUtils.js';

async function renderAdminActivity() {
  if (!adminGuard()) return;

  let _act = { page: 1, event: '', query: '', date: '' };

  app().innerHTML = adminNavBar('/admin/activity') + `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-primary/8 flex items-center justify-center">
          <i data-lucide="activity" class="w-5 h-5 text-primary"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-primary">Log Aktivitas</h1>
          <p class="text-xs text-primary/40">Riwayat kunjungan, menu klik, login, logout, dan registrasi pengunjung.</p>
        </div>
      </div>

      <div id="act-stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6"></div>

      <div class="bg-white rounded-2xl shadow-card p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[140px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Jenis Event</label>
          <select id="actf-event" onchange="actLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold bg-cream/30">
            <option value="">Semua</option>
            <option value="visit">Visit</option>
            <option value="menu_click">Menu Click</option>
            <option value="login">Login</option>
            <option value="logout">Logout</option>
            <option value="register">Register</option>
          </select>
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Cari</label>
          <input id="actf-query" type="text" placeholder="Cari user, email, IP, atau detail…" oninput="actFilterDebounce()" onkeydown="if (event.key === 'Enter') actLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <div class="flex-1 min-w-[150px]">
          <label class="block text-xs font-semibold text-primary/50 mb-1">Tanggal</label>
          <input id="actf-date" type="date" onchange="actLoad(1)"
            class="w-full px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold" />
        </div>
        <button type="button" onclick="actReset()"
          class="px-4 py-2 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors flex items-center gap-1.5">
          <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reset
        </button>
      </div>

      <div id="act-grid" class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="p-10 text-center text-primary/30 text-sm">Memuat data…</div>
      </div>

      <div id="act-pager" class="mt-4 flex items-center justify-center gap-2"></div>
    </div>`;

  reicons();

  window.actLoad = async function(p = 1) {
    _act.page  = p;
    _act.event = document.getElementById('actf-event')?.value || '';
    _act.query = document.getElementById('actf-query')?.value || '';
    _act.date  = document.getElementById('actf-date')?.value || '';
    const grid = document.getElementById('act-grid');
    grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm"><i data-lucide="loader-2" class="w-5 h-5 animate-spin inline-block mr-2"></i>Memuat…</div>';
    reicons();
    try {
      const d = await adminPost('admin_get_activity', {
        page: _act.page, event: _act.event,
        query: _act.query, date: _act.date, per_page: 20
      });

      const stats = d.stats || {};
      const statsEl = document.getElementById('act-stats');
      if (statsEl) {
        statsEl.innerHTML = [
          { icon: 'calendar',      label: 'Hari Ini',    val: stats.today || 0, color: 'text-blue-600',  bg: 'bg-blue-50'  },
          { icon: 'calendar-days', label: 'Minggu Ini',  val: stats.week  || 0, color: 'text-green-600', bg: 'bg-green-50' },
          { icon: 'list',          label: 'Total Semua', val: d.total    || 0, color: 'text-primary',  bg: 'bg-cream/60' },
          { icon: 'trending-up',   label: 'Top Event',   val: d.top_events?.length || 0, color: 'text-purple-600', bg: 'bg-purple-50' },
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

      if (!d.rows?.length) {
        grid.innerHTML = '<div class="p-10 text-center text-primary/30 text-sm">Tidak ada data activity.</div>';
        document.getElementById('act-pager').innerHTML = '';
        return;
      }

      const eventBadge = e => {
        const m = {
          visit: 'bg-blue-100 text-blue-700',
          menu_click: 'bg-yellow-100 text-yellow-700',
          login: 'bg-green-100 text-green-700',
          logout: 'bg-red-100 text-red-700',
          register: 'bg-purple-100 text-purple-700',
        };
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold ${m[e]||'bg-gray-100 text-gray-600'}">${escHtml(e)}</span>`;
      };

      grid.innerHTML = `
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-cream/60 border-b border-gold/15">
              <tr>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50 whitespace-nowrap">Waktu</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Event</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">User</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Role</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">IP</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-primary/50">Detail</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gold/8">
              ${d.rows.map(r => `
                <tr class="hover:bg-cream/30 transition-colors">
                  <td class="px-4 py-3 text-primary/50 whitespace-nowrap text-xs">${escHtml(r.created_at)}</td>
                  <td class="px-4 py-3">${eventBadge(r.event)}</td>
                  <td class="px-4 py-3 text-xs text-primary/70">${escHtml(r.user_name || 'Guest')}</td>
                  <td class="px-4 py-3 text-xs text-primary/60">${escHtml(r.user_role || 'user')}</td>
                  <td class="px-4 py-3 text-xs text-primary/60 font-mono truncate max-w-[120px]">${escHtml(r.ip_address)}</td>
                  <td class="px-4 py-3 text-xs text-primary/60 max-w-[220px] truncate" title="${escHtml(r.event_data||'')}">${escHtml(r.event_data || '—')}</td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;

      const totalPages = Math.ceil(d.total / 20);
      const pager = document.getElementById('act-pager');
      if (totalPages <= 1) {
        pager.innerHTML = '';
        return;
      }
      pager.innerHTML = `
        <div class="flex flex-col items-center gap-2 text-center">
          <div class="text-xs text-primary/50">Menampilkan halaman ${_act.page} dari ${totalPages} — total ${d.total.toLocaleString()} aktivitas</div>
          ${paginationHtml(_act.page, totalPages, 'actGotoPage')}
        </div>`;
      reicons();
    } catch (e) {
      if (handleAuthError(e)) return;
      grid.innerHTML = `<div class="p-6 text-red-500 text-sm">${escHtml(e.message)}</div>`;
    }
  };

  window.actGotoPage = p => actLoad(p);
  window.actReset = function() {
    document.getElementById('actf-event').value = '';
    document.getElementById('actf-query').value = '';
    document.getElementById('actf-date').value = '';
    actLoad(1);
  };
  let _actTimer;
  window.actFilterDebounce = function() {
    clearTimeout(_actTimer);
    _actTimer = setTimeout(() => actLoad(1), 380);
  };

  await actLoad(1);
}

export { renderAdminActivity };
