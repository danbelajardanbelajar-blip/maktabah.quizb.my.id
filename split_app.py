import os
import re

def main():
    with open('app.js.backup', 'r', encoding='utf-8') as f:
        content = f.read()

    # We will find sections based on the headers
    # // ══════════════════════════════════════════════════════════════
    # //  PAGE: HOME
    # // ══════════════════════════════════════════════════════════════

    pages = {}
    
    # Split by the double border header
    # Allow anything after PAGE: until newline
    parts = re.split(r'// [═]{10,}\s*\n//\s+PAGE:\s+(.+)\n// [═]{10,}', content)
    
    # parts[0] is everything before the first PAGE: header
    core_content = parts[0]
    
    for i in range(1, len(parts), 2):
        page_name = parts[i].strip()
        page_content = parts[i+1]
        pages[page_name] = page_content

    # Now we have core_content and a dictionary of pages
    os.makedirs('js/views', exist_ok=True)
    os.makedirs('js/core', exist_ok=True)
    os.makedirs('js/components', exist_ok=True)

    # Let's save the core_content to js/core/legacy_core.js for now
    # We will prepend imports to each page
    
    # Write core
    with open('js/core/legacy_core.js', 'w', encoding='utf-8') as f:
        f.write("/* Legacy Core - to be split further if needed */\n")
        f.write(core_content)

    # Write views
    for name, code in pages.items():
        # sanitize name
        file_name = name.lower().replace(' ', '_').replace('-', '_') + '.js'
        
        with open(f'js/views/{file_name}', 'w', encoding='utf-8') as f:
            f.write(f"// PAGE: {name}\n")
            # In a real ES module we'd add imports here.
            # For now, to ensure it runs without breaking immediately if we just load them,
            # wait, if they are ES modules they need imports.
            f.write("import * as Core from '../core/legacy_core.js';\n") # Placeholder
            f.write(code)

    print(f"Split {len(pages)} pages.")

if __name__ == '__main__':
    main()
