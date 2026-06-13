// PAGE: HOME
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js';

export async function renderHome() {
  app().innerHTML = `
    <!-- Hero -->
    <section class="hero-bg text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28 text-center">
        <div class="arabic text-gold text-4xl md:text-6xl font-bold mb-3 leading-tight">المكتبة السنية</div>
        <p class="text-white/70 text-base md:text-lg mb-8 max-w-xl mx-auto">Perpustakaan digital Islam</p>
        <!-- Search -->
        <div class="max-w-xl mx-auto relative">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-primary/40"></i>
          <input id="hero-search" type="text" placeholder="Cari teks, judul atau pengarang kitab…"
            class="w-full pl-12 pr-4 py-4 rounded-2xl text-ink text-sm bg-white shadow-xl focus:outline-none focus:ring-2 focus:ring-gold/50 transition-all" />
        </div>
        <!-- stats -->
        <div id="hero-stats" class="mt-10 flex flex-wrap items-center justify-center gap-3 sm:gap-6 text-xs sm:text-sm text-white/50">
          <span class="flex items-center gap-2"><i data-lucide="book-open" class="w-4 h-4 text-gold/60"></i> Memuat statistik…</span>
        </div>
      </div>
      <div class="gold-line"></div>
    </section>

    <!-- Pencarian Terbaru -->
    <div class="w-full bg-cream" id="recent-search-section" style="display:none">
      <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center shrink-0">
            <i data-lucide="clock" class="w-3.5 h-3.5 text-gold"></i>
          </div>
          <h2 class="text-base font-bold text-primary">Pencarian Terbaru</h2>
        </div>
        <div id="recent-search-chips" class="flex flex-wrap gap-2">
          ${Array.from({length:8}, () => `<div class="skeleton h-8 w-24 rounded-full"></div>`).join('')}
        </div>
      </section>
    </div>

    <!-- Baru Saja Dibuka -->
    <div class="gold-line"></div>
    <section class="w-full bg-cream-dark py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
          ${skeletonCards(4)}
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
  // Live search input listener removed, relying on Enter key.
  $('#hero-search')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      const q = e.target.value.trim();
      if (q) navigate('/search?q=' + encodeURIComponent(q));
    }
  });

  // Load recent searches (non-blocking, section hidden jika kosong)
  (async () => {
    try {
      const res     = await apiFetch({ action: 'recent_searches', limit: 15 });
      const queries = res.data || [];
      const section = document.getElementById('recent-search-section');
      const chips   = document.getElementById('recent-search-chips');
      if (!chips || !section) return;
      if (!queries.length) { section.style.display = 'none'; return; }
      section.style.display = '';
      chips.innerHTML = queries.map(q => {
        const safe = escHtml(q);
        const enc  = encodeURIComponent(q).replace(/'/g, "%27");
        return `<button
          onclick="navigate('/search?q=${enc}')"
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
      ${recent.map(item => recentBookCard(item)).join('')}
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

