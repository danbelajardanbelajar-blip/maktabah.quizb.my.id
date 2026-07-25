import re
import os

# Fix 1: index.php -> Change 'Lihat semua' to 'Lihat Semua Kategori'
with open('index.php', 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('Lihat semua <i data-lucide="arrow-right" style="width:12px;height:12px;"></i>',
                          'Semua Kategori <i data-lucide="arrow-right" style="width:12px;height:12px;"></i>')

with open('index.php', 'w', encoding='utf-8') as f:
    f.write(content)

# Fix 2: home.js -> Change 'Lihat Semua' to 'Semua Kitab'
with open('js/views/home.js', 'r', encoding='utf-8') as f:
    home_content = f.read()

home_content = home_content.replace('Lihat Semua <i data-lucide="arrow-right" class="w-4 h-4"></i>',
                                    'Semua Kitab <i data-lucide="arrow-right" class="w-4 h-4"></i>')

# Also fix the bg-gold text-primary in home.js to bg-amber-700 text-white
home_content = home_content.replace('bg-gold text-primary', 'bg-amber-700 text-white')
with open('js/views/home.js', 'w', encoding='utf-8') as f:
    f.write(home_content)

# Fix 3: feedback.js -> Change bg-gold text-primary to bg-amber-700 text-white
with open('js/views/feedback.js', 'r', encoding='utf-8') as f:
    fb_content = f.read()

fb_content = fb_content.replace('bg-gold text-primary', 'bg-amber-700 text-white')
with open('js/views/feedback.js', 'w', encoding='utf-8') as f:
    f.write(fb_content)

# Fix 4: kebijakan_privasi.js -> Change bg-gold text-primary to bg-amber-700 text-white
with open('js/views/kebijakan_privasi.js', 'r', encoding='utf-8') as f:
    kb_content = f.read()

kb_content = kb_content.replace('bg-gold text-primary', 'bg-amber-700 text-white')
with open('js/views/kebijakan_privasi.js', 'w', encoding='utf-8') as f:
    f.write(kb_content)

# Fix 5: settings.js -> Change bg-gold text-primary to bg-amber-700 text-white
with open('js/views/settings.js', 'r', encoding='utf-8') as f:
    set_content = f.read()

set_content = set_content.replace('bg-gold text-primary', 'bg-amber-700 text-white')
with open('js/views/settings.js', 'w', encoding='utf-8') as f:
    f.write(set_content)

print("Accessibility fixes applied.")
