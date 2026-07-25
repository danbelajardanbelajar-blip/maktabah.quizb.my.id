import os
import re

def update_file(path):
    if not os.path.exists(path): return
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # In core.js / legacy_core.js / shared.js
    content = re.sub(
        r"const dlTitle    = totalJuz > 1\s*\?\s*`Unduh \$\{totalJuz\} file DOCX dalam ZIP`\s*:\s*'Unduh sebagai DOCX';",
        r"const dlTitle    = totalJuz > 1\n    ? `Unduh kitab ${title} (${totalJuz} file DOCX dalam ZIP)`\n    : `Unduh kitab ${title} sebagai DOCX`;",
        content
    )
    
    # In detail_kitab__reader.js
    content = content.replace(
        r"title=`${book.total_juz > 1 ? `Unduh ZIP berisi ${book.total_juz} file DOCX` : 'Unduh sebagai DOCX'}`",
        r"title=`${book.total_juz > 1 ? `Unduh kitab ${escHtml(book.title)} ZIP berisi ${book.total_juz} file DOCX` : `Unduh kitab ${escHtml(book.title)} sebagai DOCX`}`"
    )
    # wait the original is: title="${book.total_juz > 1 ? `Unduh ZIP berisi ${book.total_juz} file DOCX` : 'Unduh sebagai DOCX'}"
    content = content.replace(
        r'title="${book.total_juz > 1 ? `Unduh ZIP berisi ${book.total_juz} file DOCX` : \'Unduh sebagai DOCX\'}"',
        r'title="${book.total_juz > 1 ? `Unduh kitab ${escHtml(book.title)} ZIP berisi ${book.total_juz} file DOCX` : `Unduh kitab ${escHtml(book.title)} sebagai DOCX`}"'
    )
    content = content.replace(
        r'aria-label="${book.total_juz > 1 ? `Unduh ZIP berisi ${book.total_juz} file DOCX` : \'Unduh sebagai DOCX\'}"',
        r'aria-label="${book.total_juz > 1 ? `Unduh kitab ${escHtml(book.title)} ZIP berisi ${book.total_juz} file DOCX` : `Unduh kitab ${escHtml(book.title)} sebagai DOCX`}"'
    )

    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)

update_file('js/core/core.js')
update_file('js/core/legacy_core.js')
update_file('js/components/shared.js')
update_file('js/views/detail_kitab__reader.js')
print('Done updating dlTitle aria-labels')
