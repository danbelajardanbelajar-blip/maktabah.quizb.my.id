import * as Core from './core/core.js';
import { navigate, handleAuthError, apiFetch, escHtml } from './core/core.js';

import * as View_home from './views/home.js';
import * as View_katalog from './views/katalog.js';
import * as View_kategori__daftar_kategori__kitab_per_kategori from './views/kategori__daftar_kategori__kitab_per_kategori.js';
import * as View_settings from './views/settings.js';
import * as View_detail_kitab__reader from './views/detail_kitab__reader.js';
import * as View_about from './views/about.js';
import * as View_kebijakan_privasi from './views/kebijakan_privasi.js';
import * as View_feedback from './views/feedback.js';
import * as View_404 from './views/404.js';

// Expose to window for admin.js and index.php
window.navigate = Core.navigate;
window.apiFetch = Core.apiFetch;
window.$ = Core.$;
window.$$ = Core.$$;
window.app = Core.app;
window.reicons = Core.reicons;
window.handleAuthError = Core.handleAuthError;
window.escHtml = Core.escHtml;
window.applyReaderFont = Core.applyReaderFont;
window.readerFontState = Core.readerFontState;

// Router Mapping
const routes = {
  '/': View_home.renderHome,
  '/katalog': View_katalog.renderKatalog,
  '/kategori': View_kategori__daftar_kategori__kitab_perkategori.renderKategori,
  '/settings': View_settings.renderSettings,
  '/about': View_about.renderAbout,
  '/search': View_search.renderSearch,
  '/search-advanced': View_search.renderSearchAdvanced,
  '/kitab': View_detail_kitab__reader.renderDetail,
  '/privacy': View_kebijakan_privasi.renderPrivacy,
  '/submit-file': View_submit__feedback.renderSubmitFile,
  '/request': View_submit__feedback.renderRequestKitab,
  '/feedback': View_submit__feedback.renderFeedback,
  '/catalog': View_kategori__daftar_kategori__kitab_perkategori.renderKategori,
  '/setting': View_settings.renderSettings,
};


// Override navigate to use our routes mapping
Core.navigate = function(path, push = true) {
  if (push) history.pushState({}, '', path);
  const base = path.split('?')[0];
  const handler = routes[base] || View_404.render404;
  Core.app().innerHTML = '';
  if (typeof window.closeCatDropdown === 'function') window.closeCatDropdown();
  handler(new URLSearchParams(path.includes('?') ? path.split('?')[1] : ''));
  Core.setActiveNav(base);
  Core.updateReaderMenus(base);
  window.scrollTo({ top: 0, behavior: 'smooth' });
  Core.reicons();
  Core.logVisitorActivity('visit', { route: base });
};
window.navigate = Core.navigate;

window.addEventListener('popstate', () => Core.navigate(location.pathname + location.search, false));

document.addEventListener('click', e => {
  const a = e.target.closest('[data-route]');
  if (!a) return;
  e.preventDefault();
  const route = a.getAttribute('data-route');
  Core.logVisitorActivity('menu_click', {
    route,
    label: a.textContent.trim().replace(/\s+/g, ' '),
    href: a.href,
  });
  Core.navigate(route);
  const menu = Core.$('#mobile-menu');
  if (menu && !menu.classList.contains('hidden-menu')) {
     if(typeof toggleMobileMenu === 'function') toggleMobileMenu();
  }
});
