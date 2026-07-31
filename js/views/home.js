// PAGE: HOME
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js?v=2';

if (!window.refreshHomeData) {
  window.refreshHomeData = async function(btn) {
      const icon = btn.querySelector('i');
      if (icon) icon.classList.add('animate-spin');
      
      // Simpan data penting (akun/cookie tidak ikut terhapus di sini)
      const keysToKeep = ['favorite_books', 'maktabah_recent_books', 'siteSettings', 'readerFonts'];
      const keepData = {};
      keysToKeep.forEach(k => {
        const val = localStorage.getItem(k);
        if (val !== null) keepData[k] = val;
      });
      
      // Bersihkan seluruh cache JS
      localStorage.clear();
      sessionStorage.clear();
      
      // Kembalikan data penting
      Object.keys(keepData).forEach(k => {
        localStorage.setItem(k, keepData[k]);
      });
      
      // Reload total halaman
      window.location.reload(true);
  };
}

export async function renderHome() {
  const isDark = document.documentElement.classList.contains('dark');
  
  app().innerHTML = `
    <!-- Hero is handled by SSR in index.php -->

    <!-- Pencarian Terpopuler, Terbaru & Pertanyaan Terbaru -->
    <div class="w-full bg-cream py-8">
      <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          
          <!-- Pencarian Terpopuler -->
          <div id="popular-search-section">
            <div class="flex items-center gap-2 mb-4">
              <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center shrink-0">
                <i data-lucide="trending-up" class="w-3.5 h-3.5 text-gold"></i>
              </div>
              <h2 class="text-base font-bold text-primary">Pencarian Terpopuler</h2>
            </div>
            <div id="popular-search-chips" class="flex flex-wrap gap-2">
              ${Array.from({length:5}, () => `<div class="skeleton h-8 w-24 rounded-full"></div>`).join('')}
            </div>
          </div>

          <!-- Pencarian Terbaru -->
          <div id="recent-search-section">
            <div class="flex items-center gap-2 mb-4">
              <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center shrink-0">
                <i data-lucide="clock" class="w-3.5 h-3.5 text-gold"></i>
              </div>
              <h2 class="text-base font-bold text-primary">Pencarian Terbaru</h2>
            </div>
            <div id="recent-search-chips" class="flex flex-wrap gap-2">
              ${Array.from({length:5}, () => `<div class="skeleton h-8 w-24 rounded-full"></div>`).join('')}
            </div>
          </div>

          <!-- Pertanyaan Terbaru -->
          <div id="recent-questions-section">
            <div class="flex items-center gap-2 mb-4">
              <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center shrink-0">
                <i data-lucide="message-square" class="w-3.5 h-3.5 text-gold"></i>
              </div>
              <h2 class="text-base font-bold text-primary">Pertanyaan Terbaru</h2>
            </div>
            <div id="recent-questions-chips" class="flex flex-wrap gap-2">
              ${Array.from({length:5}, () => `<div class="skeleton h-8 w-24 rounded-full"></div>`).join('')}
            </div>
          </div>

        </div>
      </section>
    </div>

    <!-- Terpopuler & Baru Saja Dibuka -->
    <div class="gold-line"></div>
    <section class="w-full bg-cream-dark py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Kitab Terpopuler -->
        <div class="mb-12">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-primary flex items-center justify-center shrink-0">
                <i data-lucide="flame" class="w-4 h-4 text-gold"></i>
              </div>
              <h2 class="text-xl font-bold text-primary">Kitab Terpopuler</h2>
            </div>
            <a href="/katalog" data-route="/katalog" class="text-sm text-amber-700 hover:text-amber-800 font-medium flex items-center gap-1">
              Semua Kitab <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
          </div>
          <div id="popular-books-grid">
            ${skeletonCards(5)}
          </div>
        </div>

        <!-- Baru Saja Dibuka -->
        <div>
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-primary flex items-center justify-center shrink-0">
                <i data-lucide="history" class="w-4 h-4 text-gold"></i>
              </div>
              <h2 class="text-xl font-bold text-primary">Baru Saja Dibuka</h2>
            </div>
            <a href="/katalog" data-route="/katalog" class="text-sm text-amber-700 hover:text-amber-800 font-medium flex items-center gap-1">
              Jelajahi Katalog <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
          </div>
          <div id="recent-opened-grid">
            ${skeletonCards(5)}
          </div>
        </div>

      </div>
    </section>
    <div class="gold-line"></div>

    <!-- CTA Kirimkan File -->
    <section class="py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-start gap-4 rounded-[32px] bg-gradient-to-br from-primary to-primary-light p-8">
          <div class="shrink-0 w-12 h-12 rounded-2xl bg-gold/20 flex items-center justify-center">
            <i data-lucide="upload-cloud" class="w-6 h-6 text-gold"></i>
          </div>
          <div class="flex-1">
            <h3 class="text-white font-bold text-base leading-snug mb-1">Kirimkan atau Request Hasil Bahsul Masail atau Kitab</h3>
            <p class="text-white/65 text-xs leading-relaxed mb-4">Bagikan karya Anda atau ajukan permohonan kitab/hasil kajian untuk koleksi perpustakaan digital ini.</p>
            <div class="flex flex-wrap gap-3">
              <button onclick="handleSubmitCTA()"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-700 text-white font-semibold text-sm shadow hover:bg-gold-light transition-colors">
                <i data-lucide="send" class="w-4 h-4"></i>
                Kirimkan File
              </button>
              <button onclick="navigate('/request')"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white/10 text-white font-semibold text-sm shadow hover:bg-white/20 transition-colors border border-white/20">
                <i data-lucide="help-circle" class="w-4 h-4"></i>
                Request Kitab
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
    ${mobileFeedbackBanner}`;

  reicons();

  // Hero search
  const doHeroSearch = () => {
    const q = $('#hero-search')?.value.trim();
    if (q) navigate('/search?q=' + encodeURIComponent(q));
  };
  $('#hero-search')?.addEventListener('keydown', e => { if (e.key === 'Enter') doHeroSearch(); });
  $('#hero-search-btn')?.addEventListener('click', doHeroSearch);

  // Load popular searches
  (async () => {
    try {
      const reqFlags = window.forceRefreshApi ? { refresh: 1 } : {};
      const res     = await apiFetch({ action: 'popular_searches', limit: 5, ...reqFlags });
      const queries = res.data || [];
      const section = document.getElementById('popular-search-section');
      const chips   = document.getElementById('popular-search-chips');
      if (!chips || !section) return;
      if (!queries.length) { chips.innerHTML = '<span class="text-sm text-slate-500 italic">Belum ada data.</span>'; return; }
      chips.innerHTML = queries.map(qObj => {
        const q = typeof qObj === 'string' ? qObj : qObj.query;
        const detail = typeof qObj === 'object' && qObj.detail ? JSON.parse(qObj.detail) : null;
        const safe = escHtml(q);
        let route = '';
        if (q.includes('|')) {
          const parts = q.split('|').map(p => p.trim());
          const params = new URLSearchParams();
          parts.forEach((p, i) => { if (p) params.set('q' + (i + 1), p); });
          
          if (detail && detail.cats && detail.cats.length > 0) {
            params.set('cats', detail.cats.join(','));
          } else if (detail && detail.all_cats) {
            params.set('all_cats', '1');
          } else if (!detail) {
            params.set('all_cats', '1'); // Default to all cats if no detail is found
          }
          
          route = '/search-advanced?' + params.toString().replace(/'/g, "%27");
        } else {
          route = '/search?q=' + encodeURIComponent(q).replace(/'/g, "%27");
        }
        return `<button
          onclick="navigate('${route}')"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full
                 border border-gold/25 bg-white hover:bg-primary hover:text-white hover:border-primary
                 text-sm text-primary transition-all duration-150 shadow-sm cursor-pointer">
          <i data-lucide="trending-up" class="w-3 h-3 opacity-50 shrink-0"></i>
          ${safe}
        </button>`;
      }).join('');
      reicons();
    } catch { /* abaikan jika gagal */ }
  })();

  // Load recent searches (non-blocking, section hidden jika kosong)
  (async () => {
    try {
      const reqFlags = window.forceRefreshApi ? { refresh: 1 } : {};
      const res     = await apiFetch({ action: 'recent_searches', limit: 5, ...reqFlags });
      const queries = res.data || [];
      const section = document.getElementById('recent-search-section');
      const chips   = document.getElementById('recent-search-chips');
      if (!chips || !section) return;
      if (!queries.length) { chips.innerHTML = '<span class="text-sm text-slate-500 italic">Belum ada data.</span>'; return; }
      chips.innerHTML = queries.map(qObj => {
        const q = typeof qObj === 'string' ? qObj : qObj.query;
        const detail = typeof qObj === 'object' && qObj.detail ? JSON.parse(qObj.detail) : null;
        const safe = escHtml(q);
        let route = '';
        if (q.includes('|')) {
          const parts = q.split('|').map(p => p.trim());
          const params = new URLSearchParams();
          parts.forEach((p, i) => { if (p) params.set('q' + (i + 1), p); });
          
          if (detail && detail.cats && detail.cats.length > 0) {
            params.set('cats', detail.cats.join(','));
          } else if (detail && detail.all_cats) {
            params.set('all_cats', '1');
          } else if (!detail) {
            params.set('all_cats', '1'); // Default to all cats if no detail is found
          }
          
          route = '/search-advanced?' + params.toString().replace(/'/g, "%27");
        } else {
          route = '/search?q=' + encodeURIComponent(q).replace(/'/g, "%27");
        }
        return `<button
          onclick="navigate('${route}')"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full
                 border border-gold/25 bg-white hover:bg-primary hover:text-white hover:border-primary
                 text-sm text-primary transition-all duration-150 shadow-sm cursor-pointer">
          <i data-lucide="search" class="w-3 h-3 opacity-50 shrink-0"></i>
          ${safe}
        </button>`;
      }).join('');
      reicons();
    } catch { /* abaikan jika gagal */ }
  })();

  // Load Pertanyaan Terbaru
  (async () => {
    try {
      const reqFlags = window.forceRefreshApi ? { refresh: 1 } : {};
      const res     = await apiFetch({ action: 'recent_questions', limit: 5, ...reqFlags });
      const queries = res.data || [];
      const section = document.getElementById('recent-questions-section');
      const chips   = document.getElementById('recent-questions-chips');
      if (!chips || !section) return;
      if (!queries.length) { chips.innerHTML = '<span class="text-sm text-slate-500 italic">Belum ada data.</span>'; return; }
      chips.innerHTML = queries.map(qObj => {
        const q = typeof qObj === 'string' ? qObj : qObj.query;
        const safe = escHtml(q);
        const route = '/ask?q=' + encodeURIComponent(q).replace(/'/g, "%27");
        return `<button
          onclick="navigate('${route}')"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full
                 border border-gold/25 bg-white hover:bg-primary hover:text-white hover:border-primary
                 text-sm text-primary transition-all duration-150 shadow-sm cursor-pointer">
          <i data-lucide="message-square" class="w-3 h-3 opacity-50 shrink-0"></i>
          ${safe}
        </button>`;
      }).join('');
      reicons();
    } catch { /* abaikan jika gagal */ }
  })();

  // Load Kitab Terpopuler
  (async () => {
    try {
      const grid = document.getElementById('popular-books-grid');
      if (!grid) return;
      const reqFlags = window.forceRefreshApi ? { refresh: 1 } : {};
      const res = await apiFetch({ action: 'popular_books', ...reqFlags });
      const books = res.data || [];
      if (!books.length) {
        grid.innerHTML = `
          <div class="col-span-full flex flex-col items-center justify-center gap-3 py-10 text-center">
            <p class="text-slate-600 text-sm">Belum ada data popularitas.</p>
          </div>`;
        return;
      }
      grid.innerHTML = `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        ${books.map(b => bookCard(b)).join('')}
      </div>`;
      reicons();
    } catch { /* abaikan jika gagal */ }
  })();

  // Load kitab yang baru saja dibuka (dari localStorage)
  (() => {
    const recent = getRecentlyOpened();
    const grid   = document.getElementById('recent-opened-grid');
    if (!grid) return;
    if (!recent.length) {
      grid.innerHTML = `
        <div class="col-span-full flex flex-col items-center justify-center gap-3 py-10 text-center">
          <div class="w-14 h-14 rounded-2xl bg-primary/6 flex items-center justify-center">
            <i data-lucide="book-open" class="w-7 h-7 text-slate-600"></i>
          </div>
          <p class="text-slate-600 text-sm">Belum ada kitab yang dibuka.</p>
          <a href="/katalog" data-route="/katalog"
             class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-white text-xs font-semibold hover:bg-primary-light transition">
            <i data-lucide="search" class="w-3.5 h-3.5"></i> Jelajahi Katalog
          </a>
        </div>`;
      reicons();
      return;
    }
    grid.innerHTML = `<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
      ${recent.slice(0, 5).map(item => recentBookCard(item)).join('')}
    </div>`;
    reicons();
  })();

  // Load statistics
  try {
    const reqFlags = window.forceRefreshApi ? { refresh: 1 } : {};
    const stats = await apiFetch({ action: 'stats', ...reqFlags });
    const formatNum = (n) => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    $('#hero-stats').innerHTML = `
      <span class="flex items-center gap-2"><i data-lucide="book-open" class="w-4 h-4 text-gold/60"></i> <strong>${formatNum(stats.total_books)}</strong> Kitab</span>
      <span class="flex items-center gap-2"><i data-lucide="folder" class="w-4 h-4 text-gold/60"></i> <strong>${formatNum(stats.total_categories)}</strong> Kategori</span>
      <span class="flex items-center gap-2"><i data-lucide="search" class="w-4 h-4 text-gold/60"></i> <strong>${formatNum(stats.total_searches)}</strong> Pencarian</span>
      <span class="flex items-center gap-2"><i data-lucide="eye" class="w-4 h-4 text-gold/60"></i> <strong>${formatNum(stats.total_visits)}</strong> Kunjungan</span>
      <span class="flex items-center gap-2"><i data-lucide="users" class="w-4 h-4 text-gold/60"></i> <strong class="text-emerald-500">${formatNum(stats.online_users || 1)}</strong> Sedang Online</span>
    `;
    reicons();
  } catch(e) { 
    if (handleAuthError(e)) return;
    $('#hero-stats').innerHTML = `<span class="text-gold/50 text-xs">Statistik sedang dimuat…</span>`;
  }

  reicons();
}

