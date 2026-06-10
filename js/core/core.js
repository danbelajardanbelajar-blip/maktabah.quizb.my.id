/* =============================================================
   Al-Maktabah As-Sunniyyah — SPA Core (app.js)
   Vanilla JS · History API · Fetch API
   ============================================================= */

'use strict';

// ── Config ────────────────────────────────────────────────────
export const API = '/api.php';

// ── Reader font configuration ─────────────────────────────────
export const FONTS_LATIN = [
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
export const FONTS_ARABIC = [
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
export const readerFontState = Object.assign({}, _rfDef,
  JSON.parse(localStorage.getItem('readerFonts') || '{}'));

export function applyReaderFont(save = true) {
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
export const el = (tag, cls, html = '') => {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (html) e.innerHTML = html;
  return e;
};
export const app     = () => $('#app-content');
export const reicons = () => { if (window.lucide) lucide.createIcons(); };

window.mobileFeedbackBanner = `
  <div class="md:hidden px-4 mb-24 mt-8">
    <a href="/feedback" data-route="/feedback" class="block bg-gradient-to-r from-cream-dark to-cream rounded-2xl p-4 border border-gold/20 shadow-sm relative overflow-hidden group no-underline">
      <div class="absolute inset-0 bg-gold/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
      <div class="flex items-center gap-4 relative z-10">
        <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center shrink-0 shadow-sm text-gold">
          <i data-lucide="message-square-plus" class="w-6 h-6"></i>
        </div>
        <div>
          <h4 class="text-sm font-bold text-primary mb-1">Menemukan Masalah?</h4>
          <p class="text-xs text-primary/70 leading-relaxed">Beritahu kami jika ada error di web ini atau ingin memberikan saran.</p>
        </div>
      </div>
    </a>
  </div>
`;
export async function apiFetch(params) {
  const url = API + '?' + new URLSearchParams(params).toString();
  const res = await fetch(url);
  if (!res.ok) {
    const text = await res.text();
    const error = new Error('API error ' + res.status + ': ' + text);
    error.status = res.status;
    error.responseText = text;
    throw error;
  }
  return res.json();
}

// ── Helper untuk handle authentication errors ──────────────
export function handleAuthError(error, fallbackMsg = 'Akses ditolak. Diperlukan hak admin.') {
  const errorMsg = error?.message || error?.toString() || '';
  const statusCode = error?.status || 0;
  
  // Cek apakah error berisi indikasi 403/auth error
  const is403 = statusCode === 403 || 
                errorMsg.includes('403') || 
                errorMsg.includes('Akses ditolak') ||
                errorMsg.includes('admin') ||
                errorMsg.includes('Diperlukan');
  
  if (is403) {
    // Parse error message untuk ditampilkan
    let displayMsg = fallbackMsg;
    try {
      // Coba parse JSON error dari response
      const jsonMatch = errorMsg.match(/:\s*(\{[^}]+\})/);
      if (jsonMatch) {
        const parsed = JSON.parse(jsonMatch[1]);
        if (parsed.error) displayMsg = parsed.error;
      } else if (errorMsg.includes('Akses ditolak')) {
        // Ambil pesan yang sudah dalam format readable
        const match = errorMsg.match(/Akses ditolak[.\s]*(.*?)$/);
        if (match) displayMsg = match[0];
      }
    } catch (e) {
      // Gunakan error message apa adanya
      const match = errorMsg.match(/:\s*(.+)$/);
      if (match) displayMsg = match[1];
    }
    
    // Simpan ke localStorage dan redirect ke login
    localStorage.setItem('authErrorMsg', displayMsg);
    window.location.href = '/auth.php?action=login&error=1';
    return true;
  }
  
  return false;
}

export const UPDATE_NOTICE_SESSION_KEY = 'updateNoticeDismissedV1';
const UPDATE_NOTICE_PLAYSTORE_URL = 'https://play.google.com/store/apps/details?id=com.maktabah.premium';

export function isMobileViewport() {
  return window.matchMedia('(max-width: 767px)').matches;
}

export function hasDismissedUpdateNotice() {
  return sessionStorage.getItem(UPDATE_NOTICE_SESSION_KEY) === '1';
}

export function setDismissedUpdateNotice() {
  sessionStorage.setItem(UPDATE_NOTICE_SESSION_KEY, '1');
}

export function closeUpdateNotice() {
  const overlay = document.getElementById('update-notice-overlay');
  if (overlay) {
    overlay.remove();
    setDismissedUpdateNotice();
  }
}

function createUpdateNoticeOverlay() {
  if (document.getElementById('update-notice-overlay')) return;

  const overlay = el('div', 'update-notice-overlay');
  overlay.id = 'update-notice-overlay';
  overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,34,24,.92);display:flex;align-items:center;justify-content:center;padding:20px;';
  overlay.innerHTML = `
    <div style="width:100%;max-width:520px;background:#fff;border-radius:30px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.24);">
      <div style="padding:24px 24px 16px;background:#1a3a2a;color:#fff;display:flex;align-items:flex-start;gap:14px;">
        <div style="width:46px;height:46px;border-radius:18px;background:rgba(201,168,76,.18);display:flex;align-items:center;justify-content:center;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="24" height="24" style="color:#f8e6c1;"><path d="M12 5v14m7-7H5"/></svg>
        </div>
        <div style="flex:1;">
          <div style="font-size:1rem;font-weight:700;letter-spacing:.01em;margin-bottom:6px;">Update Terbaru Telah Tayang</div>
          <p style="margin:0;font-size:.95rem;line-height:1.6;color:rgba(255,255,255,.84);">Versi terbaru telah diterbitkan dengan pembaruan penting untuk pengalaman aplikasi.</p>
        </div>
      </div>
      <div style="padding:20px 24px 24px;background:#faf8f3;color:#1c1c1e;">
        <div style="display:grid;gap:12px;margin-bottom:20px;">
          <div style="display:flex;align-items:flex-start;gap:10px;">
            <span style="width:10px;height:10px;margin-top:6px;background:#c9a84c;border-radius:999px;flex-shrink:0;"></span>
            <span style="font-size:.95rem;line-height:1.6;">Pembaruan ikon dan splash screen</span>
          </div>
          <div style="display:flex;align-items:flex-start;gap:10px;">
            <span style="width:10px;height:10px;margin-top:6px;background:#c9a84c;border-radius:999px;flex-shrink:0;"></span>
            <span style="font-size:.95rem;line-height:1.6;">Menu download</span>
          </div>
          <div style="display:flex;align-items:flex-start;gap:10px;">
            <span style="width:10px;height:10px;margin-top:6px;background:#c9a84c;border-radius:999px;flex-shrink:0;"></span>
            <span style="font-size:.95rem;line-height:1.6;">Menu kirim file dan kitab</span>
          </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:flex-end;">
          <button id="update-notice-dismiss" style="flex:1 1 100%;min-width:120px;padding:14px 18px;border:none;border-radius:16px;background:#e5e1d7;color:#1c1c1e;font-weight:700;cursor:pointer;transition:transform .2s ease;">Tutup</button>
          <a href="${UPDATE_NOTICE_PLAYSTORE_URL}" target="_blank" rel="noopener noreferrer" id="update-notice-link"
             style="flex:1 1 100%;min-width:120px;padding:14px 18px;border:none;border-radius:16px;background:#1a3a2a;color:#fff;font-weight:700;text-align:center;text-decoration:none;">Buka Play Store</a>
        </div>
      </div>
    </div>
  `;

  overlay.addEventListener('click', e => {
    if (e.target === overlay) closeUpdateNotice();
  });

  document.body.appendChild(overlay);
  document.getElementById('update-notice-dismiss')?.addEventListener('click', closeUpdateNotice);
  document.getElementById('update-notice-link')?.addEventListener('click', setDismissedUpdateNotice);
}

function isAndroidApp() {
  const ua = navigator.userAgent || navigator.vendor || window.opera;
  // APK Android biasa membungkus web dalam WebView, yang mengandung kata 'wv' di User-Agent
  return (ua.includes('Android') && ua.includes('wv'));
}

export function showUpdateNoticeIfNeeded() {
  if (!isMobileViewport() || !isAndroidApp() || hasDismissedUpdateNotice()) return;
  createUpdateNoticeOverlay();
}

window.closeUpdateNotice = closeUpdateNotice;

export function logVisitorActivity(event, data = {}) {
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


export function navigate(path, push = true) {
  if (push) history.pushState({}, '', path);
  const base = path.split('?')[0];
  const handler = routes[base] || render404;
  app().innerHTML = '';
  // Close kategori dropdown whenever navigating
  if (typeof window.closeCatDropdown === 'function') window.closeCatDropdown();
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

export function setActiveNav(base) {
  // Desktop top nav links
  $$('.nav-link').forEach(a => {
    a.classList.toggle('active', a.getAttribute('data-route') === base);
  });
  // Desktop Kategori dropdown button — active when on /kategori or /katalog
  const catNavBtn = document.getElementById('nav-cat-btn');
  if (catNavBtn) {
    catNavBtn.classList.toggle('active', base === '/kategori' || base === '/katalog');
  }
  // Mobile bottom nav — /search counts as "Cari" active
  $$('.bnav-item').forEach(a => {
    const route = a.getAttribute('data-route');
    const isActive = route === base || (base === '/search' && route === '/search');
    a.classList.toggle('active', isActive);
  });
}

export function updateReaderMenus(base) {
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

  // Live search input listener removed, relying on Enter key.
  $('#global-search-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      const q = e.target.value.trim();
      if (q) { $('#search-bar').classList.add('hidden'); navigate('/search?q=' + encodeURIComponent(q)); }
    }
  });

  navigate(location.pathname + location.search, false);
  showUpdateNoticeIfNeeded();
});

// ── Skeleton helpers ──────────────────────────────────────────
export function skeletonCards(n = 8) {
  return Array.from({ length: n }, () =>
    `<div class="bg-white rounded-2xl shadow-card p-4 space-y-3">
       <div class="skeleton h-5 rounded-lg w-4/5"></div>
       <div class="skeleton h-4 rounded w-3/5"></div>
       <div class="skeleton h-3 rounded w-2/5"></div>
     </div>`
  ).join('');
}

// ── Shared: Book Card ─────────────────────────────────────────
export function bookCard(b) {
  const title  = b.title  || 'بدون عنوان';
  const author = b.author || 'مجهول';
  const cat    = b.category_name || '';
  const pages  = b.pages  ? b.pages + ' hal.' : '';
  const totalJuz   = b.total_juz || 1;
  const fmtBadge   = totalJuz > 1
    ? `<span class="dl-fmt-badge dl-fmt-zip">ZIP · ${totalJuz} juz</span>`
    : `<span class="dl-fmt-badge dl-fmt-docx">DOCX</span>`;
  const dlTitle    = totalJuz > 1
    ? `Unduh ${totalJuz} file DOCX dalam ZIP`
    : 'Unduh sebagai DOCX';
  return `
    <div class="book-card bg-white rounded-2xl shadow-card p-5 flex flex-col gap-3 cursor-pointer"
         onclick="navigate('/kitab?id=${b.bkid}')">
      <div class="flex-1">
        <div class="arabic text-primary font-semibold text-base leading-snug line-clamp-2 mb-1">${escHtml(title)}</div>
        <div class="text-primary/60 text-xs font-medium line-clamp-1">${escHtml(author)}</div>
      </div>
      <div class="flex items-center gap-2 mt-auto pt-2 border-t border-cream-dark">
        ${pages ? `<span class="text-xs text-gold font-medium">${escHtml(pages)}</span>` : ''}
        <a href="/api.php?action=download_book&id=${b.bkid}"
           class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-gold/20 text-gold hover:bg-gold/10 transition ml-auto"
           onclick="event.stopPropagation();"
           title="${dlTitle}"
           aria-label="${dlTitle}">
          <i data-lucide="download" class="w-3.5 h-3.5 shrink-0"></i>
          ${fmtBadge}
        </a>
      </div>
    </div>`;
}

export function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Pagination ────────────────────────────────────────────────
export function paginationHtml(current, total, onClickFn) {
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

