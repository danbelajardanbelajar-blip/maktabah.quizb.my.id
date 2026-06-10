// PAGE: 404
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js';

export function render404() {
  const path = location.pathname;
  app().innerHTML = `
    <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4 py-12">
      <div class="w-20 h-20 rounded-2xl bg-primary/8 border border-gold/20 flex items-center justify-center mb-6">
        <i data-lucide="file-question" class="w-10 h-10 text-primary/30"></i>
      </div>
      <h1 class="text-4xl font-bold text-primary mb-2">404</h1>
      <p class="text-primary/50 text-base mb-2">Halaman <code class="text-gold font-mono text-sm">${escHtml(path)}</code> tidak ditemukan</p>
      <p class="text-primary/35 text-sm mb-8">Mengarahkan ke Beranda dalam <span id="_r404-cnt">3</span> detik…</p>
      <div class="flex flex-wrap gap-3 justify-center">
        <button onclick="navigate('/')"
          class="px-5 py-2.5 bg-primary text-white rounded-xl font-medium text-sm hover:bg-primary-light transition-colors">
          <i data-lucide="home" class="w-4 h-4 inline mr-1"></i> Beranda
        </button>
        <button onclick="navigate('/kategori')"
          class="px-5 py-2.5 bg-white border border-gold/30 text-primary rounded-xl font-medium text-sm hover:bg-cream-dark transition-colors">
          <i data-lucide="layout-grid" class="w-4 h-4 inline mr-1"></i> Kategori
        </button>
        <button onclick="navigate('/settings')"
          class="px-5 py-2.5 bg-white border border-gold/30 text-primary rounded-xl font-medium text-sm hover:bg-cream-dark transition-colors">
          <i data-lucide="settings-2" class="w-4 h-4 inline mr-1"></i> Setting
        </button>
      </div>
    </div>`;
  reicons();

  // Auto-redirect ke Beranda setelah 3 detik
  let cnt = 3;
  const timer = setInterval(() => {
    cnt--;
    const el = document.getElementById('_r404-cnt');
    if (el) el.textContent = cnt;
    if (cnt <= 0) {
      clearInterval(timer);
      navigate('/');
    }
  }, 1000);
}


