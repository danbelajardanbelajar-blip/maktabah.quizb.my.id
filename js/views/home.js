// PAGE: HOME
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js';

export async function renderHome() {
  app().innerHTML = `
    <!-- Hero -->
    <section class="hero-bg text-white relative overflow-hidden">
      <!-- Decorative background glow -->
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-gold/5 rounded-full blur-[120px] pointer-events-none"></div>
      
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 md:py-32 text-center relative z-10">
        <!-- Badge -->
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-gold text-xs font-semibold tracking-widest uppercase mb-6 shadow-[0_0_15px_rgba(201,162,39,0.15)] backdrop-blur-md">
          <span class="w-1.5 h-1.5 rounded-full bg-gold animate-pulse"></span>
          Perpustakaan Digital Islam
        </div>
        
        <div class="arabic text-gold text-5xl md:text-7xl font-bold mb-4 leading-tight drop-shadow-[0_4px_24px_rgba(201,162,39,0.4)]">المكتبة السنية</div>
        <p class="text-white/80 text-lg md:text-xl mb-12 max-w-2xl mx-auto font-light tracking-wide leading-relaxed">
          Eksplorasi ribuan literatur klasik dan khazanah keilmuan Islam dalam genggaman Anda.
        </p>
        
        <!-- Search -->
        <div class="max-w-2xl mx-auto relative group">
          <div class="absolute -inset-1 bg-gradient-to-r from-gold/30 via-primary-light/30 to-gold/30 rounded-3xl blur opacity-40 group-hover:opacity-70 transition duration-1000 group-hover:duration-300"></div>
          <div class="relative flex items-center">
            <i data-lucide="search" class="absolute left-6 w-5 h-5 text-primary/60"></i>
            <input id="hero-search" type="text" placeholder="Cari teks, judul, atau pengarang kitab…"
              class="w-full pl-14 pr-32 py-5 rounded-2xl text-ink text-base bg-white/95 backdrop-blur-xl border border-white/40 shadow-2xl focus:outline-none focus:ring-2 focus:ring-gold transition-all placeholder:text-gray-400" />
            <button id="hero-search-btn" class="absolute right-2 bg-gradient-to-r from-[#166534] to-[#14532D] hover:from-[#15803D] hover:to-[#166534] text-gold px-6 py-3 rounded-xl text-sm font-bold tracking-wider transition-all shadow-[0_4px_12px_rgba(22,101,52,0.4)] hover:shadow-[0_6px_20px_rgba(201,162,39,0.3)]">
              Cari Kitab
            </button>
          </div>
        </div>

        <!-- stats -->
        <div id="hero-stats" class="mt-14 flex flex-wrap items-center justify-center gap-3 sm:gap-6 text-sm text-white/80 font-medium">
          <span class="flex items-center gap-2 bg-white/5 backdrop-blur-md px-5 py-2.5 rounded-full border border-white/10 shadow-lg"><i data-lucide="book-open" class="w-4 h-4 text-gold"></i> Memuat statistik…</span>
        </div>
      </div>
      <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-gold to-transparent opacity-60"></div>
    </section>

    <!-- Pencarian Terpopuler & Terbaru -->
    <div class="w-full bg-cream py-8">
      <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          
          <!-- Pencarian Terpopuler -->
          <div id="popular-search-section" style="display:none">
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
          <div id="recent-search-section" style="display:none">
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
            <a href="/katalog" data-route="/katalog" class="text-sm text-gold hover:text-gold-dark font-medium flex items-center gap-1">
              Lihat Semua <i data-lucide="arrow-right" class="w-4 h-4"></i>
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
            <a href="/katalog" data-route="/katalog" class="text-sm text-gold hover:text-gold-dark font-medium flex items-center gap-1">
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
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gold text-primary font-semibold text-sm shadow hover:bg-gold-light transition-colors">
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
      const res     = await apiFetch({ action: 'popular_searches', limit: 5 });
      const queries = res.data || [];
      const section = document.getElementById('popular-search-section');
      const chips   = document.getElementById('popular-search-chips');
      if (!chips || !section) return;
      if (!queries.length) { section.style.display = 'none'; return; }
      section.style.display = '';
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
                 text-sm text-primary/75 transition-all duration-150 shadow-sm cursor-pointer">
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
      const res     = await apiFetch({ action: 'recent_searches', limit: 5 });
      const queries = res.data || [];
      const section = document.getElementById('recent-search-section');
      const chips   = document.getElementById('recent-search-chips');
      if (!chips || !section) return;
      if (!queries.length) { section.style.display = 'none'; return; }
      section.style.display = '';
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
                 text-sm text-primary/75 transition-all duration-150 shadow-sm cursor-pointer">
          <i data-lucide="search" class="w-3 h-3 opacity-50 shrink-0"></i>
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
      const res = await apiFetch({ action: 'popular_books' });
      const books = res.data || [];
      if (!books.length) {
        grid.innerHTML = `
          <div class="col-span-full flex flex-col items-center justify-center gap-3 py-10 text-center">
            <p class="text-primary/45 text-sm">Belum ada data popularitas.</p>
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
            <i data-lucide="book-open" class="w-7 h-7 text-primary/30"></i>
          </div>
          <p class="text-primary/45 text-sm">Belum ada kitab yang dibuka.</p>
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
    const stats = await apiFetch({ action: 'stats' });
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

