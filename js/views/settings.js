// PAGE: SETTINGS
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened, escapeRegex, buildArabicRegexStr, parseSearchTerms, highlightTextNodes, hlTextMulti } from '../core/core.js';

export function renderSettings() {
  const FONTS_LAT = [
    'Lato','Inter','Roboto','Open Sans','Poppins',
    'Nunito','Raleway','Merriweather','Playfair Display','Source Sans 3',
  ];
  const FONTS_AR = [
    { k: 'Amiri',                l: 'أميري — Amiri' },
    { k: 'Noto Naskh Arabic',    l: 'نوتو نسخ' },
    { k: 'Cairo',                l: 'القاهرة — Cairo' },
    { k: 'Tajawal',              l: 'تجوّل — Tajawal' },
    { k: 'Scheherazade New',     l: 'شهرزاد' },
    { k: 'Reem Kufi',            l: 'ريم كوفي' },
    { k: 'Lateef',               l: 'لطيف — Lateef' },
    { k: 'Aref Ruqaa',           l: 'عارف رقعة' },
    { k: 'El Messiri',           l: 'المسيري' },
    { k: 'IBM Plex Sans Arabic', l: 'IBM عربي' },
  ];

  // Baca settings saat ini
  const cur = Object.assign(
    { theme: 'light', latin: 'Lato', arabic: 'Amiri', size: 18 },
    JSON.parse(localStorage.getItem('siteSettings') || '{}')
  );

  const isDark = cur.theme === 'dark';

  /* ── helper render chip ── */
  const latChips = FONTS_LAT.map(f => `
    <button class="font-chip${cur.latin === f ? ' active' : ''}"
      data-key="${f}" style="font-family:'${f}',sans-serif"
      onclick="window._sdwSetLatin('${f}'); renderSettingsRefresh()"
    >${f}</button>`).join('');

  const arChips = FONTS_AR.map(f => `
    <button class="font-chip ar${cur.arabic === f.k ? ' active' : ''}"
      data-key="${f.k}" style="font-family:'${f.k}','Amiri',serif"
      onclick="window._sdwSetArabic('${f.k}'); renderSettingsRefresh()"
    >${f.l}</button>`).join('');

  /* ── card helper ── */
  const card = (icon, label, sub, body) => `
    <div class="bg-white rounded-2xl border border-gold/18 p-5 mb-4 shadow-card">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center shrink-0">
          <i data-lucide="${icon}" class="w-4 h-4 text-gold"></i>
        </div>
        <div>
          <div class="text-sm font-bold text-primary">${label}</div>
          ${sub ? `<div class="text-xs text-primary/45 mt-0.5">${sub}</div>` : ''}
        </div>
      </div>
      ${body}
    </div>`;

  app().innerHTML = `
    <div class="max-w-lg mx-auto px-4 sm:px-6 py-10 pb-28 md:pb-10">

      <!-- Header -->
      <div class="flex items-center gap-3 mb-8">
        <div class="w-11 h-11 rounded-2xl bg-primary flex items-center justify-center shadow-md">
          <i data-lucide="settings-2" class="w-5 h-5 text-gold"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-primary">Pengaturan</h1>
          <p class="text-xs text-primary/45 mt-0.5">Tampilan &amp; Aksesibilitas</p>
        </div>
      </div>

      <!-- Tema -->
      ${card('sun-moon', 'Tema Tampilan', 'Pilih tampilan terang atau gelap', `
        <div class="flex rounded-xl overflow-hidden border border-gold/20 p-1 gap-1" style="background:rgba(26,58,42,.06)">
          <button id="pg-theme-light"
            onclick="window.setTheme('light'); renderSettingsRefresh()"
            class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg text-sm font-semibold transition-all
              ${!isDark ? 'bg-white text-primary shadow-sm' : 'text-primary/40 bg-transparent'}"
          >
            <i data-lucide="sun" class="w-4 h-4"></i> Terang
          </button>
          <button id="pg-theme-dark"
            onclick="window.setTheme('dark'); renderSettingsRefresh()"
            class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg text-sm font-semibold transition-all
              ${isDark ? 'bg-primary text-gold shadow-sm' : 'text-primary/40 bg-transparent'}"
          >
            <i data-lucide="moon" class="w-4 h-4"></i> Gelap
          </button>
        </div>
      `)}

      <!-- Ukuran Teks -->
      ${card('a-large-small', 'Ukuran Teks Pembaca', 'Berlaku pada halaman baca kitab', `
        <div class="flex items-center gap-3 mb-3">
          <span class="text-sm text-primary/50" style="font-size:13px">A</span>
          <input type="range" id="pg-size-slider" min="14" max="28" step="1" value="${cur.size}"
            class="font-range flex-1" oninput="pgSizeChange(this.value)">
          <span class="text-sm text-primary/50" style="font-size:20px">A</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-xs text-primary/40">14px — 28px</span>
          <span id="pg-size-lbl" class="text-sm font-bold text-gold">${cur.size}px</span>
        </div>
        <div class="mt-4 p-3 rounded-xl bg-cream border border-gold/15">
          <p id="pg-preview" class="reader-text text-primary/70 leading-relaxed text-center arabic"
             style="font-size:${cur.size}px">
            بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ
          </p>
        </div>
      `)}

      <!-- Font Latin -->
      ${card('type', 'Font Latin', 'Untuk teks berbahasa Latin / Indonesia', `
        <div class="grid grid-cols-2 gap-2">${latChips}</div>
      `)}

      <!-- Font Arab -->
      ${card('type', 'Font Arab', 'Untuk teks berbahasa Arab (RTL)', `
        <div class="grid grid-cols-2 gap-2">${arChips}</div>
      `)}

    </div>
    ${mobileFeedbackBanner}`;

  reicons();

  /* Wire up slider setelah render */
  const slider = document.getElementById('pg-size-slider');
  if (slider) {
    slider.addEventListener('input', () => pgSizeChange(slider.value));
  }
}

/* Perbarui label & preview ukuran teks real-time tanpa re-render full */
window.pgSizeChange = function(val) {
  const n = parseInt(val);
  const lbl     = document.getElementById('pg-size-lbl');
  const preview = document.getElementById('pg-preview');
  if (lbl)     lbl.textContent          = n + 'px';
  if (preview) preview.style.fontSize   = n + 'px';
  // Simpan via fungsi global dari index.php
  if (typeof window._sdwSetSize === 'function') {
    window._sdwSetSize(n);
  } else {
    // Fallback langsung ke localStorage + CSS var
    const s = Object.assign(
      { theme:'light', latin:'Lato', arabic:'Amiri', size:18 },
      JSON.parse(localStorage.getItem('siteSettings') || '{}')
    );
    s.size = n;
    localStorage.setItem('siteSettings', JSON.stringify(s));
    localStorage.setItem('readerFonts', JSON.stringify({ latin: s.latin, arabic: s.arabic, size: n }));
    document.documentElement.style.setProperty('--font-r-size', n + 'px');
  }
};

/* Re-render halaman settings (dipanggil setelah setTheme / setFont) */
window.renderSettingsRefresh = function() {
  if (location.pathname === '/settings') renderSettings();
};

// ══════════════════════════════════════════════════════════════
//  PAGE: SEARCH


// Per-section abort controllers + progressive content search token
let _abortCat = null, _abortBooks = null, _abortCont = null, _abortContAnd = null;
let _contentSearchToken = null; // token string to cancel progressive search
function abortAll() {
  [_abortCat, _abortBooks, _abortCont, _abortContAnd].forEach(c => { try { c && c.abort(); } catch(_){} });
  _abortCat = _abortBooks = _abortCont = _abortContAnd = null;
  // Cancel progressive content search — tapi SIMPAN progres ke cache dulu
  _contentSearchToken = null;
}

// ── Cache pencarian persisten (bertahan saat navigasi) ────────
// Dibersihkan hanya ketika query berubah atau user tekan clear.
const _src = {
  q        : '',     // query yang di-cache
  results  : [],     // semua hasil yang ditemukan
  page     : 1,      // halaman terakhir dilihat user
  complete : false,  // true = semua kitab sudah diperiksa
  books    : [],     // daftar semua kitab (dari API, di-cache)
  checked  : 0,      // berapa kitab sudah dicek
  found    : 0,      // berapa kitab yang ada hasilnya
};

function _srcClear() {
  _src.q = ''; _src.results = []; _src.page = 1;
  _src.complete = false; _src.books = [];
  _src.checked  = 0; _src.found = 0;
}

// searchState declared here (used by renderSearch, execSearch, pagination)
export const searchState = { q: '', bookPage: 1, contPage: 1, pdfPage: 1, contAndPage: 1 };
export const searchAdvancedState = {
  terms: ['', '', '', '', ''],
  cats: [],
  allCats: false,   // true = semua kategori dipilih, kirim all_cats=1 ke API
  page: 1,
  samePage: true,
  categories: [],
};











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
         onclick="navigate('/kitab?id=${book.bkid}&content_id=${book.match_id}&q=${encodeURIComponent(buildAdvancedSearchQuery()).replace(/'/g, "%27")}')">
      <div class="arabic text-primary font-semibold text-sm leading-snug line-clamp-2">${titleHtml}</div>
      ${authorHtml ? `<div class="text-primary/55 text-xs line-clamp-1">${authorHtml}</div>` : ''}
      ${snippetHtml ? `<div class="snippet-bar reader-text line-clamp-4">${snippetHtml}…</div>` : ''}
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        <span class="text-xs text-primary/50 truncate max-w-[65%]">${escHtml(book.category_name || '')}</span>
        ${pageLabel ? `<span class="text-xs text-gold font-medium flex items-center gap-1"><i data-lucide="bookmark" class="w-3 h-3"></i>${escHtml(pageLabel)}</span>` : ''}
      </div>
    </div>`;
}

export function renderAdvancedCategories(categories) {
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
      let didYouMeanHtml = '';
      if (res.did_you_mean) {
        didYouMeanHtml = `<div class="mt-2 text-primary font-medium">Maksud Anda: <a href="/search-advanced?q1=${encodeURIComponent(res.did_you_mean)}" class="text-gold hover:underline cursor-pointer" onclick="event.preventDefault(); navigate(this.getAttribute('href'))">${escHtml(res.did_you_mean)}</a> ?</div>`;
      }
      stats.innerHTML = `<div class="flex flex-col">
        <div class="flex flex-wrap items-center gap-2 text-sm text-primary/60">
          <span>Menemukan <strong>${total.toLocaleString('id-ID')}</strong> halaman untuk <strong>${escHtml(queryLabel)}</strong>.</span>
          ${catLabel}
          ${perfBadge}
        </div>
        ${didYouMeanHtml}
      </div>`;
    }
    const qTerms = searchAdvancedState.terms.filter(t => t.trim()).join(' ');
    const recs = await getSearchRecommendationsHtml(qTerms);

    if (res.data.length) {
      wrap.innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${res.data.map(book => advancedContentCard(book)).join('')}</div>
         ${paginationHtml(page, totalPages, 'goAdvancedPage')}
         ${recs}`;
      wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
      wrap.innerHTML = `<div class="text-center py-20 text-primary/40 flex flex-col items-center">
        <i data-lucide="search-x" class="w-12 h-12 text-primary/20 mb-4"></i>
        <p>Maaf, tidak ditemukan halaman yang cocok dengan kata kunci dan kategori yang dipilih.</p>
        ${recs}
      </div>`;
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

export function renderSearchAdvanced(params) {
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
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
    </div>
    ${mobileFeedbackBanner}`;

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
export function renderSearch(params) {
  const newQ = params.get('q') || '';

  // Deteksi apakah ini navigasi kembali ke query yang sama
  const sameQuery = false; // caching is now handled by the browser/API

  searchState.q        = newQ;
  searchState.bookPage = 1;
  searchState.contPage = 1;
  searchState.pdfPage = 1;
  searchState.contAndPage = 1;

  // Jika kembali ke query yang sama dengan hasil tersimpan:
  const showSkeleton = newQ.length >= 2;

  app().innerHTML = `
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="max-w-2xl mx-auto mb-8">
        <div class="relative group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-primary/35 transition-colors group-focus-within:text-gold"></i>
          <input id="search-input" type="text" value="${escHtml(searchState.q)}"
            placeholder="Cari kata kunci pada isi ribuan kitab…"
            class="search-input-premium w-full pl-12 pr-24 py-4 rounded-2xl border border-gold/30 bg-white text-sm focus:outline-none focus:border-gold shadow-card" />
          <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
            <button id="search-clear" class="p-2 text-primary/30 hover:text-primary transition-colors ${searchState.q ? '' : 'hidden'}" title="Hapus pencarian">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <button id="search-btn" class="p-2 bg-gold text-primary rounded-xl hover:bg-gold-light transition-colors shadow-sm" title="Mulai pencarian">
              <i data-lucide="search" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
        <p class="mt-3 text-xs text-primary/50">Sistem pencarian modern: mencari isi halaman seluruh kitab secara instan dan menyeluruh.</p>
        <div class="mt-3 text-right">
          <a href="/search-advanced" data-route="/search-advanced" class="inline-flex items-center gap-2 text-sm font-semibold text-gold hover:text-gold-dark transition">
            <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
            Pencarian Lanjutan
          </a>
        </div>
        <div id="search-stats" class="search-stats mt-4 text-center"></div>
      </div>
      <div id="search-results">
        ${showSkeleton ? `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">${skeletonCards(6)}</div>` : (newQ.length >= 2 ? '' : emptySearchPrompt())}
      </div>
    </div>
    ${mobileFeedbackBanner}`;

  reicons();
  const inp = $('#search-input'), clr = $('#search-clear'), btn = $('#search-btn');

  clr?.addEventListener('click', () => {
    inp.value = ''; searchState.q = '';
    abortAll();
    _srcClear();  // hapus cache saat user clear
    clr.classList.add('hidden');
    $('#search-results').innerHTML = emptySearchPrompt();
    $('#search-stats').innerHTML = '';
    reicons(); inp.focus();
  });

  inp?.addEventListener('input', e => {
    searchState.q = e.target.value.trim();
    searchState.bookPage = searchState.contPage = searchState.pdfPage = searchState.contAndPage = 1;
    clr?.classList.toggle('hidden', !searchState.q);
    if (!searchState.q) { abortAll(); $('#search-results').innerHTML = emptySearchPrompt(); $('#search-stats').innerHTML = ''; reicons(); }
  });

  const triggerSearch = () => {
    if (searchState.q.length >= 2) {
      execSearch();
    }
  };

  inp?.addEventListener('keydown', e => { if (e.key === 'Enter') triggerSearch(); });
  btn?.addEventListener('click', triggerSearch);

  if (newQ.length >= 2) {
    execSearch();
  }
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

// ── Search Recommendations ─────────────────────────────────────
async function getSearchRecommendationsHtml(q = '') {
  if (!window._searchRecsCache) window._searchRecsCache = {};
  const cacheKey = 'recs_' + q;
  if (!window._searchRecsCache[cacheKey]) {
    try {
      const res = await apiFetch({ action: 'search_recommendations', q });
      window._searchRecsCache[cacheKey] = res.data || [];
    } catch { window._searchRecsCache[cacheKey] = []; }
  }
  const recs = window._searchRecsCache[cacheKey];
  if (!recs.length) return '';
  
  return `
    <div class="mt-8 pt-6 border-t border-cream-dark w-full max-w-2xl mx-auto text-center search-section-enter">
      <div class="text-xs font-bold text-primary/40 uppercase tracking-wider mb-4">Mungkin Anda mencari</div>
      <div class="flex flex-wrap justify-center gap-2">
        ${recs.map(itemObj => {
          const item = typeof itemObj === 'string' ? itemObj : itemObj.query;
          const detail = typeof itemObj === 'object' && itemObj.detail ? JSON.parse(itemObj.detail) : null;
          
          let route = '';
          if (item.includes('|')) {
            const parts = item.split('|').map(p => p.trim());
            const params = new URLSearchParams();
            parts.forEach((p, i) => { if (p) params.set('q' + (i + 1), p); });
            
            if (detail && detail.cats && detail.cats.length > 0) {
              params.set('cats', detail.cats.join(','));
            } else if (detail && detail.all_cats) {
              params.set('all_cats', '1');
            } else if (!detail) {
              params.set('all_cats', '1');
            }
            
            route = '/search-advanced?' + params.toString().replace(/'/g, "%27");
          } else {
            route = '/search?q=' + encodeURIComponent(item).replace(/'/g, "%27");
          }
          return `<button onclick="navigate('${route}')" class="px-3 py-1.5 rounded-full border border-gold/30 bg-gold/5 text-gold text-xs hover:bg-gold/10 transition-colors font-medium flex items-center gap-1.5 shadow-sm">
            <i data-lucide="search" class="w-3 h-3"></i>${escHtml(item)}
          </button>`;
        }).join('')}
      </div>
    </div>
  `;
}

// ── Empty state (premium) ─────────────────────────────────────
function emptySearchPrompt() {
  const q = window.searchState?.q || '';
  getSearchRecommendationsHtml(q).then(recs => {
    const wrap = document.getElementById('search-empty-recs');
    if (wrap && recs) { wrap.innerHTML = recs; reicons(); }
  });
  return `<div class="flex flex-col items-center py-20 gap-4">
    <div class="w-20 h-20 rounded-full bg-primary/5 flex items-center justify-center">
      <i data-lucide="search" class="w-9 h-9 text-primary/20"></i>
    </div>
    <p class="text-primary/35 text-sm font-medium">Masukkan kata kunci untuk mencari</p>
    <p class="text-primary/20 text-xs">Minimal 2 karakter</p>
    <div id="search-empty-recs" class="w-full"></div>
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
  const totalJuz   = b.total_juz || 1;
  const fmtBadge   = totalJuz > 1
    ? `<span class="dl-fmt-badge dl-fmt-zip">ZIP·${totalJuz}j</span>`
    : `<span class="dl-fmt-badge dl-fmt-docx">DOCX</span>`;
  const dlTitle    = totalJuz > 1 ? `Unduh ZIP (${totalJuz} juz)` : 'Unduh DOCX';
  return `
    <div class="book-card search-card-stagger bg-white rounded-2xl shadow-card p-4 flex flex-col gap-2 cursor-pointer border border-transparent hover:border-gold/30 hover:shadow-[0_16px_40px_rgba(201,168,76,.12)] transition-all"
         style="animation-delay:${i*40}ms" onclick="navigate('/kitab?id=${b.bkid}')">
      <div class="arabic text-primary font-semibold text-sm leading-snug line-clamp-2">${titleHtml}</div>
      <div class="text-primary/55 text-xs line-clamp-1">${authorHtml}</div>
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        <div class="flex items-center gap-2">
          ${cat   ? `<span class="text-xs bg-primary/8 text-primary/60 px-2 py-0.5 rounded-full truncate max-w-[60%]">${escHtml(cat)}</span>` : '<span></span>'}
        </div>
        <div class="flex items-center gap-2">
          ${pages ? `<span class="text-xs text-gold font-medium">${pages}</span>` : ''}
          <a href="/api.php?action=download_book&id=${b.bkid}"
             class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gold/20 text-gold hover:bg-gold/10 transition"
             onclick="event.stopPropagation();"
             title="${dlTitle}"
             aria-label="${dlTitle}">
            <i data-lucide="download" class="w-3.5 h-3.5 shrink-0"></i>
            ${fmtBadge}
          </a>
        </div>
      </div>
    </div>`;
}

// ── PDF tracking function ───────────────────────────────────
window.trackScholariumDownload = function(e, id, name, link) {
  e.preventDefault();
  e.stopPropagation();
  apiFetch({ action: 'log_download_scholarium' }, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id, name })
  }).catch(()=>{});
  window.open(link, '_blank');
};

// ── PDF card with stagger animation ─────────────────────────
function pdfCardStagger(b, i, q = '') {
  const name  = b.name || 'بدون عنوان';
  const path  = b.path_visual || '';
  const nameHtml  = hlText(name, q);
  const link  = b.link || ('https://drive.google.com/uc?id=' + b.drive_id + '&export=download');
  const safeName = name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
  const safeLink = link.replace(/'/g, "\\'");
  const onclickStr = `window.trackScholariumDownload(event, ${b.id}, '${safeName}', '${safeLink}')`;
  return `
    <div class="book-card search-card-stagger bg-white rounded-2xl shadow-card p-4 flex flex-col gap-2 cursor-pointer border border-transparent hover:border-gold/30 hover:shadow-[0_16px_40px_rgba(201,168,76,.12)] transition-all"
         style="animation-delay:${i*40}ms" onclick="${onclickStr}">
      <div class="arabic text-primary font-semibold text-sm leading-snug line-clamp-2">${nameHtml}</div>
      <div class="text-primary/55 text-[10px] line-clamp-2 leading-tight">${escHtml(path)}</div>
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        <div class="flex items-center gap-2">
           <span class="dl-fmt-badge dl-fmt-zip" style="background:rgba(212,82,82,.14); color:#c53030; border-color:rgba(212,82,82,.35);">PDF</span>
        </div>
        <div class="flex items-center gap-2">
          <a href="${link}" target="_blank"
             class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gold/20 text-gold hover:bg-gold/10 transition"
             onclick="${onclickStr}"
             title="Unduh PDF"
             aria-label="Unduh PDF">
            <i data-lucide="download" class="w-3.5 h-3.5 shrink-0"></i>
            <span class="text-[10px] font-bold">Unduh</span>
          </a>
        </div>
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
  const juzParam  = b.match_juz ? `&juz=${b.match_juz}` : '';
  const pageParam = b.match_page ? `&page=${b.match_page}` : '';
  const qParam    = q ? `&q=${encodeURIComponent(q).replace(/'/g, "%27")}` : '';
  return `
    <div class="book-card bg-white rounded-2xl shadow-card p-4 flex flex-col gap-2 cursor-pointer border border-transparent hover:border-gold/30 hover:shadow-[0_16px_40px_rgba(201,168,76,.12)] transition-all"
         onclick="navigate('/kitab?id=${b.bkid}&content_id=${b.match_id}&q=${encodeURIComponent(q).replace(/'/g, "%27")}')">
      <div class="arabic text-primary font-semibold text-sm leading-snug line-clamp-2">${titleHtml}</div>
      ${authorHtml ? `<div class="text-primary/55 text-xs line-clamp-1">${authorHtml}</div>` : ''}
      ${hlSnip ? `<div class="snippet-bar reader-text line-clamp-3">${hlSnip}…</div>` : ''}
      <div class="flex items-center justify-between mt-auto pt-2 border-t border-cream-dark">
        <div class="flex items-center gap-2">
          ${cat  ? `<span class="text-xs text-primary/50 truncate max-w-[65%]">${escHtml(cat)}</span>` : '<span></span>'}
        </div>
        <div class="flex items-center gap-2">
          ${page ? `<span class="text-xs text-gold font-medium flex items-center gap-1"><i data-lucide="bookmark" class="w-3 h-3"></i>${page}</span>` : ''}
          <a href="/api.php?action=download_book&id=${b.bkid}"
             class="inline-flex items-center justify-center p-2 rounded-full border border-gold/20 text-gold hover:bg-gold/10 transition"
             onclick="event.stopPropagation();"
             aria-label="Unduh kitab"
             title="Unduh kitab">
            <i data-lucide="download" class="w-3.5 h-3.5"></i>
          </a>
        </div>
      </div>
    </div>`;
}


// ── Patch a section header in place ──────────────────────────
function patchHeader(secId, icon, label, total, loading = false) {
  const hdr = $(`#${secId} .sec-header`);
  if (hdr) { hdr.outerHTML = sectionHeader(icon, label, total, loading); reicons(); }
}

// ── Progressive content search — per-book, satu per satu ─────
// Hasil dikumpulkan di _contResults, ditampilkan per halaman (CONT_PAGE_SIZE).
// Pencarian tetap berjalan di latar belakang; halaman 1 diperbarui langsung.
const CONT_PAGE_SIZE = 9;
let _contResults     = [];   // semua hasil terkumpul (alias _src.results)
let _contCurrentPage = 1;    // halaman aktif yang sedang ditampilkan
let _contSearchQ     = '';   // query terakhir (untuk highlight & navigasi)

// ── Restore tampilan dari cache setelah kembali dari navigasi ─
function _restoreSearchFromCache(q) {
  const wrap = $('#search-results');
  if (!wrap) return;

  // Sync state dari cache
  _contResults     = [..._src.results];
  _contCurrentPage = _src.page;
  _contSearchQ     = q;

  // Rebuild skeletal UI dengan hasil cache (kategori & judul masih perlu di-fetch ulang)
  wrap.innerHTML = `
    <div id="sec-cat" class="mb-2">
      ${sectionHeader('folder-open','Kategori', null, true)}
      <div id="sec-cat-body"><div class="flex flex-wrap gap-2">
        ${Array.from({length:3},()=>`<div class="skeleton h-9 w-28 rounded-full"></div>`).join('')}
      </div></div>
    </div>
    ${sectionDivider()}
    <div id="sec-books" class="mb-2">
      ${sectionHeader('book-open','Judul Kitab', null, true)}
      <div id="sec-books-body"><div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${skeletonCards(4)}</div></div>
    </div>
    ${sectionDivider()}
    <div id="sec-content">
      ${sectionHeader('file-text','Isi Kitab', _contResults.length, !_src.complete)}
      <div id="sec-content-body">
        <div id="cont-progress-bar" class="mb-3">
          ${!_src.complete ? `<div style="font-size:12px;color:rgba(26,58,42,.45);display:flex;align-items:center;gap:6px;padding:6px 0">
            <span class="spin-ring" style="width:13px;height:13px;border-width:2px;"></span>
            Melanjutkan pencarian…
          </div>` : ''}
        </div>
        <div id="cont-results-grid"></div>
        <div id="cont-no-result" class="${_contResults.length ? 'hidden' : ''}">${noResultBlock('Tidak ada kecocokan pada isi kitab.')}</div>
        <div id="cont-pagination"></div>
      </div>
    </div>
    </div>`;
  reicons();

  // Render halaman cache langsung
  if (_contResults.length) renderContPage(_contResults, _contCurrentPage, q, !_src.complete);

  // Fetch ulang kategori & judul kitab (ringan, tidak perlu cache)
  const t0 = performance.now();
  let fastDone = 0;
  const onFastDone = () => {
    if (++fastDone === 2) {
      const ms = Math.round(performance.now() - t0);
      const st = $('#search-stats');
      if (st) { st.innerHTML = `<i data-lucide="zap" class="w-3 h-3 text-gold"></i> ${ms} ms`; reicons(); }
    }
  };

  _abortCat = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_categories', q}), {signal:_abortCat.signal})
    .then(r=>r.json()).then(res => {
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
    }).catch(()=>{ const b=$('#sec-cat-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onFastDone);

  _abortBooks = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_books', q, page:1}), {signal:_abortBooks.signal})
    .then(r=>r.json()).then(res => {
      patchHeader('sec-books','book-open','Judul Kitab', res.total);
      const body = $('#sec-books-body');
      if (!body) return;
      body.innerHTML = res.data.length
        ? `<div class="search-section-enter">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${res.data.map((b,i)=>bookCardStagger(b,i,q)).join('')}</div>
            ${paginationHtml(res.page, res.total_pages, 'goSearchBookPage')}</div>`
        : noResultBlock('Tidak ada kitab yang cocok pada judul atau pengarang.');
      reicons();
    }).catch(()=>{ const b=$('#sec-books-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onFastDone);

  // Jika pencarian belum selesai, lanjutkan dari titik terakhir
  if (!_src.complete) {
    const token = q + '_' + Date.now();
    _contentSearchToken = token;
    _resumeProgressiveContentSearch(q, token);
  }
}

// ── Tambahkan kartu baru ke page-1 grid tanpa menyentuh yg sudah ada ──
// Hanya dipanggil selama pencarian berjalan di halaman 1.
// Menggunakan appendChild → tidak ada re-render, tidak ada jitter.
function appendContCards(newItems, q) {
  let inner = document.getElementById('cont-cards-inner');
  if (!inner) {
    // Buat inner grid pertama kali
    const grid = document.getElementById('cont-results-grid');
    if (!grid) return;
    inner = document.createElement('div');
    inner.id = 'cont-cards-inner';
    inner.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 search-section-enter';
    grid.appendChild(inner);
  }

  for (const item of newItems) {
    if (inner.children.length >= CONT_PAGE_SIZE) break; // halaman 1 sudah penuh
    const wrapper = document.createElement('div');
    wrapper.innerHTML = contentCard(item, q);
    const card = wrapper.firstElementChild;
    if (card) {
      card.style.animation = 'cardPop .3s cubic-bezier(.22,.61,.36,1) both';
      inner.appendChild(card);
    }
  }
}

// ── Update area pagination saja (tidak menyentuh grid kartu) ────────
function updateContPagination(totalResults, currentPage, searching) {
  const pag = document.getElementById('cont-pagination');
  if (!pag) return;
  const totalPages    = Math.max(1, Math.ceil(totalResults / CONT_PAGE_SIZE));
  const searchingNote = searching
    ? `<span style="font-size:11px;color:rgba(26,58,42,.4);display:flex;align-items:center;gap:5px;">
         <span class="spin-ring" style="width:12px;height:12px;border-width:2px;"></span>
         Masih mencari&hellip;
       </span>`
    : '';
  pag.innerHTML = (totalResults > CONT_PAGE_SIZE || (searching && totalResults > 0))
    ? `<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-top:12px;">
         ${paginationHtml(currentPage, totalPages, 'goSearchContResultPage')}
         ${searchingNote}
       </div>`
    : (searching
        ? `<div style="display:flex;justify-content:flex-end;margin-top:8px;">${searchingNote}</div>`
        : '');
  reicons();
}

// ── Full re-render satu halaman (hanya dipanggil saat user klik nomor) ─
// Full innerHTML aman di sini karena user yang meminta pindah halaman.
export function renderContPage(results, page, q, searching) {
  const grid = document.getElementById('cont-results-grid');
  if (!grid) return;

  const start = (page - 1) * CONT_PAGE_SIZE;
  const slice = results.slice(start, start + CONT_PAGE_SIZE);

  if (slice.length) {
    // Hapus konten lama, buat inner baru dengan animasi slide-in
    grid.innerHTML = '';
    const inner = document.createElement('div');
    inner.id        = 'cont-cards-inner';
    inner.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 search-section-enter';
    inner.innerHTML = slice.map(item => contentCard(item, q)).join('');
    grid.appendChild(inner);
  } else {
    grid.innerHTML = '';
  }

  updateContPagination(results.length, page, searching);
  reicons();
}

async function execSearch() {
  const wrap = $('#search-results');
  const stats = $('#search-stats');
  if (!wrap || searchState.q.length < 2) return;

  abortAll();
  const q = searchState.q;
  history.replaceState({}, '', '/search?q=' + encodeURIComponent(q));

  // Catat history pencarian secara eksplisit (hanya 1x per pencarian)
  fetch(API + '?action=log_search', { method: 'POST', body: new URLSearchParams({q}) }).catch(()=>{});

  // Render instant skeleton shells for 3 sections
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
    <div id="sec-content" class="mb-2">
      ${sectionHeader('file-text','Isi Kitab', null, true)}
      <div id="sec-content-body"><div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${skeletonCards(6)}</div></div>
    </div>
    ${sectionDivider()}
    <div id="sec-content-and" class="mb-2 hidden">
      ${sectionHeader('layers','Isi Kitab (Kata Tersebar)', null, true)}
      <div id="sec-content-and-body"><div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${skeletonCards(6)}</div></div>
    </div>
    ${sectionDivider()}
    <div id="sec-pdf">
      ${sectionHeader('download','Download File PDF', null, true)}
      <div id="sec-pdf-body"><div class="grid grid-cols-1 sm:grid-cols-2 gap-3">${skeletonCards(4)}</div></div>
    </div>
    </div>`;

  if (stats) {
    stats.innerHTML = `<span class="inline-flex items-center gap-1.5 text-sm text-primary/40"><span class="spin-ring"></span> Mencari…</span>`;
  }
  reicons();

  const t0 = performance.now();
  let fastDone = 0;
  let totalHits = 0;
  const onFastDone = async () => {
    if (++fastDone === 4) {
      const ms = Math.round(performance.now() - t0);
      if (stats) { stats.innerHTML = `<i data-lucide="zap" class="w-3 h-3 text-gold"></i> ${ms} ms`; reicons(); }
      const wrap = document.getElementById('search-results');
      if (wrap) {
        const recs = await getSearchRecommendationsHtml(searchState.q);
        if (recs) {
          const recDiv = document.createElement('div');
          recDiv.innerHTML = recs;
          wrap.appendChild(recDiv);
          reicons();
        }
      }
    }
  };

  // 1. Kategori (parallel, cepat)
  _abortCat = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_categories', q, skip_log: '1'}), {signal:_abortCat.signal})
    .then(r=>r.json()).then(res => {
      if ($('#search-input')?.value.trim() !== q) return;
      const catCount = res.data ? res.data.length : 0;
      totalHits += catCount;
      patchHeader('sec-cat','folder-open','Kategori', catCount);
      const body = $('#sec-cat-body');
      if (!body) return;
      body.innerHTML = res.data && res.data.length
        ? `<div class="search-section-enter flex flex-wrap gap-2">
            ${res.data.map((c,i)=>`<button class="cat-chip" style="animation-delay:${i*35}ms" onclick="navigate('/katalog?cat=${c.id}')">
              <i data-lucide="folder" class="w-3.5 h-3.5 text-gold shrink-0"></i>
              ${escHtml(c.name)}<span class="text-gold/80 text-xs font-bold">${c.book_count}</span>
            </button>`).join('')}</div>`
        : noResultBlock('Tidak ada kategori yang cocok.');
      reicons();
    }).catch(()=>{ const b=$('#sec-cat-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onFastDone);

  // 2. Judul Kitab (parallel, cepat)
  _abortBooks = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_books', q, page:searchState.bookPage, skip_log: '1'}), {signal:_abortBooks.signal})
    .then(r=>r.json()).then(res => {
      if ($('#search-input')?.value.trim() !== q) return;
      const bookCount = res.total || 0;
      totalHits += bookCount;
      patchHeader('sec-books','book-open','Judul Kitab', bookCount);
      const body = $('#sec-books-body');
      if (!body) return;
      body.innerHTML = res.data && res.data.length
        ? `<div class="search-section-enter">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${res.data.map((b,i)=>bookCardStagger(b,i,q)).join('')}</div>
            ${paginationHtml(res.page, res.total_pages, 'goSearchBookPage')}</div>`
        : noResultBlock('Tidak ada kitab yang cocok pada judul atau pengarang.');
      reicons();
    }).catch(()=>{ const b=$('#sec-books-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onFastDone);

  // 3. Isi Kitab (parallel) menggunakan search_advanced untuk pencarian konten instan
  _abortCont = new AbortController();
  searchAdvancedState.terms = [q, '', '', '', ''];
  const params = { action: 'search_advanced', page: searchState.contPage || 1, all_cats: '1', q1: q, skip_log: '1' };
  fetch(API + '?' + new URLSearchParams(params), {signal:_abortCont.signal})
    .then(r=>r.json()).then(res => {
      if ($('#search-input')?.value.trim() !== q) return;
      const total = res.total || 0;
      totalHits += total;
      patchHeader('sec-content','file-text','Isi Kitab', total);
      const body = $('#sec-content-body');
      if (!body) return;
      let emptyMsg = 'Maaf, tidak ditemukan halaman yang cocok dengan kata kunci tersebut.';
      
      if (res.did_you_mean) {
          let dymEl = document.getElementById('global-did-you-mean');
          if (!dymEl) {
              dymEl = document.createElement('div');
              dymEl.id = 'global-did-you-mean';
              dymEl.className = 'mb-4 text-sm text-primary font-medium p-3 bg-gold/10 rounded-xl border border-gold/20';
              dymEl.innerHTML = `<i data-lucide="lightbulb" class="inline w-4 h-4 text-gold mr-1 -mt-1"></i> Maksud Anda: <a href="javascript:void(0)" class="text-gold font-bold hover:underline" onclick="document.getElementById('search-input').value='${escHtml(res.did_you_mean).replace(/'/g, "\\'")}'; document.getElementById('search-input').dispatchEvent(new Event('input')); document.getElementById('search-btn').click();">${escHtml(res.did_you_mean)}</a> ?`;
              const wrap = document.getElementById('search-results');
              if (wrap) {
                  wrap.insertBefore(dymEl, wrap.firstChild);
              }
          }
      }
      
      body.innerHTML = res.data && res.data.length
        ? `<div class="search-section-enter">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${res.data.map(book => advancedContentCard(book, [q])).join('')}</div>
            ${paginationHtml(res.page || 1, res.total_pages || 1, 'goSearchContPage')}</div>`
        : noResultBlock(emptyMsg);
      reicons();
    }).catch(()=>{ const b=$('#sec-content-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onFastDone);

  // 3b. Isi Kitab (parallel) dengan logika AND (hanya jika ada spasi)
  const qWords = q.trim().split(/\s+/);
  if (qWords.length > 1) {
    $('#sec-content-and')?.classList.remove('hidden');
    _abortContAnd = new AbortController();
    const paramsAnd = { action: 'search_advanced', page: searchState.contAndPage || 1, all_cats: '1', skip_log: '1' };
    qWords.slice(0, 5).forEach((w, i) => paramsAnd['q' + (i+1)] = w);
    
    fetch(API + '?' + new URLSearchParams(paramsAnd), {signal:_abortContAnd.signal})
      .then(r=>r.json()).then(res => {
        if ($('#search-input')?.value.trim() !== q) return;
        patchHeader('sec-content-and','layers','Isi Kitab (Kata Tersebar)', res.total || 0);
        const bodyAnd = $('#sec-content-and-body');
        if (!bodyAnd) return;
        bodyAnd.innerHTML = res.data && res.data.length
          ? `<div class="search-section-enter">
              <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${res.data.map(book => advancedContentCard(book, qWords)).join('')}</div>
              ${paginationHtml(res.page || 1, res.total_pages || 1, 'goSearchContAndPage')}</div>`
          : noResultBlock('Maaf, tidak ditemukan halaman yang cocok dengan kata kunci tersebut.');
        reicons();
      }).catch(()=>{ const b=$('#sec-content-and-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); });
  } else {
    $('#sec-content-and')?.classList.add('hidden');
  }

  // 4. Download file pdf (parallel)
  let _abortPdf = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_scholarium_pdfs', q, page:searchState.pdfPage || 1, skip_log: '1'}), {signal:_abortPdf.signal})
    .then(r=>r.json()).then(res => {
      if ($('#search-input')?.value.trim() !== q) return;
      const pdfCount = res.total || 0;
      totalHits += pdfCount;
      patchHeader('sec-pdf','download','Download File PDF', pdfCount);
      const body = $('#sec-pdf-body');
      if (!body) return;
      body.innerHTML = res.data && res.data.length
        ? `<div class="search-section-enter">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">${res.data.map((b,i)=>pdfCardStagger(b,i,q)).join('')}</div>
            ${paginationHtml(res.page || 1, res.total_pages || 1, 'goSearchPdfPage')}</div>`
        : noResultBlock('Tidak ada file PDF yang cocok.');
      reicons();
    }).catch(()=>{ const b=$('#sec-pdf-body'); if(b) b.innerHTML=noResultBlock('Gagal memuat.'); }).finally(onFastDone);
}

window.goSearchBookPage = function(p) {
  searchState.bookPage = p;
  const q = searchState.q, body = $('#sec-books-body');
  if (body) body.innerHTML = `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${skeletonCards(4)}</div>`;
  _abortBooks = new AbortController();
  fetch(API + '?' + new URLSearchParams({action:'search_books', q, page:p, skip_log: '1'}), {signal:_abortBooks.signal})
    .then(r=>r.json()).then(res => {
      patchHeader('sec-books','book-open','Judul Kitab', res.total);
      if (!body) return;
      body.innerHTML = res.data && res.data.length
        ? `<div class="search-section-enter"><div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">${res.data.map((b,i)=>bookCardStagger(b,i,q)).join('')}</div>${paginationHtml(res.page,res.total_pages,'goSearchBookPage')}</div>`
        : noResultBlock('Tidak ada hasil.');
      reicons(); $('#sec-books')?.scrollIntoView({behavior:'smooth',block:'start'});
    }).catch(()=>{});
};

window.goSearchContPage = function(p) {
  searchState.contPage = p;
  const q = searchState.q, body = $('#sec-content-body');
  if (body) body.innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${skeletonCards(6)}</div>`;
  _abortCont = new AbortController();
  searchAdvancedState.terms = [q, '', '', '', ''];
  const params = { action: 'search_advanced', page: p, all_cats: '1', q1: q, skip_log: '1' };
  fetch(API + '?' + new URLSearchParams(params), {signal:_abortCont.signal})
    .then(r=>r.json()).then(res => {
      patchHeader('sec-content','file-text','Isi Kitab', res.total || 0);
      if (!body) return;
      body.innerHTML = res.data && res.data.length
        ? `<div class="search-section-enter"><div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${res.data.map(book => advancedContentCard(book, [q])).join('')}</div>${paginationHtml(res.page || 1, res.total_pages || 1, 'goSearchContPage')}</div>`
        : noResultBlock('Tidak ada hasil.');
      reicons(); $('#sec-content')?.scrollIntoView({behavior:'smooth',block:'start'});
    }).catch(()=>{});
};

window.goSearchContAndPage = function(p) {
  searchState.contAndPage = p;
  const q = searchState.q, bodyAnd = $('#sec-content-and-body');
  if (bodyAnd) bodyAnd.innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${skeletonCards(6)}</div>`;
  _abortContAnd = new AbortController();
  
  const qWords = q.trim().split(/\s+/);
  const paramsAnd = { action: 'search_advanced', page: p, all_cats: '1', skip_log: '1' };
  qWords.slice(0, 5).forEach((w, i) => paramsAnd['q' + (i+1)] = w);

  fetch(API + '?' + new URLSearchParams(paramsAnd), {signal:_abortContAnd.signal})
    .then(r=>r.json()).then(res => {
      patchHeader('sec-content-and','layers','Isi Kitab (Kata Tersebar)', res.total || 0);
      if (!bodyAnd) return;
      bodyAnd.innerHTML = res.data && res.data.length
        ? `<div class="search-section-enter"><div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">${res.data.map(book => advancedContentCard(book, qWords)).join('')}</div>${paginationHtml(res.page || 1, res.total_pages || 1, 'goSearchContAndPage')}</div>`
        : noResultBlock('Tidak ada hasil.');
      reicons(); $('#sec-content-and')?.scrollIntoView({behavior:'smooth',block:'start'});
    }).catch(()=>{});
};

