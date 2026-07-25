import os
import glob
import re

js_files = glob.glob('js/**/*.js', recursive=True)
js_files.append('index.php')

for file in js_files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_content = content
    # Replace all opacity variations of text-primary with text-slate-600
    # Match text-primary/XX where XX is any number
    new_content = re.sub(r'text-primary/\d+', 'text-slate-600', new_content)
    
    # Specific fixes for index.php
    if file == 'index.php':
        # Fix bnav-item CSS
        new_content = new_content.replace('color: #728c7c;', 'color: #526b5d;') # Darker muted green
        new_content = new_content.replace('color: #C9A227;', 'color: #a16207;') # Darker gold/amber
        new_content = new_content.replace('text-[10px] font-semibold text-gold', 'text-[10px] font-semibold text-amber-700')
        new_content = new_content.replace('border-gold/20', 'border-amber-700/20')
        
        # Kirimkan File button
        new_content = new_content.replace('bg-gold text-primary', 'bg-gold text-slate-900')
        
    if new_content != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Updated {file}")

print("Done fixing remaining contrast issues.")
