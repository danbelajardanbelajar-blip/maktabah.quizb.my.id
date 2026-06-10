import os
import re

def main():
    core_file = 'js/core/core.js'
    
    with open(core_file, 'r', encoding='utf-8') as f:
        core_js = f.read()

    exports_list = [
        'API', 'FONTS_LATIN', 'FONTS_ARABIC', 'readerFontState', 'applyReaderFont',
        '$', '$$', 'el', 'app', 'reicons', 'mobileFeedbackBanner', 'apiFetch',
        'handleAuthError', 'UPDATE_NOTICE_SESSION_KEY', 'isMobileViewport',
        'hasDismissedUpdateNotice', 'setDismissedUpdateNotice', 'closeUpdateNotice',
        'showUpdateNoticeIfNeeded', 'logVisitorActivity', 'navigate', 'setActiveNav',
        'updateReaderMenus', 'skeletonCards', 'bookCard', 'escHtml', 'paginationHtml',
        'recentBookCard', 'saveToRecentlyOpened', 'getRecentlyOpened'
    ]

    for item in exports_list:
        # Check if already exported
        if re.search(r'^export\s+(const|let|var|function|async function)\s+' + re.escape(item) + r'(\s|=|\()', core_js, flags=re.MULTILINE):
            continue
            
        # Try to replace
        core_js = re.sub(r'^(const|let|var|function|async function)\s+' + re.escape(item) + r'(\s|=|\()', r'export \1 ' + item + r'\2', core_js, flags=re.MULTILINE)
        
    with open(core_file, 'w', encoding='utf-8') as f:
        f.write(core_js)
        
    print("Fixed all exports!")

if __name__ == '__main__':
    main()
