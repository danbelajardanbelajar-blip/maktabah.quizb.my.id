/* =============================================================
   Al-Maktabah As-Sunniyyah — SPA Core (app.js)
   Vanilla JS · History API · Fetch API
   ============================================================= */

'use strict';

// ── Config ────────────────────────────────────────────────────
const API = '/api.php';

// ── Reader font configuration ─────────────────────────────────
const FONTS_LATIN = [
  { key: 'Lato',            label: 'Lato' },
  { key: 'Inter',           label: 'Inter' },
  { key: 'Roboto',          label: 'Roboto' },
  { key: 'Open Sans',       label: 'Open Sans' },
  { key: 'Poppins',         label: 'Poppins' },
  { key: 'Nunito',          label: 'Nunito' },
  { key: 'Raleway',         label: 'Raleway' },
  { key: 'Merriweather',    label: 'Merriweather' },
  { key: 'Playfair Display',label: 'Playfair Display' },
  { key: 'Source Sans 3',   label: 'Source Sans 3' },
];
const FONTS_ARABIC = [
  { key: 'Amiri',                label: 'أميري — Amiri' },
  { key: 'Noto Naskh Arabic',    label: 'نوتو نسخ' },
  { key: 'Cairo',                label: 'القاهرة — Cairo' },
  { key: 'Tajawal',              label: 'تجوّل — Tajawal' },
  { key: 'Scheherazade New',     label: 'شهرزاد' },
  { key: 'Reem Kufi',            label: 'ريم كوفي' },
  { key: 'Lateef',               label: 'لطيف — Lateef' },
  { key: 'Aref Ruqaa',           label: 'عارف رقعة' },
  { key: 'El Messiri',           label: 'المسيري' },
  { key: 'IBM Plex Sans Arabic', label: 'IBM عربي' },
];

// Load saved preferences from localStorage
const _rfDef = { latin: 'Lato', arabic: 'Amiri', size: 18 };
const readerFontState = Object.assign({}, _rfDef,
  JSON.parse(localStorage.getItem('readerFonts') || '{}'));

function applyReaderFont(save = true) {
  const root = document.documentElement;
  root.style.setProperty('--font-r-latin',  `'${readerFontState.latin}', sans-serif`);
  root.style.setProperty('--font-r-arabic', `'${readerFontState.arabic}', 'Amiri', serif`);
  root.style.setProperty('--font-r-size',   readerFontState.size + 'px');
  if (save) localStorage.setItem('readerFonts', JSON.stringify(readerFontState));
}
// Apply on page load
applyReaderFont(false);


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
  '/':          renderHome,
  '/katalog':   renderKatalog,
  '/about':     renderAbout,
  '/search':    renderSearch,
  '/kitab':     renderDetail,
  '/dashboard': renderDashboard,
  '/admin':     renderAdmin,
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
//  PAGE: SEARCH

// Per-section abort controllers
let _abortCat = null, _abortBooks = null, _abortContent = null;
function abortAll() {
  [_abortCat, _abortBooks, _abortContent].forEach(c => { try { c && c.abort(); } catch(_){} });
  _abortCat = _abortBooks = _abortContent = null;
}

// searchState declared here (used by renderSearch, execSearch, pagination)
const searchState = { q: '', bookPage: 1, contPage: 1 };

// ── Render search page ───────────────────────────────────────
function renderSearch(params) {
  searchState.q        = params.get('q') || '';
  searchState.bookPage = 1;
  searchState.contPage = 1;

  app().innerHTML = `
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="max-w-2xl mx-auto mb-8">
        <div class="relative group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-primary/35 transition-colors group-focus-within:text-gold"></i>
          <input id="search-input" type="text" value="${escHtml(searchState.q)}"
            placeholder="Cari kategori, judul, atau isi kitab…"
            class="search-input-premium w-full pl-12 pr-12 py-4 rounded-2xl border border-gold/30 bg-white text-sm focus:outline-none focus:border-gold shadow-card" />
          <button id="search-clear" class="absolute right-4 top-1/2 -translate-y-1/2 text-primary/30 hover:text-primary transition-colors ${searchState.q ? '' : 'hidden'}">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
        <p class="mt-3 text-xs text-primary/50">Pencarian premium: gunakan tanda kutip untuk frasa persis — hasil cepat, akurat, dan modern.</p>
        <div id="search-stats" class="search-stats"></div>
      </div>
      <div id="search-results">
        ${searchState.q.length >= 2 ? skeletonSearchSections() : emptySearchPrompt()}
      </div>
    </div>`;

  reicons();
  const inp = $('#search-input'), clr = $('#search-clear');

  clr?.addEventListener('click', () => {
    inp.value = ''; searchState.q = '';
    abortAll(); clr.classList.add('hidden');
    $('#search-results').innerHTML = emptySearchPrompt();
    $('#search-stats').innerHTML = '';
    reicons(); inp.focus();
  });

  let timer;
  inp?.addEventListener('input', e => {
    clearTimeout(timer);
    searchState.q = e.target.value.trim();
    searchState.bookPage = searchState.contPage = 1;
    clr?.classList.toggle('hidden', !searchState.q);
    if (searchState.q.length >= 2) timer = setTimeout(execSearch, 300);
    else if (!searchState.q) { abortAll(); $('#search-results').innerHTML = emptySearchPrompt(); $('#search-stats').innerHTML = ''; reicons(); }
  });
  inp?.addEventListener('keydown', e => { if (e.key === 'Enter') { clearTimeout(timer); execSearch(); } });

  if (searchState.q.length >= 2) execSearch();
}

// ── Skeleton shells ──────────────────────────────────────────
function skeletonSearchSections() {
  const skel = n => `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${skeletonCards(n)}</div>`;
  const skelSec = (icon, label, bodyHtml) => `
    <div class="mb-2">
      <div class="sec-header">
        <i data-lucide="${icon}" class="w-4 h-4 text-gold shrink-0"></i>
        <span class="text-xs font-bold text-primary uppercase tracking-wider">${label}</span>
        <span class="spin-ring ml-auto"></span>
      </div>
      ${bodyHtml}
    </div>`;
  return skelSec('folder-open','Kategori',`<div class="flex flex-wrap gap-2">${Array.from({length:4},()=>`<div class="skeleton h-9 w-28 rounded-full"></div>`).join('')}</div>`) +
    sectionDivider() + skelSec('book-open','Judul Kitab', skel(4)) +
    sectionDivider() + skelSec('file-text','Isi Kitab', skel(4));
}
// ── Empty state (premium) ─────────────────────────────────────
function emptySearchPrompt() {
  return `<div class="flex flex-col items-center py-20 gap-4">
    <div class="w-20 h-20 rounded-full bg-primary/5 flex items-center justify-center">
      <i data-lucide="search" class="w-9 h-9 text-primary/20"></i>
    </div>
    <p class="text-primary/35 text-sm font-medium">Masukkan kata kunci untuk mencari</p>
    <p class="text-primary/20 text-xs">Minimal 2 karakter</p>
  </div>`;
}

// ── Gold divider ──────────────────────────────────────────────
function sectionDivider() {
  return `<div class="flex items-center gap-3 my-8">
    <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gold/30 to-transparent"></div>
  </div>`;
}

// ── Section header (supports loading spinner) ─────────────────
function sectionHeader(icon, label, total, loading) {
  const badge = (total !== null && total !== undefined)
    ? `<span class="sec-badge">${Number(total).toLocaleString('id-ID')}</span>` : '';
  const spin  = loading ? `<span class="spin-ring ml-auto"></span>` : '';
  return `<div class="sec-header">
    <i data-lucide="${icon}" class="w-4 h-4 text-gold shrink-0"></i>
    <h2 class="text-xs font-bold text-primary uppercase tracking-wider">${label}</h2>
    ${badge}${spin}
  </div>`;
}

// ── No-result block ───────────────────────────────────────────
function noResultBlock(msg) {
  return `<p class="text-primary/35 text-sm py-3 flex items-center gap-2">
    <i data-lucide="minus-circle" class="w-4 h-4 shrink-0"></i>${msg}
  </p>`;
}

// ── Keyword highlight helper ──────────────────────────────────
function hlText(text, q) {
  if (!q || !text) return escHtml(text || '');
  
  // Extract phrase from quotes if present
  let searchTerm = q;
  const phraseMatch = q.match(/^"(.+)"$/);
  if (phraseMatch) {
    searchTerm = phraseMatch[1];
  }
  
  const safe = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return escHtml(text).replace(new RegExp('(' + safe + ')', 'gi'), '<mark class="hl">$1</mark>');
}

// ── Book card with stagger animation ─────────────────────────
function bookCardStagger(b, i, q = '') {
  const title  = b.title || 'بدون عنوان';
  const author = b.author || 'مجهول';
  const cat    = b.category_name || '';
  const pages  = b.pages ? b.pages + ' hal.' : '';
  const titleHtml  = hlText(title, q);
  const authorHtml = author ? hlText(author, q) : '';
  return `
    <div class="book-card search-card-stagger bg-white rounded-2xl shadow-card p-4 flex flex-col gap-2 cursor-pointer border border-transparent hover:border-gold/30 hover:shadow-[0_16px_40px_rgba(201,168,76,.12)] transition-all"
         style="animation-delay:${i*40}ms" onclick="navigate('/kitab?id=${b.bkid}')">
      <div class="arabic text-primary font-semibold text-sm leading-snug line-clamp-2">${titleHtml}</div>
      <div class="text-primary/55 text-xs line-clamp-1">${authorHtml}</div>
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        ${cat   ? `<span class="text-xs bg-primary/8 text-primary/60 px-2 py-0.5 rounded-full truncate max-w-[60%]">${escHtml(cat)}</span>` : '<span></span>'}
        ${pages ? `<span class="text-xs text-gold font-medium">${pages}</span>` : ''}
      </div>
    </div>`;
}

// ── Content card with snippet highlight ──────────────────────
function contentCard(b, q) {
  const title   = b.title         || 'بدون عنوان';
  const author  = b.author        || '';
  const cat     = b.category_name || '';
  const snippet = b.snippet       || '';
  const page    = b.match_page    ? `hal. ${b.match_page}` : '';
  const titleHtml  = hlText(title, q);
  const authorHtml = author ? hlText(author, q) : '';
  const hlSnip  = snippet ? hlText(snippet, q) : '';
  // Include match_page and query so reader opens on the correct page with highlight
  const pageParam = b.match_page ? `&page=${b.match_page}` : '';
  const qParam    = q ? `&q=${encodeURIComponent(q)}` : '';
  return `
    <div class="book-card bg-white rounded-2xl shadow-card p-4 flex flex-col gap-2 cursor-pointer border border-transparent hover:border-gold/30 hover:shadow-[0_16px_40px_rgba(201,168,76,.12)] transition-all"
         onclick="navigate('/kitab?id=${b.bkid}${pageParam}${qParam}')">
      <div class="arabic text-primary font-semibold text-sm leading-snug line-clamp-2">${titleHtml}</div>
      ${authorHtml ? `<div class="text-primary/55 text-xs line-clamp-1">${authorHtml}</div>` : ''}
      ${hlSnip ? `<div class="snippet-bar reader-text line-clamp-3">${hlSnip}…</div>` : ''}
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        ${cat  ? `<span class="text-xs text-primary/50 truncate max-w-[65%]">${escHtml(cat)}</span>` : '<span></span>'}
        ${page ? `<span class="text-xs text-gold font-medium flex items-center gap-1"><i data-lucide="bookmark" class="w-3 h-3"></i>${page}</span>` : ''}
      </div>
    </div>`;
}


// ── Patch a section header in place ──────────────────────────
function patchHeader(secId, icon, label, total) {
  const hdr = $(`#${secId} .sec-header`);
  if (hdr) { hdr.outerHTML = sectionHeader(icon, label, total, false); reicons(); }
}

// ── Main executor — 3 parallel fetches, progressive render ────
async function execSearch() {
  const wrap = $('#search-results');
  if (!wrap || searchState.q.length < 2) return;

  abortAll();
  const q  = searchState.q;
  const t0 = performance.now();
  history.replaceState({}, '', '/search?q=' + encodeURIComponent(q));

  // Render instant skeleton shells
  wrap.innerHTML = `
    <div id="sec-cat" class="mb-2">
      ${sectionHeader('folder-open','Kategori', null, true)}
      <div id="sec-cat-body"><div class="flex flex-wrap gap-2">
        ${Array.from({length:5},()=>`<div class="skeleton h-9 w-28 rounded-full"></div>`).join('')}
      </div></div>
    </div>
    ${sectionDivider()}
    <div id="sec-books" class="mb-2">
      ${sectionHeader('book-open','Judul Kitab', null, true)}
      <div id="sec-books-body"><div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${skeletonCards(4)}</div></div>
    </div>
    ${sectionDivider()}
    <div id="sec-content">
      ${sectionHeader('file-text','Isi Kitab', null, true)}
      <div id="sec-content-body"><div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${skeletonCards(4)}</div></div>
    </div>`;
  reicons();

  let done = 0;
  const onDone = () => {
    if (++done === 3) {
      const ms = Math.round(performance.now() - t0);
      const st = $('#search-stats');
      if (st) { st.innerHTML = `<i data-lucide="zap" class="w-3 h-3 text-gold"></i> ${ms} ms`; reicons(); }
    }
  };

  // 1. Kategori
  _abortCat = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_categories', q}), {signal:_abortCat.signal})
    .then(r=>r.json()).then(res => {
      if ($('#search-input')?.value.trim() !== q) return;
      patchHeader('sec-cat','folder-open','Kategori', res.data.length);
      const body = $('#sec-cat-body');
      if (!body) return;
      body.innerHTML = res.data.length
        ? `<div class="search-section-enter flex flex-wrap gap-2">
            ${res.data.map((c,i)=>`<button class="cat-chip" style="animation-delay:${i*35}ms" onclick="navigate('/katalog?cat=${c.id}')">
              <i data-lucide="folder" class="w-3.5 h-3.5 text-gold shrink-0"></i>
              ${escHtml(c.name)}<span class="text-gold/80 text-xs font-bold">${c.book_count}</span>
            </button>`).join('')}</div>`
        : noResultBlock('Tidak ada kategori yang cocok.');
      reicons();
    }).catch(()=>{ const b=$('#sec-cat-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onDone);

  // 2. Judul Kitab
  _abortBooks = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_books', q, page:searchState.bookPage}), {signal:_abortBooks.signal})
    .then(r=>r.json()).then(res => {
      if ($('#search-input')?.value.trim() !== q) return;
      patchHeader('sec-books','book-open','Judul Kitab', res.total);
      const body = $('#sec-books-body');
      if (!body) return;
      body.innerHTML = res.data.length
        ? `<div class="search-section-enter">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${res.data.map((b,i)=>bookCardStagger(b,i,q)).join('')}</div>
            ${paginationHtml(res.page, res.total_pages, 'goSearchBookPage')}</div>`
        : noResultBlock('Tidak ada kitab yang cocok pada judul atau pengarang.');
      reicons();
    }).catch(()=>{ const b=$('#sec-books-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onDone);

  // 3. Isi Kitab
  _abortContent = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_content', q, page:searchState.contPage}), {signal:_abortContent.signal})
    .then(r=>r.json()).then(res => {
      if ($('#search-input')?.value.trim() !== q) return;
      patchHeader('sec-content','file-text','Isi Kitab', res.total);
      const body = $('#sec-content-body');
      if (!body) return;
      body.innerHTML = res.data.length
        ? `<div class="search-section-enter">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${res.data.map((b,i)=>contentCard(b,q)).join('')}</div>
            ${paginationHtml(res.page, res.total_pages, 'goSearchContPage')}</div>`
        : noResultBlock('Tidak ada kecocokan pada isi kitab.');
      reicons();
    }).catch(()=>{ const b=$('#sec-content-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onDone);
}

window.goSearchBookPage = function(p) {
  searchState.bookPage = p;
  const q = searchState.q, body = $('#sec-books-body');
  if (body) body.innerHTML = `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${skeletonCards(4)}</div>`;
  _abortBooks = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_books', q, page:p}), {signal:_abortBooks.signal})
    .then(r=>r.json()).then(res => {
      patchHeader('sec-books','book-open','Judul Kitab', res.total);
      if (!body) return;
      body.innerHTML = res.data.length
        ? `<div class="search-section-enter"><div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${res.data.map((b,i)=>bookCardStagger(b,i,q)).join('')}</div>${paginationHtml(res.page,res.total_pages,'goSearchBookPage')}</div>`
        : noResultBlock('Tidak ada hasil.');
      reicons(); $('#sec-books')?.scrollIntoView({behavior:'smooth',block:'start'});
    }).catch(()=>{});
};

window.goSearchContPage = function(p) {
  searchState.contPage = p;
  const q = searchState.q, body = $('#sec-content-body');
  if (body) body.innerHTML = `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${skeletonCards(4)}</div>`;
  _abortContent = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_content', q, page:p}), {signal:_abortContent.signal})
    .then(r=>r.json()).then(res => {
      patchHeader('sec-content','file-text','Isi Kitab', res.total);
      if (!body) return;
      body.innerHTML = res.data.length
        ? `<div class="search-section-enter"><div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${res.data.map((b,i)=>contentCard(b,q)).join('')}</div>${paginationHtml(res.page,res.total_pages,'goSearchContPage')}</div>`
        : noResultBlock('Tidak ada hasil.');
      reicons(); $('#sec-content')?.scrollIntoView({behavior:'smooth',block:'start'});
    }).catch(()=>{});
};

// ══════════════════════════════════════════════════════════════
//  PAGE: DETAIL KITAB + READER
// ══════════════════════════════════════════════════════════════

// Reader state (module-level so nav buttons can reference it)
const readerState = { bkid: null, page: 1, total: 0, searchQ: '' };

async function renderDetail(params) {
  const id       = params.get('id');
  const jumpPage = parseInt(params.get('page') || '1') || 1;
  const searchQ  = (params.get('q') || '').trim();
  if (!id) { render404(); return; }

  // Reset reader
  readerState.bkid  = parseInt(id);
  readerState.page  = 1;
  readerState.total = 0;
  readerState.searchQ = searchQ;


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
                <!-- Font Settings Gear Button -->
                <button id="font-settings-btn" title="Pengaturan Font"
                  class="ml-2 p-1.5 rounded-lg border border-gold/20 hover:bg-gold/10 hover:border-gold/40 transition-all text-primary/50 hover:text-primary">
                  <i data-lucide="settings-2" class="w-4 h-4"></i>
                </button>
              </div>
            </div>

            <!-- Font settings panel (slide-down) -->
            <div id="font-panel-wrap">${renderFontPanel()}</div>

            <!-- Content area — direction & font controlled by CSS vars + unicode-bidi -->
            <div id="reader-area"
              class="bg-cream rounded-2xl p-6 md:p-8 min-h-48 text-primary leading-loose transition-opacity duration-200">
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
      // Wire up font settings gear button
      $('#font-settings-btn')?.addEventListener('click', () => {
        const panel = $('#font-panel');
        if (panel) panel.classList.toggle('open');
      });
      // Wire up font size slider
      initFontPanelEvents();

      // Open at jump page (from search result), with keyword highlight
      loadReaderPage(readerState.bkid, jumpPage, searchQ);

      // If arriving from search, show a dismissible info banner above reader
      if (searchQ) {
        setTimeout(() => {
          const readerWrap = $('#reader-area')?.parentElement;
          if (readerWrap) {
            const banner = document.createElement('div');
            banner.id = 'search-jump-banner';
            banner.className = 'flex items-center gap-2 mb-4 px-3 py-2 rounded-xl bg-gold/10 border border-gold/20 text-xs text-primary/70';
            banner.innerHTML = `<i data-lucide="search" class="w-3.5 h-3.5 text-gold shrink-0"></i>
              Ditemukan di hal. <strong class="text-primary mx-1">${jumpPage}</strong> &mdash; kata kunci: <mark class="hl mx-1">${escHtml(searchQ)}</mark>
              <button onclick="document.getElementById('search-jump-banner').remove()" class="ml-auto text-primary/30 hover:text-primary transition-colors"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>`;
            readerWrap.prepend(banner);
            reicons();
          }
        }, 100);
      }


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
// highlightQ: optional keyword to highlight in gold
async function loadReaderPage(bkid, page, highlightQ = '') {
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
      const normalised = res.content.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
      // Apply keyword highlight when coming from search, otherwise plain text
      const rendered = highlightQ ? hlText(normalised, highlightQ) : escHtml(normalised);
      area.innerHTML = `<div class="reader-text">${rendered}</div>`;
      // Auto-scroll to first highlighted word
      if (highlightQ) {
        setTimeout(() => {
          const first = area.querySelector('mark.hl');
          if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
      }

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

// ── Font Settings Panel renderer ──────────────────────────────
function renderFontPanel() {
  const latinChips = FONTS_LATIN.map(f => `
    <button class="font-chip ${readerFontState.latin === f.key ? 'active' : ''}"
      style="font-family:'${f.key}',sans-serif"
      onclick="window._setLatinFont('${f.key}')">${f.label}</button>`).join('');

  const arabicChips = FONTS_ARABIC.map(f => `
    <button class="font-chip ar ${readerFontState.arabic === f.key ? 'active' : ''}"
      style="font-family:'${f.key}','Amiri',serif"
      onclick="window._setArabicFont('${f.key}')">${f.label}</button>`).join('');

  return `
    <div class="font-panel" id="font-panel">
      <div class="bg-white border border-gold/20 rounded-2xl p-5 shadow-card mb-4">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
          <!-- Latin LTR -->
          <div>
            <div class="flex items-center gap-2 mb-3">
              <i data-lucide="type" class="w-3.5 h-3.5 text-gold shrink-0"></i>
              <span class="text-xs font-bold text-primary/50 uppercase tracking-wider">Font Latin (LTR)</span>
            </div>
            <div class="grid grid-cols-2 gap-1.5">${latinChips}</div>
          </div>
          <!-- Arabic RTL -->
          <div>
            <div class="flex items-center gap-2 mb-3">
              <i data-lucide="type" class="w-3.5 h-3.5 text-gold shrink-0"></i>
              <span class="text-xs font-bold text-primary/50 uppercase tracking-wider">فونت عربي (RTL)</span>
            </div>
            <div class="grid grid-cols-2 gap-1.5">${arabicChips}</div>
          </div>
        </div>

        <!-- Size slider -->
        <div class="border-t border-cream-dark pt-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-primary/50 uppercase tracking-wider flex items-center gap-1">
              <i data-lucide="a-large-small" class="w-3.5 h-3.5 text-gold"></i> Ukuran Teks
            </span>
            <span id="font-size-label" class="text-xs font-bold text-gold">${readerFontState.size}px</span>
          </div>
          <input type="range" class="font-range" id="font-size-slider"
            min="14" max="28" step="1" value="${readerFontState.size}">
          <div class="flex justify-between text-[10px] text-primary/25 mt-1">
            <span>A</span><span style="font-size:14px">A</span>
          </div>
        </div>

      </div>
    </div>`;
}

// Font setter globals (called from onclick in font chips)
window._setLatinFont = function(key) {
  readerFontState.latin = key;
  applyReaderFont();
  // re-render panel to update active chips
  const panel = $('#font-panel');
  if (panel) { const parent = panel.parentElement; parent.innerHTML = renderFontPanel(); initFontPanelEvents(); reicons(); panel.classList.add('open'); $('#font-panel').classList.add('open'); }
};
window._setArabicFont = function(key) {
  readerFontState.arabic = key;
  applyReaderFont();
  const panel = $('#font-panel');
  if (panel) { const parent = panel.parentElement; parent.innerHTML = renderFontPanel(); initFontPanelEvents(); reicons(); $('#font-panel').classList.add('open'); }
};

function initFontPanelEvents() {
  const slider = $('#font-size-slider');
  const lbl    = $('#font-size-label');
  if (!slider) return;
  slider.addEventListener('input', () => {
    readerFontState.size = parseInt(slider.value);
    if (lbl) lbl.textContent = readerFontState.size + 'px';
    applyReaderFont();
  });
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

// ══════════════════════════════════════════════════════════════
//  PAGE: DASHBOARD  (semua user yang sudah login)
// ══════════════════════════════════════════════════════════════
function renderDashboard() {
  const user = window.SESSION_USER;
  if (!user) {
    // Belum login — redirect ke login Google
    window.location.href = '/auth.php?action=login';
    return;
  }

  const isAdmin = user.role === 'admin';
  app().innerHTML = `
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
      <!-- Welcome card -->
      <div class="bg-white rounded-2xl shadow-card p-6 mb-8 flex items-center gap-5">
        ${user.picture
          ? `<img src="${escHtml(user.picture)}" class="w-16 h-16 rounded-full object-cover border-4 border-gold/30 shadow" />`
          : `<div class="w-16 h-16 rounded-full bg-primary flex items-center justify-center text-white text-2xl font-bold shadow">${escHtml(user.name.charAt(0).toUpperCase())}</div>`
        }
        <div>
          <div class="text-xs text-primary/40 font-medium uppercase tracking-wider mb-1">Selamat datang kembali</div>
          <h1 class="text-xl font-bold text-primary">${escHtml(user.name)}</h1>
          <div class="text-sm text-primary/50 mt-0.5">${escHtml(user.email)}</div>
          <span class="inline-block mt-2 px-3 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
            ${isAdmin ? 'bg-gold/15 text-yellow-700' : 'bg-primary/8 text-primary/60'}">
            ${isAdmin ? '👑 Admin' : '👤 User'}
          </span>
        </div>
      </div>

      <!-- Menu cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        <a href="/katalog" data-route="/katalog"
           class="book-card bg-white rounded-2xl shadow-card p-5 flex items-center gap-4 no-underline">
          <div class="w-12 h-12 rounded-xl bg-primary/8 flex items-center justify-center">
            <i data-lucide="library" class="w-6 h-6 text-primary"></i>
          </div>
          <div>
            <div class="font-semibold text-primary text-sm">Katalog Kitab</div>
            <div class="text-xs text-primary/50 mt-0.5">Jelajahi seluruh koleksi</div>
          </div>
        </a>

        <a href="/search" data-route="/search"
           class="book-card bg-white rounded-2xl shadow-card p-5 flex items-center gap-4 no-underline">
          <div class="w-12 h-12 rounded-xl bg-primary/8 flex items-center justify-center">
            <i data-lucide="search" class="w-6 h-6 text-primary"></i>
          </div>
          <div>
            <div class="font-semibold text-primary text-sm">Cari Kitab</div>
            <div class="text-xs text-primary/50 mt-0.5">Cari judul, pengarang, atau isi</div>
          </div>
        </a>

        ${isAdmin ? `
        <a href="/admin" data-route="/admin"
           class="book-card bg-white rounded-2xl shadow-card p-5 flex items-center gap-4 no-underline col-span-full sm:col-span-1 border-2 border-gold/30">
          <div class="w-12 h-12 rounded-xl bg-gold/10 flex items-center justify-center">
            <i data-lucide="shield-check" class="w-6 h-6 text-yellow-700"></i>
          </div>
          <div>
            <div class="font-semibold text-yellow-800 text-sm">Panel Admin</div>
            <div class="text-xs text-yellow-700/60 mt-0.5">Kelola kitab, kategori & konten</div>
          </div>
        </a>` : ''}
      </div>

      <!-- Logout -->
      <div class="text-center">
        <a href="/auth.php?action=logout"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-red-200 text-red-500 text-sm font-medium hover:bg-red-50 transition-colors">
          <i data-lucide="log-out" class="w-4 h-4"></i> Keluar dari Akun
        </a>
      </div>
    </div>`;
  reicons();
}

// ══════════════════════════════════════════════════════════════
//  PAGE: ADMIN PANEL  (hanya admin)
// ══════════════════════════════════════════════════════════════

// State admin panel
const adminState = {
  tab: 'books',       // 'books' | 'categories' | 'content'
  bookPage: 1,
  catPage: 1,
  editBook: null,
  editCat: null,
  contentBkid: null,
  contentPage: 1,
};

async function renderAdmin() {
  const user = window.SESSION_USER;
  if (!user || user.role !== 'admin') {
    app().innerHTML = `
      <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
        <i data-lucide="shield-alert" class="w-16 h-16 text-red-300 mb-4"></i>
        <h1 class="text-xl font-bold text-primary mb-2">Akses Ditolak</h1>
        <p class="text-primary/50 text-sm mb-6">Halaman ini hanya untuk admin.</p>
        <a href="/" data-route="/" class="px-6 py-3 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary-light transition-colors">Kembali</a>
      </div>`;
    reicons(); return;
  }

  app().innerHTML = `
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
      <!-- Header -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-primary flex items-center gap-2">
            <i data-lucide="shield-check" class="w-6 h-6 text-yellow-600"></i>
            Panel Admin
          </h1>
          <p class="text-primary/50 text-sm mt-1">Kelola kitab, kategori, dan konten perpustakaan</p>
        </div>
        <a href="/dashboard" data-route="/dashboard"
           class="text-sm text-primary/60 hover:text-primary flex items-center gap-1 transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4"></i> Dashboard
        </a>
      </div>

      <!-- Tabs -->
      <div class="flex gap-2 mb-8 border-b border-gold/20 pb-0">
        <button onclick="adminSwitchTab('books')" id="tab-books"
          class="admin-tab px-5 py-2.5 text-sm font-semibold rounded-t-xl border-b-2 transition-colors">
          <i data-lucide="book" class="w-4 h-4 inline mr-1.5"></i>Kitab
        </button>
        <button onclick="adminSwitchTab('categories')" id="tab-categories"
          class="admin-tab px-5 py-2.5 text-sm font-semibold rounded-t-xl border-b-2 transition-colors">
          <i data-lucide="folder" class="w-4 h-4 inline mr-1.5"></i>Kategori
        </button>
        <button onclick="adminSwitchTab('content')" id="tab-content"
          class="admin-tab px-5 py-2.5 text-sm font-semibold rounded-t-xl border-b-2 transition-colors">
          <i data-lucide="file-text" class="w-4 h-4 inline mr-1.5"></i>Konten
        </button>
      </div>

      <!-- Tab content -->
      <div id="admin-tab-content"></div>
    </div>`;

  reicons();
  adminSwitchTab(adminState.tab);
}

function adminSwitchTab(tab) {
  adminState.tab = tab;
  // Style tabs
  ['books','categories','content'].forEach(t => {
    const btn = document.getElementById('tab-' + t);
    if (!btn) return;
    if (t === tab) {
      btn.className = 'admin-tab px-5 py-2.5 text-sm font-semibold rounded-t-xl border-b-2 border-gold text-primary bg-white transition-colors';
    } else {
      btn.className = 'admin-tab px-5 py-2.5 text-sm font-semibold rounded-t-xl border-b-2 border-transparent text-primary/50 hover:text-primary transition-colors';
    }
  });

  if (tab === 'books')      adminLoadBooks();
  if (tab === 'categories') adminLoadCategories();
  if (tab === 'content')    adminLoadContentSelector();
}

// ── Admin: Kitab ──────────────────────────────────────────────

async function adminLoadBooks() {
  const container = document.getElementById('admin-tab-content');
  container.innerHTML = `<div class="skeleton h-10 rounded-xl mb-4"></div>${skeletonCards(6)}`;

  try {
    const [booksRes, catsRes] = await Promise.all([
      apiFetch({ action: 'books', limit: 24, page: adminState.bookPage }),
      apiFetch({ action: 'categories' }),
    ]);

    const cats = catsRes.data || [];

    container.innerHTML = `
      <!-- Add/Edit Form -->
      <div class="bg-white rounded-2xl shadow-card p-6 mb-6">
        <h3 class="font-bold text-primary mb-4 flex items-center gap-2">
          <i data-lucide="plus-circle" class="w-5 h-5 text-gold"></i>
          <span id="book-form-title">Tambah Kitab Baru</span>
        </h3>
        <form id="book-form" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <input type="hidden" id="bf-bkid" value="" />
          <div class="sm:col-span-2">
            <label class="text-xs font-semibold text-primary/60 mb-1 block">Judul Kitab *</label>
            <input id="bf-title" type="text" placeholder="عنوان الكتاب"
              class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 font-arabic" />
          </div>
          <div>
            <label class="text-xs font-semibold text-primary/60 mb-1 block">Pengarang</label>
            <input id="bf-author" type="text" placeholder="اسم المؤلف"
              class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20" />
          </div>
          <div>
            <label class="text-xs font-semibold text-primary/60 mb-1 block">Kategori</label>
            <select id="bf-cat" class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold bg-white">
              <option value="">— Pilih Kategori —</option>
              ${cats.map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('')}
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-primary/60 mb-1 block">Bahasa (iso)</label>
            <select id="bf-iso" class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold bg-white">
              <option value="ar">ar — Arab</option>
              <option value="id">id — Indonesia</option>
              <option value="en">en — Inggris</option>
            </select>
          </div>
          <div class="sm:col-span-2 flex gap-3">
            <button type="submit"
              class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors flex items-center gap-2">
              <i data-lucide="save" class="w-4 h-4"></i> Simpan Kitab
            </button>
            <button type="button" onclick="adminResetBookForm()"
              class="px-5 py-2.5 rounded-xl border border-gold/30 text-sm text-primary/70 hover:bg-cream-dark transition-colors">
              Reset
            </button>
          </div>
        </form>
        <div id="book-form-msg" class="mt-3 text-sm hidden"></div>
      </div>

      <!-- Books table -->
      <div class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-cream-dark flex items-center justify-between">
          <h3 class="font-bold text-primary">Daftar Kitab</h3>
          <span class="text-xs text-primary/40">${booksRes.total} kitab total</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-cream-dark text-primary/60 text-xs">
                <th class="px-4 py-3 text-left font-semibold">ID</th>
                <th class="px-4 py-3 text-left font-semibold">Judul</th>
                <th class="px-4 py-3 text-left font-semibold hidden sm:table-cell">Pengarang</th>
                <th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Kategori</th>
                <th class="px-4 py-3 text-center font-semibold">Aksi</th>
              </tr>
            </thead>
            <tbody id="books-tbody">
              ${booksRes.data.map(b => adminBookRow(b, cats)).join('')}
            </tbody>
          </table>
        </div>
        ${paginationHtml(booksRes.page, booksRes.total_pages, 'adminBooksGoPage')}
      </div>`;

    reicons();

    // Form submit
    document.getElementById('book-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      await adminSubmitBook(cats);
    });

  } catch(err) {
    container.innerHTML = `<div class="text-red-500 text-sm p-4">Gagal memuat data: ${err.message}</div>`;
  }
}

function adminBookRow(b, cats) {
  return `<tr class="border-b border-cream-dark hover:bg-cream/50 transition-colors">
    <td class="px-4 py-3 text-primary/40 font-mono text-xs">#${b.bkid}</td>
    <td class="px-4 py-3">
      <div class="arabic font-medium text-primary line-clamp-1">${escHtml(b.title)}</div>
    </td>
    <td class="px-4 py-3 text-primary/60 text-xs hidden sm:table-cell line-clamp-1">${escHtml(b.author || '—')}</td>
    <td class="px-4 py-3 hidden md:table-cell">
      ${b.category_name ? `<span class="px-2 py-0.5 bg-primary/8 text-primary/60 rounded-full text-xs">${escHtml(b.category_name)}</span>` : '<span class="text-primary/30 text-xs">—</span>'}
    </td>
    <td class="px-4 py-3">
      <div class="flex items-center justify-center gap-2">
        <button onclick="adminEditBook(${JSON.stringify(b).replace(/"/g,'&quot;')})"
          class="p-1.5 rounded-lg hover:bg-primary/8 text-primary/60 hover:text-primary transition-colors" title="Edit">
          <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
        </button>
        <button onclick="adminGoContent(${b.bkid})"
          class="p-1.5 rounded-lg hover:bg-gold/10 text-gold/60 hover:text-yellow-700 transition-colors" title="Edit Konten">
          <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
        </button>
        <button onclick="adminDeleteBook(${b.bkid}, '${escHtml(b.title).replace(/'/g,"\\'")}' )"
          class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" title="Hapus">
          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
        </button>
      </div>
    </td>
  </tr>`;
}

window.adminBooksGoPage = function(p) {
  adminState.bookPage = p;
  adminLoadBooks();
};

window.adminEditBook = function(book) {
  document.getElementById('book-form-title').textContent = 'Edit Kitab';
  document.getElementById('bf-bkid').value   = book.bkid;
  document.getElementById('bf-title').value  = book.title || '';
  document.getElementById('bf-author').value = book.author || '';
  document.getElementById('bf-cat').value    = book.category_id || '';
  document.getElementById('bf-iso').value    = book.iso || 'ar';
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.adminResetBookForm = function() {
  document.getElementById('book-form-title').textContent = 'Tambah Kitab Baru';
  document.getElementById('bf-bkid').value   = '';
  document.getElementById('bf-title').value  = '';
  document.getElementById('bf-author').value = '';
  document.getElementById('bf-cat').value    = '';
  document.getElementById('bf-iso').value    = 'ar';
  const msg = document.getElementById('book-form-msg');
  msg.className = 'mt-3 text-sm hidden';
};

async function adminSubmitBook(cats) {
  const bkid   = document.getElementById('bf-bkid').value;
  const title  = document.getElementById('bf-title').value.trim();
  const author = document.getElementById('bf-author').value.trim();
  const catId  = document.getElementById('bf-cat').value;
  const iso    = document.getElementById('bf-iso').value;
  const msg    = document.getElementById('book-form-msg');

  if (!title) { showAdminMsg(msg, 'Judul tidak boleh kosong.', 'error'); return; }

  try {
    const res = await fetch('/api.php?action=admin_save_book', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bkid: bkid ? parseInt(bkid) : 0, title, author, category_id: catId ? parseInt(catId) : 0, iso }),
    });
    const data = await res.json();
    if (data.success) {
      showAdminMsg(msg, `Kitab berhasil ${data.action === 'created' ? 'ditambahkan' : 'diperbarui'} (ID: ${data.bkid})`, 'success');
      adminResetBookForm();
      adminLoadBooks();
    } else {
      showAdminMsg(msg, data.error || 'Gagal menyimpan.', 'error');
    }
  } catch(e) {
    showAdminMsg(msg, 'Kesalahan jaringan: ' + e.message, 'error');
  }
}

window.adminDeleteBook = async function(bkid, title) {
  if (!confirm(`Hapus kitab:\n"${title}"\n\nSemua isi kitab juga akan dihapus. Lanjutkan?`)) return;
  try {
    const res = await fetch('/api.php?action=admin_delete_book', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bkid }),
    });
    const data = await res.json();
    if (data.success) adminLoadBooks();
    else alert('Gagal menghapus: ' + (data.error || 'Unknown error'));
  } catch(e) { alert('Error: ' + e.message); }
};

window.adminGoContent = function(bkid) {
  adminState.contentBkid = bkid;
  adminState.contentPage = 1;
  adminSwitchTab('content');
};

// ── Admin: Kategori ───────────────────────────────────────────

async function adminLoadCategories() {
  const container = document.getElementById('admin-tab-content');
  container.innerHTML = `<div class="skeleton h-10 rounded-xl mb-4"></div>${skeletonCards(4)}`;

  try {
    const res = await apiFetch({ action: 'categories' });
    container.innerHTML = `
      <!-- Add/Edit Form -->
      <div class="bg-white rounded-2xl shadow-card p-6 mb-6">
        <h3 class="font-bold text-primary mb-4 flex items-center gap-2">
          <i data-lucide="plus-circle" class="w-5 h-5 text-gold"></i>
          <span id="cat-form-title">Tambah Kategori Baru</span>
        </h3>
        <form id="cat-form" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <input type="hidden" id="cf-id" value="" />
          <div class="sm:col-span-3">
            <label class="text-xs font-semibold text-primary/60 mb-1 block">Nama Kategori *</label>
            <input id="cf-name" type="text" placeholder="Nama kategori kitab"
              class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20" />
          </div>
          <div>
            <label class="text-xs font-semibold text-primary/60 mb-1 block">Urutan (catord)</label>
            <input id="cf-catord" type="number" value="0" min="0"
              class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20" />
          </div>
          <div>
            <label class="text-xs font-semibold text-primary/60 mb-1 block">Level (lvl)</label>
            <input id="cf-lvl" type="number" value="1" min="1" max="5"
              class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20" />
          </div>
          <div class="flex items-end gap-3">
            <button type="submit"
              class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors flex items-center gap-2">
              <i data-lucide="save" class="w-4 h-4"></i> Simpan
            </button>
            <button type="button" onclick="adminResetCatForm()"
              class="px-5 py-2.5 rounded-xl border border-gold/30 text-sm text-primary/70 hover:bg-cream-dark transition-colors">
              Reset
            </button>
          </div>
        </form>
        <div id="cat-form-msg" class="mt-3 text-sm hidden"></div>
      </div>

      <!-- Category table -->
      <div class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-cream-dark flex items-center justify-between">
          <h3 class="font-bold text-primary">Daftar Kategori</h3>
          <span class="text-xs text-primary/40">${res.data.length} kategori</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-cream-dark text-primary/60 text-xs">
                <th class="px-4 py-3 text-left font-semibold">ID</th>
                <th class="px-4 py-3 text-left font-semibold">Nama</th>
                <th class="px-4 py-3 text-center font-semibold hidden sm:table-cell">Kitab</th>
                <th class="px-4 py-3 text-center font-semibold hidden md:table-cell">Ord</th>
                <th class="px-4 py-3 text-center font-semibold">Aksi</th>
              </tr>
            </thead>
            <tbody>
              ${res.data.map(c => `
                <tr class="border-b border-cream-dark hover:bg-cream/50 transition-colors">
                  <td class="px-4 py-3 text-primary/40 font-mono text-xs">#${c.id}</td>
                  <td class="px-4 py-3 font-medium text-primary">${escHtml(c.name)}</td>
                  <td class="px-4 py-3 text-center text-primary/50 text-xs hidden sm:table-cell">${c.book_count}</td>
                  <td class="px-4 py-3 text-center text-primary/40 text-xs hidden md:table-cell">${c.catord}</td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-2">
                      <button onclick="adminEditCat(${JSON.stringify(c).replace(/"/g,'&quot;')})"
                        class="p-1.5 rounded-lg hover:bg-primary/8 text-primary/60 hover:text-primary transition-colors" title="Edit">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                      </button>
                      <button onclick="adminDeleteCat(${c.id}, '${escHtml(c.name).replace(/'/g,"\\'")}', ${c.book_count})"
                        class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" title="Hapus">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                      </button>
                    </div>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>`;

    reicons();

    document.getElementById('cat-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      await adminSubmitCat();
    });

  } catch(err) {
    container.innerHTML = `<div class="text-red-500 text-sm p-4">Gagal memuat: ${err.message}</div>`;
  }
}

window.adminEditCat = function(cat) {
  document.getElementById('cat-form-title').textContent = 'Edit Kategori';
  document.getElementById('cf-id').value     = cat.id;
  document.getElementById('cf-name').value   = cat.name;
  document.getElementById('cf-catord').value = cat.catord || 0;
  document.getElementById('cf-lvl').value    = cat.lvl || 1;
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.adminResetCatForm = function() {
  document.getElementById('cat-form-title').textContent = 'Tambah Kategori Baru';
  document.getElementById('cf-id').value     = '';
  document.getElementById('cf-name').value   = '';
  document.getElementById('cf-catord').value = '0';
  document.getElementById('cf-lvl').value    = '1';
  const msg = document.getElementById('cat-form-msg');
  msg.className = 'mt-3 text-sm hidden';
};

async function adminSubmitCat() {
  const id     = document.getElementById('cf-id').value;
  const name   = document.getElementById('cf-name').value.trim();
  const catord = parseInt(document.getElementById('cf-catord').value) || 0;
  const lvl    = parseInt(document.getElementById('cf-lvl').value) || 1;
  const msg    = document.getElementById('cat-form-msg');

  if (!name) { showAdminMsg(msg, 'Nama tidak boleh kosong.', 'error'); return; }

  try {
    const res = await fetch('/api.php?action=admin_save_category', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id ? parseInt(id) : 0, name, catord, lvl }),
    });
    const data = await res.json();
    if (data.success) {
      showAdminMsg(msg, `Kategori berhasil ${data.action === 'created' ? 'ditambahkan' : 'diperbarui'}`, 'success');
      adminResetCatForm();
      adminLoadCategories();
    } else {
      showAdminMsg(msg, data.error || 'Gagal menyimpan.', 'error');
    }
  } catch(e) { showAdminMsg(msg, 'Error: ' + e.message, 'error'); }
}

window.adminDeleteCat = async function(id, name, bookCount) {
  const warn = bookCount > 0 ? `\n\n⚠️ ${bookCount} kitab dalam kategori ini akan kehilangan kategorinya.` : '';
  if (!confirm(`Hapus kategori:\n"${name}"${warn}\n\nLanjutkan?`)) return;
  try {
    const res = await fetch('/api.php?action=admin_delete_category', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    });
    const data = await res.json();
    if (data.success) adminLoadCategories();
    else alert('Gagal: ' + (data.error || 'Unknown'));
  } catch(e) { alert('Error: ' + e.message); }
};

// ── Admin: Konten ─────────────────────────────────────────────

async function adminLoadContentSelector() {
  const container = document.getElementById('admin-tab-content');

  if (!adminState.contentBkid) {
    // Tampilkan daftar kitab untuk dipilih
    container.innerHTML = `
      <div class="bg-white rounded-2xl shadow-card p-6 mb-4">
        <h3 class="font-bold text-primary mb-3">Pilih Kitab untuk Edit Konten</h3>
        <div class="flex gap-3">
          <input id="content-search-input" type="text" placeholder="Cari judul kitab…"
            class="flex-1 px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20" />
          <button onclick="adminSearchBooksForContent()"
            class="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors">
            Cari
          </button>
        </div>
      </div>
      <div id="content-book-list" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        ${skeletonCards(6)}
      </div>`;
    reicons();
    // Load default books
    await adminFetchBooksForContent('');
    document.getElementById('content-search-input').addEventListener('keydown', e => {
      if (e.key === 'Enter') adminSearchBooksForContent();
    });
    return;
  }

  // Kitab dipilih — load kontennya
  await adminLoadContent(adminState.contentBkid, adminState.contentPage);
}

window.adminSearchBooksForContent = async function() {
  const q = document.getElementById('content-search-input')?.value.trim() || '';
  await adminFetchBooksForContent(q);
};

async function adminFetchBooksForContent(q) {
  const listEl = document.getElementById('content-book-list');
  if (!listEl) return;
  listEl.innerHTML = skeletonCards(6);

  try {
    const params = q ? { action: 'search_books', q } : { action: 'books', limit: 24 };
    const res = await apiFetch(params);
    const books = res.data || [];
    listEl.innerHTML = books.length
      ? books.map(b => `
          <div class="book-card bg-white rounded-2xl shadow-card p-4 cursor-pointer"
               onclick="adminSelectBook(${b.bkid})">
            <div class="arabic font-semibold text-primary text-sm line-clamp-2 mb-1">${escHtml(b.title)}</div>
            <div class="text-xs text-primary/50">${escHtml(b.author || '—')}</div>
            <div class="mt-3 text-xs text-gold font-medium flex items-center gap-1">
              <i data-lucide="file-text" class="w-3 h-3"></i> Edit Konten
            </div>
          </div>`).join('')
      : '<p class="text-primary/40 text-sm col-span-full text-center py-8">Tidak ada kitab ditemukan.</p>';
    reicons();
  } catch(e) {
    listEl.innerHTML = `<div class="text-red-500 text-sm col-span-full">${e.message}</div>`;
  }
}

window.adminSelectBook = function(bkid) {
  adminState.contentBkid = bkid;
  adminState.contentPage = 1;
  adminLoadContent(bkid, 1);
};

async function adminLoadContent(bkid, page) {
  const container = document.getElementById('admin-tab-content');
  container.innerHTML = `<div class="skeleton h-10 rounded-xl mb-4 w-full"></div><div class="skeleton h-64 rounded-2xl"></div>`;

  try {
    const [bookRes, contentRes] = await Promise.all([
      apiFetch({ action: 'book', id: bkid }),
      apiFetch({ action: 'content', bkid, page }),
    ]);
    const book = bookRes.data;
    const totalPages = contentRes.total_pages || 0;

    container.innerHTML = `
      <!-- Book info bar -->
      <div class="bg-white rounded-2xl shadow-card p-4 mb-5 flex items-center gap-3">
        <button onclick="adminState.contentBkid=null; adminLoadContentSelector()"
          class="p-2 rounded-lg hover:bg-cream-dark transition-colors text-primary/60 hover:text-primary">
          <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </button>
        <div class="flex-1 min-w-0">
          <div class="arabic font-bold text-primary text-sm line-clamp-1">${escHtml(book.title)}</div>
          <div class="text-xs text-primary/40 mt-0.5">${escHtml(book.author || '')} · ${totalPages} halaman konten</div>
        </div>
        <button onclick="adminAddNewPage(${bkid}, ${totalPages})"
          class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-xl text-xs font-semibold hover:bg-primary-light transition-colors">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i> Halaman Baru
        </button>
      </div>

      <!-- Content editor -->
      <div class="bg-white rounded-2xl shadow-card p-6 mb-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-primary text-sm">
            ${contentRes.content ? `Halaman ${contentRes.page_number ?? page}` : 'Tidak ada konten pada halaman ini'}
          </h3>
          ${contentRes.content ? `
          <button onclick="adminDeletePage(${bkid}, ${contentRes.page_number ?? page})"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 text-red-500 text-xs hover:bg-red-50 transition-colors">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus Halaman
          </button>` : ''}
        </div>

        ${contentRes.content !== null ? `
        <div class="mb-3">
          <label class="text-xs font-semibold text-primary/60 mb-1 block">Nomor Halaman</label>
          <input id="ce-page-num" type="number" value="${contentRes.page_number ?? page}" min="1"
            class="w-32 px-3 py-2 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold" />
        </div>
        <div>
          <label class="text-xs font-semibold text-primary/60 mb-1 block">Isi Konten</label>
          <textarea id="ce-content" rows="14"
            class="w-full px-4 py-3 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 font-arabic leading-relaxed resize-y"
            dir="auto" placeholder="Isi konten kitab…">${escHtml(contentRes.content)}</textarea>
        </div>
        <div class="flex gap-3 mt-4">
          <button onclick="adminSavePage(${bkid})"
            class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors flex items-center gap-2">
            <i data-lucide="save" class="w-4 h-4"></i> Simpan Konten
          </button>
        </div>
        <div id="content-msg" class="mt-3 text-sm hidden"></div>
        ` : `
        <div class="text-center py-8 text-primary/40 text-sm">
          <i data-lucide="file-x" class="w-8 h-8 mx-auto mb-2 text-primary/20"></i>
          Halaman ini belum memiliki konten.
        </div>
        <div id="content-msg" class="mt-3 text-sm hidden"></div>
        `}
      </div>

      <!-- Pagination konten -->
      ${totalPages > 0 ? `
      <div class="bg-white rounded-2xl shadow-card p-4">
        <div class="text-xs text-primary/50 text-center mb-3">Navigasi Halaman Konten</div>
        ${paginationHtml(page, totalPages, 'adminContentGoPage')}
      </div>` : ''}`;

    reicons();

  } catch(e) {
    container.innerHTML = `<div class="text-red-500 text-sm p-4">Error: ${e.message}</div>`;
  }
}

window.adminContentGoPage = function(p) {
  adminState.contentPage = p;
  adminLoadContent(adminState.contentBkid, p);
};

window.adminSavePage = async function(bkid) {
  const pageNum = parseInt(document.getElementById('ce-page-num')?.value) || adminState.contentPage;
  const content = document.getElementById('ce-content')?.value ?? '';
  const msg     = document.getElementById('content-msg');

  try {
    const res = await fetch('/api.php?action=admin_save_content', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bkid, page: pageNum, content }),
    });
    const data = await res.json();
    if (data.success) {
      showAdminMsg(msg, 'Konten berhasil disimpan!', 'success');
    } else {
      showAdminMsg(msg, data.error || 'Gagal menyimpan.', 'error');
    }
  } catch(e) { showAdminMsg(msg, 'Error: ' + e.message, 'error'); }
};

window.adminDeletePage = async function(bkid, page) {
  if (!confirm(`Hapus halaman ${page} dari kitab ini?`)) return;
  try {
    const res = await fetch('/api.php?action=admin_delete_content', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bkid, page }),
    });
    const data = await res.json();
    if (data.success) {
      adminState.contentPage = Math.max(1, adminState.contentPage - 1);
      adminLoadContent(bkid, adminState.contentPage);
    } else {
      alert('Gagal menghapus: ' + (data.error || 'Unknown'));
    }
  } catch(e) { alert('Error: ' + e.message); }
};

window.adminAddNewPage = async function(bkid, currentTotal) {
  const newPage = currentTotal + 1;
  try {
    const res = await fetch('/api.php?action=admin_save_content', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bkid, page: newPage, content: '' }),
    });
    const data = await res.json();
    if (data.success) {
      adminState.contentPage = newPage;
      adminLoadContent(bkid, newPage);
    } else {
      alert('Gagal menambah halaman: ' + (data.error || 'Unknown'));
    }
  } catch(e) { alert('Error: ' + e.message); }
};

// ── Admin: Utilities ──────────────────────────────────────────

function showAdminMsg(el, text, type) {
  el.textContent = text;
  el.className = `mt-3 text-sm px-4 py-2.5 rounded-xl ${
    type === 'success'
      ? 'bg-green-50 text-green-700 border border-green-200'
      : 'bg-red-50 text-red-600 border border-red-200'
  }`;
  setTimeout(() => { if (el) el.className = 'mt-3 text-sm hidden'; }, 4000);
}

// expose navigate globally (used in onclick attributes)
window.navigate = navigate;
