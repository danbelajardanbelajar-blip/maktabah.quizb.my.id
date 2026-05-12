/* =============================================================
   Al-Maktabah As-Sunniyyah — SPA Core (app.js)
   Vanilla JS · History API · Fetch API
   ============================================================= */

'use strict';

// ── Config ────────────────────────────────────────────────────
const API = '/api.php';

// ── Utility helpers ───────────────────────────────────────────
const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
const el = (tag, cls, html = '') => {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (html) e.innerHTML = html;
  return e;
};
const app     = () => $('#app-content');
const reicons = () => { if (window.lucide) lucide.createIcons(); };

async function apiFetch(params) {
  const url = API + '?' + new URLSearchParams(params).toString();
  const res = await fetch(url);
  if (!res.ok) throw new Error('API error ' + res.status);
  return res.json();
}

// ── Router ────────────────────────────────────────────────────
const routes = {
  '/':        renderHome,
  '/katalog': renderKatalog,
  '/about':   renderAbout,
  '/search':  renderSearch,
  '/kitab':   renderDetail,
};

function navigate(path, push = true) {
  if (push) history.pushState({}, '', path);
  const base = path.split('?')[0];
  const handler = routes[base] || render404;
  app().innerHTML = '';
  handler(new URLSearchParams(path.includes('?') ? path.split('?')[1] : ''));
  setActiveNav(base);
  window.scrollTo({ top: 0, behavior: 'smooth' });
  reicons();
}

window.addEventListener('popstate', () => navigate(location.pathname + location.search, false));

document.addEventListener('click', e => {
  const a = e.target.closest('[data-route]');
  if (!a) return;
  e.preventDefault();
  const route = a.getAttribute('data-route');
  navigate(route);
  // close mobile menu
  const menu = $('#mobile-menu');
  if (menu && !menu.classList.contains('hidden-menu')) toggleMobileMenu();
});

function setActiveNav(base) {
  // Desktop top nav
  $$('.nav-link').forEach(a => {
    a.classList.toggle('active', a.getAttribute('data-route') === base);
  });
  // Mobile bottom nav — /search counts as "Cari" active
  $$('.bnav-item').forEach(a => {
    const route = a.getAttribute('data-route');
    const isActive = route === base || (base === '/search' && route === '/search');
    a.classList.toggle('active', isActive);
  });
}

// ── Navbar behaviours ─────────────────────────────────────────
window.addEventListener('scroll', () => {
  $('#navbar')?.classList.toggle('scrolled', window.scrollY > 20);
});

document.addEventListener('DOMContentLoaded', () => {
  // Global search bar
  $('#nav-search-btn')?.addEventListener('click', () => {
    const sb = $('#search-bar');
    sb.classList.toggle('hidden');
    if (!sb.classList.contains('hidden')) $('#global-search-input').focus();
  });
  $('#search-bar-close')?.addEventListener('click', () => $('#search-bar').classList.add('hidden'));

  let searchTimer;
  $('#global-search-input')?.addEventListener('input', e => {
    clearTimeout(searchTimer);
    const q = e.target.value.trim();
    if (q.length < 2) return;
    searchTimer = setTimeout(() => {
      $('#search-bar').classList.add('hidden');
      navigate('/search?q=' + encodeURIComponent(q));
    }, 500);
  });
  $('#global-search-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      const q = e.target.value.trim();
      if (q) { $('#search-bar').classList.add('hidden'); navigate('/search?q=' + encodeURIComponent(q)); }
    }
  });

  navigate(location.pathname + location.search, false);
});

// ── Skeleton helpers ──────────────────────────────────────────
function skeletonCards(n = 8) {
  return Array.from({ length: n }, () =>
    `<div class="bg-white rounded-2xl shadow-card p-4 space-y-3">
       <div class="skeleton h-5 rounded-lg w-4/5"></div>
       <div class="skeleton h-4 rounded w-3/5"></div>
       <div class="skeleton h-3 rounded w-2/5"></div>
     </div>`
  ).join('');
}

// ── Shared: Book Card ─────────────────────────────────────────
function bookCard(b) {
  const title  = b.title  || 'بدون عنوان';
  const author = b.author || 'مجهول';
  const cat    = b.category_name || '';
  const pages  = b.pages  ? b.pages + ' hal.' : '';
  return `
    <div class="book-card bg-white rounded-2xl shadow-card p-5 flex flex-col gap-3 cursor-pointer"
         onclick="navigate('/kitab?id=${b.bkid}')">
      <div class="flex-1">
        <div class="arabic text-primary font-semibold text-base leading-snug line-clamp-2 mb-1">${escHtml(title)}</div>
        <div class="text-primary/60 text-xs font-medium line-clamp-1">${escHtml(author)}</div>
      </div>
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        ${cat ? `<span class="text-xs bg-primary/8 text-primary/70 px-2 py-0.5 rounded-full truncate max-w-[60%]">${escHtml(cat)}</span>` : '<span></span>'}
        ${pages ? `<span class="text-xs text-gold font-medium">${escHtml(pages)}</span>` : ''}
      </div>
    </div>`;
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Pagination ────────────────────────────────────────────────
function paginationHtml(current, total, onClickFn) {
  if (total <= 1) return '';
  const pages = [];
  const delta = 2;
  let prev = null;
  for (let p = 1; p <= total; p++) {
    if (p === 1 || p === total || (p >= current - delta && p <= current + delta)) {
      if (prev && p - prev > 1) pages.push('...');
      pages.push(p);
      prev = p;
    }
  }
  const btnBase = 'w-9 h-9 flex items-center justify-center rounded-lg text-sm font-medium transition-colors';
  const btns = pages.map(p => p === '...'
    ? `<span class="${btnBase} text-primary/40">…</span>`
    : `<button onclick="${onClickFn}(${p})"
         class="${btnBase} ${p === current ? 'bg-primary text-white' : 'bg-white text-primary hover:bg-cream-dark border border-gold/20'}">${p}</button>`
  ).join('');
  return `<div class="flex items-center justify-center gap-1 mt-8 flex-wrap">${btns}</div>`;
}

// ══════════════════════════════════════════════════════════════
//  PAGE: HOME
// ══════════════════════════════════════════════════════════════
async function renderHome() {
  app().innerHTML = `
    <!-- Hero -->
    <section class="hero-bg text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28 text-center">
        <div class="arabic text-gold text-4xl md:text-6xl font-bold mb-3 leading-tight">المكتبة السنية</div>
        <p class="text-white/70 text-base md:text-lg mb-8 max-w-xl mx-auto">Perpustakaan digital Islam — ribuan kitab salaf dalam genggaman Anda.</p>
        <!-- Search -->
        <div class="max-w-xl mx-auto relative">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-primary/40"></i>
          <input id="hero-search" type="text" placeholder="Cari judul atau pengarang kitab…"
            class="w-full pl-12 pr-4 py-4 rounded-2xl text-ink text-sm bg-white shadow-xl focus:outline-none focus:ring-2 focus:ring-gold/50 transition-all" />
        </div>
        <!-- stats -->
        <div id="hero-stats" class="mt-10 flex items-center justify-center gap-6 text-sm text-white/50">
          <span class="flex items-center gap-2"><i data-lucide="book-open" class="w-4 h-4 text-gold/60"></i> Memuat statistik…</span>
        </div>
      </div>
      <div class="gold-line"></div>
    </section>

    <!-- Latest Kitab -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-primary">Kitab Terbaru</h2>
        <a href="/katalog" data-route="/katalog" class="text-sm text-gold hover:text-gold-dark font-medium flex items-center gap-1">
          Lihat Semua <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
      </div>
      <div id="latest-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        ${skeletonCards(8)}
      </div>
    </section>

    <!-- Categories -->
    <section class="bg-white py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-primary mb-6">Jelajahi Kategori</h2>
        <div id="cat-grid" class="flex flex-wrap gap-3">
          ${Array.from({length:6}, () => `<div class="skeleton h-9 w-28 rounded-full"></div>`).join('')}
        </div>
      </div>
    </section>`;

  reicons();

  // Hero search
  let timer;
  $('#hero-search')?.addEventListener('input', e => {
    clearTimeout(timer);
    const q = e.target.value.trim();
    if (q.length >= 2) timer = setTimeout(() => navigate('/search?q=' + encodeURIComponent(q)), 500);
  });
  $('#hero-search')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      const q = e.target.value.trim();
      if (q) navigate('/search?q=' + encodeURIComponent(q));
    }
  });

  // Load latest
  try {
    const res = await apiFetch({ action: 'latest', limit: 8 });
    $('#latest-grid').innerHTML = res.data.map(bookCard).join('') || '<p class="text-primary/50 col-span-full text-center py-8">Belum ada kitab.</p>';
    $('#hero-stats').innerHTML = `<span class="flex items-center gap-2"><i data-lucide="book-open" class="w-4 h-4 text-gold/60"></i> ${res.data.length > 0 ? 'Koleksi terus bertambah' : 'Koleksi belum tersedia'}</span>`;
  } catch { $('#latest-grid').innerHTML = '<p class="text-red-500 col-span-full text-sm text-center py-8">Gagal memuat kitab.</p>'; }

  // Load categories
  try {
    const res = await apiFetch({ action: 'categories' });
    const cats = res.data.filter(c => c.book_count > 0).slice(0, 18);
    $('#cat-grid').innerHTML = cats.map(c =>
      `<button onclick="navigate('/katalog?cat=${c.id}')"
         class="px-4 py-2 rounded-full border border-gold/30 text-sm text-primary/80 hover:bg-primary hover:text-white hover:border-primary transition-all">
         ${escHtml(c.name)} <span class="text-gold text-xs ml-1">${c.book_count}</span>
       </button>`
    ).join('') || '<span class="text-primary/40 text-sm">Kategori belum tersedia.</span>';
  } catch { /* ignore */ }

  reicons();
}

// ══════════════════════════════════════════════════════════════
//  PAGE: KATALOG
// ══════════════════════════════════════════════════════════════
let katalogState = { page: 1, cat: '' };

async function renderKatalog(params) {
  if (params.has('cat')) katalogState.cat = params.get('cat');
  if (params.has('page')) katalogState.page = parseInt(params.get('page')) || 1;

  app().innerHTML = `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
        <div>
          <h1 class="text-2xl font-bold text-primary">Katalog Kitab</h1>
          <p class="text-primary/50 text-sm mt-1">Seluruh koleksi kitab perpustakaan</p>
        </div>
        <div id="cat-filter-wrap" class="w-full sm:w-auto">
          <select id="cat-filter" class="w-full sm:w-56 px-3 py-2 rounded-xl border border-gold/30 bg-white text-sm text-primary focus:outline-none focus:border-gold">
            <option value="">Semua Kategori</option>
          </select>
        </div>
      </div>
      <div id="katalog-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        ${skeletonCards(12)}
      </div>
      <div id="katalog-pagination"></div>
    </div>`;

  reicons();

  // Load categories for filter
  try {
    const res = await apiFetch({ action: 'categories' });
    const sel = $('#cat-filter');
    res.data.filter(c => c.book_count > 0).forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id; opt.textContent = c.name + ' (' + c.book_count + ')';
      if (String(c.id) === String(katalogState.cat)) opt.selected = true;
      sel.appendChild(opt);
    });
    sel.addEventListener('change', e => {
      katalogState.cat = e.target.value;
      katalogState.page = 1;
      loadKatalogBooks();
    });
  } catch { /* ignore */ }

  loadKatalogBooks();
}

async function loadKatalogBooks() {
  const grid = $('#katalog-grid');
  const pag  = $('#katalog-pagination');
  if (!grid) return;
  grid.innerHTML = skeletonCards(12);
  if (pag) pag.innerHTML = '';

  const params = { action: 'books', page: katalogState.page, limit: 24 };
  if (katalogState.cat) params.cat = katalogState.cat;

  try {
    const res = await apiFetch(params);
    grid.innerHTML = res.data.length
      ? res.data.map(bookCard).join('')
      : '<p class="col-span-full text-center py-12 text-primary/40">Tidak ada kitab di kategori ini.</p>';
    if (pag) pag.innerHTML = paginationHtml(res.page, res.total_pages, 'goKatalogPage');
  } catch {
    grid.innerHTML = '<p class="col-span-full text-center py-12 text-red-500 text-sm">Gagal memuat katalog.</p>';
  }
  reicons();
}

window.goKatalogPage = function(p) {
  katalogState.page = p;
  loadKatalogBooks();
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

// ══════════════════════════════════════════════════════════════
//  PAGE: SEARCH  — tiga section: Kategori · Judul Kitab · Isi
// ══════════════════════════════════════════════════════════════
const searchState = { q: '', bookPage: 1, contPage: 1 };

async function renderSearch(params) {
  searchState.q        = params.get('q') || '';
  searchState.bookPage = 1;
  searchState.contPage = 1;

  app().innerHTML = `
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

      <!-- Search input -->
      <div class="max-w-2xl mx-auto mb-8">
        <div class="relative">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-primary/40"></i>
          <input id="search-input" type="text" value="${escHtml(searchState.q)}"
            placeholder="Cari kategori, judul, pengarang, atau isi kitab…"
            class="w-full pl-12 pr-4 py-3.5 rounded-2xl border border-gold/30 bg-white text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 transition-all shadow-card" />
        </div>
      </div>

      <!-- Results container -->
      <div id="search-results">
        ${searchState.q ? skeletonSearchResults() : emptySearchPrompt()}
      </div>
    </div>`;

  reicons();

  let timer;
  $('#search-input')?.addEventListener('input', e => {
    clearTimeout(timer);
    searchState.q        = e.target.value.trim();
    searchState.bookPage = 1;
    searchState.contPage = 1;
    if (searchState.q.length >= 2) timer = setTimeout(execSearch, 450);
    else if (!searchState.q) $('#search-results').innerHTML = emptySearchPrompt();
  });
  $('#search-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { clearTimeout(timer); execSearch(); }
  });

  if (searchState.q.length >= 2) execSearch();
}

// ── Skeleton placeholder for all three sections ───────────────
function skeletonSearchResults() {
  return ['Kategori', 'Nama Kitab', 'Isi Kitab'].map(label => `
    <div class="mb-10">
      <div class="flex items-center gap-3 mb-4">
        <div class="skeleton h-4 w-4 rounded"></div>
        <div class="skeleton h-4 w-40 rounded"></div>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">${skeletonCards(4)}</div>
    </div>`).join(sectionDivider());
}

// ── Empty state ───────────────────────────────────────────────
function emptySearchPrompt() {
  return `<div class="flex flex-col items-center py-16 text-primary/30">
    <i data-lucide="search" class="w-14 h-14 mb-4 opacity-30"></i>
    <p class="text-sm">Masukkan kata kunci untuk mencari</p>
  </div>`;
}

// ── Gold divider with label ───────────────────────────────────
function sectionDivider() {
  return `<div class="flex items-center gap-3 my-8">
    <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gold/40 to-transparent"></div>
  </div>`;
}

// ── Section header ─────────────────────────────────────────────
function sectionHeader(icon, label, total) {
  const badge = total > 0
    ? `<span class="ml-2 px-2 py-0.5 rounded-full bg-gold/15 text-gold text-xs font-semibold">${total.toLocaleString('id-ID')}</span>`
    : '';
  return `<div class="flex items-center gap-2 mb-5">
    <i data-lucide="${icon}" class="w-4 h-4 text-gold shrink-0"></i>
    <h2 class="text-sm font-bold text-primary uppercase tracking-wider">${label}</h2>
    ${badge}
  </div>`;
}

// ── No result state for one section ──────────────────────────
function noResultBlock(msg) {
  return `<p class="text-primary/35 text-sm py-4 flex items-center gap-2">
    <i data-lucide="minus-circle" class="w-4 h-4 shrink-0"></i>${msg}
  </p>`;
}

// ── Content-result card (book + snippet) ──────────────────────
function contentCard(b, q) {
  const title   = b.title         || 'بدون عنوان';
  const author  = b.author        || '';
  const cat     = b.category_name || '';
  const snippet = b.snippet       || '';
  const page    = b.match_page    ? `hal. ${b.match_page}` : '';

  // Highlight query in snippet
  const highlighted = snippet
    ? escHtml(snippet).replace(
        new RegExp('(' + escHtml(q).replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi'),
        '<mark class="bg-gold/25 text-primary rounded px-0.5">$1</mark>'
      )
    : '';

  return `
    <div class="book-card bg-white rounded-2xl shadow-card p-4 flex flex-col gap-2 cursor-pointer"
         onclick="navigate('/kitab?id=${b.bkid}')">
      <div class="arabic text-primary font-semibold text-sm leading-snug line-clamp-2">${escHtml(title)}</div>
      ${author ? `<div class="text-primary/55 text-xs line-clamp-1">${escHtml(author)}</div>` : ''}
      ${highlighted
        ? `<p class="reader-text text-primary/65 text-xs leading-relaxed line-clamp-3 mt-1 border-l-2 border-gold/40 pl-2">${highlighted}…</p>`
        : ''}
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        ${cat  ? `<span class="text-xs text-primary/50 truncate max-w-[65%]">${escHtml(cat)}</span>` : '<span></span>'}
        ${page ? `<span class="text-xs text-gold font-medium">${page}</span>` : ''}
      </div>
    </div>`;
}

// ── Main search executor ──────────────────────────────────────
async function execSearch() {
  const wrap = $('#search-results');
  if (!wrap || searchState.q.length < 2) return;

  wrap.innerHTML = skeletonSearchResults();
  reicons();
  history.replaceState({}, '', '/search?q=' + encodeURIComponent(searchState.q));

  try {
    const res = await apiFetch({
      action:       'search',
      q:            searchState.q,
      book_page:    searchState.bookPage,
      content_page: searchState.contPage,
    });

    const q = res.query || searchState.q;

    // ── Section 1: Kategori ──────────────────────────────────
    const catHtml = res.categories.length
      ? `<div class="flex flex-wrap gap-2">
           ${res.categories.map(c => `
             <button onclick="navigate('/katalog?cat=${c.id}')"
               class="flex items-center gap-1.5 px-4 py-2 rounded-full border border-gold/30 text-sm text-primary/80 hover:bg-primary hover:text-white hover:border-primary transition-all">
               <i data-lucide="folder" class="w-3.5 h-3.5"></i>
               ${escHtml(c.name)}
               <span class="text-gold text-xs ml-0.5">${c.book_count}</span>
             </button>`).join('')}
         </div>`
      : noResultBlock('Tidak ada kategori yang cocok.');

    // ── Section 2: Nama Kitab ────────────────────────────────
    const booksGrid = res.books.data.length
      ? `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
           ${res.books.data.map(bookCard).join('')}
         </div>
         ${paginationHtml(res.books.page, res.books.total_pages, 'goSearchBookPage')}`
      : noResultBlock('Tidak ada kitab yang cocok pada judul atau pengarang.');

    // ── Section 3: Isi Kitab ─────────────────────────────────
    const contGrid = res.content.data.length
      ? `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
           ${res.content.data.map(b => contentCard(b, q)).join('')}
         </div>
         ${paginationHtml(res.content.page, res.content.total_pages, 'goSearchContPage')}`
      : noResultBlock('Tidak ada kecocokan pada isi kitab.');

    wrap.innerHTML = `
      <!-- SECTION 1 -->
      <div id="sec-cat">
        ${sectionHeader('folder-open', 'Hasil Pencarian Kategori', res.categories.length)}
        ${catHtml}
      </div>

      ${sectionDivider()}

      <!-- SECTION 2 -->
      <div id="sec-books">
        ${sectionHeader('book-open', 'Hasil Pencarian Nama Kitab', res.books.total)}
        ${booksGrid}
      </div>

      ${sectionDivider()}

      <!-- SECTION 3 -->
      <div id="sec-content">
        ${sectionHeader('file-text', 'Hasil Pencarian Isi Kitab', res.content.total)}
        ${contGrid}
      </div>`;

  } catch (err) {
    wrap.innerHTML = `<p class="text-center text-red-500 text-sm py-12">Terjadi kesalahan. Coba lagi.</p>`;
  }

  reicons();
}

window.goSearchBookPage = function(p) {
  searchState.bookPage = p;
  execSearch().then(() => $('#sec-books')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
};
window.goSearchContPage = function(p) {
  searchState.contPage = p;
  execSearch().then(() => $('#sec-content')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
};

// ══════════════════════════════════════════════════════════════
//  PAGE: DETAIL KITAB + READER
// ══════════════════════════════════════════════════════════════

// Reader state (module-level so nav buttons can reference it)
const readerState = { bkid: null, page: 1, total: 0 };

async function renderDetail(params) {
  const id = params.get('id');
  if (!id) { render404(); return; }

  // Reset reader
  readerState.bkid  = parseInt(id);
  readerState.page  = 1;
  readerState.total = 0;

  app().innerHTML = `
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <button onclick="history.back()" class="flex items-center gap-2 text-primary/60 hover:text-primary text-sm mb-6 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
      </button>
      <div id="detail-content">
        <div class="bg-white rounded-3xl shadow-card p-8 space-y-4">
          ${Array.from({length:4}, (_,i) => `<div class="skeleton h-${i===0?7:4} rounded-lg w-${i===0?'3/4':i===1?'2/4':'1/3'}"></div>`).join('')}
        </div>
      </div>
    </div>`;
  reicons();

  try {
    const res  = await apiFetch({ action: 'book', id });
    const book = res.data;
    const title       = book.title       || 'بدون عنوان';
    const author      = book.author      || 'مجهول المؤلف';
    const authorInfo  = book.author_info || '';
    const description = book.description || book.info || '';
    const pages       = book.pages       ? book.pages + ' hal.' : '';
    const catName     = book.cat_name    || book.category_name || '';
    const contentPgs  = book.content_pages || 0;

    readerState.total = contentPgs;

    $('#detail-content').innerHTML = `
      <div class="bg-white rounded-3xl shadow-card overflow-hidden">

        <!-- ── Book Header ── -->
        <div class="hero-bg text-white p-8 md:p-10">
          <div class="arabic text-3xl md:text-4xl font-bold text-white mb-2 leading-tight">${escHtml(title)}</div>
          <div class="text-gold text-base font-medium mt-1">${escHtml(author)}</div>
          <div class="flex flex-wrap gap-3 mt-4">
            ${catName    ? `<span class="px-3 py-1 rounded-full bg-white/10 text-white/80 text-xs">${escHtml(catName)}</span>` : ''}
            ${pages      ? `<span class="px-3 py-1 rounded-full bg-gold/20 text-gold text-xs flex items-center gap-1"><i data-lucide="file-text" class="w-3 h-3"></i>${pages}</span>` : ''}
            ${contentPgs ? `<span class="px-3 py-1 rounded-full bg-white/10 text-white/70 text-xs flex items-center gap-1"><i data-lucide="layers" class="w-3 h-3"></i>${contentPgs} halaman tersedia</span>` : ''}
          </div>
        </div>

        <!-- ── Meta (description / author info) ── -->
        <div class="px-8 md:px-10 pt-8 space-y-5">
          ${description ? `
            <div>
              <h3 class="text-xs font-semibold text-primary/40 uppercase tracking-wider mb-2">Deskripsi</h3>
              <p class="text-primary/75 text-sm leading-relaxed arabic">${escHtml(description)}</p>
            </div>` : ''}
          ${authorInfo ? `
            <div>
              <h3 class="text-xs font-semibold text-primary/40 uppercase tracking-wider mb-2">Tentang Pengarang</h3>
              <p class="text-primary/75 text-sm leading-relaxed arabic">${escHtml(authorInfo)}</p>
            </div>` : ''}
        </div>

        <!-- ── Reader ── -->
        ${contentPgs > 0 ? `
        <div class="px-8 md:px-10 pb-10 mt-8">
          <div class="border-t border-cream-dark pt-6">

            <!-- Reader toolbar -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
              <h3 class="text-sm font-semibold text-primary flex items-center gap-2">
                <i data-lucide="book-open" class="w-4 h-4 text-gold"></i> Baca Kitab
              </h3>
              <div class="flex items-center gap-2 text-sm">
                <span class="text-primary/40 text-xs">Halaman</span>
                <input id="reader-page-input" type="number" min="1" max="${contentPgs}" value="1"
                  class="w-14 text-center border border-gold/30 rounded-lg px-2 py-1 text-sm text-primary focus:outline-none focus:border-gold" />
                <span class="text-primary/40 text-xs">dari ${contentPgs}</span>
              </div>
            </div>

            <!-- Content area -->
            <div id="reader-area"
              class="bg-cream rounded-2xl p-6 md:p-8 min-h-48 text-primary arabic text-lg md:text-xl leading-loose text-right transition-opacity duration-200">
              <div class="flex justify-center py-8">
                <div class="w-8 h-8 border-2 border-gold/30 border-t-gold rounded-full animate-spin" style="border-width:2px"></div>
              </div>
            </div>

            <!-- Nav buttons -->
            <div class="flex items-center justify-between mt-5 gap-3">
              <button id="reader-prev"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gold/30 text-sm font-medium text-primary hover:bg-primary hover:text-white hover:border-primary disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <i data-lucide="chevron-right" class="w-4 h-4"></i> Sebelumnya
              </button>
              <span id="reader-label" class="text-xs text-primary/40"></span>
              <button id="reader-next"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-light disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                Berikutnya <i data-lucide="chevron-left" class="w-4 h-4"></i>
              </button>
            </div>

          </div>
        </div>` : `
        <div class="px-8 md:px-10 pb-10 mt-6">
          <div class="border-t border-cream-dark pt-6 flex items-center gap-3 text-primary/40 text-sm">
            <i data-lucide="info" class="w-4 h-4"></i>
            <span>Konten kitab ini belum tersedia.</span>
          </div>
        </div>`}

      </div>`;

    reicons();

    if (contentPgs > 0) {
      // load first page
      loadReaderPage(readerState.bkid, 1);

      // nav buttons
      $('#reader-prev')?.addEventListener('click', () => {
        if (readerState.page > 1) loadReaderPage(readerState.bkid, readerState.page - 1);
      });
      $('#reader-next')?.addEventListener('click', () => {
        if (readerState.page < readerState.total) loadReaderPage(readerState.bkid, readerState.page + 1);
      });

      // page input jump
      let jumpTimer;
      $('#reader-page-input')?.addEventListener('input', e => {
        clearTimeout(jumpTimer);
        const v = parseInt(e.target.value);
        if (v >= 1 && v <= readerState.total) {
          jumpTimer = setTimeout(() => loadReaderPage(readerState.bkid, v), 600);
        }
      });
      $('#reader-page-input')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
          clearTimeout(jumpTimer);
          const v = parseInt(e.target.value);
          if (v >= 1 && v <= readerState.total) loadReaderPage(readerState.bkid, v);
        }
      });
    }

  } catch {
    $('#detail-content').innerHTML = '<p class="text-center text-red-500 py-12">Kitab tidak ditemukan.</p>';
  }
}

// Load a single page of content into the reader
async function loadReaderPage(bkid, page) {
  const area  = $('#reader-area');
  const label = $('#reader-label');
  const inp   = $('#reader-page-input');
  const prev  = $('#reader-prev');
  const next  = $('#reader-next');
  if (!area) return;

  readerState.page = page;

  // spinner
  area.style.opacity = '0.4';
  area.innerHTML = `<div class="flex justify-center py-8">
    <div class="w-8 h-8 border-2 border-gold/30 border-t-gold rounded-full animate-spin" style="border-width:2px"></div>
  </div>`;

  try {
    const res = await apiFetch({ action: 'content', bkid, page });

    if (res.content) {
      // Render content — preserve line breaks
      // Normalise line endings (\r\n → \n, stray \r → \n) then let
      // white-space: pre-wrap do all the work — no brittle regex needed.
      const normalised = res.content.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
      area.innerHTML = `<div class="reader-text">${escHtml(normalised)}</div>`;
    } else {
      area.innerHTML = `<p class="text-center text-primary/30 py-6 text-sm">Halaman ini kosong.</p>`;
    }

    area.style.opacity = '1';

    // update controls
    if (label) label.textContent = `Halaman ${page} dari ${res.total_pages}`;
    if (inp)   { inp.value = page; inp.max = res.total_pages; }
    if (prev)  prev.disabled = (page <= 1);
    if (next)  next.disabled = (page >= res.total_pages);

    readerState.total = res.total_pages;

    // scroll reader into view smoothly
    area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  } catch {
    area.innerHTML = `<p class="text-center text-red-500 text-sm py-6">Gagal memuat halaman.</p>`;
    area.style.opacity = '1';
  }
  reicons();
}

window.loadReaderPage = loadReaderPage;

// ══════════════════════════════════════════════════════════════
//  PAGE: ABOUT
// ══════════════════════════════════════════════════════════════
function renderAbout() {
  app().innerHTML = `
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <!-- Header -->
      <div class="text-center mb-10">
        <div class="arabic text-primary text-4xl font-bold mb-2">المكتبة السنية</div>
        <div class="text-gold text-sm font-medium tracking-widest uppercase">Al-Maktabah As-Sunniyyah</div>
        <div class="gold-line mt-6 max-w-xs mx-auto"></div>
      </div>

      <div class="bg-white rounded-3xl shadow-card p-8 md:p-10 space-y-8 text-sm leading-relaxed text-primary/80">

        <div>
          <h2 class="text-primary font-bold text-base mb-3 flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4 text-gold"></i> Tentang Kami
          </h2>
          <p>
            <strong>المكتبة السنية</strong> adalah perpustakaan digital Islam yang hadir untuk memudahkan umat dalam mengakses khazanah ilmu Islam, khususnya kitab-kitab dari para ulama salaf. Kami mengumpulkan, menata, dan menyajikan ribuan kitab dalam format digital yang mudah diakses oleh siapa saja, di mana saja, kapan saja — secara <em>gratis</em>.
          </p>
        </div>

        <div>
          <h2 class="text-primary font-bold text-base mb-3 flex items-center gap-2">
            <i data-lucide="eye" class="w-4 h-4 text-gold"></i> Visi
          </h2>
          <p class="arabic text-base text-right text-primary mb-2">"نشر العلم الشرعي وتيسيره للأمة"</p>
          <p>Menjadi portal terdepan dalam menyebarkan ilmu syar'i dan mempermudah akses umat Islam terhadap warisan intelektual para ulama.</p>
        </div>

        <div>
          <h2 class="text-primary font-bold text-base mb-3 flex items-center gap-2">
            <i data-lucide="target" class="w-4 h-4 text-gold"></i> Misi
          </h2>
          <ul class="space-y-2 list-none">
            ${['Menghadirkan kitab-kitab salaf yang otentik dan terpercaya.',
               'Mempermudah pencarian dan akses kitab secara digital.',
               'Mendukung para penuntut ilmu dengan koleksi yang terus berkembang.',
               'Menjaga warisan keilmuan Islam agar tetap lestari dan mudah diakses generasi mendatang.']
              .map(m => `<li class="flex items-start gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-gold mt-0.5 shrink-0"></i><span>${m}</span></li>`).join('')}
          </ul>
        </div>

        <div class="bg-cream rounded-2xl p-6 text-center">
          <div class="arabic text-primary text-lg font-bold mb-1">طلب العلم فريضة على كل مسلم</div>
          <div class="text-primary/50 text-xs">HR. Ibnu Mājah — Menuntut ilmu adalah kewajiban setiap Muslim</div>
        </div>

        <div>
          <h2 class="text-primary font-bold text-base mb-3 flex items-center gap-2">
            <i data-lucide="globe" class="w-4 h-4 text-gold"></i> Kontak & Akses
          </h2>
          <p>Anda dapat mengakses perpustakaan ini di: <a href="https://lib.quizb.my.id" class="text-gold hover:underline font-medium">lib.quizb.my.id</a></p>
        </div>

      </div>
    </div>`;
  reicons();
}

// ══════════════════════════════════════════════════════════════
//  PAGE: 404
// ══════════════════════════════════════════════════════════════
function render404() {
  app().innerHTML = `
    <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
      <div class="arabic text-8xl font-bold text-primary/10 mb-4">٤٠٤</div>
      <h1 class="text-2xl font-bold text-primary mb-2">Halaman Tidak Ditemukan</h1>
      <p class="text-primary/50 text-sm mb-6">Maaf, halaman yang Anda cari tidak tersedia.</p>
      <a href="/" data-route="/" class="px-6 py-3 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary-light transition-colors">Kembali ke Beranda</a>
    </div>`;
  reicons();
}

// expose navigate globally (used in onclick attributes)
window.navigate = navigate;
