import os, re

settings_path = r'c:\Users\zenhk\OneDrive\Documents\GitHub\maktabah.quizb.my.id\js\views\settings.js'
core_path = r'c:\Users\zenhk\OneDrive\Documents\GitHub\maktabah.quizb.my.id\js\core\core.js'
reader_path = r'c:\Users\zenhk\OneDrive\Documents\GitHub\maktabah.quizb.my.id\js\views\detail_kitab__reader.js'

with open(settings_path, 'r', encoding='utf-8') as f:
    settings_code = f.read()

funcs = ['escapeRegex', 'buildArabicRegexStr', 'parseSearchTerms', 'highlightTextNodes', 'hlTextMulti']
funcs_code = []

for func in funcs:
    pattern = re.compile(rf'function {func}\([^)]*\)\s*{{.*?^}}', re.MULTILINE | re.DOTALL)
    match = pattern.search(settings_code)
    if match:
        funcs_code.append('export ' + match.group(0))
        settings_code = settings_code.replace(match.group(0), '')

with open(settings_path, 'w', encoding='utf-8') as f:
    f.write(settings_code)

with open(core_path, 'a', encoding='utf-8') as f:
    f.write('\n\n// --- Search Highlight Utilities ---\n')
    f.write('\n\n'.join(funcs_code))
    f.write('\n')

import_pattern = re.compile(r'import\s+\{([^}]+)\}\s+from\s+[\'\"]./core/core\.js[\'\"];')
settings_import_match = import_pattern.search(settings_code)
if settings_import_match:
    new_import = settings_import_match.group(1).strip() + ', ' + ', '.join(funcs)
    new_import_stmt = f"import {{ {new_import} }} from '../core/core.js';"
    settings_code = settings_code.replace(settings_import_match.group(0), new_import_stmt)
    with open(settings_path, 'w', encoding='utf-8') as f:
        f.write(settings_code)

with open(reader_path, 'r', encoding='utf-8') as f:
    reader_code = f.read()

reader_import_match = import_pattern.search(reader_code)
if reader_import_match:
    new_import = reader_import_match.group(1).strip() + ', parseSearchTerms, highlightTextNodes'
    new_import_stmt = f"import {{ {new_import} }} from '../core/core.js';"
    reader_code = reader_code.replace(reader_import_match.group(0), new_import_stmt)
    with open(reader_path, 'w', encoding='utf-8') as f:
        f.write(reader_code)

print('Success')
