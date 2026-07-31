// PAGE: KATEGORI  (daftar kategori + kitab per-kategori)
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js?v=2';

export let kategoriState = { selectedCat: null, catName: '', page: 1 };

export async function renderKategori(params) {
  // Jika URL mengandung ?cat=ID langsung tampilkan buku kategori tersebut
  if (params && params.has('cat')) {
    kategoriState.selectedCat = params.get('cat');
    kategoriState.page = parseInt(params.get('page')) || 1;
  } else {
    kategoriState.selectedCat = null;
    kategoriState.catName = '';
    kategoriState.page = 1;
  }

  if (kategoriState.selectedCat) {
    await renderKategoriBuku();
    return;
  }

  // ── Tampilan daftar semua kategori ──────────────────────────
  app().innerHTML = `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-primary">Kategori</h1>
        <p class="text-slate-500 text-sm mt-1">Pilih kategori untuk melihat kitab-kitab di dalamnya, atau cari langsung judul kitab.</p>
        <input id="kat-search" type="text" placeholder="Cari nama kategori atau judul kitab..." 
               class="w-full sm:w-64 mt-4 px-4 py-2.5 rounded-xl border border-gold/30 text-sm text-primary focus:border-gold focus:outline-none bg-white placeholder-slate-400">
      </div>
      <!-- Grid kategori -->
      <div id="kat-cat-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        ${Array.from({length:10},()=>`
          <div class="skeleton h-28 rounded-2xl"></div>`).join('')}
      </div>

      <!-- Container hasil pencarian kitab -->
      <div id="kat-global-buku-section" class="mt-12 hidden">
        <h2 class="text-xl font-bold text-primary mb-4 border-b border-gold/20 pb-2">Hasil Pencarian Kitab</h2>
        <div id="kat-global-buku-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"></div>
      </div>
    </div>
    ${mobileFeedbackBanner}`;

  reicons();

  try {
    const res = await apiFetch({ action: 'categories' });
    const cats = res.data.filter(c => c.book_count > 0);
    const grid = $('#kat-cat-grid');
    if (!grid) return;

    if (!cats.length) {
      grid.innerHTML = `<p class="col-span-full text-center py-16 text-slate-600">Belum ada kategori tersedia.</p>`;
      return;
    }

    grid.innerHTML = cats.map((c, i) => {
      // Pilih ikon lucide berdasarkan urutan
      const icons = ['book-open','folder','scroll','library','archive','bookmark','file-text','layers','grid','tag'];
      const icon  = icons[i % icons.length];
      return `
        <button
          onclick="navigate('/kategori?cat=${c.id}')"
          class="group flex flex-col items-center justify-center gap-3 rounded-2xl border border-gold/25
                 bg-white p-5 shadow-card text-center cursor-pointer
                 hover:border-primary/40 hover:shadow-card-hover hover:bg-primary hover:text-white
                 transition-all duration-200"
          style="animation: cardPop .28s cubic-bezier(.22,.61,.36,1) ${i*30}ms both;"
        >
          <div class="w-12 h-12 rounded-xl bg-primary/8 flex items-center justify-center
                      group-hover:bg-white/15 transition-colors">
            <i data-lucide="${icon}" class="w-5 h-5 text-primary group-hover:text-white transition-colors"></i>
          </div>
          <div>
            <div class="arabic text-sm font-semibold text-primary group-hover:text-white leading-snug line-clamp-2 transition-colors">
              ${escHtml(c.name)}
            </div>
            <div class="text-xs text-gold font-bold mt-1 group-hover:text-gold-light transition-colors">
              ${c.book_count} kitab
            </div>
          </div>
        </button>`;
    }).join('');

    reicons();

    let searchTimeout;
    const searchInp = $('#kat-search');
    if (searchInp) {
      searchInp.oninput = (e) => {
        const q = e.target.value.trim();
        const qLower = q.toLowerCase();
        
        // 1. Filter kategori lokal
        $$('#kat-cat-grid > button').forEach(btn => {
          btn.style.display = btn.textContent.toLowerCase().includes(qLower) ? '' : 'none';
        });

        // 2. Pencarian judul kitab global via API
        const bookSec = $('#kat-global-buku-section');
        const bookGrid = $('#kat-global-buku-grid');
        
        clearTimeout(searchTimeout);
        if (q.length < 2) {
          if (bookSec) bookSec.classList.add('hidden');
          return;
        }

        searchTimeout = setTimeout(async () => {
          if (bookSec) bookSec.classList.remove('hidden');
          if (bookGrid) bookGrid.innerHTML = skeletonCards(4);
          try {
            const res = await apiFetch({ action: 'search_books', q: q, limit: 12 });
            if (bookGrid) {
              bookGrid.innerHTML = res.data && res.data.length 
                ? res.data.map(bookCard).join('')
                : '<p class="col-span-full text-center py-8 text-slate-500 text-sm">Tidak ada kitab yang cocok.</p>';
              reicons();
            }
          } catch(e) {
            if (bookGrid) bookGrid.innerHTML = '<p class="col-span-full text-center py-8 text-red-500 text-sm">Gagal mencari kitab.</p>';
          }
        }, 400); // 400ms debounce delay
      };
    }
  } catch(e) {
    if (handleAuthError(e)) return;
    const g = $('#kat-cat-grid');
    if (g) g.innerHTML = `<p class="col-span-full text-center py-16 text-red-500 text-sm">Gagal memuat kategori.</p>`;
  }
}

export async function renderKategoriBuku() {
  const catId = kategoriState.selectedCat;

  // Cari nama kategori (dari cache API)
  app().innerHTML = `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 mb-6">
        <button onclick="navigate('/kategori')"
          class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-primary transition-colors">
          <i data-lucide="grid" class="w-4 h-4"></i>
          Kategori
        </button>
        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-600"></i>
        <span id="kat-buku-title" class="text-sm font-semibold text-primary">Memuat…</span>
      </div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
        <div>
          <h1 id="kat-buku-h1" class="text-2xl font-bold text-primary">Memuat…</h1>
          <p class="text-slate-500 text-sm mt-1">Kitab-kitab dalam kategori ini</p>
          <input id="kat-book-search" type="text" placeholder="Cari judul kitab di halaman ini..." 
                 class="w-full sm:w-64 mt-4 px-4 py-2.5 rounded-xl border border-gold/30 text-sm text-primary focus:border-gold focus:outline-none bg-white placeholder-slate-400">
        </div>
      </div>
      <!-- Grid buku -->
      <div id="kat-buku-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        ${skeletonCards(12)}
      </div>
      <div id="kat-buku-pagination"></div>
    </div>
    ${mobileFeedbackBanner}`;

  reicons();

  // Ambil nama kategori
  try {
    const res = await apiFetch({ action: 'categories' });
    const cat = (res.data || []).find(c => String(c.id) === String(catId));
    kategoriState.catName = cat ? cat.name : 'Kategori';
    const titleEl = $('#kat-buku-title');
    const h1El    = $('#kat-buku-h1');
    if (titleEl) titleEl.textContent = kategoriState.catName;
    if (h1El)    h1El.textContent    = kategoriState.catName;
  } catch { /* ignore */ }

  await loadKategoriBuku();
}

async function loadKategoriBuku() {
  const grid = $('#kat-buku-grid');
  const pag  = $('#kat-buku-pagination');
  if (!grid) return;
  grid.innerHTML = skeletonCards(12);
  if (pag) pag.innerHTML = '';

  const params = { action: 'books', page: kategoriState.page, limit: 24, cat: kategoriState.selectedCat };

  try {
    const res = await apiFetch(params);
    grid.innerHTML = res.data.length
      ? res.data.map(bookCard).join('')
      : `<p class="col-span-full text-center py-12 text-slate-600">Tidak ada kitab di kategori ini.</p>`;
    if (pag) pag.innerHTML = paginationHtml(res.page, res.total_pages, 'goKategoriBukuPage');

    const searchInp = $('#kat-book-search');
    if (searchInp) {
      const filterBooks = () => {
        const q = searchInp.value.toLowerCase();
        $$('#kat-buku-grid > .book-card').forEach(card => {
          card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
      };
      filterBooks(); // Apply if there's existing text after pagination
      searchInp.oninput = filterBooks;
    }
  } catch(e) {
    if (handleAuthError(e)) return;
    if (grid) grid.innerHTML = `<p class="col-span-full text-center py-12 text-red-500 text-sm">Gagal memuat kitab.</p>`;
  }
  reicons();
}

window.goKategoriBukuPage = function(p) {
  kategoriState.page = p;
  loadKategoriBuku();
window.scrollTo({ top: 0, behavior: 'smooth' });
};

