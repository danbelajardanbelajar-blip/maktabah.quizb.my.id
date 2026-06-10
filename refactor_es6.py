import os
import re

def main():
    with open('app.js.backup', 'r', encoding='utf-8') as f:
        content = f.read()

    pages = {}
    parts = re.split(r'// [═]{10,}\s*\n//\s+PAGE:\s+(.+)\n// [═]{10,}', content)
    
    core_content = parts[0]
    for i in range(1, len(parts), 2):
        pages[parts[i].strip()] = parts[i+1]

    os.makedirs('js/views', exist_ok=True)
    os.makedirs('js/core', exist_ok=True)

    # 1. Process Core
    # We will export all functions and consts/lets that are top-level
    # To do this safely, we will just manually export the known globals at the end of core.js
    
    exports_list = [
        'API', 'FONTS_LATIN', 'FONTS_ARABIC', 'readerFontState', 'applyReaderFont',
        '$', '$$', 'el', 'app', 'reicons', 'mobileFeedbackBanner', 'apiFetch',
        'handleAuthError', 'UPDATE_NOTICE_SESSION_KEY', 'isMobileViewport',
        'hasDismissedUpdateNotice', 'setDismissedUpdateNotice', 'closeUpdateNotice',
        'showUpdateNoticeIfNeeded', 'logVisitorActivity', 'navigate', 'setActiveNav',
        'updateReaderMenus', 'skeletonCards', 'bookCard', 'escHtml', 'paginationHtml',
        'recentBookCard', 'saveToRecentlyOpened', 'getRecentlyOpened'
    ]
    
    core_js = core_content
    # The routes object needs to import views, so let's REMOVE routes definition from core_content
    # and put it in main.js
    
    # We'll just remove the whole routes block from core
    core_js = re.sub(r'const routes = \{.*?\};', '', core_js, flags=re.DOTALL)
    
    # Add exports to function declarations in core
    # Simple regex to replace top-level const/let/function
    core_js = re.sub(r'^(const|let|var)\s+(' + '|'.join(exports_list) + r')\b', r'export \1 \2', core_js, flags=re.MULTILINE)
    core_js = re.sub(r'^async function\s+(' + '|'.join(exports_list) + r')\b', r'export async function \1', core_js, flags=re.MULTILINE)
    core_js = re.sub(r'^function\s+(' + '|'.join(exports_list) + r')\b', r'export function \1', core_js, flags=re.MULTILINE)
    
    # Write core.js
    with open('js/core/core.js', 'w', encoding='utf-8') as f:
        f.write(core_js)

    # 2. Process Views
    view_imports = "import { " + ", ".join(exports_list) + " } from '../core/core.js';\n"
    
    view_files = []
    
    for name, code in pages.items():
        file_name = name.lower().replace(' ', '_').replace('-', '_')
        # clean filename
        file_name = re.sub(r'[^a-z0-9_]', '', file_name) + '.js'
        
        # Export render functions
        code = re.sub(r'^async function\s+(render[A-Za-z0-9_]+)', r'export async function \1', code, flags=re.MULTILINE)
        code = re.sub(r'^function\s+(render[A-Za-z0-9_]+)', r'export function \1', code, flags=re.MULTILINE)
        
        # Also export states if any
        code = re.sub(r'^(let|const)\s+([A-Za-z0-9_]+State)\b', r'export \1 \2', code, flags=re.MULTILINE)
        
        with open(f'js/views/{file_name}', 'w', encoding='utf-8') as f:
            f.write(f"// PAGE: {name}\n")
            f.write(view_imports)
            f.write(code)
            
        view_files.append((name, file_name))

    # 3. Write main.js (Router + Initialization)
    with open('js/main.js', 'w', encoding='utf-8') as f:
        f.write("import * as Core from './core/core.js';\n")
        f.write("import { navigate, handleAuthError, apiFetch, escHtml } from './core/core.js';\n\n")
        
        # Import all views
        for name, file_name in view_files:
            f.write(f"import * as View_{file_name.replace('.js', '')} from './views/{file_name}';\n")
            
        f.write("\n// Expose to window for admin.js and index.php\n")
        f.write("window.navigate = Core.navigate;\n")
        f.write("window.apiFetch = Core.apiFetch;\n")
        f.write("window.$ = Core.$;\n")
        f.write("window.$$ = Core.$$;\n")
        f.write("window.app = Core.app;\n")
        f.write("window.reicons = Core.reicons;\n")
        f.write("window.handleAuthError = Core.handleAuthError;\n")
        f.write("window.escHtml = Core.escHtml;\n")
        f.write("window.applyReaderFont = Core.applyReaderFont;\n")
        f.write("window.readerFontState = Core.readerFontState;\n")
        
        # Re-attach routes
        f.write("\n// Router Mapping\n")
        f.write("const routes = {\n")
        f.write("  '/': View_home.renderHome,\n")
        f.write("  '/katalog': View_katalog.renderKatalog,\n")
        f.write("  '/kategori': View_kategori__daftar_kategori__kitab_perkategori.renderKategori,\n")
        f.write("  '/settings': View_settings.renderSettings,\n")
        f.write("  '/about': View_about.renderAbout,\n")
        f.write("  '/search': View_search.renderSearch,\n")
        f.write("  '/search-advanced': View_search.renderSearchAdvanced,\n")
        f.write("  '/kitab': View_detail_kitab__reader.renderDetail,\n")
        f.write("  '/privacy': View_kebijakan_privasi.renderPrivacy,\n")
        f.write("  '/submit-file': View_submit__feedback.renderSubmitFile,\n")
        f.write("  '/request': View_submit__feedback.renderRequestKitab,\n")
        f.write("  '/feedback': View_submit__feedback.renderFeedback,\n")
        f.write("  '/catalog': View_kategori__daftar_kategori__kitab_perkategori.renderKategori,\n")
        f.write("  '/setting': View_settings.renderSettings,\n")
        f.write("};\n\n")
        
        f.write("""
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
    label: a.textContent.trim().replace(/\\s+/g, ' '),
    href: a.href,
  });
  Core.navigate(route);
  const menu = Core.$('#mobile-menu');
  if (menu && !menu.classList.contains('hidden-menu')) {
     if(typeof toggleMobileMenu === 'function') toggleMobileMenu();
  }
});
""")

    print("ES6 Refactor Complete.")

if __name__ == '__main__':
    main()
