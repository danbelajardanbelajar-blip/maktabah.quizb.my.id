import { app, reicons, escHtml } from '../core/core.js';

// ══════════════════════════════════════════════════════════════
//  DASHBOARD
// ══════════════════════════════════════════════════════════════
function renderDashboard() {
  const u = window.SESSION_USER;
  if (!u) { window.location.href = '/auth.php?action=login'; return; }
  const isAdmin = u.role === 'admin';

  app().innerHTML = `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
      <!-- Profile card -->
      <div class="bg-white rounded-2xl shadow-card p-6 mb-8 flex items-center gap-5">
        ${u.picture
          ? `<img src="${escHtml(u.picture)}" class="w-16 h-16 rounded-full object-cover border-4 border-gold/30 shadow"/>`
          : `<div class="w-16 h-16 rounded-full bg-primary flex items-center justify-center text-white text-2xl font-bold shadow">${escHtml(u.name.charAt(0).toUpperCase())}</div>`}
        <div class="flex-1 min-w-0">
          <div class="text-xs text-primary/40 font-semibold uppercase tracking-wider mb-1">Selamat datang</div>
          <div class="text-xl font-bold text-primary truncate">${escHtml(u.name)}</div>
          <div class="text-sm text-primary/50 truncate">${escHtml(u.email)}</div>
          <span class="inline-block mt-2 px-3 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
            ${isAdmin ? 'bg-yellow-100 text-yellow-700' : 'bg-primary/8 text-primary/60'}">
            ${isAdmin ? '👑 Admin' : '👤 User'}
          </span>
        </div>
      </div>

      <!-- Menu -->
      <div class="grid grid-cols-2 gap-4 mb-8">
        ${dashCard('/katalog',          'library',        'Katalog Kitab',     'Jelajahi koleksi',          false)}
        ${dashCard('/search',           'search',         'Cari Kitab',        'Cari judul & isi',          false)}
        ${dashCard('/my-activity',      'activity',       'Aktivitas Saya',    'Riwayat submit & request',  false)}
        ${dashCard('/notifications',    'bell',           'Notifikasi',        'Pesan dari admin',          false)}
        ${isAdmin ? dashCard('/admin/books',      'book',      'Kitab',             'Tambah / edit / hapus',        true)  : ''}
        ${isAdmin ? dashCard('/admin/categories', 'folder',    'Kategori',          'Atur kategori kitab',          true)  : ''}
        ${isAdmin ? dashCard('/admin/history',    'history',   'CRUD History',      'Jejak perubahan admin',        true)  : ''}
        ${isAdmin ? dashCard('/admin/activity',   'activity',  'Aktivitas',         'Kunjungan & login/logout',     true)  : ''}
        ${isAdmin ? dashCard('/admin/search-logs','search',    'Pencarian',         'Riwayat pencarian pengguna',   true)  : ''}
        ${isAdmin ? dashCard('/admin/download-logs','download','Download',          'Riwayat unduhan kitab',        true)  : ''}
        ${isAdmin ? dashCard('/admin/submissions','inbox',     'Review Kiriman',    'Approve kiriman pengguna',     true)  : ''}
        ${isAdmin ? dashCard('/admin/requests',   'help-circle','Request Kitab',    'Permohonan kitab baru',        true)  : ''}
        ${isAdmin ? dashCard('/admin/feedback',   'message-square','Review Feedback','Masukan dari pengguna',      true)  : ''}
      </div>

      <div class="text-center">
        <a href="/auth.php?action=logout"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-red-200 text-red-500 text-sm font-medium hover:bg-red-50 transition-colors">
          <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
        </a>
      </div>
    </div>
    ${window.mobileFeedbackBanner || ''}`;
  reicons();
}

function dashCard(route, icon, title, sub, admin) {
  return `
    <a href="${route}" data-route="${route}"
       class="book-card bg-white rounded-2xl shadow-card p-5 flex items-center gap-4 no-underline
         ${admin ? 'border-2 border-gold/25' : ''}">
      <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0
        ${admin ? 'bg-yellow-50' : 'bg-primary/8'}">
        <i data-lucide="${icon}" class="w-5 h-5 ${admin ? 'text-yellow-700' : 'text-primary'}"></i>
      </div>
      <div class="min-w-0">
        <div class="font-semibold text-sm ${admin ? 'text-yellow-800' : 'text-primary'} truncate">${title}</div>
        <div class="text-xs mt-0.5 ${admin ? 'text-yellow-600/70' : 'text-primary/50'}">${sub}</div>
      </div>
    </a>`;
}

export { renderDashboard };
