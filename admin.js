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
  '/admin/history':    renderAdminHistory,
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
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return res.json();
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
    { r: '/admin/history',    icon: 'history',          label: 'CRUD History',    adminOnly: true,  desktopOnly: true },
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
        ${isAdmin ? dashCard('/admin/books',      'book',           'Kelola Kitab',      'Tambah / edit / hapus',     true)  : ''}
        ${isAdmin ? dashCard('/admin/categories', 'folder',         'Kelola Kategori',   'Atur kategori kitab',       true)  : ''}
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
    msg.textContent = 'Error: ' + e.message;
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Simpan'; reicons();
  }
}

window.bkDelete = async function(bkid, title) {
  if (!confirm(`Hapus kitab:\n"${title}"\n\nSeluruh isi kitab juga akan dihapus. Yakin?`)) return;
  const data = await adminPost('admin_delete_book', { bkid }).catch(e => ({ error: e.message }));
  if (data.success) { adminToast('Kitab dihapus'); bksLoad(); }
  else adminToast(data.error || 'Gagal', 'error');
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
  } catch(e) { adminToast('Error: ' + e.message, 'error'); }
  finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Simpan'; reicons(); }
  }
};

window.contDeletePage = async function(bkid, page) {
  if (!confirm(`Hapus halaman ${page}? Konten akan hilang permanen.`)) return;
  if (_ctrlS) { document.removeEventListener('keydown', _ctrlS); _ctrlS = null; }
  const data = await adminPost('admin_delete_content', { bkid, page }).catch(e => ({ error: e.message }));
  if (data.success) {
    adminToast('Halaman dihapus');
    contAS.page = Math.max(1, contAS.page - 1);
    await contLoadEditor();
  } else adminToast(data.error || 'Gagal', 'error');
};

window.contAddPage = async function() {
  const bkid = contAS.bkid;
  let total = 0;
  try { const r = await apiFetch({ action: 'content', bkid, page: 1 }); total = r.total_pages || 0; } catch {}
  const newPg = total + 1;
  const data = await adminPost('admin_save_content', { bkid, page: newPg, content: '' }).catch(e => ({ error: e.message }));
  if (data.success) {
    adminToast(`Halaman ${newPg} dibuat`);
    if (_ctrlS) { document.removeEventListener('keydown', _ctrlS); _ctrlS = null; }
    contAS.page = newPg;
    await contLoadEditor();
  } else adminToast(data.error || 'Gagal buat halaman', 'error');
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
    msg.textContent = 'Error: ' + e.message;
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Simpan'; reicons();
  }
}

window.catDelete = async function(id, name, cnt) {
  const warn = cnt > 0 ? `\n⚠️ ${cnt} kitab akan kehilangan kategorinya.` : '';
  if (!confirm(`Hapus kategori "${name}"?${warn}`)) return;
  const data = await adminPost('admin_delete_category', { id }).catch(e => ({ error: e.message }));
  if (data.success) { adminToast('Kategori dihapus'); loadCatGrid(); }
  else adminToast(data.error || 'Gagal', 'error');
};

// ══════════════════════════════════════════════════════════════
//  CRUD HISTORY  /admin/history  (admin + desktop only)
// ══════════════════════════════════════════════════════════════

async function renderAdminHistory() {
  if (!adminGuard()) return;

  // Hanya tampil di desktop (≥ 768 px)
  if (window.innerWidth < 768) {
    app().innerHTML = `
      <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-6 gap-4">
        <i data-lucide="monitor" class="w-16 h-16 text-primary/20"></i>
        <h1 class="text-lg font-bold text-primary">Tampilan Desktop Diperlukan</h1>
        <p class="text-primary/45 text-sm max-w-xs">Halaman CRUD History hanya tersedia di tampilan desktop. Silakan buka di layar yang lebih lebar.</p>
        <a href="/dashboard" data-route="/dashboard"
           class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary-light transition-colors">
          Kembali ke Dashboard
        </a>
      </div>`;
    reicons(); return;
  }

  // State filter & pagination
  window._histState = window._histState || {
    page: 1, actionFilter: '', tableFilter: '', adminFilter: '',
  };

  app().innerHTML = adminNavBar('/admin/history') + `
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-xl font-bold text-primary flex items-center gap-2">
            <i data-lucide="history" class="w-5 h-5 text-gold"></i> CRUD History
          </h1>
          <p class="text-primary/40 text-xs mt-1">Rekaman semua perubahan data oleh admin</p>
        </div>
        <button onclick="histLoad(true)"
          class="flex items-center gap-2 px-4 py-2 rounded-xl border border-gold/30 text-sm text-primary/60 hover:bg-cream-dark transition-colors">
          <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh
        </button>
      </div>

      <!-- Filter Bar -->
      <div class="bg-white rounded-2xl shadow-card p-4 mb-5 flex flex-wrap gap-3 items-end">

        <!-- Filter Aksi -->
        <div class="flex flex-col gap-1 min-w-[140px]">
          <label class="text-[10px] font-bold uppercase tracking-widest text-primary/40">Jenis Aksi</label>
          <select id="hist-action-filter"
            class="px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold bg-cream/40"
            onchange="histFilter()">
            <option value="">Semua Aksi</option>
            <option value="CREATE">CREATE</option>
            <option value="UPDATE">UPDATE</option>
            <option value="DELETE">DELETE</option>
            <option value="IMPORT">IMPORT</option>
          </select>
        </div>

        <!-- Filter Tabel -->
        <div class="flex flex-col gap-1 min-w-[160px]">
          <label class="text-[10px] font-bold uppercase tracking-widest text-primary/40">Tabel Data</label>
          <select id="hist-table-filter"
            class="px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold bg-cream/40"
            onchange="histFilter()">
            <option value="">Semua Tabel</option>
            <option value="books">books (Kitab)</option>
            <option value="categories">categories (Kategori)</option>
            <option value="book_content">book_content (Isi Kitab)</option>
          </select>
        </div>

        <!-- Filter Admin -->
        <div class="flex flex-col gap-1 flex-1 min-w-[180px]">
          <label class="text-[10px] font-bold uppercase tracking-widest text-primary/40">Nama Admin</label>
          <input id="hist-admin-filter" type="text" placeholder="Cari nama / email admin…"
            class="px-3 py-2 rounded-xl border border-gold/25 text-sm focus:outline-none focus:border-gold bg-cream/40"
            oninput="histFilterDebounce()" />
        </div>

        <!-- Tombol reset -->
        <button onclick="histReset()"
          class="px-4 py-2 rounded-xl border border-gold/20 text-sm text-primary/50 hover:bg-cream-dark transition-colors self-end">
          <i data-lucide="x" class="w-3.5 h-3.5 inline-block mr-1 align-text-bottom"></i>Reset
        </button>
      </div>

      <!-- Tabel -->
      <div id="hist-table-wrap"></div>

      <!-- Pagination -->
      <div id="hist-pagination" class="mt-4 flex items-center justify-between"></div>
    </div>`;

  reicons();
  await histLoad(true);
}

// ── debounce untuk input pencarian admin
let _histDebTimer = null;
window.histFilterDebounce = function() {
  clearTimeout(_histDebTimer);
  _histDebTimer = setTimeout(() => histFilter(), 450);
};

window.histFilter = function() {
  window._histState.page          = 1;
  window._histState.actionFilter  = document.getElementById('hist-action-filter')?.value  || '';
  window._histState.tableFilter   = document.getElementById('hist-table-filter')?.value   || '';
  window._histState.adminFilter   = document.getElementById('hist-admin-filter')?.value   || '';
  histLoad(false);
};

window.histReset = function() {
  window._histState = { page: 1, actionFilter: '', tableFilter: '', adminFilter: '' };
  const af = document.getElementById('hist-action-filter'); if (af) af.value = '';
  const tf = document.getElementById('hist-table-filter');  if (tf) tf.value = '';
  const nf = document.getElementById('hist-admin-filter');  if (nf) nf.value = '';
  histLoad(false);
};

window.histGotoPage = function(p) {
  window._histState.page = p;
  histLoad(false);
};

async function histLoad(reset = false) {
  if (reset) window._histState.page = 1;
  const s   = window._histState;
  const wrap = document.getElementById('hist-table-wrap');
  const pgEl = document.getElementById('hist-pagination');
  if (!wrap) return;

  wrap.innerHTML = adminSpinner();

  try {
    const qs = new URLSearchParams({
      action:        'admin_get_history',
      page:          s.page,
      limit:         50,
      action_filter: s.actionFilter,
      table_filter:  s.tableFilter,
      admin_filter:  s.adminFilter,
    });
    const res  = await fetch(`/api.php?${qs}`);
    const data = await res.json();

    if (!data.success) throw new Error(data.error || 'Gagal memuat data.');

    const rows = data.data || [];

    if (!rows.length) {
      wrap.innerHTML = `
        <div class="bg-white rounded-2xl shadow-card py-16 text-center text-primary/25 text-sm">
          <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-25"></i>
          <p>Belum ada riwayat perubahan.</p>
        </div>`;
      if (pgEl) pgEl.innerHTML = '';
      reicons(); return;
    }

    // Badge warna per aksi
    const badge = a => {
      const cfg = {
        CREATE: 'bg-emerald-100 text-emerald-700',
        UPDATE: 'bg-blue-100 text-blue-700',
        DELETE: 'bg-red-100 text-red-600',
        IMPORT: 'bg-amber-100 text-amber-700',
      };
      return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide ${cfg[a] || 'bg-primary/8 text-primary/60'}">${a}</span>`;
    };

    // Ikon per tabel
    const tblIcon = t => ({
      books:        'book',
      categories:   'folder',
      book_content: 'file-text',
    }[t] || 'database');

    // Format tanggal lokal
    const fmtDate = str => {
      if (!str) return '-';
      try {
        return new Date(str).toLocaleString('id-ID', {
          day:'2-digit', month:'short', year:'numeric',
          hour:'2-digit', minute:'2-digit', second:'2-digit',
        });
      } catch { return str; }
    };

    wrap.innerHTML = `
      <div class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="px-5 py-3.5 bg-cream/40 border-b border-cream-dark flex items-center justify-between">
          <span class="text-xs font-medium text-primary/50">
            ${data.total} entri ditemukan
            ${s.actionFilter || s.tableFilter || s.adminFilter ? '(difilter)' : ''}
          </span>
          <span class="text-xs text-primary/30">Halaman ${s.page} dari ${data.pages}</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-cream-dark text-left">
                <th class="px-4 py-3 text-xs font-semibold text-primary/40 uppercase tracking-wide whitespace-nowrap">Waktu</th>
                <th class="px-4 py-3 text-xs font-semibold text-primary/40 uppercase tracking-wide">Aksi</th>
                <th class="px-4 py-3 text-xs font-semibold text-primary/40 uppercase tracking-wide">Tabel</th>
                <th class="px-4 py-3 text-xs font-semibold text-primary/40 uppercase tracking-wide">ID Record</th>
                <th class="px-4 py-3 text-xs font-semibold text-primary/40 uppercase tracking-wide">Detail</th>
                <th class="px-4 py-3 text-xs font-semibold text-primary/40 uppercase tracking-wide whitespace-nowrap">Admin</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-cream-dark/60">
              ${rows.map(r => `
                <tr class="hover:bg-cream/30 transition-colors">
                  <td class="px-4 py-3 text-xs text-primary/50 whitespace-nowrap font-mono">${fmtDate(r.created_at)}</td>
                  <td class="px-4 py-3">${badge(r.action)}</td>
                  <td class="px-4 py-3">
                    <span class="flex items-center gap-1.5 text-xs text-primary/60">
                      <i data-lucide="${tblIcon(r.table_name)}" class="w-3.5 h-3.5 shrink-0 text-primary/30"></i>
                      ${escHtml(r.table_name)}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-xs font-mono text-primary/45 max-w-[100px] truncate" title="${escHtml(r.record_id)}">${escHtml(r.record_id)}</td>
                  <td class="px-4 py-3 text-xs text-primary/65 max-w-[260px]">
                    <span class="block truncate" title="${escHtml(r.detail || '')}">${escHtml(r.detail || '—')}</span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-700 font-bold text-xs shrink-0">
                        ${escHtml((r.admin_name || '?').charAt(0).toUpperCase())}
                      </div>
                      <div class="min-w-0">
                        <div class="text-xs font-semibold text-primary truncate max-w-[120px]" title="${escHtml(r.admin_name)}">${escHtml(r.admin_name || '—')}</div>
                        <div class="text-[10px] text-primary/35 truncate max-w-[120px]" title="${escHtml(r.admin_email)}">${escHtml(r.admin_email || '')}</div>
                      </div>
                    </div>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>`;

    // Render pagination
    if (pgEl && data.pages > 1) {
      const curP  = s.page;
      const total = data.pages;
      let btns = '';
      // Prev
      btns += `<button onclick="histGotoPage(${curP - 1})" ${curP <= 1 ? 'disabled' : ''}
        class="px-3 py-1.5 rounded-lg border border-gold/20 text-xs text-primary/50 hover:bg-cream-dark disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
        ← Prev</button>`;
      // Page numbers (maksimal 7 tombol)
      const range = [];
      for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= curP - 2 && i <= curP + 2)) range.push(i);
        else if (range[range.length - 1] !== '…') range.push('…');
      }
      range.forEach(p => {
        if (p === '…') {
          btns += `<span class="px-2 text-primary/25 text-xs">…</span>`;
        } else {
          btns += `<button onclick="histGotoPage(${p})"
            class="px-3 py-1.5 rounded-lg border text-xs transition-colors
              ${p === curP ? 'bg-primary text-white border-primary' : 'border-gold/20 text-primary/50 hover:bg-cream-dark'}">
            ${p}</button>`;
        }
      });
      // Next
      btns += `<button onclick="histGotoPage(${curP + 1})" ${curP >= total ? 'disabled' : ''}
        class="px-3 py-1.5 rounded-lg border border-gold/20 text-xs text-primary/50 hover:bg-cream-dark disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
        Next →</button>`;

      pgEl.innerHTML = `
        <span class="text-xs text-primary/35">${data.total} total entri</span>
        <div class="flex items-center gap-1.5 flex-wrap justify-end">${btns}</div>`;
    } else if (pgEl) {
      pgEl.innerHTML = data.total
        ? `<span class="text-xs text-primary/35">${data.total} entri</span>`
        : '';
    }

    reicons();
  } catch(e) {
    wrap.innerHTML = `<div class="bg-white rounded-2xl shadow-card p-6 text-red-500 text-sm">${escHtml(e.message)}</div>`;
  }
}

