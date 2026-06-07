/* =============================================================
   Al-Maktabah As-Sunniyyah — Admin Panel (admin.js)
   Halaman: Dashboard · Kelola Kitab · Kelola Kategori · Kelola Isi Kitab · CRUD History
   ============================================================= */
'use strict';

// ── Tambah rute admin ke router SPA ──────────────────────────
Object.assign(window._adminRoutes = window._adminRoutes || {}, {
  '/dashboard':        renderDashboard,
  '/admin':            () => navigate('/admin/books', true),
  '/admin/books':      renderAdminBooks,
  '/admin/categories': renderAdminCategories,
  '/admin/content':    () => navigate('/admin/books', true),
  '/admin/history':     renderAdminHistory,
  '/admin/activity':    renderAdminActivity,
  '/admin/search-logs': renderAdminSearchLogs,
  '/admin/submissions': renderAdminSubmissions,
});

// Patch router setelah app.js selesai load
document.addEventListener('DOMContentLoaded', () => {
  // Merge admin routes ke routes object app.js
  if (typeof routes !== 'undefined') {
    Object.assign(routes, window._adminRoutes);
  }
  // Jika halaman saat ini adalah rute admin, render langsung
  const base = location.pathname.split('?')[0];
  if (window._adminRoutes[base]) navigate(location.pathname + location.search, false);
});

// ── Shared helpers ────────────────────────────────────────────

// Deteksi RTL (Arab/Ibrani/dll) berdasarkan karakter pertama yang kuat
const _RTL_RE = /[֑-߿יִ-﷽ﹰ-ﻼ]/;
function autoDir(el, text) {
  if (!el) return;
  const val = text !== undefined ? text : el.value;
  el.dir = _RTL_RE.test(val.trim()) ? 'rtl' : 'ltr';
}
// Pasang listener "input" agar arah berubah saat user mengetik
function bindAutoDir(el) {
  if (!el) return;
  autoDir(el);
  el.addEventListener('input', () => autoDir(el));
}


function adminGuard() {
  const u = window.SESSION_USER;
  if (!u) { window.location.href = '/auth.php?action=login'; return false; }
  if (u.role !== 'admin') {
    app().innerHTML = `
      <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4 gap-4">
        <i data-lucide="shield-alert" class="w-16 h-16 text-red-300"></i>
        <h1 class="text-xl font-bold text-primary">Akses Ditolak</h1>
        <p class="text-primary/50 text-sm">Halaman ini hanya untuk admin.</p>
        <a href="/" data-route="/" class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-medium">Kembali</a>
      </div>`;
    reicons(); return false;
  }
  return true;
}

async function adminPost(action, body) {
  const res = await fetch(`/api.php?action=${action}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await res.json();
  if (!res.ok) {
    const error = new Error(data.error || `Request failed with status ${res.status}`);
    error.status = res.status;
    throw error;
  }
  return data;
}

function adminToast(msg, type = 'success') {
  let t = document.getElementById('admin-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'admin-toast';
    Object.assign(t.style, {
      position: 'fixed', bottom: '84px', left: '50%',
      transform: 'translateX(-50%)', zIndex: '9999',
      transition: 'opacity .3s ease', pointerEvents: 'none',
    });
    document.body.appendChild(t);
  }
  const bg = type === 'success' ? '#1a3a2a' : '#dc2626';
  t.innerHTML = `<div style="display:flex;align-items:center;gap:10px;padding:12px 22px;border-radius:14px;font-size:13px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,.2);background:${bg};color:#fff;white-space:nowrap;">${type === 'success' ? '✓' : '✕'} ${escHtml(msg)}</div>`;
  t.style.opacity = '1';
  clearTimeout(t._tmr);
  t._tmr = setTimeout(() => { t.style.opacity = '0'; }, 3200);
}

function adminSpinner() {
  return `<div class="p-10 flex justify-center"><div class="w-8 h-8 border-[3px] border-gold/20 border-t-gold rounded-full animate-spin"></div></div>`;
}

function adminNavBar(active) {
  const u = window.SESSION_USER;
  const isAdmin = u && u.role === 'admin';
  const tabs = [
    { r: '/dashboard',        icon: 'layout-dashboard', label: 'Dashboard',       adminOnly: false },
    { r: '/admin/books',      icon: 'book',             label: 'Kelola Kitab',    adminOnly: false },
    { r: '/admin/categories', icon: 'folder',           label: 'Kelola Kategori', adminOnly: false },
    { r: '/admin/history',     icon: 'history',     label: 'CRUD History',    adminOnly: true,  desktopOnly: true },
    { r: '/admin/activity',    icon: 'activity',    label: 'Log Aktivitas',   adminOnly: true },
    { r: '/admin/search-logs', icon: 'search',      label: 'Log Pencarian',   adminOnly: true },
    { r: '/admin/submissions', icon: 'inbox',       label: 'Review Kiriman',  adminOnly: true },
  ];
  return `
    <div class="bg-white border-b border-gold/15 sticky top-16 z-40 shadow-sm">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center overflow-x-auto no-scrollbar">
        ${tabs.filter(t => (!t.adminOnly || isAdmin)).map(t => {
          const on = active === t.r;
          const deskOnly = t.desktopOnly ? 'hidden md:flex' : 'flex';
          return `<a href="${t.r}" data-route="${t.r}"
            class="${deskOnly} items-center gap-2 px-4 sm:px-5 py-3.5 text-sm font-semibold whitespace-nowrap border-b-2 transition-colors no-underline shrink-0
              ${on ? 'border-gold text-primary' : 'border-transparent text-primary/45 hover:text-primary hover:border-gold/30'}">
            <i data-lucide="${t.icon}" class="w-4 h-4"></i>${t.label}
          </a>`;
        }).join('')}
      </div>
    </div>`;
}

// ══════════════════════════════════════════════════════════════
//  DASHBOARD
// ══════════════════════════════════════════════════════════════
function renderDashboard() {
  const u = window.SESSION_USER;
  if (!u) { window.location.href = '/auth.php?action=login'; return; }
  const isAdmin = u.role === 'admin';

  app().innerHTML = `
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
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
        ${isAdmin ? dashCard('/admin/books',      'book',      'Kelola Kitab',      'Tambah / edit / hapus',        true)  : ''}
        ${isAdmin ? dashCard('/admin/categories', 'folder',    'Kelola Kategori',   'Atur kategori kitab',          true)  : ''}
        ${isAdmin ? dashCard('/admin/history',    'history',   'CRUD History',      'Jejak perubahan admin',        true)  : ''}
        ${isAdmin ? dashCard('/admin/search-logs','search',    'Log Pencarian',     'Riwayat pencarian pengguna',    true)  : ''}
        ${isAdmin ? dashCard('/admin/activity',   'activity',  'Log Aktivitas',     'Kunjungan & login/logout',     true)  : ''}
        ${isAdmin ? dashCard('/admin/submissions','inbox',    'Review Kiriman',    'Approve kiriman pengguna',     true)  : ''}
      </div>

      <div class="text-center">
        <a href="/auth.php?action=logout"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-red-200 text-red-500 text-sm font-medium hover:bg-red-50 transition-colors">
          <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
        </a>
      </div>
    </div>`;
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

// ══════════════════════════════════════════════════════════════
//  KELOLA KITAB  /admin/books
// ══════════════════════════════════════════════════════════════

// ══════════════════════════════════════════════════════════════
//  KELOLA KITAB + ISI KITAB  /admin/books
// ══════════════════════════════════════════════════════════════

const booksAS = { page: 1, q: '', cat: '', total: 0 };
const _booksMap = new Map();
const contAS   = { bkid: null, page: 1, q: '' };
let _ctrlS = null;

// Aktif view: 'books' | 'content'
let _booksActiveView = 'books';

async function renderAdminBooks() {
  if (!adminGuard()) return;
  _booksActiveView = contAS.bkid ? 'content' : 'books';

  app().innerHTML = adminNavBar('/admin/books') + `
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

      <!-- ── VIEW: DAFTAR KITAB ─────────────────────────────── -->
      <div id="view-books">

        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-xl font-bold text-primary flex items-center gap-2">
              <i data-lucide="book" class="w-5 h-5 text-gold"></i> Kelola Kitab
            </h1>
            <p class="text-primary/40 text-xs mt-1">Tambah, edit, hapus, dan kelola isi kitab</p>
          </div>
          <div class="flex items-center gap-2">
            <button onclick="openImportWordModal()"
              class="flex items-center gap-2 px-4 py-2.5 border border-primary text-primary rounded-xl text-sm font-semibold hover:bg-cream-dark transition-colors">
              <i data-lucide="file-up" class="w-4 h-4"></i> Import Word
            </button>
            <button onclick="openBookModal(null)"
              class="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm">
              <i data-lucide="plus" class="w-4 h-4"></i> Tambah Kitab
            </button>
          </div>
        </div>

        <!-- Filter bar -->
        <div class="bg-white rounded-2xl shadow-card p-4 mb-5 flex flex-col sm:flex-row gap-3">
          <div class="flex-1 relative">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-primary/30"></i>
            <input id="bks-q" type="text" value="${escHtml(booksAS.q)}" placeholder="Cari judul atau pengarang…" dir="auto"
              class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15 bg-cream/50" />
          </div>
          <select id="bks-cat"
            class="w-full sm:w-52 px-3 py-2.5 rounded-xl border border-gold/25 text-sm bg-cream/50 focus:outline-none focus:border-gold">
            <option value="">Semua Kategori</option>
          </select>
          <button onclick="bksSearch()"
            class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
            Cari
          </button>
        </div>

        <!-- Table card -->
        <div id="bks-wrap" class="bg-white rounded-2xl shadow-card overflow-hidden">
          ${adminSpinner()}
        </div>

      </div>

      <!-- ── VIEW: EDITOR ISI KITAB ─────────────────────────── -->
      <div id="view-content" class="hidden">
        <div id="cont-body"></div>
      </div>

    </div>

    ${bookModalHtml()}`;

  reicons();

  // Load kategori untuk filter + modal
  try {
    const r = await apiFetch({ action: 'categories' });
    const sel = document.getElementById('bks-cat');
    (r.data || []).forEach(c => {
      const o = document.createElement('option');
      o.value = c.id; o.textContent = `${c.name} (${c.book_count})`;
      if (String(c.id) === String(booksAS.cat)) o.selected = true;
      sel.appendChild(o);
    });
    const ms = document.getElementById('bm-cat');
    if (ms) (r.data || []).forEach(c => {
      const o = document.createElement('option');
      o.value = c.id; o.textContent = c.name; ms.appendChild(o);
    });
  } catch {}

  document.getElementById('bks-q')?.addEventListener('keydown', e => { if (e.key === 'Enter') bksSearch(); });
  document.getElementById('bk-form')?.addEventListener('submit', async e => { e.preventDefault(); await bkSubmit(); });
  // Auto RTL/LTR untuk semua input teks di modal dan search bar
  bindAutoDir(document.getElementById('bks-q'));
  bindAutoDir(document.getElementById('bm-title'));
  bindAutoDir(document.getElementById('bm-author'));

  await bksLoad();

  // Jika ada bkid aktif, langsung buka editor
  if (contAS.bkid) _switchToContent();
}

/* ── Ganti view ──────────────────────────────────────────── */
function _switchToContent() {
  document.getElementById('view-books')?.classList.add('hidden');
  document.getElementById('view-content')?.classList.remove('hidden');
  _booksActiveView = 'content';
}

function _switchToBooks() {
  document.getElementById('view-content')?.classList.add('hidden');
  document.getElementById('view-books')?.classList.remove('hidden');
  _booksActiveView = 'books';
}

/* ── Search & pagination ─────────────────────────────────── */
window.bksSearch = function() {
  booksAS.q   = document.getElementById('bks-q')?.value.trim() || '';
  booksAS.cat = document.getElementById('bks-cat')?.value || '';
  booksAS.page = 1;
  bksLoad();
};

window.bksPage = function(p) { booksAS.page = p; bksLoad(); window.scrollTo({top:0}); };

async function bksLoad() {
  const wrap = document.getElementById('bks-wrap');
  if (!wrap) return;
  wrap.innerHTML = adminSpinner();

  try {
    let res;
    if (booksAS.q) {
      res = await apiFetch({ action: 'search_books', q: booksAS.q, page: booksAS.page });
    } else {
      const p = { action: 'books', limit: 20, page: booksAS.page };
      if (booksAS.cat) p.cat = booksAS.cat;
      res = await apiFetch(p);
    }
    booksAS.total = res.total || 0;
    _booksMap.clear(); (res.data||[]).forEach(b => _booksMap.set(b.bkid, b));
    const books = res.data || [];

    wrap.innerHTML = `
      <div class="px-5 py-3 border-b border-cream-dark bg-cream/40 flex items-center justify-between">
        <span class="text-xs text-primary/50 font-medium">${booksAS.total.toLocaleString('id')} kitab${booksAS.q ? ` — hasil: "${escHtml(booksAS.q)}"` : ''}</span>
      </div>
      ${books.length === 0
        ? `<div class="py-16 text-center"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 text-primary/15"></i><p class="text-primary/30 text-sm">Tidak ada kitab ditemukan.</p></div>`
        : `<div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-cream/50 text-primary/45 text-xs border-b border-cream-dark">
                  <th class="px-4 py-3 text-left font-semibold w-14">ID</th>
                  <th class="px-4 py-3 text-left font-semibold">Judul</th>
                  <th class="px-4 py-3 text-left font-semibold hidden sm:table-cell">Pengarang</th>
                  <th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Kategori</th>
                  <th class="px-4 py-3 text-center font-semibold w-20 hidden lg:table-cell">Hal.</th>
                  <th class="px-4 py-3 text-center font-semibold w-32">Aksi</th>
                </tr>
              </thead>
              <tbody>
                ${books.map(b => `
                  <tr class="border-b border-cream-dark/50 hover:bg-cream/40 transition-colors">
                    <td class="px-4 py-3 text-primary/30 font-mono text-xs">#${b.bkid}</td>
                    <td class="px-4 py-3 max-w-[200px]">
                      <div class="arabic font-semibold text-primary text-sm leading-snug line-clamp-2">${escHtml(b.title)}</div>
                    </td>
                    <td class="px-4 py-3 text-primary/50 text-xs hidden sm:table-cell max-w-[140px]">
                      <div class="truncate">${escHtml(b.author||'—')}</div>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell">
                      ${b.category_name
                        ? `<span class="px-2.5 py-0.5 bg-primary/7 text-primary/55 rounded-full text-xs">${escHtml(b.category_name)}</span>`
                        : `<span class="text-primary/20 text-xs">—</span>`}
                    </td>
                    <td class="px-4 py-3 text-center text-primary/40 text-xs hidden lg:table-cell">${b.pages||0}</td>
                    <td class="px-4 py-3">
                      <div class="flex items-center justify-center gap-1">
                        <button title="Edit Kitab" onclick="openBookModal(${b.bkid})"
                          class="p-2 rounded-lg hover:bg-primary/8 text-primary/40 hover:text-primary transition-colors">
                          <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                        <button title="Kelola Isi" onclick="gotoContent(${b.bkid})"
                          class="p-2 rounded-lg hover:bg-yellow-50 text-yellow-500/60 hover:text-yellow-700 transition-colors">
                          <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        </button>
                        <button title="Hapus" onclick="bkDelete(${b.bkid},\`${b.title.replace(/`/g,'\\`')}\`)"
                          class="p-2 rounded-lg hover:bg-red-50 text-red-300 hover:text-red-600 transition-colors">
                          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                      </div>
                    </td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>
          ${paginationHtml(res.page||1, res.total_pages||1, 'bksPage')}`}`;

    reicons();
  } catch(e) {
    if (handleAuthError(e)) return;
    wrap.innerHTML = `<div class="p-6 text-red-500 text-sm">Gagal memuat: ${escHtml(e.message)}</div>`;
  }
}

/* ── Book modal HTML ─────────────────────────────────────── */
function bookModalHtml() {
  return `
    <div id="bk-modal" class="fixed inset-0 z-[300] hidden" style="background:rgba(15,34,24,.6);backdrop-filter:blur(5px);">
      <div class="flex items-center justify-center min-h-full p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] overflow-y-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b border-cream-dark">
            <h2 id="bk-modal-ttl" class="font-bold text-primary">Tambah Kitab</h2>
            <button onclick="closeBookModal()" class="p-2 rounded-lg hover:bg-cream-dark text-primary/40 transition-colors">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>
          <form id="bk-form" class="p-6 space-y-4">
            <input type="hidden" id="bm-bkid" />
            <div>
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Judul Kitab <span class="text-red-400">*</span></label>
              <input id="bm-title" type="text" placeholder="عنوان الكتاب" dir="auto"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15 font-arabic" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Pengarang</label>
              <input id="bm-author" type="text" placeholder="اسم المؤلف" dir="auto"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-primary/55 mb-1.5">Kategori</label>
                <select id="bm-cat" class="w-full px-3 py-2.5 rounded-xl border border-gold/30 text-sm bg-white focus:outline-none focus:border-gold">
                  <option value="">— Pilih —</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-primary/55 mb-1.5">Bahasa</label>
                <select id="bm-iso" class="w-full px-3 py-2.5 rounded-xl border border-gold/30 text-sm bg-white focus:outline-none focus:border-gold">
                  <option value="ar">Arab (ar)</option>
                  <option value="id">Indonesia (id)</option>
                  <option value="en">English (en)</option>
                </select>
              </div>
            </div>
            <div id="bm-msg" class="hidden text-sm rounded-xl px-4 py-2.5"></div>
            <div class="flex gap-3 pt-2">
              <button type="submit" id="bk-submit-btn"
                class="flex-1 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan
              </button>
              <button type="button" onclick="closeBookModal()"
                class="px-5 py-2.5 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">
                Batal
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ IMPORT WORD MODAL ══════════════════════════════ -->
    <div id="import-modal" class="fixed inset-0 z-[400] hidden" style="background:rgba(15,34,24,.65);backdrop-filter:blur(6px);">
      <div class="flex items-center justify-center min-h-full p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">

          <!-- Modal header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-cream-dark shrink-0">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center">
                <i data-lucide="file-up" class="w-4 h-4 text-gold"></i>
              </div>
              <div>
                <h2 id="import-modal-ttl" class="font-bold text-primary text-sm">Import Kitab dari Word</h2>
                <p class="text-xs text-primary/40">Format .docx — paginasi otomatis</p>
              </div>
            </div>
            <button onclick="closeImportModal()" class="p-2 rounded-lg hover:bg-cream-dark text-primary/40 transition-colors">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>

          <!-- Step indicator -->
          <div class="px-6 pt-4 shrink-0">
            <div class="flex items-center gap-2 text-xs mb-4">
              <div id="imp-step1-dot" class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs shrink-0">1</div>
              <span id="imp-step1-lbl" class="font-semibold text-primary">Upload & Metadata</span>
              <div class="flex-1 h-px bg-cream-dark mx-2"></div>
              <div id="imp-step2-dot" class="w-6 h-6 rounded-full bg-cream-dark text-primary/30 flex items-center justify-center font-bold text-xs shrink-0">2</div>
              <span id="imp-step2-lbl" class="text-primary/30">Review & Konfirmasi</span>
            </div>
          </div>

          <!-- Body -->
          <div id="import-body" class="overflow-y-auto flex-1 px-6 pb-6"></div>

        </div>
      </div>
    </div>`;
}

/* ── openBookModal ───────────────────────────────────────── */
// Jika dipanggil dengan ID (angka), fetch data segar dari API
// Jika dipanggil dengan null, buka form kosong (Tambah)
window.openBookModal = async function(bookOrId) {
  const modal = document.getElementById('bk-modal');
  const form  = document.getElementById('bk-form');
  const btn   = document.getElementById('bk-submit-btn');
  const msg   = document.getElementById('bm-msg');

  // Tampilkan modal segera dengan spinner agar tidak terasa lag
  if (form) form.reset();
  document.getElementById('bm-bkid').value = '';
  if (msg) msg.className = 'hidden text-sm rounded-xl px-4 py-2.5';
  modal.classList.remove('hidden');

  const isEdit = bookOrId !== null && bookOrId !== undefined;

  if (isEdit) {
    // Tunjukkan loading sementara fetch
    document.getElementById('bk-modal-ttl').textContent = 'Memuat data…';
    if (btn) btn.disabled = true;

    let book = null;
    try {
      // Fetch langsung dari API — tidak bergantung pada cache/map
      const res = await apiFetch({ action: 'book', id: +bookOrId });
      book = res.data || null;
    } catch(e) {
      if (handleAuthError(e)) return;
      document.getElementById('bk-modal-ttl').textContent = 'Gagal memuat data';
      if (btn) btn.disabled = false;
      return;
    }

    if (!book) {
      document.getElementById('bk-modal-ttl').textContent = 'Kitab tidak ditemukan';
      if (btn) btn.disabled = false;
      return;
    }

    document.getElementById('bk-modal-ttl').textContent = 'Edit Kitab';
    document.getElementById('bm-bkid').value   = book.bkid   ?? '';
    const titleEl  = document.getElementById('bm-title');
    const authorEl = document.getElementById('bm-author');
    if (titleEl)  { titleEl.value  = book.title  ?? ''; autoDir(titleEl); }
    if (authorEl) { authorEl.value = book.author ?? ''; autoDir(authorEl); }
    const isoEl = document.getElementById('bm-iso');
    if (isoEl) isoEl.value = book.iso || 'ar';
    const catEl = document.getElementById('bm-cat');
    if (catEl) catEl.value = book.category_id ? String(book.category_id) : '';
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan';
    }
  } else {
    document.getElementById('bk-modal-ttl').textContent = 'Tambah Kitab Baru';
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="plus" class="w-4 h-4"></i> Tambah Kitab';
    }
  }

  setTimeout(() => document.getElementById('bm-title')?.focus(), 60);
  reicons();
};

// ══════════════════════════════════════════════════════════════
//  IMPORT KITAB DARI WORD (.docx)
// ══════════════════════════════════════════════════════════════

const _imp = { cats: [], pages: [], currentPage: 0 };

/* Lazy-load mammoth.js (docx parser) */
function _loadMammoth() {
  return new Promise((res, rej) => {
    if (window.mammoth) { res(); return; }
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js';
    s.onload = res; s.onerror = rej;
    document.head.appendChild(s);
  });
}

/* Paginasi — gabungkan paragraf sampai ~350 kata lalu potong */
function _paginate(paragraphs, wordsPerPage = 350) {
  const pages = [];
  let buf = [], wc = 0;
  for (const para of paragraphs) {
    const words = para.trim().split(/\s+/).filter(Boolean).length;
    if (wc > 0 && wc + words > wordsPerPage) {
      pages.push(buf.join('\n\n'));
      buf = []; wc = 0;
    }
    buf.push(para.trim());
    wc += words;
  }
  if (buf.length) pages.push(buf.join('\n\n'));
  return pages;
}

window.openImportWordModal = async function() {
  try { await _loadMammoth(); } catch {
    adminToast('Gagal memuat parser Word. Periksa koneksi internet.', 'error'); return;
  }
  _imp.pages = []; _imp.currentPage = 0;
  _setImportStep(1);
  document.getElementById('import-modal').classList.remove('hidden');
  _renderImportStep1();
  reicons();
};

window.closeImportModal = function() {
  document.getElementById('import-modal').classList.add('hidden');
};

function _setImportStep(step) {
  const d1 = document.getElementById('imp-step1-dot');
  const d2 = document.getElementById('imp-step2-dot');
  const l1 = document.getElementById('imp-step1-lbl');
  const l2 = document.getElementById('imp-step2-lbl');
  if (!d1) return;
  if (step === 1) {
    d1.className = 'w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs shrink-0';
    l1.className = 'font-semibold text-primary';
    d2.className = 'w-6 h-6 rounded-full bg-cream-dark text-primary/30 flex items-center justify-center font-bold text-xs shrink-0';
    l2.className = 'text-primary/30';
  } else {
    d1.className = 'w-6 h-6 rounded-full bg-gold/30 text-gold flex items-center justify-center font-bold text-xs shrink-0';
    l1.className = 'text-primary/40';
    d2.className = 'w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs shrink-0';
    l2.className = 'font-semibold text-primary';
  }
}

async function _renderImportStep1() {
  // Load categories jika belum
  if (!_imp.cats.length) {
    try {
      const r = await apiFetch({ action: 'categories' });
      _imp.cats = r.data || [];
    } catch {}
  }

  const catOptions = _imp.cats.map(c =>
    `<option value="${c.id}">${escHtml(c.name)}</option>`
  ).join('');

  document.getElementById('import-body').innerHTML = `
    <div class="space-y-4 pt-2">

      <!-- File upload -->
      <div>
        <label class="block text-xs font-semibold text-primary/55 mb-1.5">
          File Word <span class="text-red-400">*</span>
          <span class="font-normal text-primary/30 ml-1">(.docx)</span>
        </label>
        <label id="imp-file-label"
          class="flex items-center justify-center gap-3 w-full px-4 py-8 rounded-xl border-2 border-dashed border-gold/30 cursor-pointer hover:border-gold/60 hover:bg-cream/40 transition-all">
          <i data-lucide="file-up" class="w-8 h-8 text-gold/50"></i>
          <div class="text-center">
            <div class="text-sm font-medium text-primary/60">Klik untuk pilih file .docx</div>
            <div class="text-xs text-primary/30 mt-1">atau seret & lepas di sini</div>
          </div>
          <input id="imp-file" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            class="hidden" onchange="window._impFileChosen(this)" />
        </label>
        <div id="imp-file-name" class="mt-2 text-xs text-primary/40 hidden"></div>
      </div>

      <!-- Metadata -->
      <div>
        <label class="block text-xs font-semibold text-primary/55 mb-1.5">Judul Kitab <span class="text-red-400">*</span></label>
        <input id="imp-title" type="text" dir="auto" placeholder="عنوان الكتاب"
          class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-primary/55 mb-1.5">Pengarang</label>
        <input id="imp-author" type="text" dir="auto" placeholder="اسم المؤلف"
          class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-primary/55 mb-1.5">Kategori</label>
          <select id="imp-cat" class="w-full px-3 py-2.5 rounded-xl border border-gold/30 text-sm bg-white focus:outline-none focus:border-gold">
            <option value="">— Pilih —</option>${catOptions}
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-primary/55 mb-1.5">Bahasa</label>
          <select id="imp-iso" class="w-full px-3 py-2.5 rounded-xl border border-gold/30 text-sm bg-white focus:outline-none focus:border-gold">
            <option value="ar">Arab (ar)</option>
            <option value="id">Indonesia (id)</option>
            <option value="en">English (en)</option>
          </select>
        </div>
      </div>

      <!-- Words per page -->
      <div>
        <label class="flex items-center justify-between text-xs font-semibold text-primary/55 mb-1.5">
          <span>Kata per Halaman</span>
          <span id="imp-wpp-lbl" class="text-gold font-bold">350 kata</span>
        </label>
        <input type="range" class="font-range" id="imp-wpp" min="150" max="700" step="50" value="350"
          oninput="document.getElementById('imp-wpp-lbl').textContent=this.value+' kata'">
        <div class="flex justify-between text-xs text-primary/25 mt-1">
          <span>150 (padat)</span><span>350 (standar)</span><span>700 (lebar)</span>
        </div>
      </div>

      <div id="imp-step1-msg" class="hidden text-sm rounded-xl px-4 py-2.5"></div>

      <button onclick="window._impProcess()"
        class="w-full py-3 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors flex items-center justify-center gap-2">
        <i data-lucide="scan-text" class="w-4 h-4"></i> Proses & Pratinjau
      </button>
    </div>`;
  reicons();

  // Bind autoDir
  bindAutoDir(document.getElementById('imp-title'));
  bindAutoDir(document.getElementById('imp-author'));
}

window._impFileChosen = function(input) {
  const file = input.files[0];
  if (!file) return;
  const lbl  = document.getElementById('imp-file-name');
  const wrap = document.getElementById('imp-file-label');
  lbl.textContent = `📄 ${file.name} (${(file.size/1024).toFixed(1)} KB)`;
  lbl.classList.remove('hidden');
  wrap.classList.add('border-gold', 'bg-gold/5');
  // Auto-fill title dari nama file
  const titleEl = document.getElementById('imp-title');
  if (titleEl && !titleEl.value) {
    titleEl.value = file.name.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
    autoDir(titleEl);
  }
};

window._impProcess = async function() {
  const fileInput = document.getElementById('imp-file');
  const title     = document.getElementById('imp-title')?.value.trim();
  const msg       = document.getElementById('imp-step1-msg');
  const wpp       = parseInt(document.getElementById('imp-wpp')?.value || '350');

  const showErr = t => {
    msg.textContent = t;
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
  };

  if (!fileInput?.files[0]) { showErr('Pilih file .docx terlebih dahulu.'); return; }
  if (!title) { showErr('Judul kitab wajib diisi.'); return; }

  const btn = document.querySelector('button[onclick="window._impProcess()"]');
  if (btn) { btn.disabled = true; btn.innerHTML = '<div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div> Memproses…'; }

  try {
    const arrayBuffer = await fileInput.files[0].arrayBuffer();
    const result = await mammoth.extractRawText({ arrayBuffer });
    const rawText = result.value || '';

    if (!rawText.trim()) { showErr('File kosong atau tidak dapat dibaca.'); return; }

    // Pisah per paragraf (pisah di newline ganda atau single)
    const paragraphs = rawText
      .split(/\n{2,}|\r\n{2,}/)
      .map(p => p.replace(/\r?\n/g, ' ').trim())
      .filter(p => p.length > 0);

    _imp.pages = _paginate(paragraphs, wpp);
    _imp.currentPage = 0;

    if (!_imp.pages.length) { showErr('Tidak ada konten yang dapat diekstrak.'); return; }

    // Simpan metadata
    _imp.title  = title;
    _imp.author = document.getElementById('imp-author')?.value.trim() || '';
    _imp.catId  = document.getElementById('imp-cat')?.value || '';
    _imp.iso    = document.getElementById('imp-iso')?.value || 'ar';

    _setImportStep(2);
    _renderImportStep2();

  } catch(e) {
    showErr('Gagal membaca file: ' + e.message);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="scan-text" class="w-4 h-4"></i> Proses & Pratinjau'; reicons(); }
  }
};

function _renderImportStep2() {
  const total = _imp.pages.length;
  const pg    = _imp.currentPage;
  const wordCount = _imp.pages[pg]?.split(/\s+/).filter(Boolean).length || 0;

  document.getElementById('import-body').innerHTML = `
    <div class="space-y-4 pt-2">

      <!-- Summary bar -->
      <div class="bg-cream rounded-xl px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center shrink-0">
            <i data-lucide="file-check" class="w-4 h-4 text-gold"></i>
          </div>
          <div>
            <div class="text-sm font-bold text-primary">${escHtml(_imp.title)}</div>
            <div class="text-xs text-primary/50">${total} halaman terdeteksi · ${_imp.iso.toUpperCase()}</div>
          </div>
        </div>
        <button onclick="window._impBack()" class="text-xs text-primary/40 hover:text-primary transition-colors flex items-center gap-1">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Ubah
        </button>
      </div>

      <!-- Page navigator + preview -->
      <div class="grid grid-cols-[100px_1fr] gap-3" style="min-height:340px;">

        <!-- Sidebar halaman -->
        <div class="bg-white rounded-xl border border-cream-dark overflow-y-auto" style="max-height:420px;">
          <div class="px-3 py-2 bg-cream/50 border-b border-cream-dark text-xs font-semibold text-primary/40">Hal.</div>
          <div id="imp-page-nav">
            ${Array.from({length:total},(_,i)=>`
              <button onclick="window._impGoPage(${i})"
                class="w-full px-3 py-2 text-left text-xs transition-colors flex items-center gap-1.5
                  ${i===pg ? 'bg-primary text-white font-bold' : 'hover:bg-cream/60 text-primary/60'}">
                <span class="font-mono ${i===pg?'text-white/60':'text-primary/25'}">${String(i+1).padStart(3,'0')}</span>
                Hal ${i+1}
              </button>`).join('')}
          </div>
        </div>

        <!-- Editor halaman -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-primary/50">
              Halaman <span class="text-primary font-bold">${pg+1}</span> dari ${total}
              <span class="text-primary/30 ml-2">(${wordCount} kata)</span>
            </span>
            <div class="flex items-center gap-1">
              <button onclick="window._impGoPage(${pg-1})" ${pg===0?'disabled':''} class="p-1.5 rounded-lg text-primary/40 hover:bg-cream-dark disabled:opacity-25 transition-colors">
                <i data-lucide="chevron-up" class="w-3.5 h-3.5"></i>
              </button>
              <button onclick="window._impGoPage(${pg+1})" ${pg>=total-1?'disabled':''} class="p-1.5 rounded-lg text-primary/40 hover:bg-cream-dark disabled:opacity-25 transition-colors">
                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
              </button>
            </div>
          </div>
          <textarea id="imp-page-text" dir="auto" rows="14"
            class="w-full px-4 py-3 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15 resize-y leading-relaxed font-arabic"
            style="min-height:260px;">${escHtml(_imp.pages[pg]||'')}</textarea>
          <div class="flex justify-end">
            <button onclick="window._impSavePage()"
              class="px-4 py-1.5 rounded-xl border border-gold/30 text-xs text-primary/60 hover:bg-cream-dark transition-colors flex items-center gap-1">
              <i data-lucide="check" class="w-3 h-3 text-gold"></i> Simpan edit halaman ini
            </button>
          </div>
        </div>
      </div>

      <!-- Progress import -->
      <div id="imp-progress-wrap" class="hidden">
        <div class="flex items-center justify-between text-xs text-primary/50 mb-1.5">
          <span>Mengimpor…</span>
          <span id="imp-progress-lbl">0 / ${total}</span>
        </div>
        <div class="w-full bg-cream-dark rounded-full h-2">
          <div id="imp-progress-bar" class="bg-gold h-2 rounded-full transition-all" style="width:0%"></div>
        </div>
      </div>

      <div id="imp-step2-msg" class="hidden text-sm rounded-xl px-4 py-2.5"></div>

      <!-- Actions -->
      <div class="flex gap-3 pt-1">
        <button onclick="window._impConfirm()"
          class="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors flex items-center justify-center gap-2">
          <i data-lucide="upload-cloud" class="w-4 h-4"></i> Konfirmasi & Import (${total} halaman)
        </button>
        <button onclick="window._impBack()"
          class="px-5 py-3 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">
          ← Kembali
        </button>
      </div>
    </div>`;

  reicons();

  // Auto-dir textarea
  const ta = document.getElementById('imp-page-text');
  if (ta) { autoDir(ta); bindAutoDir(ta); }
}

window._impGoPage = function(i) {
  // Simpan edit halaman saat ini dulu
  const ta = document.getElementById('imp-page-text');
  if (ta) _imp.pages[_imp.currentPage] = ta.value;
  _imp.currentPage = Math.max(0, Math.min(_imp.pages.length-1, i));
  _renderImportStep2();
};

window._impSavePage = function() {
  const ta = document.getElementById('imp-page-text');
  if (ta) _imp.pages[_imp.currentPage] = ta.value;
  adminToast(`Halaman ${_imp.currentPage+1} disimpan ✓`);
};

window._impBack = function() {
  // Simpan edit halaman terakhir
  const ta = document.getElementById('imp-page-text');
  if (ta) _imp.pages[_imp.currentPage] = ta.value;
  _setImportStep(1);
  _renderImportStep1();
  // Restore data
  setTimeout(() => {
    const t = document.getElementById('imp-title');
    const a = document.getElementById('imp-author');
    const c = document.getElementById('imp-cat');
    const s = document.getElementById('imp-iso');
    if (t) { t.value = _imp.title || ''; autoDir(t); }
    if (a) { a.value = _imp.author || ''; autoDir(a); }
    if (c) c.value = _imp.catId || '';
    if (s) s.value = _imp.iso || 'ar';
  }, 50);
};

window._impConfirm = async function() {
  // Simpan edit halaman terakhir
  const ta = document.getElementById('imp-page-text');
  if (ta) _imp.pages[_imp.currentPage] = ta.value;

  const total   = _imp.pages.length;
  const msg     = document.getElementById('imp-step2-msg');
  const progWrap = document.getElementById('imp-progress-wrap');
  const progBar  = document.getElementById('imp-progress-bar');
  const progLbl  = document.getElementById('imp-progress-lbl');
  const btn     = document.querySelector('button[onclick="window._impConfirm()"]');

  if (btn) btn.disabled = true;
  if (progWrap) progWrap.classList.remove('hidden');

  try {
    const res = await adminPost('admin_import_book', {
      title:       _imp.title,
      author:      _imp.author,
      category_id: _imp.catId ? +_imp.catId : 0,
      iso:         _imp.iso,
      pages:       _imp.pages,
    });

    if (!res.success) throw new Error(res.error || 'Import gagal.');

    // Animasi progress selesai
    if (progBar) progBar.style.width = '100%';
    if (progLbl) progLbl.textContent = `${total} / ${total}`;

    adminToast(`✅ ${total} halaman berhasil diimpor!`);
    closeImportModal();
    bksLoad(); // refresh tabel kitab

  } catch(e) {
    if (handleAuthError(e)) return;
    msg.textContent = 'Error: ' + e.message;
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
    if (btn) btn.disabled = false;
  }
};

window.closeBookModal = function() { document.getElementById('bk-modal')?.classList.add('hidden'); };

async function bkSubmit() {
  const bkid   = document.getElementById('bm-bkid').value;
  const title  = document.getElementById('bm-title').value.trim();
  const author = document.getElementById('bm-author').value.trim();
  const catId  = document.getElementById('bm-cat').value;
  const iso    = document.getElementById('bm-iso').value;
  const msg    = document.getElementById('bm-msg');

  if (!title) {
    msg.textContent = 'Judul tidak boleh kosong.';
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
    return;
  }
  const btn = document.getElementById('bk-submit-btn');
  btn.disabled = true; btn.textContent = 'Menyimpan…';

  try {
    const data = await adminPost('admin_save_book', {
      bkid: bkid ? +bkid : 0, title, author,
      category_id: catId ? +catId : 0, iso,
    });
    if (data.success) {
      closeBookModal();
      adminToast(bkid ? 'Kitab diperbarui ✓' : 'Kitab ditambahkan ✓');
      await bksLoad();
    } else {
      msg.textContent = data.error || 'Gagal.';
      msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
    }
  } catch(e) {
    if (handleAuthError(e)) return;
    msg.textContent = 'Error: ' + e.message;
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Simpan'; reicons();
  }
}

window.bkDelete = async function(bkid, title) {
  if (!confirm(`Hapus kitab:\n"${title}"\n\nSeluruh isi kitab juga akan dihapus. Yakin?`)) return;
  try {
    const data = await adminPost('admin_delete_book', { bkid });
    if (data.success) { adminToast('Kitab dihapus'); bksLoad(); }
    else adminToast(data.error || 'Gagal', 'error');
  } catch(e) {
    if (handleAuthError(e)) return;
    adminToast(e.message || 'Gagal', 'error');
  }
};

/* ── Buka editor isi kitab (inline, tanpa pindah halaman) ── */
window.gotoContent = async function(bkid) {
  contAS.bkid = bkid; contAS.page = 1;
  _switchToContent();
  await contLoadEditor();
};

/* ── Content editor ─────────────────────────────────────── */
async function contLoadEditor() {
  const body = document.getElementById('cont-body');
  if (!body) return;
  if (_ctrlS) { document.removeEventListener('keydown', _ctrlS); _ctrlS = null; }
  body.innerHTML = adminSpinner();

  try {
    const bkid = contAS.bkid;
    const pg   = contAS.page;
    const [bkRes, ctRes] = await Promise.all([
      apiFetch({ action: 'book', id: bkid }),
      apiFetch({ action: 'content', bkid, page: pg }),
    ]);
    const book    = bkRes.data;
    const total   = ctRes.total_pages || 0;
    const hasCont = ctRes.content !== null;
    const pageNum = ctRes.page_number ?? pg;

    body.innerHTML = `
      <!-- Header strip -->
      <div class="bg-white rounded-2xl shadow-card p-4 mb-5 flex items-center gap-3">
        <button onclick="contBack()"
          class="p-2 rounded-lg hover:bg-cream-dark text-primary/45 hover:text-primary transition-colors shrink-0"
          title="Kembali ke daftar kitab">
          <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </button>
        <div class="flex-1 min-w-0">
          <div class="arabic font-bold text-primary text-sm line-clamp-1">${escHtml(book.title)}</div>
          <div class="text-xs text-primary/35 mt-0.5">
            ${escHtml(book.author||'')}
            · <span id="cont-total-lbl">${total}</span> halaman tersimpan
          </div>
        </div>
        <button onclick="contAddPage()"
          class="flex items-center gap-1.5 px-4 py-2 bg-primary text-white rounded-xl text-xs font-semibold hover:bg-primary-light transition-colors shrink-0">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i> Halaman Baru
        </button>
      </div>

      <!-- Layout: sidebar + editor -->
      <div class="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-5">

        <!-- Sidebar: page list -->
        <div class="bg-white rounded-2xl shadow-card overflow-hidden hidden lg:flex flex-col">
          <div class="px-4 py-3 bg-cream/40 border-b border-cream-dark flex items-center justify-between">
            <span class="text-xs font-semibold text-primary/50">Halaman</span>
            <span class="text-xs text-primary/30">${total}</span>
          </div>
          <div id="cont-page-nav" class="overflow-y-auto flex-1" style="max-height:600px;">
            ${buildPageNav(total, pg)}
          </div>
        </div>

        <!-- Editor -->
        <div class="space-y-4">
          <div class="lg:hidden">${paginationHtml(pg, total, 'contGoPage')}</div>

          <div class="bg-white rounded-2xl shadow-card p-6">
            <div class="flex items-center justify-between mb-5">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white font-bold text-sm shrink-0">${pg}</div>
                <div>
                  <div class="font-bold text-primary text-sm">${hasCont ? `Halaman ke-${pg}` : 'Halaman Kosong'}</div>
                  <div class="text-xs text-primary/35">${hasCont ? 'Ctrl+S untuk simpan cepat' : 'Belum ada konten pada halaman ini'}</div>
                </div>
              </div>
              ${hasCont ? `
              <button onclick="contDeletePage(${bkid},${pageNum})"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-red-200 text-red-400 text-xs font-medium hover:bg-red-50 hover:text-red-600 transition-colors">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
              </button>` : ''}
            </div>

            <div class="flex items-center gap-4 mb-4">
              <div>
                <label class="block text-xs font-semibold text-primary/50 mb-1.5">No. Halaman DB</label>
                <input id="cont-pgnum" type="number" min="1" value="${pageNum}"
                  class="w-28 px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
              </div>
              <div class="text-xs text-primary/30 self-end pb-2">Navigasi: <strong>${pg}</strong> dari <strong>${total}</strong></div>
            </div>

            <div class="mb-5">
              <label class="block text-xs font-semibold text-primary/50 mb-1.5">Isi Konten</label>
              <textarea id="cont-text" rows="18" dir="auto"
                placeholder="Ketik atau tempel isi halaman kitab di sini…"
                class="w-full px-4 py-3 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15 resize-y leading-relaxed font-arabic"
                style="min-height:340px;">${hasCont ? escHtml(ctRes.content) : ''}</textarea>
              <div class="flex justify-between mt-1.5">
                <span id="cont-chars" class="text-xs text-primary/30">0 karakter</span>
                <span class="text-xs text-primary/25">Teks Arab otomatis RTL • Ctrl+S simpan</span>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <button onclick="contSave()"
                class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan
              </button>
              <span id="cont-save-lbl" class="text-xs text-primary/35 hidden"></span>
            </div>
          </div>

          <div class="hidden lg:block">${paginationHtml(pg, total, 'contGoPage')}</div>
        </div>
      </div>`;

    reicons();

    const ta = document.getElementById('cont-text');
    const cc = document.getElementById('cont-chars');
    if (ta && cc) {
      const upd = () => { cc.textContent = ta.value.length.toLocaleString('id') + ' karakter'; };
      ta.addEventListener('input', upd); upd();
    }
    // Auto RTL/LTR pada textarea editor
    if (ta) { autoDir(ta); bindAutoDir(ta); }

    _ctrlS = e => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); contSave(); }
    };
    document.addEventListener('keydown', _ctrlS);

  } catch(e) {
    body.innerHTML = `<div class="bg-white rounded-2xl shadow-card p-6 text-red-500 text-sm">Gagal: ${escHtml(e.message)}</div>`;
  }
}

function buildPageNav(total, current) {
  if (!total) return `<div class="p-4 text-center text-primary/25 text-xs">Belum ada halaman</div>`;
  const max = Math.min(total, 300);
  let html = '';
  for (let p = 1; p <= max; p++) {
    const on = p === current;
    html += `<button onclick="contGoPage(${p})"
      class="w-full px-4 py-2.5 text-left text-xs transition-colors flex items-center gap-2
        ${on ? 'bg-primary text-white font-bold' : 'hover:bg-cream/60 text-primary/65'}">
      <span class="font-mono w-7 shrink-0 ${on ? 'text-white/60' : 'text-primary/25'}">${String(p).padStart(3,'0')}</span>
      Halaman ${p}
    </button>`;
  }
  if (total > 300) html += `<div class="p-3 text-center text-xs text-primary/25">…${total-300} halaman lainnya</div>`;
  return html;
}

window.contGoPage = async function(p) {
  if (_ctrlS) { document.removeEventListener('keydown', _ctrlS); _ctrlS = null; }
  contAS.page = p;
  await contLoadEditor();
};

window.contBack = function() {
  if (_ctrlS) { document.removeEventListener('keydown', _ctrlS); _ctrlS = null; }
  contAS.bkid = null; contAS.page = 1;
  _switchToBooks();
};

window.contSave = async function() {
  const bkid    = contAS.bkid;
  const pageNum = +document.getElementById('cont-pgnum')?.value || contAS.page;
  const content = document.getElementById('cont-text')?.value ?? '';
  const lbl     = document.getElementById('cont-save-lbl');
  const btn     = document.querySelector('button[onclick="contSave()"]');

  if (btn) { btn.disabled = true; btn.innerHTML = '<div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div> Menyimpan…'; }

  try {
    const data = await adminPost('admin_save_content', { bkid, page: pageNum, content });
    if (data.success) {
      adminToast('Tersimpan ✓');
      if (lbl) { lbl.textContent = 'Tersimpan ✓'; lbl.className = 'text-xs text-green-600'; setTimeout(() => { if(lbl) lbl.className='text-xs text-primary/35 hidden'; }, 3000); }
    } else adminToast(data.error || 'Gagal simpan', 'error');
  } catch(e) { 
    if (handleAuthError(e)) return;
    adminToast('Error: ' + e.message, 'error'); 
  }
  finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Simpan'; reicons(); }
  }
};

window.contDeletePage = async function(bkid, page) {
  if (!confirm(`Hapus halaman ${page}? Konten akan hilang permanen.`)) return;
  if (_ctrlS) { document.removeEventListener('keydown', _ctrlS); _ctrlS = null; }
  try {
    const data = await adminPost('admin_delete_content', { bkid, page });
    if (data.success) {
      adminToast('Halaman dihapus');
      contAS.page = Math.max(1, contAS.page - 1);
      await contLoadEditor();
    } else adminToast(data.error || 'Gagal', 'error');
  } catch(e) {
    if (handleAuthError(e)) return;
    adminToast(e.message || 'Gagal', 'error');
  }
};

window.contAddPage = async function() {
  const bkid = contAS.bkid;
  let total = 0;
  try { const r = await apiFetch({ action: 'content', bkid, page: 1 }); total = r.total_pages || 0; } catch {}
  const newPg = total + 1;
  try {
    const data = await adminPost('admin_save_content', { bkid, page: newPg, content: '' });
    if (data.success) {
      adminToast(`Halaman ${newPg} dibuat`);
      if (_ctrlS) { document.removeEventListener('keydown', _ctrlS); _ctrlS = null; }
      contAS.page = newPg;
      await contLoadEditor();
    } else adminToast(data.error || 'Gagal buat halaman', 'error');
  } catch(e) {
    if (handleAuthError(e)) return;
    adminToast(e.message || 'Gagal buat halaman', 'error');
  }
};

// ══════════════════════════════════════════════════════════════
//  KELOLA KATEGORI  /admin/categories
// ══════════════════════════════════════════════════════════════

async function renderAdminCategories() {
  if (!adminGuard()) return;

  app().innerHTML = adminNavBar('/admin/categories') + `
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-xl font-bold text-primary flex items-center gap-2">
            <i data-lucide="folder" class="w-5 h-5 text-gold"></i> Kelola Kategori
          </h1>
          <p class="text-primary/40 text-xs mt-1">Tambah, edit, dan hapus kategori kitab</p>
        </div>
        <button onclick="openCatModal(null)"
          class="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm">
          <i data-lucide="plus" class="w-4 h-4"></i> Tambah Kategori
        </button>
      </div>
      <div id="cat-grid"></div>
    </div>
    ${catModalHtml()}`;

  reicons();
  document.getElementById('cat-form')?.addEventListener('submit', async e => { e.preventDefault(); await catSubmit(); });
  await loadCatGrid();
}

async function loadCatGrid() {
  const grid = document.getElementById('cat-grid');
  if (!grid) return;
  grid.innerHTML = adminSpinner();
  try {
    const res = await apiFetch({ action: 'categories' });
    const cats = res.data || [];
    if (!cats.length) {
      grid.innerHTML = `<div class="bg-white rounded-2xl shadow-card py-16 text-center text-primary/25 text-sm"><i data-lucide="folder-open" class="w-10 h-10 mx-auto mb-3 opacity-25"></i><p>Belum ada kategori.</p></div>`;
      reicons(); return;
    }
    grid.innerHTML = `
      <div class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="px-5 py-3.5 bg-cream/40 border-b border-cream-dark flex items-center justify-between">
          <span class="text-xs font-medium text-primary/50">${cats.length} kategori</span>
          <span class="text-xs text-primary/30">Urut berdasarkan catord ASC</span>
        </div>
        <div class="divide-y divide-cream-dark">
          ${cats.map(c => `
            <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-cream/40 transition-colors">
              <div class="w-9 h-9 rounded-xl bg-primary/7 flex items-center justify-center shrink-0">
                <i data-lucide="folder" class="w-4 h-4 text-primary/40"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-primary text-sm">${escHtml(c.name)}</div>
                <div class="text-xs text-primary/35 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                  <span class="flex items-center gap-1"><i data-lucide="book" class="w-3 h-3"></i>${c.book_count} kitab</span>
                  <span>Ord: ${c.catord}</span>
                  <span>Lvl: ${c.lvl}</span>
                </div>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <button onclick='openCatModal(${JSON.stringify(c).replace(/'/g,"\\'")})'
                  class="p-2 rounded-lg hover:bg-primary/8 text-primary/40 hover:text-primary transition-colors" title="Edit">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </button>
                <button onclick="catDelete(${c.id},'${escHtml(c.name).replace(/'/g,"\\'")}',${c.book_count})"
                  class="p-2 rounded-lg hover:bg-red-50 text-red-300 hover:text-red-600 transition-colors" title="Hapus">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </div>
            </div>`).join('')}
        </div>
      </div>`;
    reicons();
  } catch(e) {
    if (handleAuthError(e)) return;
    grid.innerHTML = `<div class="bg-white rounded-2xl shadow-card p-6 text-red-500 text-sm">${escHtml(e.message)}</div>`;
  }
}

function catModalHtml() {
  return `
    <div id="cat-modal" class="fixed inset-0 z-[300] hidden" style="background:rgba(15,34,24,.6);backdrop-filter:blur(5px);">
      <div class="flex items-center justify-center min-h-full p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b border-cream-dark">
            <h2 id="cat-modal-ttl" class="font-bold text-primary">Tambah Kategori</h2>
            <button onclick="closeCatModal()" class="p-2 rounded-lg hover:bg-cream-dark text-primary/40 transition-colors">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>
          <form id="cat-form" class="p-6 space-y-4">
            <input type="hidden" id="cm-id" />
            <div>
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Nama Kategori <span class="text-red-400">*</span></label>
              <input id="cm-name" type="text" placeholder="Nama kategori"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-primary/55 mb-1.5">Urutan tampil</label>
                <input id="cm-catord" type="number" value="0" min="0"
                  class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-primary/55 mb-1.5">Level (1–5)</label>
                <input id="cm-lvl" type="number" value="1" min="1" max="5"
                  class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
              </div>
            </div>
            <div id="cm-msg" class="hidden text-sm rounded-xl px-4 py-2.5"></div>
            <div class="flex gap-3 pt-2">
              <button type="submit" id="cat-submit-btn"
                class="flex-1 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan
              </button>
              <button type="button" onclick="closeCatModal()"
                class="px-5 py-2.5 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">
                Batal
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>`;
}

window.openCatModal = function(cat) {
  document.getElementById('cat-modal-ttl').textContent = cat ? 'Edit Kategori' : 'Tambah Kategori Baru';
  document.getElementById('cm-id').value     = cat?.id     || '';
  document.getElementById('cm-name').value   = cat?.name   || '';
  document.getElementById('cm-catord').value = cat?.catord ?? 0;
  document.getElementById('cm-lvl').value    = cat?.lvl    ?? 1;
  const msg = document.getElementById('cm-msg');
  if (msg) msg.className = 'hidden text-sm rounded-xl px-4 py-2.5';
  document.getElementById('cat-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('cm-name')?.focus(), 60);
  reicons();
};
window.closeCatModal = function() { document.getElementById('cat-modal')?.classList.add('hidden'); };

async function catSubmit() {
  const id     = document.getElementById('cm-id').value;
  const name   = document.getElementById('cm-name').value.trim();
  const catord = +document.getElementById('cm-catord').value || 0;
  const lvl    = +document.getElementById('cm-lvl').value    || 1;
  const msg    = document.getElementById('cm-msg');

  if (!name) {
    msg.textContent = 'Nama tidak boleh kosong.';
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
    return;
  }
  const btn = document.getElementById('cat-submit-btn');
  btn.disabled = true; btn.textContent = 'Menyimpan…';

  try {
    const data = await adminPost('admin_save_category', { id: id ? +id : 0, name, catord, lvl });
    if (data.success) {
      closeCatModal();
      adminToast(id ? 'Kategori diperbarui ✓' : 'Kategori ditambahkan ✓');
      await loadCatGrid();
    } else {
      msg.textContent = data.error || 'Gagal.';
      msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
    }
  } catch(e) {
    if (handleAuthError(e)) return;
    msg.textContent = 'Error: ' + e.message;
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Simpan'; reicons();
  }
}
window.catSubmit = catSubmit;

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
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
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

async function renderAdminActivity() {
  if (!adminGuard()) return;

  let _act = { page: 1, event: '', query: '', date: '' };

  app().innerHTML = adminNavBar('/admin/activity') + `
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
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

// ══════════════════════════════════════════════════════════════
//  LOG PENCARIAN
// ══════════════════════════════════════════════════════════════
async function renderAdminSearchLogs() {
  if (!adminGuard()) return;

  let _sl = { page: 1, type: '', query: '', date: '' };

  app().innerHTML = adminNavBar('/admin/search-logs') + `
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
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
                <button onclick="document.getElementById('slf-query').value=${JSON.stringify(q.query)};slLoad(1)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-cream hover:bg-cream-dark text-xs text-primary/70 font-medium transition-colors border border-gold/15">
                  <i data-lucide="search" class="w-3 h-3 text-gold/60"></i>
                  ${escHtml(q.query)}
                  <span class="text-primary/35">${q.cnt}×</span>
                </button>`).join('')}
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
              </tr>
            </thead>
            <tbody class="divide-y divide-gold/8">
              ${d.rows.map(r => {
                const ua = r.user_agent || '';
                const browserShort = ua.includes('Chrome') ? 'Chrome'
                  : ua.includes('Firefox') ? 'Firefox'
                  : ua.includes('Safari') ? 'Safari'
                  : ua.includes('Edge') ? 'Edge'
                  : ua.slice(0, 18) || '—';
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

  await slLoad(1);
}

// ══════════════════════════════════════════════════════════════
//  ADMIN: REVIEW KIRIMAN FILE
// ══════════════════════════════════════════════════════════════
async function renderAdminSubmissions() {
  app().innerHTML = adminNavBar('/admin/submissions') + `
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-bold text-primary flex items-center gap-2">
            <i data-lucide="inbox" class="w-5 h-5 text-gold"></i> Review Kiriman
          </h1>
          <p class="text-primary/45 text-xs mt-0.5">Approve atau tolak kiriman Hasil Bahsul Masail &amp; Kitab dari pengguna</p>
        </div>
        <div class="flex gap-2 flex-wrap" id="sub-tabs"></div>
      </div>
      <div class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div id="sub-table-wrap">
          <div class="py-16 text-center"><i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin text-gold/50"></i></div>
        </div>
        <div id="sub-pagination" class="px-4 py-3 border-t border-cream-dark flex items-center justify-between text-xs text-primary/40"></div>
      </div>
    </div>
    <div id="sub-modal-overlay" class="hidden fixed inset-0 z-50 bg-ink/40 backdrop-blur-sm flex items-end sm:items-center justify-center p-4">
      <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-6">
        <h3 id="sub-modal-title" class="font-bold text-primary mb-1 text-base"></h3>
        <p  id="sub-modal-meta"  class="text-xs text-primary/45 mb-4"></p>
        <label class="block text-xs font-semibold text-primary/55 mb-1.5">Catatan Review <span class="text-primary/30 font-normal">(opsional)</span></label>
        <textarea id="sub-modal-note" rows="3" placeholder="Tulis catatan untuk pengirim…"
          class="w-full px-3 py-2 rounded-xl border border-gold/30 bg-cream text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 resize-none mb-5"></textarea>
        <div class="flex gap-3">
          <button onclick="subReview('approve')" class="flex-1 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition-colors flex items-center justify-center gap-1.5">
            <i data-lucide="check-circle" class="w-4 h-4"></i> Setujui
          </button>
          <button onclick="subReview('reject')" class="flex-1 py-2.5 rounded-xl bg-red-500 text-white text-sm font-semibold hover:bg-red-600 transition-colors flex items-center justify-center gap-1.5">
            <i data-lucide="x-circle" class="w-4 h-4"></i> Tolak
          </button>
          <button onclick="closeSubModal()" class="px-4 py-2.5 rounded-xl border border-gold/30 text-sm text-primary/60 hover:bg-cream-dark transition-colors">Batal</button>
        </div>
      </div>
    </div>`;

  reicons();

  let _subStatus = '';
  let _subPage   = 1;
  let _subTarget = null;

  // Build status tabs
  const tabDefs = [
    { s: '',         label: 'Semua' },
    { s: 'pending',  label: 'Menunggu' },
    { s: 'approved', label: 'Disetujui' },
    { s: 'rejected', label: 'Ditolak' },
  ];
  const tabsEl = document.getElementById('sub-tabs');
  tabsEl.innerHTML = tabDefs.map(t =>
    `<button data-sub-tab="${t.s}" onclick="window.subSetStatus('${t.s}')"
      class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors
      ${t.s === '' ? 'bg-primary text-white border-primary' : 'bg-white text-primary/60 border-gold/30 hover:border-primary/30'}">
      ${t.label}</button>`
  ).join('');

  window.subSetStatus = function(s) {
    _subStatus = s;
    _subPage   = 1;
    tabsEl.querySelectorAll('[data-sub-tab]').forEach(btn => {
      const active = btn.dataset.subTab === s;
      btn.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors '
        + (active ? 'bg-primary text-white border-primary' : 'bg-white text-primary/60 border-gold/30 hover:border-primary/30');
    });
    subLoad();
  };

  window.openSubModal = function(id, name, type) {
    _subTarget = { id, name, type };
    document.getElementById('sub-modal-title').textContent = name;
    document.getElementById('sub-modal-meta').textContent  = type === 'bahsul_masail' ? 'Hasil Bahsul Masail' : 'File Kitab';
    document.getElementById('sub-modal-note').value = '';
    document.getElementById('sub-modal-overlay').classList.remove('hidden');
    reicons();
  };
  window.closeSubModal = function() {
    document.getElementById('sub-modal-overlay').classList.add('hidden');
    _subTarget = null;
  };
  window.subReview = async function(action) {
    if (!_subTarget) return;
    const note = document.getElementById('sub-modal-note').value.trim();
    try {
      const res = await apiFetch({ action: 'admin_review_submission', id: _subTarget.id, review_action: action, note });
      if (res.error) throw new Error(res.error);
      closeSubModal();
      adminToast(action === 'approve' ? 'Kiriman disetujui ✓' : 'Kiriman ditolak');
      subLoad();
    } catch(e) { 
      if (handleAuthError(e)) return;
      adminToast('Gagal: ' + e.message, 'error'); 
    }
  };

  const fmtSize = b => b < 1024 ? b + ' B' : b < 1048576 ? (b/1024).toFixed(1) + ' KB' : (b/1048576).toFixed(1) + ' MB';
  const statusBadge = s => {
    const cls = { pending: 'bg-yellow-100 text-yellow-700', approved: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-600' };
    const lbl = { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak' };
    return '<span class="px-2 py-0.5 rounded-full text-xs font-semibold ' + (cls[s]||'') + '">' + (lbl[s]||s) + '</span>';
  };

  async function subLoad() {
    const wrap = document.getElementById('sub-table-wrap');
    const pag  = document.getElementById('sub-pagination');
    wrap.innerHTML = '<div class="py-16 text-center"><i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin text-gold/50"></i></div>';
    reicons();
    try {
      const params = { action: 'admin_get_submissions', page: _subPage };
      if (_subStatus) params.status = _subStatus;
      const res  = await apiFetch(params);
      const rows = res.data || [];
      if (!rows.length) {
        wrap.innerHTML = '<div class="py-16 text-center"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 text-primary/15"></i><p class="text-primary/30 text-sm">Tidak ada kiriman ditemukan.</p></div>';
        pag.innerHTML = '';
        reicons(); return;
      }
      const rowsHtml = rows.map(r => {
        const fileBtn = r.file_url ? '<a href="' + escHtml(r.file_url) + '" target="_blank" title="Lihat file" class="p-1.5 rounded-lg hover:bg-cream-dark transition-colors text-primary/50 hover:text-primary"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>' : '';
        const actBtn  = r.status === 'pending' ? '<button onclick="openSubModal(' + r.id + ',' + JSON.stringify(r.file_name) + ',\'' + r.file_type + '\')" title="Review" class="p-1.5 rounded-lg hover:bg-gold/10 text-gold transition-colors"><i data-lucide="clipboard-check" class="w-3.5 h-3.5"></i></button>' : '';
        return '<tr class="hover:bg-cream/60 transition-colors">'
          + '<td class="px-4 py-3"><div class="font-medium text-primary line-clamp-1">' + escHtml(r.file_name) + '</div>'
          + '<div class="text-primary/40 text-xs mt-0.5">' + fmtSize(r.file_size) + ' · ' + (r.created_at||'').slice(0,10) + '</div>'
          + (r.review_note ? '<div class="text-primary/40 text-xs italic mt-0.5 line-clamp-1">“' + escHtml(r.review_note) + '”</div>' : '')
          + '</td>'
          + '<td class="px-4 py-3 hidden sm:table-cell"><div class="text-primary/70 text-xs line-clamp-1">' + escHtml(r.user_name) + '</div><div class="text-primary/35 text-xs">' + escHtml(r.user_email) + '</div></td>'
          + '<td class="px-4 py-3 hidden md:table-cell text-primary/55 text-xs">' + (r.file_type === 'bahsul_masail' ? 'Bahsul Masail' : 'Kitab') + '</td>'
          + '<td class="px-4 py-3 hidden lg:table-cell text-primary/55 text-xs">' + escHtml(r.category_name || '—') + '</td>'
          + '<td class="px-4 py-3 text-center">' + statusBadge(r.status) + '</td>'
          + '<td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1.5">' + fileBtn + actBtn + '</div></td>'
          + '</tr>';
      }).join('');
      wrap.innerHTML = '<div class="overflow-x-auto"><table class="w-full text-sm">'
        + '<thead class="bg-cream border-b border-cream-dark text-primary/50 text-xs"><tr>'
        + '<th class="px-4 py-3 text-left font-semibold">Nama File</th>'
        + '<th class="px-4 py-3 text-left font-semibold hidden sm:table-cell">Pengirim</th>'
        + '<th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Tipe</th>'
        + '<th class="px-4 py-3 text-left font-semibold hidden lg:table-cell">Kategori</th>'
        + '<th class="px-4 py-3 text-center font-semibold">Status</th>'
        + '<th class="px-4 py-3 text-center font-semibold w-24">Aksi</th>'
        + '</tr></thead><tbody class="divide-y divide-cream-dark">' + rowsHtml + '</tbody></table></div>';
      pag.innerHTML = res.pages > 1
        ? '<span>' + res.total + ' kiriman</span><div class="flex gap-1">'
          + (_subPage > 1 ? '<button onclick="window._subPageG=' + (_subPage-1) + ';window.subLoadG&&window.subLoadG()" class="px-2 py-1 rounded bg-cream hover:bg-cream-dark text-primary/60">‹</button>' : '')
          + '<span class="px-2 py-1 text-primary/50">' + _subPage + '/' + res.pages + '</span>'
          + (_subPage < res.pages ? '<button onclick="window._subPageG=' + (_subPage+1) + ';window.subLoadG&&window.subLoadG()" class="px-2 py-1 rounded bg-cream hover:bg-cream-dark text-primary/60">›</button>' : '')
          + '</div>'
        : '<span>' + res.total + ' kiriman</span>';
      reicons();
    } catch(e) {
      if (handleAuthError(e)) return;
      wrap.innerHTML = '<p class="text-center py-12 text-red-500 text-sm">Gagal memuat: ' + escHtml(e.message) + '</p>';
    }
  }

  window.subLoadG  = subLoad;
  window._subPageG = _subPage;
  await subLoad();
}
