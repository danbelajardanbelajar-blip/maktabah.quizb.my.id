/* =============================================================
   Al-Maktabah As-Sunniyyah — Admin Panel (admin.js)
   Halaman: Dashboard · Kelola Kitab · Kelola Kategori · Kelola Isi Kitab
   ============================================================= */
'use strict';

// ── Tambah rute admin ke router SPA ──────────────────────────
Object.assign(window._adminRoutes = window._adminRoutes || {}, {
  '/dashboard':        renderDashboard,
  '/admin':            () => navigate('/admin/books', true),
  '/admin/books':      renderAdminBooks,
  '/admin/categories': renderAdminCategories,
  '/admin/content':    () => navigate('/admin/books', true),
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
  const tabs = [
    { r: '/dashboard',        icon: 'layout-dashboard', label: 'Dashboard' },
    { r: '/admin/books',      icon: 'book',             label: 'Kelola Kitab' },
    { r: '/admin/categories', icon: 'folder',           label: 'Kelola Kategori' },
  ];
  return `
    <div class="bg-white border-b border-gold/15 sticky top-16 z-40 shadow-sm">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center overflow-x-auto" style="scrollbar-width:none;">
        ${tabs.map(t => {
          const on = active === t.r;
          return `<a href="${t.r}" data-route="${t.r}"
            class="flex items-center gap-2 px-4 sm:px-5 py-3.5 text-sm font-semibold whitespace-nowrap border-b-2 transition-colors no-underline shrink-0
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
          <button onclick="openBookModal(null)"
            class="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Kitab
          </button>
        </div>

        <!-- Filter bar -->
        <div class="bg-white rounded-2xl shadow-card p-4 mb-5 flex flex-col sm:flex-row gap-3">
          <div class="flex-1 relative">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-primary/30"></i>
            <input id="bks-q" type="text" value="${escHtml(booksAS.q)}" placeholder="Cari judul atau pengarang…"
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
    </div>`;
}

/* ── openBookModal ───────────────────────────────────────── */
window.openBookModal = function(book) {
  if (typeof book === 'number' || (typeof book === 'string' && !isNaN(book))) {
    book = _booksMap.get(+book) || null;
  }
  const form = document.getElementById('bk-form');
  if (form) form.reset();
  document.getElementById('bm-bkid').value = '';

  const isEdit = !!book;
  document.getElementById('bk-modal-ttl').textContent = isEdit ? 'Edit Kitab' : 'Tambah Kitab Baru';

  if (isEdit) {
    document.getElementById('bm-bkid').value   = book.bkid   ?? '';
    document.getElementById('bm-title').value  = book.title  ?? '';
    document.getElementById('bm-author').value = book.author ?? '';
    const isoEl = document.getElementById('bm-iso');
    if (isoEl) isoEl.value = book.iso || 'ar';
    const catEl = document.getElementById('bm-cat');
    if (catEl) catEl.value = book.category_id ? String(book.category_id) : '';
  }

  const msg = document.getElementById('bm-msg');
  if (msg) msg.className = 'hidden text-sm rounded-xl px-4 py-2.5';

  const btn = document.getElementById('bk-submit-btn');
  if (btn) btn.innerHTML = isEdit
    ? '<i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan'
    : '<i data-lucide="plus" class="w-4 h-4"></i> Tambah Kitab';

  document.getElementById('bk-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('bm-title')?.focus(), 60);
  reicons();
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

