// PAGE: DETAIL KITAB + READER
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened, parseSearchTerms, highlightTextNodes } from '../core/core.js';


// Reader state (module-level so nav buttons can reference it)
export let readerState = { bkid: null, page: 1, juz: 1, total: 0, totalJuz: 1, juzList: [], searchQ: '' };

export async function renderDetail(params) {
  const id       = params.get('id');
  const jumpPage = parseInt(params.get('page') || '1') || 1;
  const jumpJuz  = parseInt(params.get('juz') || '1') || 1;
  const searchQ  = (params.get('q') || '').trim();
  const contentId = parseInt(params.get('content_id') || '0');

  if (!id) { render404(); return; }

  // Reset reader
  readerState.bkid     = parseInt(id);
  readerState.page     = jumpPage;
  readerState.total    = 0;
  readerState.juz      = jumpJuz;
  readerState.totalJuz = 1;
  readerState.juzList  = [];
  readerState.searchQ  = searchQ;


  app().innerHTML = `
    <div class="max-w-7xl mx-auto px-1 sm:px-6 lg:px-8 py-10">
      <button onclick="history.back()" class="flex items-center gap-2 text-primary/60 hover:text-primary text-sm mb-6 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
      </button>
      <div id="detail-content">
        <div class="bg-white rounded-3xl shadow-card p-8 space-y-4">
          ${Array.from({length:4}, (_,i) => `<div class="skeleton h-${i===0?7:4} rounded-lg w-${i===0?'3/4':i===1?'2/4':'1/3'}"></div>`).join('')}
        </div>
      </div>
    </div>
    ${mobileFeedbackBanner}`;
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

    readerState.total    = contentPgs;
    readerState.totalJuz = book.total_juz || 1;
    readerState.juzList  = book.juz_list  || [];

    // Simpan ke riwayat kitab dibuka (localStorage)
    saveToRecentlyOpened({
      id    : book.bkid,
      title : title,
      author: author,
      cat   : catName,
    });

    $('#detail-content').innerHTML = `
      <div class="bg-white rounded-3xl shadow-card overflow-hidden">

        <!-- ── Book Header ── -->
        <div class="hero-bg text-white p-4 sm:p-8 md:p-10">
          <div class="arabic text-3xl md:text-4xl font-bold text-white mb-2 leading-tight">${escHtml(title)}</div>
          <div class="text-gold text-base font-medium mt-1">${escHtml(author)}</div>
          <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-4">
            ${catName    ? `<span class="px-2 sm:px-3 py-1 rounded-full bg-white/10 text-white/80 text-xs">${escHtml(catName)}</span>` : ''}
            ${pages      ? `<span class="px-2 sm:px-3 py-1 rounded-full bg-gold/20 text-gold text-xs flex items-center gap-1"><i data-lucide="file-text" class="w-3 h-3"></i>${pages}</span>` : ''}
            ${contentPgs ? `<span class="px-2 sm:px-3 py-1 rounded-full bg-white/10 text-white/70 text-xs flex items-center gap-1"><i data-lucide="layers" class="w-3 h-3"></i>${contentPgs} halaman tersedia${book.total_juz > 1 ? ` &bull; ${book.total_juz} juz` : ''}</span>` : ''}
            <a href="/api.php?action=download_book&id=${book.bkid}"
               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-gold/20 text-gold border border-gold/30 hover:bg-gold/30 transition shrink-0"
               title="${book.total_juz > 1 ? `Unduh ZIP berisi ${book.total_juz} file DOCX` : 'Unduh sebagai DOCX'}"
               aria-label="Unduh kitab">
              <i data-lucide="download" class="w-4 h-4"></i>
              <span class="text-xs font-bold tracking-wide">
                ${book.total_juz > 1
                  ? `<span class="dl-fmt-badge dl-fmt-zip" style="color:inherit;background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.3)">ZIP · ${book.total_juz} juz</span>`
                  : `<span class="dl-fmt-badge dl-fmt-docx" style="color:inherit;background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.3)">DOCX</span>`
                }
              </span>
            </a>
            <!-- Desktop Favorite Button -->
            <button id="desktop-fav" class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-gold/20 text-gold border border-gold/30 hover:bg-gold/30 transition shrink-0" title="Favorit">
              <i data-lucide="star" class="w-4 h-4"></i>
              <span class="text-xs font-bold tracking-wide">Favorit</span>
            </button>
          </div>
        </div>

        <!-- ── Meta (description / author info) ── -->
        <div class="px-3 sm:px-8 md:px-10 pt-6 sm:pt-8 space-y-5">
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
        <div class="px-3 sm:px-8 md:px-10 pb-6 sm:pb-10 mt-6 sm:mt-8">
          <div class="border-t border-cream-dark pt-6">

            <!-- Reader toolbar -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
              <h3 class="text-xs sm:text-sm font-semibold text-primary flex items-center gap-2">
                <i data-lucide="book-open" class="w-4 h-4 text-gold"></i> Baca Kitab
              </h3>
              <div class="flex flex-wrap items-center gap-1 sm:gap-2 text-xs sm:text-sm">
                ${book.total_juz > 1 ? `
                <select id="reader-juz-select"
                  class="border border-gold/30 rounded-lg px-2 py-1 text-xs text-primary bg-white focus:outline-none focus:border-gold">
                  ${(book.juz_list || []).map(j =>
                    `<option value="${j.juz}">Juz ${j.juz} (${j.pages} hal.)</option>`
                  ).join('')}
                </select>
                <span class="text-primary/30 text-xs hidden sm:inline">|</span>` : ''}
                <span class="text-primary/40 text-xs whitespace-nowrap">Halaman</span>
                <input id="reader-page-input" type="number" min="1" max="${contentPgs}" value="1"
                  class="w-12 sm:w-14 text-center border border-gold/30 rounded-lg px-1 sm:px-2 py-1 text-xs sm:text-sm text-primary focus:outline-none focus:border-gold" />
                <span id="reader-total-label" class="text-primary/40 text-xs whitespace-nowrap">dari ${contentPgs}</span>
                <!-- Font Settings Gear Button -->
                <button id="font-settings-btn" title="Pengaturan Font"
                  class="p-1.5 rounded-lg border border-gold/20 hover:bg-gold/10 hover:border-gold/40 transition-all text-primary/50 hover:text-primary shrink-0 ml-1 sm:ml-2">
                  <i data-lucide="settings-2" class="w-4 h-4"></i>
                </button>
                <!-- In-book Search Button -->
                <button id="in-book-search-btn" title="Cari di Kitab ini"
                  class="p-1.5 rounded-lg border border-gold/20 hover:bg-gold/10 hover:border-gold/40 transition-all text-primary/50 hover:text-primary shrink-0 ml-1 sm:ml-2">
                  <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <!-- TOC Button -->
                <button id="reader-toc-btn" title="Daftar Isi"
                  class="p-1.5 rounded-lg border border-gold/20 hover:bg-gold/10 hover:border-gold/40 transition-all text-primary/50 hover:text-primary shrink-0 ml-1 sm:ml-2 hidden">
                  <i data-lucide="list" class="w-4 h-4"></i>
                </button>
              </div>
            </div>

            <!-- Font settings panel (slide-down) -->
            <div id="font-panel-wrap">${renderFontPanel()}</div>
            
            <!-- In-book search panel -->
            <div id="in-book-search-panel" class="hidden mb-4 p-3 sm:p-4 border border-gold/20 rounded-2xl bg-white shadow-sm">
              <div class="flex gap-2">
                <input type="text" id="in-book-search-input" placeholder="Cari kata dalam kitab ini..." class="flex-1 px-3 py-2 border border-gold/30 rounded-xl text-sm text-primary focus:outline-none focus:border-gold" />
                <button id="in-book-search-submit" class="px-4 py-2 bg-gold text-white rounded-xl text-sm font-medium hover:bg-gold/90 transition-colors">Cari</button>
              </div>
              <div id="in-book-search-results" class="mt-3 space-y-2 hidden max-h-60 overflow-y-auto pr-1"></div>
            </div>

            <!-- TOC panel -->
            <div id="reader-toc-panel" class="hidden mb-4 p-3 sm:p-4 border border-gold/20 rounded-2xl bg-white shadow-sm">
              <div class="flex items-center justify-between mb-3 gap-3">
                <h4 class="font-lora font-bold text-primary text-sm shrink-0">Daftar Isi</h4>
                <div class="relative flex-1 max-w-sm">
                  <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-primary/40">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i>
                  </div>
                  <input type="text" id="reader-toc-search" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-xs rounded-lg focus:ring-gold focus:border-gold block pl-8 p-1.5 transition-colors" placeholder="Cari judul bab...">
                </div>
              </div>
              <div id="reader-toc-list" class="max-h-80 overflow-y-auto pr-1 space-y-1">
                <!-- TOC items injected here -->
              </div>
            </div>

            <!-- Content area — direction & font controlled by CSS vars + unicode-bidi -->
            <div id="reader-area"
              class="bg-cream rounded-2xl p-3 sm:p-6 md:p-8 min-h-48 text-primary leading-loose transition-opacity duration-200">
              <div class="flex justify-center py-8">
                <div class="w-8 h-8 border-2 border-gold/30 border-t-gold rounded-full animate-spin" style="border-width:2px"></div>
              </div>
            </div>

            <!-- Nav buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-between mt-5 gap-2 sm:gap-3 w-full">
              <button id="reader-prev"
                class="flex items-center justify-center gap-1 sm:gap-2 flex-1 sm:flex-none px-3 sm:px-5 py-2 sm:py-2.5 rounded-xl border border-gold/30 text-xs sm:text-sm font-medium text-primary hover:bg-primary hover:text-white hover:border-primary disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <i data-lucide="chevron-left" class="w-4 h-4 shrink-0"></i> <span class="hidden sm:inline">Sebelumnya</span>
              </button>
              <span id="reader-label" class="text-xs text-primary/40 whitespace-nowrap"></span>
              <button id="reader-next"
                class="flex items-center justify-center gap-1 sm:gap-2 flex-1 sm:flex-none px-3 sm:px-5 py-2 sm:py-2.5 rounded-xl bg-primary text-white text-xs sm:text-sm font-medium hover:bg-primary-light disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <span class="hidden sm:inline">Berikutnya</span> <i data-lucide="chevron-right" class="w-4 h-4 shrink-0"></i>
              </button>
            </div>

          </div>
        </div>` : `
        <div class="px-3 sm:px-8 md:px-10 pb-10 mt-6">
          <div class="border-t border-cream-dark pt-6 flex items-center gap-3 text-primary/40 text-sm">
            <i data-lucide="info" class="w-4 h-4"></i>
            <span>Konten kitab ini belum tersedia.</span>
          </div>
        </div>`}

      </div>
      
      <!-- Mobile Bottom Action Bar -->
      ${contentPgs > 0 ? `
      <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-cream-dark shadow-[0_-4px_10px_rgba(0,0,0,0.05)] z-[60] sm:hidden flex justify-around items-center px-2 py-3 text-primary pb-safe">
        <a href="/" data-route="/" class="p-2 hover:bg-cream rounded-xl transition flex flex-col items-center gap-1 no-underline text-primary" title="Beranda">
          <i data-lucide="home" class="w-5 h-5"></i>
        </a>
        <button id="mobile-font-dec" class="p-2 hover:bg-cream rounded-xl transition flex flex-col items-center gap-1" title="Perkecil Font">
          <i data-lucide="minus" class="w-5 h-5"></i>
        </button>
        <button id="mobile-font-inc" class="p-2 hover:bg-cream rounded-xl transition flex flex-col items-center gap-1" title="Perbesar Font">
          <i data-lucide="plus" class="w-5 h-5"></i>
        </button>
        <button id="mobile-copy" class="p-2 hover:bg-cream rounded-xl transition flex flex-col items-center gap-1" title="Salin">
          <i data-lucide="copy" class="w-5 h-5"></i>
        </button>
        <button id="mobile-share" class="p-2 hover:bg-cream rounded-xl transition flex flex-col items-center gap-1" title="Bagikan">
          <i data-lucide="share-2" class="w-5 h-5"></i>
        </button>
        <button id="mobile-fav" class="p-2 hover:bg-cream rounded-xl transition flex flex-col items-center gap-1" title="Favorit">
          <i data-lucide="star" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="h-16 sm:hidden"></div>
      ` : ''}`;

    reicons();

    // -- Favorite Logic --
    const checkFav = () => {
      const favs = JSON.parse(localStorage.getItem('favorite_books') || '[]');
      return favs.includes(book.bkid);
    };
    const updateFavIcon = () => {
      const isFav = checkFav();
      const mobFav = $('#mobile-fav');
      const deskFav = $('#desktop-fav');
      if (mobFav) {
        mobFav.innerHTML = isFav ? '<i data-lucide="star" class="w-5 h-5 fill-gold text-gold"></i>' : '<i data-lucide="star" class="w-5 h-5"></i>';
      }
      if (deskFav) {
        deskFav.innerHTML = isFav 
          ? '<i data-lucide="star" class="w-4 h-4 fill-gold"></i><span class="text-xs font-bold tracking-wide">Favorit</span>' 
          : '<i data-lucide="star" class="w-4 h-4"></i><span class="text-xs font-bold tracking-wide">Favorit</span>';
      }
      reicons();
    };
    updateFavIcon();

    const toggleFav = () => {
      let favs = JSON.parse(localStorage.getItem('favorite_books') || '[]');
      if (favs.includes(book.bkid)) {
        favs = favs.filter(id => id !== book.bkid);
      } else {
        favs.push(book.bkid);
      }
      localStorage.setItem('favorite_books', JSON.stringify(favs));
      updateFavIcon();
    };

    $('#mobile-fav')?.addEventListener('click', toggleFav);
    $('#desktop-fav')?.addEventListener('click', toggleFav);

    if (contentPgs > 0) {
      // Wire up font settings gear button
      $('#font-settings-btn')?.addEventListener('click', () => {
        const panel = $('#font-panel');
        if (panel) panel.classList.toggle('open');
      });
      // Wire up font size slider
      initFontPanelEvents();

      // In-book search events
      $('#in-book-search-btn')?.addEventListener('click', () => {
        const panel = $('#in-book-search-panel');
        if (panel) {
          panel.classList.toggle('hidden');
          if (!panel.classList.contains('hidden')) {
            $('#in-book-search-input')?.focus();
            $('#reader-toc-panel')?.classList.add('hidden');
          }
        }
      });
      
      // TOC events
      $('#reader-toc-btn')?.addEventListener('click', () => {
        const panel = $('#reader-toc-panel');
        if (panel) {
          panel.classList.toggle('hidden');
          if (!panel.classList.contains('hidden')) {
            $('#in-book-search-panel')?.classList.add('hidden');
          }
        }
      });
      
      // Fetch TOC
      let currentTocData = [];
      const renderTocList = (filterQuery = '') => {
        const list = $('#reader-toc-list');
        if (!list) return;
        
        const q = filterQuery.toLowerCase();
        const filtered = q ? currentTocData.filter(item => (item.title || '').toLowerCase().includes(q)) : currentTocData;
        
        if (filtered.length === 0) {
          list.innerHTML = '<div class="py-4 text-center text-sm text-primary/40">Tidak ada daftar isi ditemukan.</div>';
          return;
        }

        let html = '';
        filtered.forEach(item => {
          const titleStr = item.title || '';
          const isAr = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF]/.test(titleStr);
          
          const padding = item.level > 1 
            ? (isAr ? 'pr-6 border-r-2 border-gold/30' : 'pl-6 border-l-2 border-gold/30') 
            : (isAr ? 'pr-2 border-r-2 border-gold' : 'pl-2 border-l-2 border-gold');
            
          const bg = item.level == 1 ? 'bg-cream/30 font-medium text-primary' : 'bg-transparent text-primary/80';
          const title = titleStr.replace(/</g, '&lt;');
          const totalJuzLabel = book.total_juz > 1 ? `Juz ${item.juz} · ` : '';
          
          const dir = isAr ? 'rtl' : 'ltr';
          const titleClass = isAr ? 'arabic text-right' : 'text-left font-medium text-slate-800';
          
          html += `<div class="py-2 px-2 cursor-pointer hover:bg-gold/10 transition-colors text-sm rounded mb-1 ${padding} ${bg}" 
              onclick="window.loadReaderPage(${readerState.bkid}, ${item.page}, '', ${item.juz}); $('#reader-toc-panel').classList.add('hidden');">
            <div class="flex justify-between items-start gap-3" dir="${dir}">
              <span class="${titleClass}">${title}</span>
              <span class="text-[10px] text-primary/50 whitespace-nowrap mt-1" dir="ltr">${totalJuzLabel}Hal. ${item.page}</span>
            </div>
          </div>`;
        });
        list.innerHTML = html;
      };

      $('#reader-toc-search')?.addEventListener('input', (e) => {
        renderTocList(e.target.value);
      });

      apiFetch({ action: 'book_toc', bkid: readerState.bkid }).then(data => {
        if (Array.isArray(data) && data.length > 0) {
          currentTocData = data;
          const btn = $('#reader-toc-btn');
          if (btn) btn.classList.remove('hidden');
          renderTocList();
        }
      }).catch(console.error);
      
      const doInBookSearch = async () => {
        const q = $('#in-book-search-input')?.value.trim();
        if (!q || q.length < 2) return;
        
        const resDiv = $('#in-book-search-results');
        if (resDiv) {
          resDiv.innerHTML = '<div class="text-center py-4"><div class="w-6 h-6 border-2 border-gold/30 border-t-gold rounded-full animate-spin mx-auto" style="border-width:2px"></div></div>';
          resDiv.classList.remove('hidden');
        }
        
        try {
          const res = await apiFetch({ action: 'search_content_in_book', bkid: readerState.bkid, q: q });
          if (res && res.found && res.data.length > 0) {
            let html = '';
            res.data.forEach(item => {
              const totalJuzLabel = book.total_juz > 1 ? `Juz ${item.match_juz} · ` : '';
              html += `<div class="p-3 border border-cream-dark rounded-xl hover:border-gold/50 cursor-pointer transition-colors bg-cream/50" onclick="window.loadReaderPage(${readerState.bkid}, ${item.match_page}, '${q.replace(/'/g, "\\'")}', ${item.match_juz}, ${item.match_id})">
                <div class="text-xs font-semibold text-gold mb-1.5">${totalJuzLabel}Halaman ${item.match_page}</div>
                <div class="text-sm arabic leading-loose text-primary" dir="rtl">${item.snippet}</div>
              </div>`;
            });
            if (resDiv) resDiv.innerHTML = html;
          } else {
            if (resDiv) resDiv.innerHTML = '<div class="text-center text-sm text-primary/50 py-4">Kata kunci tidak ditemukan dalam kitab ini.</div>';
          }
        } catch(e) {
          if (resDiv) resDiv.innerHTML = '<div class="text-center text-sm text-red-500 py-4">Gagal melakukan pencarian.</div>';
        }
      };

      $('#in-book-search-submit')?.addEventListener('click', doInBookSearch);
      $('#in-book-search-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') doInBookSearch();
      });

      // Mobile action bar events
      $('#mobile-font-dec')?.addEventListener('click', () => {
        readerFontState.size = Math.max(14, readerFontState.size - 1);
        const slider = $('#font-size-slider');
        const lbl = $('#font-size-label');
        if (slider) slider.value = readerFontState.size;
        if (lbl) lbl.textContent = readerFontState.size + 'px';
        applyReaderFont();
      });
      $('#mobile-font-inc')?.addEventListener('click', () => {
        readerFontState.size = Math.min(28, readerFontState.size + 1);
        const slider = $('#font-size-slider');
        const lbl = $('#font-size-label');
        if (slider) slider.value = readerFontState.size;
        if (lbl) lbl.textContent = readerFontState.size + 'px';
        applyReaderFont();
      });
      $('#mobile-copy')?.addEventListener('click', () => {
        const text = $('#reader-area')?.innerText || '';
        if (text) {
          navigator.clipboard.writeText(text).then(() => {
            alert('Teks halaman ini berhasil disalin!');
          }).catch(() => {
            alert('Gagal menyalin teks.');
          });
        }
      });
      $('#mobile-share')?.addEventListener('click', () => {
        if (navigator.share) {
          navigator.share({
            title: title,
            text: `Baca kitab ${title} di Al-Maktabah As-Sunniyyah`,
            url: window.location.href
          }).catch(console.error);
        } else {
          navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Tautan halaman berhasil disalin!');
          });
        }
      });

      // Open at jump page (from search result), with keyword highlight
      if (contentId > 0) {
        loadReaderPage(readerState.bkid, 1, searchQ, 1, contentId);
      } else {
        loadReaderPage(readerState.bkid, jumpPage, searchQ, readerState.juz);
      }

      // Juz selector
      $('#reader-juz-select')?.addEventListener('change', e => {
        const newJuz = parseInt(e.target.value) || 1;
        readerState.juz  = newJuz;
        readerState.page = 1;
        // Update max page di input sesuai juz baru
        const juzInfo = readerState.juzList.find(j => j.juz === newJuz);
        const inp = $('#reader-page-input');
        if (inp && juzInfo) inp.max = juzInfo.pages;
        loadReaderPage(readerState.bkid, 1, '', newJuz);
      });

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
        if (readerState.page > 1) loadReaderPage(readerState.bkid, readerState.page - 1, '', readerState.juz);
      });
      $('#reader-next')?.addEventListener('click', () => {
        if (readerState.page < readerState.total) loadReaderPage(readerState.bkid, readerState.page + 1, '', readerState.juz);
      });

      // page input jump
      let jumpTimer;
      $('#reader-page-input')?.addEventListener('input', e => {
        clearTimeout(jumpTimer);
        const v = parseInt(e.target.value);
        if (v >= 1 && v <= readerState.total) {
          jumpTimer = setTimeout(() => loadReaderPage(readerState.bkid, v, '', readerState.juz), 600);
        }
      });
      $('#reader-page-input')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
          clearTimeout(jumpTimer);
          const v = parseInt(e.target.value);
          if (v >= 1 && v <= readerState.total) loadReaderPage(readerState.bkid, v, '', readerState.juz);
        }
      });
    }

  } catch(e) {
    if (handleAuthError(e)) return;
    $('#detail-content').innerHTML = '<p class="text-center text-red-500 py-12">Kitab tidak ditemukan.</p>';
  }
}

// Load a single page of content into the reader
// highlightQ: optional keyword to highlight in gold
// juz: nomor juz (default dari readerState.juz)
async function loadReaderPage(bkid, page, highlightQ = '', juz = 0, contentId = 0) {
  const area  = $('#reader-area');
  const label = $('#reader-label');
  const inp   = $('#reader-page-input');
  const prev  = $('#reader-prev');
  const next  = $('#reader-next');
  const totalLbl = $('#reader-total-label');
  if (!area) return;

  // Gunakan juz dari argumen, atau fallback ke readerState.juz
  if (juz < 1) juz = readerState.juz || 1;
  readerState.page = page;
  readerState.juz  = juz;

  // Sync dropdown juz
  const juzSel = $('#reader-juz-select');
  if (juzSel && juzSel.value != juz) juzSel.value = juz;

  // spinner
  area.style.opacity = '0.4';
  area.innerHTML = `<div class="flex justify-center py-8">
    <div class="w-8 h-8 border-2 border-gold/30 border-t-gold rounded-full animate-spin" style="border-width:2px"></div>
  </div>`;

  try {
    const res = await apiFetch({ action: 'content', bkid, page, juz, content_id: contentId });

    if (res.content) {
      // If content_id was used, it resolved the correct page and juz for us
      if (contentId > 0 && res.page) {
        readerState.page = res.page;
        readerState.juz  = res.juz;
        page = res.page;
        if (juzSel) juzSel.value = res.juz;
      }
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

    // Update controls
    const totalJuzLabel = res.total_juz > 1 ? ` · Juz ${res.juz}/${res.total_juz}` : '';
    if (label)    label.textContent = `Halaman ${page} dari ${res.total_pages}${totalJuzLabel}`;
    if (inp)      { inp.value = page; inp.max = res.total_pages; }
    if (totalLbl) totalLbl.textContent = `dari ${res.total_pages}`;
    if (prev)     prev.disabled  = (page <= 1);
    if (next)     next.disabled  = (page >= res.total_pages);

    readerState.total    = res.total_pages;
    readerState.totalJuz = res.total_juz || 1;

    // scroll reader into view smoothly
    area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  } catch(e) {
    if (handleAuthError(e)) return;
    area.innerHTML = `<p class="text-center text-red-500 text-sm py-6">Gagal memuat halaman.</p>`;
    area.style.opacity = '1';
  }
  reicons();
}

// ── Font Settings Panel renderer ──────────────────────────────
export function renderFontPanel() {
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

