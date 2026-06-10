// PAGE: KATALOG
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js';

export let katalogState = { page: 1, cat: '' };

export async function renderKatalog(params) {
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
    </div>
    ${window.mobileFeedbackBanner}`;

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
  } catch(e) {
    if (handleAuthError(e)) return;
    grid.innerHTML = '<p class="col-span-full text-center py-12 text-red-500 text-sm">Gagal memuat katalog.</p>';
  }
  reicons();
}

window.goKatalogPage = function(p) {
  katalogState.page = p;
  loadKatalogBooks();
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

