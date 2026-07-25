import os
import re

# Fix index.php
path = 'index.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix desktop nav active states
content = content.replace('color: #C9A227 !important;', 'color: #a16207 !important;')
content = content.replace('background:#C9A227;', 'background:#a16207;')

# Fix footer text-gold (since footer is dark bg, use amber-400)
# We will use regex to specifically target the footer section in index.php
# Footer starts around <footer class="bg-primary...
footer_match = re.search(r'<footer.*?</footer>', content, re.DOTALL)
if footer_match:
    footer_content = footer_match.group(0)
    # Replace text-gold with text-amber-400
    footer_content = footer_content.replace('text-gold', 'text-amber-400')
    content = content[:footer_match.start()] + footer_content + content[footer_match.end():]

# Fix Tanya nav icon text-gold to text-amber-700
content = content.replace('class="w-3.5 h-3.5 text-gold"', 'class="w-3.5 h-3.5 text-amber-700"')

# Fix Panel Admin text-gold/70 on white bg
content = content.replace('text-gold/70', 'text-amber-700/70')

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

# Fix about.js text-gold -> text-amber-700
path_about = 'js/views/about.js'
with open(path_about, 'r', encoding='utf-8') as f:
    about_content = f.read()
about_content = about_content.replace('text-gold', 'text-amber-700')
with open(path_about, 'w', encoding='utf-8') as f:
    f.write(about_content)

# Fix 404.js text-gold -> text-amber-700
path_404 = 'js/views/404.js'
with open(path_404, 'r', encoding='utf-8') as f:
    content_404 = f.read()
content_404 = content_404.replace('text-gold', 'text-amber-700')
with open(path_404, 'w', encoding='utf-8') as f:
    f.write(content_404)
    
print("Done fixing contrast.")
