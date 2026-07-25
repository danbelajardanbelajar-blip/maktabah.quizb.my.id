import sys
path = 'index.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

import re
target = r'  <!-- Compiled Tailwind CSS -->\s*<link rel="stylesheet" href="/css/tailwind.css\?v=<\?= \$cssTailwindVer \?>" />\s*<!-- Critical Fonts \(Amiri, Lato\) loaded synchronously -->\s*<link rel="stylesheet" href="https://fonts.googleapis.com/css2\?family=Amiri:ital,wght@0,400;0,700;1,400&family=Lato:wght@300;400;700;900&display=swap" />'
replacement = '''  <!-- INLINED CRITICAL CSS (Tailwind & Google Fonts) to eliminate render blocking -->
  <style>
    <?php
      echo file_get_contents(__DIR__ . "/css/fonts.css");
      echo file_get_contents(__DIR__ . "/css/tailwind.css");
    ?>
  </style>'''

new_content = re.sub(target, replacement, content)
if new_content == content:
    print("Replace failed! Target not found.")
else:
    with open(path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Done!")
