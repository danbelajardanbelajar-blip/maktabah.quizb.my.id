import os
import glob

# Mapping of replacements to fix low contrast issues
replacements = {
    # Replace text-primary/60 (and /50, /75) with text-slate-600 which passes contrast on white
    'text-primary/60': 'text-slate-600',
    'text-primary/50': 'text-slate-500',
    'text-primary/75': 'text-primary',
    
    # "10 hal" text in book cards
    'text-xs text-gold font-medium': 'text-xs text-amber-700 font-medium',
    
    # "Lihat Katalog" link in home.js
    'text-gold hover:text-gold-dark': 'text-amber-700 hover:text-amber-800',

    # small "Read" button border and text in core.js
    'border-gold/20 text-gold hover:bg-gold/10': 'border-amber-700/30 text-amber-700 hover:bg-amber-700/10'
}

js_files = glob.glob('js/**/*.js', recursive=True)

for file in js_files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_content = content
    for old, new in replacements.items():
        new_content = new_content.replace(old, new)
        
    if new_content != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Updated {file}")

print("Done fixing contrast.")
