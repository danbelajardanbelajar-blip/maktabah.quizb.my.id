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
export const $  = (sel, ctx = document) => ctx.querySelector(sel);
export const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
export const el = (tag, cls, html = '') => {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (html) e.innerHTML = html;
  return e;
};
export const app     = () => $('#app-content');
export const reicons = () => { if (window.lucide) lucide.createIcons(); };

export const mobileFeedbackBanner = `
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
const LATEST_APK_VERSION_NAME = '1.0.8';

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
          <div style="font-size:1rem;font-weight:700;letter-spacing:.01em;margin-bottom:6px;">Update Terbaru Tersedia! (v${LATEST_APK_VERSION_NAME})</div>
          <p style="margin:0;font-size:.95rem;line-height:1.6;color:rgba(255,255,255,.84);">Versi ${LATEST_APK_VERSION_NAME} telah diterbitkan dengan pembaruan penting untuk pengalaman aplikasi yang lebih baik.</p>
        </div>
      </div>
      <div style="padding:20px 24px 24px;background:#faf8f3;color:#1c1c1e;">
        <div style="display:grid;gap:12px;margin-bottom:20px;">
          <div style="display:flex;align-items:flex-start;gap:10px;">
            <span style="width:10px;height:10px;margin-top:6px;background:#c9a84c;border-radius:999px;flex-shrink:0;"></span>
            <span style="font-size:.95rem;line-height:1.6;">Pembaruan Ikon dan Splash Screen</span>
          </div>
          <div style="display:flex;align-items:flex-start;gap:10px;">
            <span style="width:10px;height:10px;margin-top:6px;background:#c9a84c;border-radius:999px;flex-shrink:0;"></span>
            <span style="font-size:.95rem;line-height:1.6;">Tampilan Menu Download Baru</span>
          </div>
          <div style="display:flex;align-items:flex-start;gap:10px;">
            <span style="width:10px;height:10px;margin-top:6px;background:#c9a84c;border-radius:999px;flex-shrink:0;"></span>
            <span style="font-size:.95rem;line-height:1.6;">Optimalisasi Sistem dan Log Error</span>
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

function getMaktabahAppVersion() {
  const ua = navigator.userAgent || navigator.vendor || window.opera;
  const match = ua.match(/MaktabahApp\/([0-9]+)/);
  if (match) {
    return parseInt(match[1], 10);
  }
  return null;
}

export function showUpdateNoticeIfNeeded() {
  if (hasDismissedUpdateNotice()) return;
  
  const appVersion = getMaktabahAppVersion();
  
  // Jika bukan dari APK Maktabah Turats, jangan tampilkan (Desktop & Chrome Android)
  if (appVersion === null) return;
  
  // Jika versi 9 ke atas, jangan tampilkan
  if (appVersion >= 9) return;
  
  // Jika versi <= 8, tampilkan
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

// Router is now handled in main.js

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



// ── Recently Opened Books (localStorage) ─────────────────────
const RECENT_KEY      = 'maktabah_recent_opened';
const RECENT_MAX      = 8;

export function getRecentlyOpened() {
  try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); }
  catch { return []; }
}

export function saveToRecentlyOpened(book) {
  try {
    const list = getRecentlyOpened().filter(b => String(b.id) !== String(book.id));
    list.unshift({ ...book, openedAt: Date.now() });
    localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, RECENT_MAX)));
  } catch { /* ignore storage errors */ }
}

export function recentBookCard(item) {
  const timeAgo = (() => {
    const diff = Date.now() - (item.openedAt || 0);
    const m = Math.floor(diff / 60000);
    const h = Math.floor(diff / 3600000);
    const d = Math.floor(diff / 86400000);
    if (m < 1)  return 'Baru saja';
    if (m < 60) return `${m} menit lalu`;
    if (h < 24) return `${h} jam lalu`;
    if (d < 7)  return `${d} hari lalu`;
    return new Date(item.openedAt).toLocaleDateString('id-ID', {day:'numeric',month:'short'});
  })();
  return `
    <div class="book-card bg-white rounded-2xl shadow-card overflow-hidden cursor-pointer relative group"
         onclick="navigate('/kitab?id=${item.id}')">
      <!-- Time badge -->
      <div class="absolute top-2 right-2 z-10">
        <span style="font-size:10px;background:rgba(26,58,42,.72);color:rgba(212,197,160,.9);padding:2px 7px;border-radius:999px;backdrop-filter:blur(4px);white-space:nowrap;">
          ${escHtml(timeAgo)}
        </span>
      </div>
      <div class="hero-bg p-5 flex items-center justify-center min-h-[90px]">
        <div class="arabic text-white text-center font-bold leading-snug text-lg line-clamp-3">${escHtml(item.title)}</div>
      </div>
      <div class="p-3">
        <div class="text-xs text-primary/55 truncate">${escHtml(item.author || '')}</div>
        ${item.cat ? `<span class="mt-1 inline-block text-[10px] px-2 py-0.5 rounded-full bg-gold/10 text-gold-dark font-medium">${escHtml(item.cat)}</span>` : ''}
      </div>
    </div>`;
}


export let navigate = () => {};
export function setNavigate(fn) { navigate = fn; }


// --- Search Highlight Utilities ---
export function escapeRegex(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function buildArabicRegexStr(term) {
  const diacritics = '[\\u064B-\\u065F\\u0670\\u06D6-\\u06ED\\u06DF-\\u06E8\\u06EA-\\u06ED]*';
  let result = '';
  for (let i = 0; i < term.length; i++) {
    const char = term[i];
    if (/\\s/.test(char)) {
      // Collapse multiple spaces into one space matcher
      if (!result.endsWith('\\s+')) {
        result += '\\s+';
      }
    } else {
      result += escapeRegex(char) + diacritics;
    }
  }
  return result;
}

export function parseSearchTerms(q) {
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

export function highlightTextNodes(container, terms) {
  const escapedTerms = terms
    .map(raw => String(raw || '').trim())
    .filter(Boolean)
    .map(t => t.replace(/^"|"$/g, '').trim())
    .filter(Boolean)
    .sort((a, b) => b.length - a.length)
    .map(buildArabicRegexStr);
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

export function hlTextMulti(text, terms) {
  if (!text) return escHtml('');
  let escaped = escHtml(text);
  const patterns = [];
  (terms || []).forEach(raw => {
    const term = String(raw || '').trim();
    if (!term) return;
    const unquoted = term.replace(/^"|"$/g, '').trim();
    if (!unquoted) return;
    patterns.push(buildArabicRegexStr(unquoted));
  });
  if (!patterns.length) return escaped;
  const regex = new RegExp('(' + patterns.sort((a, b) => b.length - a.length).join('|') + ')', 'gi');
  return escaped.replace(regex, '<mark class="hl">$1</mark>');
}
