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
  if (!res.ok) {
    const text = await res.text();
    throw new Error('API error ' + res.status + ': ' + text);
  }
  return res.json();
}

function logVisitorActivity(event, data = {}) {
  try {
    fetch(API + '?action=log_activity', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event, data }),
      keepalive: true,
    });
  } catch (err) {
    // Non-blocking tracking
  }
}
window.logVisitorActivity = logVisitorActivity;

// ── Router ────────────────────────────────────────────────────
// Rute admin didaftarkan oleh admin.js setelah DOM ready
const routes = {
  '/':              renderHome,
  '/katalog':       renderKatalog,
  '/about':         renderAbout,
  '/search':        renderSearch,
  '/search-advanced': renderSearchAdvanced,
  '/kitab':         renderDetail,
  '/privacy':       renderPrivacy,
  '/submit-file':   renderSubmitFile,
};

function navigate(path, push = true) {
  if (push) history.pushState({}, '', path);
  const base = path.split('?')[0];
  const handler = routes[base] || render404;
  app().innerHTML = '';
  handler(new URLSearchParams(path.includes('?') ? path.split('?')[1] : ''));
  setActiveNav(base);
  updateReaderMenus(base);
  window.scrollTo({ top: 0, behavior: 'smooth' });
  reicons();
  logVisitorActivity('visit', { route: base });
}

window.addEventListener('popstate', () => navigate(location.pathname + location.search, false));

document.addEventListener('click', e => {
  const a = e.target.closest('[data-route]');
  if (!a) return;
  e.preventDefault();
  const route = a.getAttribute('data-route');
  logVisitorActivity('menu_click', {
    route,
    label: a.textContent.trim().replace(/\s+/g, ' '),
    href: a.href,
  });
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

function updateReaderMenus(base) {
  const hide = base === '/kitab';
  $('#bottom-nav')?.classList.toggle('reader-hide-menu', hide);
  $('#navbar')?.classList.toggle('reader-hide-menu', hide);
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
    </section>

    <!-- CTA Kirimkan File -->
    <section class="py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-start gap-4 rounded-[32px] bg-gradient-to-br from-primary to-primary-light p-8">
          <div class="shrink-0 w-12 h-12 rounded-2xl bg-gold/20 flex items-center justify-center">
            <i data-lucide="upload-cloud" class="w-6 h-6 text-gold"></i>
          </div>
          <div class="flex-1">
            <h3 class="text-white font-bold text-base leading-snug mb-1">Kirimkan Hasil Bahsul Masail atau Kitab Anda</h3>
            <p class="text-white/65 text-xs leading-relaxed mb-4">Bagikan karya & hasil kajian Anda untuk koleksi perpustakaan digital ini.</p>
            <button onclick="handleSubmitCTA()"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gold text-primary font-semibold text-sm shadow hover:bg-gold-light transition-colors">
              <i data-lucide="send" class="w-4 h-4"></i>
              Kirimkan File
            </button>
          </div>
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
const searchAdvancedState = {
  terms: ['', '', '', '', ''],
  cats: [],
  allCats: false,   // true = semua kategori dipilih, kirim all_cats=1 ke API
  page: 1,
  samePage: true,
  categories: [],
};

function escapeRegex(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function parseSearchTerms(q) {
  if (!q) return [];
  q = String(q).replace(/\+/g, ' ').trim();
  // If user wrapped phrase in quotes, respect quoted groups and separate others.
  if (/".*"/u.test(q)) {
    const terms = [];
    const regex = /"([^\"]+)"|([^"\s]+)/g;
    let match;
    while ((match = regex.exec(q)) !== null) {
      const term = (match[1] || match[2] || '').trim();
      if (term) terms.push(term);
    }
    return terms;
  }

  // If the query contains whitespace (multi-word) treat it as a single phrase
  // so matches require the words to appear together in the same order.
  if (/\s+/u.test(q)) {
    return [q];
  }

  // Single-word query
  return [q];
}

function highlightTextNodes(container, terms) {
  const escapedTerms = terms
    .map(raw => String(raw || '').trim())
    .filter(Boolean)
    .map(t => t.replace(/^"|"$/g, '').trim())
    .filter(Boolean)
    .sort((a, b) => b.length - a.length)
    .map(escapeRegex);
  if (!escapedTerms.length) return false;

  const regex = new RegExp('(' + escapedTerms.join('|') + ')', 'gi');
  let found = false;
  const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
  const nodes = [];
  while (walker.nextNode()) nodes.push(walker.currentNode);

  nodes.forEach(node => {
    const value = node.nodeValue;
    if (!value || !regex.test(value)) {
      regex.lastIndex = 0;
      return;
    }
    regex.lastIndex = 0;
    const frag = document.createDocumentFragment();
    let lastIndex = 0;
    let match;
    while ((match = regex.exec(value)) !== null) {
      if (match.index > lastIndex) {
        frag.appendChild(document.createTextNode(value.slice(lastIndex, match.index)));
      }
      const mark = document.createElement('mark');
      mark.className = 'hl';
      mark.textContent = match[0];
      frag.appendChild(mark);
      lastIndex = regex.lastIndex;
    }
    if (lastIndex < value.length) {
      frag.appendChild(document.createTextNode(value.slice(lastIndex)));
    }
    node.parentNode.replaceChild(frag, node);
    found = true;
    regex.lastIndex = 0;
  });
  return found;
}

function hlTextMulti(text, terms) {
  if (!text) return escHtml('');
  let escaped = escHtml(text);
  const patterns = [];
  (terms || []).forEach(raw => {
    const term = String(raw || '').trim();
    if (!term) return;
    const unquoted = term.replace(/^"|"$/g, '').trim();
    if (!unquoted) return;
    patterns.push(escapeRegex(unquoted));
  });
  if (!patterns.length) return escaped;
  const regex = new RegExp('(' + patterns.sort((a, b) => b.length - a.length).join('|') + ')', 'gi');
  return escaped.replace(regex, '<mark class="hl">$1</mark>');
}

async function getSearchAdvancedCategories() {
  if (searchAdvancedState.categories.length) return searchAdvancedState.categories;
  try {
    const res = await apiFetch({ action: 'categories' });
    searchAdvancedState.categories = res.data || [];
  } catch {
    searchAdvancedState.categories = [];
  }
  return searchAdvancedState.categories;
}

function updateAdvancedPageUrl() {
  const params = new URLSearchParams();
  searchAdvancedState.terms.forEach((value, idx) => {
    if (value.trim()) params.set('q' + (idx + 1), value.trim());
  });
  if (searchAdvancedState.allCats) {
    params.set('all_cats', '1');
  } else if (searchAdvancedState.cats.length) {
    params.set('cats', searchAdvancedState.cats.join(','));
  }
  if (!searchAdvancedState.samePage) {
    params.set('same_page', '0');
  }
  if (searchAdvancedState.page > 1) {
    params.set('page', searchAdvancedState.page);
  }
  const query = params.toString();
  history.replaceState({}, '', '/search-advanced' + (query ? '?' + query : ''));
}

function buildAdvancedSearchQuery() {
  return searchAdvancedState.terms
    .map(q => String(q || '').trim())
    .filter(Boolean)
    .join(' ');
}

function advancedContentCard(book) {
  const titleHtml = hlTextMulti(book.title || 'بدون عنوان', searchAdvancedState.terms);
  const authorHtml = book.author ? hlTextMulti(book.author, searchAdvancedState.terms) : '';
  const snippetHtml = book.snippet ? hlTextMulti(book.snippet, searchAdvancedState.terms) : '';
  const pageLabel = book.match_page ? `hal. ${book.match_page}` : '';
  return `
    <div class="book-card bg-white rounded-2xl shadow-card p-4 flex flex-col gap-3 cursor-pointer hover:border-gold/30 hover:shadow-[0_16px_40px_rgba(201,168,76,.12)] transition-all"
         onclick="navigate('/kitab?id=${book.bkid}&page=${book.match_page || 1}&q=${encodeURIComponent(buildAdvancedSearchQuery())}')">
      <div class="arabic text-primary font-semibold text-sm leading-snug line-clamp-2">${titleHtml}</div>
      ${authorHtml ? `<div class="text-primary/55 text-xs line-clamp-1">${authorHtml}</div>` : ''}
      ${snippetHtml ? `<div class="snippet-bar reader-text line-clamp-4">${snippetHtml}…</div>` : ''}
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        <span class="text-xs text-primary/50 truncate max-w-[65%]">${escHtml(book.category_name || '')}</span>
        ${pageLabel ? `<span class="text-xs text-gold font-medium flex items-center gap-1"><i data-lucide="bookmark" class="w-3 h-3"></i>${escHtml(pageLabel)}</span>` : ''}
      </div>
    </div>`;
}

function renderAdvancedCategories(categories) {
  const wrapper = $('#adv-categories');
  if (!wrapper) return;
  if (!categories.length) {
    wrapper.innerHTML = `<p class="text-sm text-primary/40 col-span-full">Tidak ada kategori untuk ditampilkan.</p>`;
    return;
  }
  wrapper.innerHTML = categories.map(cat => {
    const checked = (searchAdvancedState.allCats || searchAdvancedState.cats.includes(String(cat.id))) ? 'checked' : '';
    return `
      <label class="flex items-center gap-3 rounded-2xl border border-cream-dark bg-white px-3 py-2 cursor-pointer hover:border-gold/30 transition-colors">
        <input type="checkbox" class="adv-cat-checkbox" value="${cat.id}" ${checked} />
        <span class="text-sm text-primary">${escHtml(cat.name)}</span>
      </label>`;
  }).join('');
}

function applyAdvancedCheckboxState() {
  $$('.adv-cat-checkbox').forEach(input => {
    // Jika allCats=true, centang semua; jika tidak, centang berdasarkan daftar
    input.checked = searchAdvancedState.allCats || searchAdvancedState.cats.includes(input.value);
  });
}

// Perbarui tampilan tombol "Tandai semua" sesuai status allCats
function updateAdvSelectAllBtn() {
  const btn = $('#adv-select-all');
  if (!btn) return;
  if (searchAdvancedState.allCats) {
    btn.classList.add('bg-gold/10', 'border-gold', 'text-gold');
    btn.classList.remove('bg-white', 'border-gold/30', 'text-primary');
    btn.innerHTML = '<i data-lucide="check-check" class="w-4 h-4"></i> Semua dipilih';
  } else {
    btn.classList.remove('bg-gold/10', 'border-gold', 'text-gold');
    btn.classList.add('bg-white', 'border-gold/30', 'text-primary');
    btn.innerHTML = 'Tandai semua';
  }
  reicons();
}

function updateAdvancedSearchStateFromInputs() {
  $$('.adv-term-input').forEach((input, idx) => {
    searchAdvancedState.terms[idx] = input.value;
  });
}

async function execAdvancedSearch() {
  const wrap = $('#adv-results');
  const stats = $('#adv-search-stats');
  if (!wrap) return;
  updateAdvancedSearchStateFromInputs();
  const searchTerms = searchAdvancedState.terms.filter(term => term.trim());
  if (!searchTerms.length) {
    wrap.innerHTML = `<div class="text-center py-20 text-primary/40">Isi minimal satu kolom pencarian untuk memulai.</div>`;
    if (stats) stats.textContent = '';
    updateAdvancedPageUrl();
    return;
  }
  // Validasi kategori: harus ada pilihan atau semua dipilih
  const hasCatFilter = searchAdvancedState.allCats || searchAdvancedState.cats.length > 0;
  if (!hasCatFilter) {
    wrap.innerHTML = `
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-[32px] bg-white p-6 shadow-[0_24px_80px_rgba(15,23,42,0.25)] ring-1 ring-slate-200">
          <div class="flex flex-col items-center gap-4 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-700">
              <i data-lucide="alert-triangle" class="w-8 h-8"></i>
            </div>
            <h2 class="text-xl font-semibold text-slate-900">Kategori belum dipilih</h2>
            <p class="text-sm leading-6 text-slate-600">Pilih satu kategori atau semua kategori terlebih dahulu untuk melanjutkan pencarian.</p>
            <button id="adv-alert-close" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-700">Tutup</button>
          </div>
        </div>
      </div>`;
    if (stats) stats.textContent = '';
    updateAdvancedPageUrl();
    document.getElementById('adv-alert-close')?.addEventListener('click', () => {
      wrap.innerHTML = `<div class="text-center py-20 text-primary/40">Isi minimal satu kolom pencarian untuk memulai.</div>`;
      if (stats) stats.textContent = '';
    });
    return;
  }

  abortAll();
  wrap.innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">${skeletonCards(6)}</div>`;
  reicons();
  searchAdvancedState.page = Math.max(1, searchAdvancedState.page || 1);
  // Tampilkan indikator loading di stats bar
  if (stats) {
    stats.innerHTML = `<span class="inline-flex items-center gap-1.5 text-sm text-primary/40"><span class="spin-ring"></span> Mencari…</span>`;
    reicons();
  }

  const t0 = performance.now();
  const params = { action: 'search_advanced', page: searchAdvancedState.page };
  searchAdvancedState.terms.forEach((value, idx) => {
    if (value.trim()) params['q' + (idx + 1)] = value.trim();
  });
  if (!searchAdvancedState.samePage) {
    params.same_page = '0';
  }
  // Kirim all_cats=1 jika semua dipilih → API gunakan jalur cepat 2-step tanpa JOIN pada scan utama
  if (searchAdvancedState.allCats) {
    params.all_cats = '1';
  } else if (searchAdvancedState.cats.length) {
    params.cats = searchAdvancedState.cats.join(',');
  }

  try {
    const res = await apiFetch(params);
    const ms = Math.round(performance.now() - t0);
    updateAdvancedPageUrl();
    const total = res.total || 0;
    const page = res.page || 1;
    const totalPages = res.total_pages || 1;
    const queryLabel = searchTerms.length === 1 ? searchTerms[0] : searchTerms.join(' + ');

    // Badge kategori
    const catLabel = searchAdvancedState.allCats
      ? `<span class="inline-flex items-center gap-1 text-xs bg-gold/10 text-gold font-semibold px-2 py-0.5 rounded-full"><i data-lucide="layers" class="w-3 h-3"></i>Semua Kategori</span>`
      : `<span class="text-xs text-primary/40">${searchAdvancedState.cats.length} kategori dipilih</span>`;

    // Badge performa / cache
    const perfBadge = res.cached
      ? `<span class="inline-flex items-center gap-1 text-xs bg-emerald-50 text-emerald-600 font-semibold px-2 py-0.5 rounded-full"><i data-lucide="database" class="w-3 h-3"></i>Cache</span>`
      : `<span class="inline-flex items-center gap-1 text-xs text-primary/40"><i data-lucide="zap" class="w-3 h-3 text-gold"></i>${ms} ms</span>`;

    if (stats) {
      stats.innerHTML = `<div class="flex flex-wrap items-center gap-2">
        <span class="text-sm text-primary/60">Menemukan <strong>${total.toLocaleString('id-ID')}</strong> halaman untuk <strong>${escHtml(queryLabel)}</strong>.</span>
        ${catLabel}
        ${perfBadge}
      </div>`;
    }
    wrap.innerHTML = res.data.length
      ? `<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${res.data.map(book => advancedContentCard(book)).join('')}</div>
         ${paginationHtml(page, totalPages, 'goAdvancedPage')}`
      : `<div class="text-center py-20 text-primary/40">Maaf, tidak ditemukan halaman yang cocok dengan kata kunci dan kategori yang dipilih.</div>`;
    if (res.data.length) {
      wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    reicons();
  } catch (err) {
    console.error('Advanced search API error:', err, 'Params:', params);
    wrap.innerHTML = `<p class="text-center py-20 text-sm text-red-500">Gagal memuat hasil pencarian. Silakan coba lagi.</p>`;
    if (stats) stats.textContent = '';
  }
}

window.goAdvancedPage = function(p) {
  searchAdvancedState.page = p;
  execAdvancedSearch();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderSearchAdvanced(params) {
  searchAdvancedState.terms = ['', '', '', '', ''];
  searchAdvancedState.cats = [];
  searchAdvancedState.allCats = params.get('all_cats') === '1';
  searchAdvancedState.page = Math.max(1, parseInt(params.get('page') || '1', 10));
  searchAdvancedState.samePage = params.get('same_page') !== '0';
  for (let i = 0; i < 5; i += 1) {
    searchAdvancedState.terms[i] = params.get('q' + (i + 1)) || '';
  }
  const catsParam = params.get('cats') || '';
  if (!searchAdvancedState.allCats && catsParam) {
    searchAdvancedState.cats = catsParam.split(',').map(id => String(parseInt(id, 10))).filter(id => id && id !== '0');
  }

  app().innerHTML = `
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="mb-8">
        <div class="flex flex-col gap-3">
          <div class="text-sm uppercase tracking-[.2em] text-gold font-bold">Pencarian Lanjutan</div>
          <h1 class="text-3xl md:text-4xl font-bold text-primary">Cari halaman kitab dengan kata kunci dan kategori</h1>
          <p class="text-sm text-primary/60 max-w-3xl">Masukkan hingga 5 kolom. Jika sebuah kolom berisi beberapa kata, sistem akan mencocokkan frasa persis pada satu halaman. Gunakan kotak di bawah untuk mengontrol apakah semua kata harus ditemukan dalam satu halaman.</p>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="space-y-5 bg-white rounded-3xl border border-cream-dark p-6 shadow-card">
          <div class="grid gap-3">
            ${Array.from({ length: 5 }, (_, idx) => `
              <div class="space-y-2">
                <label class="text-sm font-semibold text-primary">Kolom ${idx + 1}</label>
                <input type="text" id="adv-term-${idx + 1}" data-idx="${idx}" value="${escHtml(searchAdvancedState.terms[idx])}"
                  class="adv-term-input w-full rounded-2xl border border-gold/20 bg-cream/70 px-4 py-3 text-sm text-primary focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/20 transition" 
                  placeholder="Masukkan kata atau frasa" />
              </div>
            `).join('')}
          </div>
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="text-sm text-primary/70">Pilih kategori pilihan untuk mempersempit pencarian.</div>
            <div class="flex flex-wrap gap-2">
              <button id="adv-select-all" type="button" class="inline-flex items-center gap-2 rounded-full border border-gold/30 bg-white px-4 py-2 text-sm font-semibold text-primary hover:bg-gold/5 transition">Tandai semua</button>
              <button id="adv-clear-all" type="button" class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-white px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/5 transition">Hapus semua</button>
            </div>
          </div>
          <div id="adv-categories" class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[360px] overflow-y-auto pr-1"></div>
        </div>

        <div class="bg-white rounded-3xl border border-cream-dark p-6 shadow-card">
          <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h2 class="text-lg font-semibold text-primary">Kontrol Pencarian</h2>
                <p class="text-sm text-primary/60">Tekan cari untuk memulai.</p>
              </div>
            </div>
            <div class="grid gap-3">
              <button id="adv-search-btn" class="w-full rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white hover:bg-primary-light transition">Cari</button>
              <button id="adv-reset-btn" class="w-full rounded-2xl border border-primary/20 bg-white px-5 py-3 text-sm font-semibold text-primary hover:bg-cream-dark transition">Reset Form</button>
            </div>
            <div class="flex items-start gap-3 text-sm text-primary/60">
              <label class="flex items-center gap-2 cursor-pointer">
                <input id="adv-same-page" type="checkbox" class="form-checkbox h-4 w-4 rounded border-gold/25 text-gold focus:ring-gold" ${searchAdvancedState.samePage ? 'checked' : ''} />
                <span>Semua harus ditemukan dalam satu halaman</span>
              </label>
            </div>
            <div class="text-xs text-primary/50">Jika dicentang, semua kolom yang diisi harus ditemukan di halaman yang sama. Jika tidak dicentang, hasil akan mencakup halaman yang cocok dengan salah satu kolom.</div>
            <div id="adv-search-stats" class="text-sm text-primary/60"></div>
          </div>
        </div>
      </div>

      <div id="adv-results" class="mt-8"></div>
    </div>`;

  reicons();

  getSearchAdvancedCategories().then(categories => {
    renderAdvancedCategories(categories);
    applyAdvancedCheckboxState();
    updateAdvSelectAllBtn();
    $$('.adv-cat-checkbox').forEach(input => {
      input.addEventListener('change', () => {
        if (input.checked) {
          if (!searchAdvancedState.cats.includes(input.value)) searchAdvancedState.cats.push(input.value);
        } else {
          // Jika sebelumnya allCats dan user unchecks satu → beralih ke mode parsial
          if (searchAdvancedState.allCats) {
            searchAdvancedState.allCats = false;
            searchAdvancedState.cats = categories
              .map(cat => String(cat.id))
              .filter(id => id !== input.value);
          } else {
            searchAdvancedState.cats = searchAdvancedState.cats.filter(id => id !== input.value);
          }
        }
        // Deteksi otomatis: jika semua centang → pakai allCats flag
        if (searchAdvancedState.cats.length === categories.length) {
          searchAdvancedState.allCats = true;
          searchAdvancedState.cats = [];
        }
        updateAdvSelectAllBtn();
        updateAdvancedPageUrl();
      });
    });
  });

  // "Tandai semua" → set allCats=true, cats=[] untuk efisiensi
  $('#adv-select-all')?.addEventListener('click', async () => {
    await getSearchAdvancedCategories();
    searchAdvancedState.allCats = true;
    searchAdvancedState.cats = [];
    applyAdvancedCheckboxState();
    updateAdvSelectAllBtn();
    updateAdvancedPageUrl();
  });

  $('#adv-clear-all')?.addEventListener('click', () => {
    searchAdvancedState.allCats = false;
    searchAdvancedState.cats = [];
    applyAdvancedCheckboxState();
    updateAdvSelectAllBtn();
    updateAdvancedPageUrl();
  });

  $('#adv-same-page')?.addEventListener('change', e => {
    searchAdvancedState.samePage = e.target.checked;
    updateAdvancedPageUrl();
  });

  $('#adv-search-btn')?.addEventListener('click', () => { searchAdvancedState.page = 1; execAdvancedSearch(); });
  $('#adv-reset-btn')?.addEventListener('click', () => {
    searchAdvancedState.terms = ['', '', '', '', ''];
    searchAdvancedState.cats = [];
    searchAdvancedState.allCats = false;
    searchAdvancedState.page = 1;
    searchAdvancedState.samePage = true;
    Array.from(document.querySelectorAll('.adv-term-input')).forEach(input => input.value = '');
    $('#adv-same-page').checked = true;
    applyAdvancedCheckboxState();
    updateAdvSelectAllBtn();
    $('#adv-search-stats').textContent = '';
    $('#adv-results').innerHTML = `<div class="text-center py-20 text-primary/40">Isi minimal satu kolom pencarian untuk memulai.</div>`;
    updateAdvancedPageUrl();
  });

  $$('.adv-term-input').forEach(input => {
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchAdvancedState.page = 1;
        execAdvancedSearch();
      }
    });
  });

  if (searchAdvancedState.terms.some(term => term.trim()) || searchAdvancedState.cats.length) {
    execAdvancedSearch();
  }
}

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
        <div class="mt-3 text-right">
          <a href="/search-advanced" data-route="/search-advanced" class="inline-flex items-center gap-2 text-sm font-semibold text-gold hover:text-gold-dark transition">
            <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
            Pencarian Lanjutan
          </a>
        </div>
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
                <i data-lucide="chevron-left" class="w-4 h-4"></i> Sebelumnya
              </button>
              <span id="reader-label" class="text-xs text-primary/40"></span>
              <button id="reader-next"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-light disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                Berikutnya <i data-lucide="chevron-right" class="w-4 h-4"></i>
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
      area.innerHTML = `<div class="reader-text">${escHtml(normalised)}</div>`;
      const terms = parseSearchTerms(highlightQ);
      if (terms.length) {
        const found = highlightTextNodes(area, terms);
        if (found) {
          setTimeout(() => {
            const first = area.querySelector('mark.hl');
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }, 150);
        }
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
          <p class="mb-4">Anda dapat mengakses perpustakaan ini di: <a href="https://maktabah.quizb.my.id" class="text-gold hover:underline font-medium">maktabah.quizb.my.id</a></p>

          <!-- Founder & Developer cards -->
          <div class="grid sm:grid-cols-2 gap-4 mt-4">

            <!-- Founder -->
            <div class="rounded-2xl border border-border bg-surface p-5">
              <div class="flex items-center gap-2 mb-4">
                <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center">
                  <i data-lucide="star" class="w-3.5 h-3.5 text-gold"></i>
                </div>
                <span class="text-xs font-bold tracking-widest uppercase text-gold">Founder</span>
              </div>
              <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Nama</span>
                  <span class="font-semibold text-primary">Cak Zen</span>
                </div>
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Email</span>
                  <a href="mailto:akhmadzaeni535@gmail.com" class="font-semibold text-primary hover:text-gold transition-colors">akhmadzaeni535@gmail.com</a>
                </div>
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Facebook</span>
                  <a href="https://facebook.com/akhnadzaeni" target="_blank" rel="noopener" class="font-semibold text-primary hover:text-gold transition-colors">akhnadzaeni</a>
                </div>
              </div>
            </div>

            <!-- Developer -->
            <div class="rounded-2xl border border-border bg-surface p-5">
              <div class="flex items-center gap-2 mb-4">
                <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center">
                  <i data-lucide="code-2" class="w-3.5 h-3.5 text-gold"></i>
                </div>
                <span class="text-xs font-bold tracking-widest uppercase text-gold">Developer</span>
              </div>
              <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Website</span>
                  <a href="https://hakimz.site" target="_blank" rel="noopener" class="font-semibold text-primary hover:text-gold transition-colors">hakimz.site</a>
                </div>
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Email</span>
                  <a href="mailto:zenhkm@gmail.com" class="font-semibold text-primary hover:text-gold transition-colors">zenhkm@gmail.com</a>
                </div>
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Instagram</span>
                  <a href="https://instagram.com/zainul.hakim" target="_blank" rel="noopener" class="font-semibold text-primary hover:text-gold transition-colors">@zainul.hakim</a>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>`;
  reicons();
}


// ══════════════════════════════════════════════════════════════
//  PAGE: KEBIJAKAN PRIVASI
// ══════════════════════════════════════════════════════════════
function renderPrivacy() {
  const LAST_UPDATED = '13 Mei 2026';
  const SITE_URL     = 'https://maktabah.quizb.my.id';
  const CONTACT      = 'admin@quizb.my.id';

  app().innerHTML = `
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-12 pb-20">

      <!-- Header -->
      <div class="mb-10">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shadow-sm">
            <i data-lucide="shield-check" class="w-5 h-5 text-gold"></i>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-primary leading-tight">Kebijakan Privasi</h1>
            <p class="text-sm text-muted">Al-Maktabah As-Sunniyyah</p>
          </div>
        </div>
        <p class="text-sm text-muted">Terakhir diperbarui: <span class="font-medium text-primary">${LAST_UPDATED}</span></p>
        <p class="mt-3 text-base text-secondary leading-relaxed">
          Kebijakan Privasi ini menjelaskan bagaimana Al-Maktabah As-Sunniyyah
          (<a href="${SITE_URL}" class="text-gold hover:underline font-medium">${SITE_URL}</a>)
          mengumpulkan, menggunakan, dan melindungi informasi pribadi Anda ketika menggunakan layanan kami.
        </p>
      </div>

      <!-- Sections -->
      <div class="space-y-6">

        ${privacySection('database', 'Informasi yang Kami Kumpulkan', `
          <p class="text-secondary mb-4">Saat Anda masuk menggunakan akun Google, kami menerima informasi berikut dari Google:</p>
          <div class="grid sm:grid-cols-2 gap-3">
            ${privacySubCard('user', 'Nama Lengkap', 'Nama yang terdaftar pada akun Google Anda.')}
            ${privacySubCard('mail', 'Alamat Email', 'Email utama akun Google Anda.')}
            ${privacySubCard('image', 'Foto Profil', 'URL foto profil publik Google Anda.')}
            ${privacySubCard('key', 'ID Google', 'Identitas unik dari Google (tidak dapat digunakan di luar layanan ini).')}
          </div>
          <p class="text-secondary mt-4 text-sm">Kami <strong>tidak</strong> menyimpan kata sandi Anda. Otentikasi sepenuhnya ditangani oleh Google.</p>
        `)}

        ${privacySection('settings', 'Cara Kami Menggunakan Informasi', `
          <ul class="space-y-2 text-secondary">
            ${privacyItem('Membuat dan mengelola akun pengguna Anda di platform ini.')}
            ${privacyItem('Menampilkan nama dan foto profil Anda di antarmuka aplikasi.')}
            ${privacyItem('Menentukan hak akses Anda (pengguna biasa atau administrator).')}
            ${privacyItem('Mencatat waktu login terakhir untuk keperluan keamanan.')}
            ${privacyItem('Kami tidak menggunakan data Anda untuk iklan, analitik pihak ketiga, atau tujuan komersial.')}
          </ul>
        `)}

        ${privacySection('share-2', 'Berbagi Data dengan Pihak Ketiga', `
          <p class="text-secondary mb-3">Kami <strong>tidak menjual, menyewakan, atau membagikan</strong> data pribadi Anda kepada pihak ketiga, kecuali:</p>
          <ul class="space-y-2 text-secondary">
            ${privacyItem('Google LLC — sebagai penyedia layanan autentikasi OAuth 2.0.')}
            ${privacyItem('Penyedia hosting server — hanya memiliki akses teknis ke infrastruktur, bukan data pengguna secara individual.')}
            ${privacyItem('Kewajiban hukum — apabila diwajibkan oleh peraturan perundang-undangan yang berlaku.')}
          </ul>
        `)}

        ${privacySection('cookie', 'Cookie & Sesi', `
          <p class="text-secondary mb-3">Kami menggunakan cookie sesi PHP standar untuk:</p>
          <ul class="space-y-2 text-secondary">
            ${privacyItem('Mempertahankan status login Anda selama sesi aktif.')}
            ${privacyItem('Melindungi dari serangan CSRF (Cross-Site Request Forgery) selama proses login.')}
          </ul>
          <p class="text-secondary mt-4 text-sm">Cookie sesi dihapus otomatis ketika Anda keluar (logout) atau menutup browser. Kami tidak menggunakan cookie pelacak atau cookie pihak ketiga.</p>
        `)}

        ${privacySection('shield', 'Keamanan Data', `
          <div class="grid sm:grid-cols-3 gap-3">
            ${privacyRightCard('lock', 'Enkripsi HTTPS', 'Seluruh komunikasi antara browser dan server dienkripsi menggunakan TLS/SSL.')}
            ${privacyRightCard('database', 'PDO Prepared Statements', 'Semua kueri database menggunakan prepared statements untuk mencegah SQL injection.')}
            ${privacyRightCard('shield-check', 'CSRF Protection', 'Token state divalidasi pada setiap proses autentikasi OAuth.')}
          </div>
        `)}

        ${privacySection('user-check', 'Hak Pengguna', `
          <p class="text-secondary mb-3">Anda memiliki hak atas data pribadi Anda, termasuk:</p>
          <ul class="space-y-2 text-secondary">
            ${privacyItem('Hak akses: Melihat informasi yang kami simpan tentang Anda melalui halaman profil.')}
            ${privacyItem('Hak penghapusan: Menghubungi kami untuk menghapus akun dan seluruh data Anda secara permanen.')}
            ${privacyItem('Hak pencabutan: Mencabut izin akses aplikasi ini dari pengaturan akun Google Anda kapan saja.')}
            ${privacyItem('Hak koreksi: Data profil diperbarui otomatis dari Google setiap kali Anda login.')}
          </ul>
        `)}

        ${privacySection('users', 'Pengguna di Bawah Umur', `
          <p class="text-secondary">
            Layanan ini tidak ditujukan secara khusus untuk anak-anak di bawah 13 tahun.
            Kami tidak secara sengaja mengumpulkan data dari anak di bawah umur.
            Jika Anda adalah orang tua atau wali dan mengetahui bahwa anak Anda telah memberikan informasi pribadi kepada kami,
            silakan hubungi kami agar kami dapat menghapus data tersebut.
          </p>
        `)}

        ${privacySection('mail', 'Hubungi Kami', `
          <p class="text-secondary mb-4">Jika Anda memiliki pertanyaan, permintaan, atau kekhawatiran mengenai kebijakan privasi ini, silakan hubungi kami:</p>
          <div class="bg-surface rounded-xl p-4 border border-border">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-primary flex items-center justify-center">
                <i data-lucide="mail" class="w-4 h-4 text-gold"></i>
              </div>
              <div>
                <p class="text-sm text-muted">Email</p>
                <a href="mailto:${CONTACT}" class="font-medium text-primary hover:text-gold transition-colors">${CONTACT}</a>
              </div>
            </div>
          </div>
        `)}

        ${privacySection('refresh-cw', 'Perubahan Kebijakan', `
          <p class="text-secondary">
            Kami dapat memperbarui Kebijakan Privasi ini dari waktu ke waktu.
            Setiap perubahan akan dipublikasikan di halaman ini dengan tanggal pembaruan yang baru.
            Penggunaan layanan secara berkelanjutan setelah perubahan diterbitkan berarti Anda menyetujui kebijakan yang diperbarui.
          </p>
          <p class="text-secondary mt-3 text-sm">
            Versi saat ini berlaku sejak <strong>${LAST_UPDATED}</strong>.
          </p>
        `)}

      </div>

      <!-- Footer note -->
      <div class="mt-12 pt-6 border-t border-border text-center">
        <p class="text-sm text-muted">
          <i data-lucide="heart" class="w-3.5 h-3.5 inline text-gold mr-1"></i>
          Al-Maktabah As-Sunniyyah — perpustakaan digital kitab-kitab Islam klasik
        </p>
        <p class="text-xs text-muted mt-1">
          <a href="/" data-route="/" class="hover:text-gold transition-colors">Kembali ke Beranda</a>
          <span class="mx-2">·</span>
          <a href="/katalog" data-route="/katalog" class="hover:text-gold transition-colors">Jelajahi Katalog</a>
        </p>
      </div>

    </div>`;
  reicons();
}

// ── Helper: section card ──────────────────────────────────────
function privacySection(icon, title, bodyHtml) {
  return `
    <div class="bg-surface rounded-2xl border border-border p-6 shadow-sm">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center flex-shrink-0">
          <i data-lucide="${icon}" class="w-4 h-4 text-gold"></i>
        </div>
        <h2 class="text-lg font-semibold text-primary">${title}</h2>
      </div>
      <div class="text-sm leading-relaxed">${bodyHtml}</div>
    </div>`;
}

// ── Helper: sub info card ─────────────────────────────────────
function privacySubCard(icon, title, bodyHtml) {
  return `
    <div class="flex items-start gap-3 bg-background rounded-xl p-3 border border-border">
      <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center flex-shrink-0 mt-0.5">
        <i data-lucide="${icon}" class="w-3.5 h-3.5 text-gold"></i>
      </div>
      <div>
        <p class="text-sm font-medium text-primary">${title}</p>
        <p class="text-xs text-muted mt-0.5">${bodyHtml}</p>
      </div>
    </div>`;
}

// ── Helper: list item ─────────────────────────────────────────
function privacyItem(text) {
  return `
    <li class="flex items-start gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 text-gold mt-0.5 flex-shrink-0"></i>
      <span>${text}</span>
    </li>`;
}

// ── Helper: right card (security pillars etc.) ────────────────
function privacyRightCard(icon, title, desc) {
  return `
    <div class="bg-background rounded-xl p-4 border border-border text-center">
      <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center mx-auto mb-3">
        <i data-lucide="${icon}" class="w-5 h-5 text-gold"></i>
      </div>
      <p class="text-sm font-semibold text-primary mb-1">${title}</p>
      <p class="text-xs text-muted leading-relaxed">${desc}</p>
    </div>`;
}

// ══════════════════════════════════════════════════════════════
//  SUBMIT FILE — CTA helper & halaman kirim file
// ══════════════════════════════════════════════════════════════

/** Dipanggil dari tombol CTA di beranda.
 *  Jika sudah login → langsung ke /submit-file
 *  Jika belum       → simpan tujuan di sessionStorage, lalu ke halaman login */
function handleSubmitCTA() {
  // Allow anonymous users to access submit page; no login required anymore
  navigate('/submit-file');
}
window.handleSubmitCTA = handleSubmitCTA;

// Setelah login, cek apakah ada pending redirect
(function checkPostLoginRedirect() {
  const target = sessionStorage.getItem('_afterLoginRedirect');
  if (!target) return;
  apiFetch({ action: 'auth_me' }).then(res => {
    if (res.loggedIn) {
      sessionStorage.removeItem('_afterLoginRedirect');
      navigate(target);
    }
  }).catch(() => {});
})();

async function renderSubmitFile() {
  // Sekarang halaman dapat diakses tanpa login; anonymous submit diperbolehkan dengan email

  // Muat kategori
  let cats = [];
  try {
    const r = await apiFetch({ action: 'categories' });
    cats = r.data || [];
  } catch { /* ignore */ }

  app().innerHTML = `
    <div class="min-h-screen bg-cream py-10 px-4">
      <div class="max-w-lg mx-auto">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-7">
          <button onclick="navigate('/')" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gold/30 hover:bg-cream-dark transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4 text-primary"></i>
          </button>
          <div>
            <h1 class="text-lg font-bold text-primary">Kirimkan File</h1>
            <p class="text-xs text-primary/50">Hasil Bahsul Masail atau Kitab</p>
          </div>
        </div>

        <!-- Card form -->
        <div class="bg-white rounded-2xl shadow-card p-6">

          <div id="submit-error"   class="hidden mb-4 p-3 rounded-xl bg-red-50 text-red-600 text-sm"></div>
          <div id="submit-success" class="hidden mb-4 p-4 rounded-xl bg-green-50 text-green-700 text-sm font-medium"></div>

          <form id="submit-form" enctype="multipart/form-data" onsubmit="submitFileForm(event)">

            <!-- Nama File -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                Nama File <span class="text-red-400">*</span>
              </label>
              <input type="text" id="sf-name" name="file_name" required
                placeholder="Contoh: Bahsul Masail Pesantren Al-Falah 2024"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
            </div>

            <!-- Tipe File -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                Tipe File <span class="text-red-400">*</span>
              </label>
              <select id="sf-type" name="file_type" required
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all appearance-none">
                <option value="">— Pilih Tipe —</option>
                <option value="bahsul_masail">Hasil Bahsul Masail</option>
                <option value="kitab">File Kitab</option>
              </select>
            </div>

            <!-- Kategori -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Kategori</label>
              <select id="sf-cat" name="category_id"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all appearance-none">
                <option value="">— Pilih Kategori (opsional) —</option>
                ${cats.map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('')}
              </select>
            </div>

            <!-- Deskripsi -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Deskripsi <span class="text-primary/30 font-normal">(opsional)</span></label>
              <textarea id="sf-desc" name="description" rows="3"
                placeholder="Keterangan singkat tentang isi file…"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all resize-none"></textarea>
            </div>

            <!-- Email pengirim (jika tidak login) -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Email Anda <span class="text-red-400">*</span></label>
              <input type="email" id="sf-email" name="submitter_email" required
                placeholder="email@contoh.com"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
            </div>

            <!-- Upload File -->
            <div class="mb-6">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                File <span class="text-red-400">*</span>
                <span class="text-primary/30 font-normal ml-1">PDF / Word · maks. 20 MB</span>
              </label>
              <label class="flex flex-col items-center justify-center gap-2 w-full border-2 border-dashed border-gold/40 rounded-xl py-7 px-4 bg-cream cursor-pointer hover:border-gold hover:bg-gold/5 transition-all" id="file-drop-zone">
                <i data-lucide="upload-cloud" class="w-8 h-8 text-gold/60"></i>
                <span class="text-sm text-primary/50" id="file-label">Klik untuk pilih file atau seret ke sini</span>
                <input type="file" id="sf-file" name="file" accept=".pdf,.doc,.docx" required class="hidden"
                  onchange="document.getElementById('file-label').textContent = this.files[0]?.name || 'Pilih file'" />
              </label>
            </div>

            <button type="submit" id="sf-submit-btn"
              class="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-primary text-white font-semibold text-sm hover:bg-primary-light transition-colors shadow-sm">
              <i data-lucide="send" class="w-4 h-4"></i>
              <span id="sf-btn-label">Kirim untuk Direview</span>
            </button>

          </form>
        </div>

        <p class="text-xs text-center text-primary/35 mt-5">File akan direview oleh admin sebelum ditampilkan di perpustakaan.</p>
      </div>
    </div>`;

  reicons();
}
window.renderSubmitFile = renderSubmitFile;

async function submitFileForm(e) {
  e.preventDefault();
  const errEl  = document.getElementById('submit-error');
  const okEl   = document.getElementById('submit-success');
  const btn    = document.getElementById('sf-submit-btn');
  const lbl    = document.getElementById('sf-btn-label');

  const showErr = (msg) => { errEl.textContent = msg; errEl.classList.remove('hidden'); okEl.classList.add('hidden'); };
  errEl.classList.add('hidden'); okEl.classList.add('hidden');

  const name   = document.getElementById('sf-name').value.trim();
  const email  = document.getElementById('sf-email')?.value.trim() || '';
  const type   = document.getElementById('sf-type').value;
  const catId  = document.getElementById('sf-cat').value;
  const desc   = document.getElementById('sf-desc').value.trim();
  const fileEl = document.getElementById('sf-file');

  if (!name)         { showErr('Nama file wajib diisi.'); return; }
  if (!email || !/^\S+@\S+\.\S+$/.test(email)) { showErr('Masukkan email yang valid.'); return; }
  if (!type)         { showErr('Pilih tipe file terlebih dahulu.'); return; }
  if (!fileEl.files?.length) { showErr('Pilih file yang akan dikirim.'); return; }

  btn.disabled = true;
  lbl.textContent = 'Mengirim…';

  const fd = new FormData();
  fd.append('file_name',   name);
  fd.append('submitter_email', email);
  fd.append('file_type',   type);
  fd.append('category_id', catId);
  fd.append('description', desc);
  fd.append('file',        fileEl.files[0]);

  try {
    const res = await fetch('/api.php?action=submit_file', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || 'Gagal mengirim.');
    okEl.textContent = data.message || 'Kiriman berhasil dikirim!';
    okEl.classList.remove('hidden');
    document.getElementById('submit-form').reset();
    document.getElementById('file-label').textContent = 'Klik untuk pilih file atau seret ke sini';
  } catch (err) {
    showErr(err.message);
  } finally {
    btn.disabled = false;
    lbl.textContent = 'Kirim untuk Direview';
  }
}
window.submitFileForm = submitFileForm;

// ══════════════════════════════════════════════════════════════
//  PAGE: 404
// ══════════════════════════════════════════════════════════════
function render404() {
  app().innerHTML = `
    <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
      <div class="w-20 h-20 rounded-2xl bg-surface border border-border flex items-center justify-center mb-6">
        <i data-lucide="file-question" class="w-10 h-10 text-muted"></i>
      </div>
      <h1 class="text-4xl font-bold text-primary mb-2">404</h1>
      <p class="text-muted text-lg mb-6">Halaman tidak ditemukan</p>
      <button onclick="navigate('/')"
        class="px-6 py-2.5 bg-gold text-white rounded-xl font-medium hover:bg-yellow-600 transition-colors">
        Kembali ke Beranda
      </button>
    </div>`;
  reicons();
}

// ── Export navigate for admin.js ──────────────────────────────
window.navigate = navigate;
