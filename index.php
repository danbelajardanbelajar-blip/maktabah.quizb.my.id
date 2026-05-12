<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="المكتبة السنية — perpustakaan digital Islam memuat ribuan kitab salaf." />
  <title>المكتبة السنية — Al-Maktabah As-Sunniyyah</title>

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary:  { DEFAULT: '#1a3a2a', light: '#2d5c42', dark: '#0f2218' },
            gold:     { DEFAULT: '#c9a84c', light: '#e0c074', dark: '#a07828' },
            cream:    { DEFAULT: '#faf8f3', dark: '#f0ece0' },
            ink:      { DEFAULT: '#1c1c1e' },
          },
          fontFamily: {
            latin:  ['Lato', 'sans-serif'],
            arabic: ['"Amiri"', '"Noto Naskh Arabic"', 'serif'],
          },
          boxShadow: {
            card: '0 2px 16px 0 rgba(26,58,42,.08)',
            'card-hover': '0 8px 32px 0 rgba(26,58,42,.16)',
          },
        }
      }
    }
  </script>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Lato:wght@300;400;700;900&family=Noto+Naskh+Arabic:wght@400;600;700&display=swap" rel="stylesheet" />

  <!-- Lucide Icons (CDN) -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

  <style>
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { font-family: 'Lato', sans-serif; background: #faf8f3; color: #1c1c1e; }

    .arabic { font-family: 'Amiri', 'Noto Naskh Arabic', serif; direction: rtl; }

    /* Navbar scroll shadow */
    #navbar { transition: box-shadow .25s ease, background .25s ease; }
    #navbar.scrolled { box-shadow: 0 2px 20px rgba(26,58,42,.12); background: rgba(250,248,243,.97); backdrop-filter: blur(10px); }

    /* SPA page transitions */
    #app-content { animation: fadeIn .3s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

    /* Card hover */
    .book-card { transition: transform .2s ease, box-shadow .2s ease; }
    .book-card:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(26,58,42,.16); }

    /* Hero gradient */
    .hero-bg {
      background: linear-gradient(135deg, #1a3a2a 0%, #0f2218 40%, #1e3a2f 70%, #2d5c42 100%);
    }

    /* Gold accent line */
    .gold-line { background: linear-gradient(90deg, transparent, #c9a84c, transparent); height: 1px; }

    /* Skeleton shimmer */
    .skeleton { background: linear-gradient(90deg, #e8e4d9 25%, #f0ece0 50%, #e8e4d9 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
    @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }

    /* Book reader — preserves every newline & space exactly as stored in DB */
    .reader-text {
      white-space: pre-wrap;
      word-break: break-word;
      overflow-wrap: break-word;
    }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #f0ece0; } ::-webkit-scrollbar-thumb { background: #c9a84c; border-radius: 3px; }

    /* Mobile nav drawer */
    #mobile-menu { transition: transform .3s ease, opacity .3s ease; }
    #mobile-menu.hidden-menu { transform: translateY(-10px); opacity: 0; pointer-events: none; }

    /* Active nav link */
    .nav-link.active { color: #c9a84c !important; }
    .nav-link.active::after { width: 100%; }
    .nav-link::after { content:''; display:block; height:2px; background:#c9a84c; border-radius:1px; width:0; transition:width .25s ease; }
    .nav-link:hover::after { width:100%; }
  </style>
</head>

<body class="min-h-screen flex flex-col">

  <!-- ===================== NAVBAR ===================== -->
  <nav id="navbar" class="fixed top-0 left-0 right-0 z-50 bg-cream">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">

        <!-- Logo -->
        <a href="/" data-route="/" class="flex items-center gap-3 group">
          <div class="w-9 h-9 rounded-lg bg-primary flex items-center justify-center shadow-md group-hover:bg-primary-light transition-colors">
            <span class="text-gold arabic text-sm font-bold">م</span>
          </div>
          <div class="leading-tight">
            <div class="arabic text-primary font-bold text-base leading-none">المكتبة السنية</div>
            <div class="text-xs text-primary/60 font-light tracking-wide">Al-Maktabah As-Sunniyyah</div>
          </div>
        </a>

        <!-- Desktop Nav -->
        <div class="hidden md:flex items-center gap-6">
          <a href="/" data-route="/"          class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Beranda</a>
          <a href="/katalog" data-route="/katalog" class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Katalog</a>
          <a href="/about"   data-route="/about"   class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Tentang</a>
        </div>

        <!-- Search + Mobile toggle -->
        <div class="flex items-center gap-3">
          <button id="nav-search-btn" class="p-2 rounded-lg hover:bg-cream-dark transition-colors" title="Cari Kitab">
            <i data-lucide="search" class="w-5 h-5 text-primary"></i>
          </button>
          <button id="mobile-toggle" class="md:hidden p-2 rounded-lg hover:bg-cream-dark transition-colors">
            <i data-lucide="menu" class="w-5 h-5 text-primary" id="menu-icon"></i>
          </button>
        </div>

      </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden-menu md:hidden bg-cream border-t border-gold/20 px-4 pb-4 pt-2 space-y-1">
      <a href="/" data-route="/"          class="nav-link block py-2 text-sm font-medium text-primary/80 hover:text-primary border-b border-gold/10">Beranda</a>
      <a href="/katalog" data-route="/katalog" class="nav-link block py-2 text-sm font-medium text-primary/80 hover:text-primary border-b border-gold/10">Katalog</a>
      <a href="/about"   data-route="/about"   class="nav-link block py-2 text-sm font-medium text-primary/80 hover:text-primary">Tentang</a>
    </div>

    <!-- Global Search Bar (slide-down) -->
    <div id="search-bar" class="hidden bg-cream border-t border-gold/20 px-4 py-3">
      <div class="max-w-2xl mx-auto relative">
        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-primary/40"></i>
        <input id="global-search-input" type="text" placeholder="Cari judul kitab atau pengarang…"
          class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gold/30 bg-white focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
        <button id="search-bar-close" class="absolute right-3 top-1/2 -translate-y-1/2">
          <i data-lucide="x" class="w-4 h-4 text-primary/40 hover:text-primary"></i>
        </button>
      </div>
    </div>
  </nav>

  <!-- ===================== MAIN SPA CONTAINER ===================== -->
  <main id="app-content" class="flex-1 pt-16">
    <!-- JS renders pages here -->
    <div id="page-loader" class="flex items-center justify-center min-h-[60vh]">
      <div class="flex flex-col items-center gap-3">
        <div class="w-10 h-10 border-3 border-gold/30 border-t-gold rounded-full animate-spin" style="border-width:3px"></div>
        <span class="text-primary/50 text-sm arabic">جارٍ التحميل…</span>
      </div>
    </div>
  </main>

  <!-- ===================== FOOTER ===================== -->
  <footer class="bg-primary text-white mt-16">
    <div class="gold-line"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

        <!-- Brand -->
        <div>
          <div class="arabic text-gold text-2xl font-bold mb-2">المكتبة السنية</div>
          <p class="text-white/60 text-sm leading-relaxed">Perpustakaan digital Islam yang memuat ribuan kitab salaf untuk kemudahan umat dalam menuntut ilmu.</p>
        </div>

        <!-- Nav Links -->
        <div>
          <h4 class="text-gold text-sm font-semibold uppercase tracking-wider mb-4">Navigasi</h4>
          <ul class="space-y-2 text-sm text-white/70">
            <li><a href="/" data-route="/" class="hover:text-gold transition-colors">Beranda</a></li>
            <li><a href="/katalog" data-route="/katalog" class="hover:text-gold transition-colors">Katalog Kitab</a></li>
            <li><a href="/about" data-route="/about" class="hover:text-gold transition-colors">Tentang Kami</a></li>
          </ul>
        </div>

        <!-- Info -->
        <div>
          <h4 class="text-gold text-sm font-semibold uppercase tracking-wider mb-4">Info</h4>
          <ul class="space-y-2 text-sm text-white/70">
            <li class="flex items-center gap-2"><i data-lucide="globe" class="w-4 h-4 text-gold/60"></i> lib.quizb.my.id</li>
            <li class="flex items-center gap-2"><i data-lucide="book-open" class="w-4 h-4 text-gold/60"></i> Ribuan Kitab Tersedia</li>
          </ul>
        </div>

      </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-white/30">
      &copy; <?= date('Y') ?> المكتبة السنية &mdash; Semua hak dilindungi.
    </div>
  </footer>

  <!-- ===================== SCRIPTS ===================== -->
  <script>
    // Init Lucide icons after DOM ready — re-called after each SPA render
    document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
  </script>
  <script src="/app.js"></script>
</body>
</html>
