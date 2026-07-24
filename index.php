<?php
require_once __DIR__ . '/seo/seo_master.php';
require_once __DIR__ . '/app/bootstrap.php';

$sessionUser = $_SESSION['user'] ?? null;

// ══════════════════════════════════════════════════════════════
//  MAINTENANCE MODE
//  Ubah $maintenanceMode = true  → tampilkan halaman maintenance
//  Ubah $maintenanceMode = false → situs berjalan normal
// ══════════════════════════════════════════════════════════════
$maintenanceMode = false;

// Pesan yang ditampilkan (bisa diubah sesuai kebutuhan)
$maintenanceTitle   = 'Sedang Dalam Pemeliharaan';
$maintenanceMessage = 'Kami sedang melakukan pembaruan sistem untuk meningkatkan layanan. Mohon bersabar, situs akan kembali aktif nanti siang, insya Allah.';
$maintenanceEta     = ''; // Isi estimasi waktu, contoh: '± 30 menit' atau kosongkan ''

if ($maintenanceMode) :
?><!DOCTYPE html>
<html lang="id">
<head>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-TTMPGH6R');</script>
<!-- End Google Tag Manager -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex, nofollow">
  <title>Pemeliharaan — المكتبة السنية</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      width: 100%; height: 100%;
      min-height: 100vh;
      /* Android WebView safe */
      min-height: -webkit-fill-available;
    }

    body {
      font-family: 'Lato', sans-serif;
      background: #14532D;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      /* Mencegah scroll di semua perangkat */
      position: fixed;
      inset: 0;
    }

    /* Background animasi */
    .bg-radial {
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 50% -10%, rgba(201,162,39,.18) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 100%, rgba(20,83,45,.6) 0%, transparent 60%),
        linear-gradient(160deg, #14532D 0%, #166534 50%, #06402b 100%);
      z-index: 0;
    }

    /* Partikel bintang (pure CSS) */
    .stars {
      position: fixed;
      inset: 0;
      z-index: 0;
      overflow: hidden;
    }
    .stars::before, .stars::after {
      content: '';
      position: absolute;
      width: 2px; height: 2px;
      background: rgba(255,255,255,.15);
      border-radius: 50%;
      box-shadow:
        120px 40px 0 rgba(201,162,39,.3),
        240px 180px 0 rgba(255,255,255,.12),
        80px 300px 0 rgba(201,162,39,.2),
        380px 60px 0 rgba(255,255,255,.1),
        500px 200px 0 rgba(201,162,39,.25),
        620px 350px 0 rgba(255,255,255,.08),
        30px 500px 0 rgba(201,162,39,.15),
        700px 450px 0 rgba(255,255,255,.12),
        150px 700px 0 rgba(201,162,39,.2),
        450px 600px 0 rgba(255,255,255,.1),
        820px 120px 0 rgba(201,162,39,.18),
        900px 500px 0 rgba(255,255,255,.08);
      animation: twinkle 4s infinite alternate;
    }
    .stars::after {
      width: 1px; height: 1px;
      box-shadow:
        200px 80px 0 rgba(255,255,255,.2),
        350px 250px 0 rgba(201,162,39,.25),
        60px 400px 0 rgba(255,255,255,.1),
        750px 300px 0 rgba(201,162,39,.18),
        550px 500px 0 rgba(255,255,255,.15),
        100px 600px 0 rgba(201,162,39,.2),
        650px 700px 0 rgba(255,255,255,.1),
        850px 80px 0 rgba(201,162,39,.15);
      animation-delay: 2s;
      animation-duration: 6s;
    }
    @keyframes twinkle { from { opacity: .6; } to { opacity: 1; } }

    /* Kartu utama */
    .card {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 520px;
      margin: 0 16px;
      text-align: center;
      animation: fadeUp .8s cubic-bezier(.22,.61,.36,1) both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(32px); }
      to   { opacity: 1; transform: none; }
    }

    /* Logo / ikon */
    .icon-wrap {
      width: 88px; height: 88px;
      border-radius: 28px;
      background: linear-gradient(135deg, rgba(201,162,39,.22), rgba(201,162,39,.06));
      border: 1.5px solid rgba(201,162,39,.3);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 28px;
      box-shadow: 0 0 40px rgba(201,162,39,.15), inset 0 1px 0 rgba(255,255,255,.08);
      animation: pulse 3s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { box-shadow: 0 0 30px rgba(201,162,39,.15), inset 0 1px 0 rgba(255,255,255,.08); }
      50%       { box-shadow: 0 0 60px rgba(201,162,39,.30), inset 0 1px 0 rgba(255,255,255,.08); }
    }

    /* Gear SVG animasi */
    .gear { animation: spin 8s linear infinite; transform-origin: center; }
    .gear-inner { animation: spin 6s linear infinite reverse; transform-origin: center; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Judul Arab */
    .arabic-title {
      font-family: 'Amiri', serif;
      font-size: clamp(1.6rem, 5vw, 2.4rem);
      font-weight: 700;
      color: #C9A227;
      direction: rtl;
      line-height: 1.3;
      margin-bottom: 6px;
      text-shadow: 0 2px 12px rgba(201,162,39,.3);
    }

    /* Sub judul */
    .site-name {
      font-size: clamp(.7rem, 2vw, .8rem);
      letter-spacing: .12em;
      text-transform: uppercase;
      color: rgba(255,255,255,.35);
      margin-bottom: 32px;
    }

    /* Garis pemisah */
    .divider {
      width: 60px; height: 2px;
      background: linear-gradient(90deg, transparent, #C9A227, transparent);
      margin: 0 auto 32px;
      border-radius: 1px;
    }

    /* Badge status */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 18px;
      border-radius: 999px;
      background: rgba(201,162,39,.12);
      border: 1px solid rgba(201,162,39,.3);
      font-size: .78rem;
      font-weight: 700;
      color: #C9A227;
      letter-spacing: .08em;
      text-transform: uppercase;
      margin-bottom: 24px;
    }
    .badge-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #C9A227;
      animation: blink 1.4s ease-in-out infinite;
    }
    @keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: .25; } }

    /* Judul maintenance */
    .main-title {
      font-size: clamp(1.3rem, 4vw, 1.9rem);
      font-weight: 700;
      color: #fff;
      margin-bottom: 14px;
      line-height: 1.3;
    }

    /* Deskripsi */
    .main-desc {
      font-size: clamp(.85rem, 2.5vw, .98rem);
      line-height: 1.75;
      color: rgba(255,255,255,.6);
      max-width: 400px;
      margin: 0 auto 28px;
    }

    /* ETA */
    .eta-box {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 14px;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      font-size: .85rem;
      color: rgba(255,255,255,.5);
      margin-bottom: 36px;
    }
    .eta-box span { color: rgba(255,255,255,.8); font-weight: 600; }

    /* Progress bar animasi */
    .progress-track {
      width: 100%;
      height: 3px;
      background: rgba(255,255,255,.08);
      border-radius: 999px;
      overflow: hidden;
      margin-bottom: 36px;
    }
    .progress-fill {
      height: 100%;
      width: 30%;
      border-radius: 999px;
      background: linear-gradient(90deg, transparent, #C9A227, transparent);
      animation: sweep 2.5s ease-in-out infinite;
    }
    @keyframes sweep {
      0%   { transform: translateX(-200%); }
      100% { transform: translateX(500%); }
    }

    /* Footer */
    .footer {
      font-size: .72rem;
      color: rgba(255,255,255,.2);
      margin-top: 8px;
    }

    /* Responsif mobile kecil */
    @media (max-height: 600px) {
      .icon-wrap { width: 64px; height: 64px; border-radius: 20px; margin-bottom: 18px; }
      .arabic-title { font-size: 1.4rem; margin-bottom: 4px; }
      .site-name { margin-bottom: 18px; }
      .divider { margin-bottom: 18px; }
      .main-desc { margin-bottom: 18px; }
    }
  </style>
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TTMPGH6R"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
  <div class="bg-radial"></div>
  <div class="stars"></div>

  <div class="card">

    <!-- Icon -->
    <div class="icon-wrap">
      <svg width="44" height="44" viewBox="0 0 44 44" fill="none">
        <!-- Outer gear -->
        <g class="gear">
          <path fill="rgba(201,162,39,.8)"
            d="M22 14a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm0 13a5 5 0 1 1 0-10 5 5 0 0 1 0 10Z"/>
          <path fill="rgba(201,162,39,.5)"
            d="M39 20.5h-2.1a15.3 15.3 0 0 0-1.2-3l1.5-1.5-3.2-3.2-1.5 1.5a15.3 15.3 0 0 0-3-1.2V11h-4.5v2.1a15.3 15.3 0 0 0-3 1.2l-1.5-1.5-3.2 3.2 1.5 1.5a15.3 15.3 0 0 0-1.2 3H5v4.5h2.1a15.3 15.3 0 0 0 1.2 3l-1.5 1.5 3.2 3.2 1.5-1.5a15.3 15.3 0 0 0 3 1.2V33h4.5v-2.1a15.3 15.3 0 0 0 3-1.2l1.5 1.5 3.2-3.2-1.5-1.5a15.3 15.3 0 0 0 1.2-3H39V20.5Z"/>
        </g>
      </svg>
    </div>

    <!-- Logo teks -->
    <div class="arabic-title">المكتبة السنية</div>
    <div class="site-name">Al-Maktabah As-Sunniyyah</div>

    <div class="divider"></div>

    <!-- Badge -->
    <div class="badge">
      <span class="badge-dot"></span>
      Maintenance
    </div>

    <!-- Judul -->
    <div class="main-title"><?= htmlspecialchars($maintenanceTitle) ?></div>

    <!-- Deskripsi -->
    <p class="main-desc"><?= htmlspecialchars($maintenanceMessage) ?></p>

    <?php if (!empty($maintenanceEta)): ?>
    <!-- ETA -->
    <div class="eta-box">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Estimasi selesai: <span><?= htmlspecialchars($maintenanceEta) ?></span>
    </div>
    <?php else: ?>
    <div style="margin-bottom:28px"></div>
    <?php endif; ?>

    <!-- Progress animasi -->
    <div class="progress-track">
      <div class="progress-fill"></div>
    </div>

    <div class="footer">
      © <?= date('Y') ?> Al-Maktabah As-Sunniyyah &mdash; maktabah.quizb.my.id
    </div>

  </div>
</body>
</html>
<?php
  exit();
endif;
// ── Akhir blok maintenance ──────────────────────────────────

// ── Setup SEO Meta Tags ─────────────────────────────────────
$seoTitle = 'المكتبة السنية — Al-Maktabah As-Sunniyyah';
$seoDesc = 'المكتبة السنية — perpustakaan digital Islam memuat ribuan kitab salaf.';
$seoUrl = 'https://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
$seoImage = 'https://' . $_SERVER['HTTP_HOST'] . '/favicon.png';
$seoType = 'website';
$schemaJson = null;

// Deteksi jika user mengakses /kitab?id=...
$reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($reqPath === '/kitab' && isset($_GET['id'])) {
    $bookId = (int)$_GET['id'];
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT bkname, author, betaka FROM books WHERE bkid = :id LIMIT 1");
        $stmt->execute(['id' => $bookId]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($book) {
            $seoTitle = $book['bkname'] . ' - Al-Maktabah As-Sunniyyah';
            $seoDesc = "Membaca dan mengunduh kitab " . $book['bkname'] . " karya " . $book['author'] . ". " . mb_substr(strip_tags($book['betaka']), 0, 150) . "...";
            $seoUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/kitab?id=' . $bookId;
            $seoType = 'book';

            // Schema.org untuk Kitab
            $schemaArr = [
                "@context" => "https://schema.org",
                "@type" => "Book",
                "name" => $book['bkname'],
                "author" => [
                    "@type" => "Person",
                    "name" => $book['author']
                ],
                "url" => $seoUrl
            ];
            $schemaJson = json_encode($schemaArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } catch (Exception $e) {
        // Abaikan jika error, fallback ke meta default
    }
} else {
    // Schema.org untuk Beranda
    $schemaArr = [
        "@context" => "https://schema.org",
        "@type" => "WebSite",
        "name" => "Al-Maktabah As-Sunniyyah",
        "url" => "https://" . $_SERVER['HTTP_HOST'] . "/",
        "description" => "Perpustakaan digital Islam memuat ribuan kitab salaf."
    ];
    $schemaJson = json_encode($schemaArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Versi file JS untuk cache busting
$jsMainVer  = @filemtime(__DIR__ . '/js/main.js')      ?: '1';
$jsAdminVer = @filemtime(__DIR__ . '/js/admin_main.js') ?: '1';
// ────────────────────────────────────────────────────────────
?>

<!DOCTYPE html>
<html lang="id" dir="ltr">
<head>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-TTMPGH6R');</script>
<!-- End Google Tag Manager -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= htmlspecialchars($seoDesc) ?>" />
  <meta name="csrf-token" content="<?= htmlspecialchars(\App\Helpers\CsrfHelper::getToken()) ?>" />
  <meta name="google-site-verification" content="Mf1jf_wj_XAyYcuKEMWcKVjPTy8hWToL3lUYzQA6_Kc" />
  <link rel="icon" type="image/x-icon" href="/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="/favicon.png" />
  <link rel="canonical" href="<?= htmlspecialchars($seoUrl) ?>" />
  
  <title><?= htmlspecialchars($seoTitle) ?></title>

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="<?= htmlspecialchars($seoType) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($seoUrl) ?>" />
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($seoDesc) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($seoImage) ?>" />

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image" />
  <meta property="twitter:url" content="<?= htmlspecialchars($seoUrl) ?>" />
  <meta property="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>" />
  <meta property="twitter:description" content="<?= htmlspecialchars($seoDesc) ?>" />
  <meta property="twitter:image" content="<?= htmlspecialchars($seoImage) ?>" />

  <?php if ($schemaJson): ?>
  <!-- Structured Data -->
  <script type="application/ld+json">
<?= $schemaJson ?>
  </script>
  <?php endif; ?>

  <link rel="modulepreload" href="/js/main.js?v=<?= $jsMainVer ?>" />
  <link rel="modulepreload" href="/js/core/core.js" />
  <link rel="modulepreload" href="/js/views/home.js" />

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary:  { DEFAULT: '#166534', light: '#15803D', dark: '#14532D' },
            gold:     { DEFAULT: '#C9A227', light: '#F4E7B2', dark: '#A1821F' },
            cream:    { DEFAULT: '#F8FAF9', dark: '#F0FDF4' },
            ink:      { DEFAULT: '#1F2937' },
          },
          fontFamily: {
            latin:  ['Lato', 'sans-serif'],
            arabic: ['"Amiri"', '"Noto Naskh Arabic"', 'serif'],
          },
          boxShadow: {
            card: '0 2px 16px 0 rgba(22,101,52,.08)',
            'card-hover': '0 8px 32px 0 rgba(22,101,52,.16)',
          },
        }
      }
    }
  </script>

  <!-- Google Fonts (Optimized Async Loading) -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Noto+Naskh+Arabic:wght@400;600;700&family=Cairo:wght@400;600;700&family=Tajawal:wght@400;500;700&family=Scheherazade+New:wght@400;700&family=Reem+Kufi:wght@400;600&family=Lateef:wght@400;700&family=Aref+Ruqaa&family=El+Messiri:wght@400;600;700&family=IBM+Plex+Sans+Arabic:wght@300;400;600&family=Lato:wght@300;400;700;900&family=Inter:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Raleway:wght@300;400;600;700&family=Merriweather:wght@300;400;700&family=Playfair+Display:wght@400;600;700&family=Source+Sans+3:wght@300;400;600;700&display=swap" />
  <link rel="stylesheet" media="print" onload="this.media='all'" href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Noto+Naskh+Arabic:wght@400;600;700&family=Cairo:wght@400;600;700&family=Tajawal:wght@400;500;700&family=Scheherazade+New:wght@400;700&family=Reem+Kufi:wght@400;600&family=Lateef:wght@400;700&family=Aref+Ruqaa&family=El+Messiri:wght@400;600;700&family=IBM+Plex+Sans+Arabic:wght@300;400;600&family=Lato:wght@300;400;700;900&family=Inter:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Raleway:wght@300;400;600;700&family=Merriweather:wght@300;400;700&family=Playfair+Display:wght@400;600;700&family=Source+Sans+3:wght@300;400;600;700&display=swap" />
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Noto+Naskh+Arabic:wght@400;600;700&family=Cairo:wght@400;600;700&family=Tajawal:wght@400;500;700&family=Scheherazade+New:wght@400;700&family=Reem+Kufi:wght@400;600&family=Lateef:wght@400;700&family=Aref+Ruqaa&family=El+Messiri:wght@400;600;700&family=IBM+Plex+Sans+Arabic:wght@300;400;600&family=Lato:wght@300;400;700;900&family=Inter:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Raleway:wght@300;400;600;700&family=Merriweather:wght@300;400;700&family=Playfair+Display:wght@400;600;700&family=Source+Sans+3:wght@300;400;600;700&display=swap" />
  </noscript>

  <!-- Lucide Icons (CDN) -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>

  <style>
        :root {
      --islamic-primary: #166534;
      --islamic-primary-dark: #14532D;
      --islamic-secondary: #15803D;
      --islamic-accent: #22C55E;
      --islamic-soft: #DCFCE7;
      --islamic-background: #F8FAF9;
      --islamic-background-secondary: #F0FDF4;
      --islamic-gold: #C9A227;
      --islamic-gold-soft: #F4E7B2;
      --text-primary: #1F2937;
      --text-secondary: #4B5563;
      --border-soft: #BBF7D0;
      --white: #FFFFFF;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { font-family: 'Lato', sans-serif; background: var(--islamic-background); color: var(--text-primary); }

    .arabic { font-family: 'Amiri', 'Noto Naskh Arabic', serif; direction: rtl; unicode-bidi: plaintext; }

    /* Navbar scroll shadow */
    #navbar { transition: box-shadow .25s ease, background .25s ease; }
    #navbar.scrolled { box-shadow: 0 2px 20px rgba(22,101,52,.12); background: rgba(248,250,249,.97); backdrop-filter: blur(10px); }

    /* SPA page transitions */
    #app-content { animation: fadeIn .3s ease; text-align: justify; text-justify: inter-word; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

    /* Card hover */
    .book-card { transition: transform .2s ease, box-shadow .2s ease; }
    .book-card:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(22,101,52,.16); }

    /* ── Download format badge (ZIP / DOCX) ─────────────────────
       Shown next to the download icon to indicate output format.
       .dl-fmt-zip  → amber/gold for multi-juz ZIP archives
       .dl-fmt-docx → green/primary for single DOCX files       */
    .dl-fmt-badge {
      display: inline-flex;
      align-items: center;
      font-size: 9px;
      font-weight: 800;
      letter-spacing: .06em;
      padding: 2px 5px;
      border-radius: 4px;
      border: 1px solid transparent;
      line-height: 1.2;
      vertical-align: middle;
      white-space: nowrap;
      pointer-events: none;
    }
    .dl-fmt-zip  { background: rgba(162,123,92,.14); color: #835d40; border-color: rgba(162,123,92,.35); }
    .dl-fmt-docx { background: rgba(22,101,52,.09);  color: #1F2937; border-color: rgba(22,101,52,.20); }
    html.dark .dl-fmt-zip  { background: rgba(162,123,92,.18); color: #a27b5c; border-color: rgba(162,123,92,.35); }
    html.dark .dl-fmt-docx { background: rgba(92,109,110,.10); color: #5c6d6e; border-color: rgba(92,109,110,.25); }

    /* Hero Premium Green Gradient */
    .hero-bg {
      background:
        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(201,162,39,0.15) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 100%, rgba(22,101,52,0.4) 0%, transparent 60%),
        linear-gradient(145deg, #06402b 0%, #14532D 45%, #0f3d20 100%);
      position: relative;
      overflow: hidden;
    }
    .hero-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a227' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      z-index: 0;
      pointer-events: none;
    }

    /* Gold accent line */
    .gold-line { background: linear-gradient(90deg, transparent, #a27b5c, transparent); height: 1px; }

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
    ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #f0ece0; } ::-webkit-scrollbar-thumb { background: #C9A227; border-radius: 3px; }

    /* Admin nav scrollbar hidden */
    .no-scrollbar { scrollbar-width: none; -ms-overflow-style: none; }
    .no-scrollbar::-webkit-scrollbar { display: none; }

    /* Active desktop nav link */
    .nav-link.active { color: #C9A227 !important; }
    .nav-link.active::after { width: 100%; }
    .nav-link::after { content:''; display:block; height:2px; background:#C9A227; border-radius:1px; width:0; transition:width .25s ease; }
    .nav-link:hover::after { width:100%; }

    /* Kategori dropdown button — same visual as nav-link */
    .nav-cat-btn {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: .875rem; font-weight: 500;
      color: rgba(20,83,45,.8); cursor: pointer;
      background: none; border: none; padding-bottom: 4px;
      position: relative; transition: color .2s;
    }
    .nav-cat-btn::after { content:''; display:block; height:2px; background:#C9A227; border-radius:1px; width:0; transition:width .25s ease; position:absolute; bottom:0; left:0; right:0; }
    .nav-cat-btn:hover { color: #166534; }
    .nav-cat-btn:hover::after { width: 100%; }
    .nav-cat-btn.active { color: #C9A227 !important; }
    .nav-cat-btn.active::after { width: 100%; }

    /* Mega dropdown */
    #cat-mega-dropdown {
      position: fixed;
      top: 64px; left: 0; right: 0;
      z-index: 48;
      background: #F8FAF9;
      border-top: 1px solid rgba(201,162,39,.18);
      border-bottom: 1px solid rgba(201,162,39,.12);
      box-shadow: 0 8px 40px rgba(20,83,45,.12);
      opacity: 0; pointer-events: none;
      transform: translateY(-8px);
      transition: opacity .22s ease, transform .22s cubic-bezier(.22,.61,.36,1);
      max-height: 70vh; overflow-y: auto;
    }
    #cat-mega-dropdown.open {
      opacity: 1; pointer-events: all; transform: translateY(0);
    }
    html.dark #cat-mega-dropdown {
      background: #0b1510 !important;
      border-color: rgba(201,162,39,.12) !important;
    }
    .cat-mega-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 9px 14px;
      border-radius: 12px;
      cursor: pointer;
      transition: background .15s, transform .12s;
      text-decoration: none;
      color: rgba(20,83,45,.85);
      font-size: 13px;
      font-weight: 500;
    }
    .cat-mega-item:hover {
      background: rgba(201,162,39,.10);
      transform: translateX(3px);
      color: #166534;
    }
    html.dark .cat-mega-item { color: rgba(212,197,160,.85); }
    html.dark .cat-mega-item:hover { background: rgba(201,162,39,.12); color: #C9A227; }

    /* Bottom mobile nav */
    #bottom-nav {
      box-shadow: 0 -4px 24px rgba(20,83,45,.08);
      padding-bottom: env(safe-area-inset-bottom);
      background: rgba(255,255,255,0.98);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    html.dark #bottom-nav {
      background: rgba(15, 34, 24, 0.96) !important;
      border-top-color: rgba(201,162,39,.15) !important;
      box-shadow: 0 -4px 24px rgba(0,0,0,.4);
    }
    .reader-hide-menu {
      display: none !important;
    }
    @media (max-width: 767px) {
      #navbar,
      #search-bar,
      #font-modal-overlay,
      footer {
        display: none !important;
      }
      body {
        min-height: 100vh;
      }
      main#app-content {
        padding-top: 0 !important;
        padding-bottom: 5rem !important;
      }
      #app-content {
        min-height: 100vh;
      }
    }
    @media (min-width: 768px) {
      /* Keep top navbar visible on desktop even when reader mode is active */
      #navbar.reader-hide-menu { display: block !important; }
    }
    
    /* Modern Stylish Bottom Nav Items */
    .bnav-item {
      color: #728c7c; /* Visible muted green */
      transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
      -webkit-tap-highlight-color: transparent;
      padding-top: 4px;
    }
    html.dark .bnav-item {
      color: #8da396; /* Visible muted light green for dark mode */
    }

    .bnav-item i {
      transition: transform .3s cubic-bezier(0.34, 1.56, 0.64, 1), color .2s ease;
    }
    
    .bnav-item.active {
      color: #C9A227;
    }
    html.dark .bnav-item.active {
      color: #C9A227;
    }

    .bnav-item.active i {
      transform: translateY(-3px) scale(1.1);
      color: #C9A227;
      filter: drop-shadow(0 2px 4px rgba(201,162,39,0.3));
    }
    
    .bnav-item:active i { 
      transform: scale(0.9); 
    }

    /* ── Search Premium ─────────────────────────────────────── */

    /* Search input glow */
    .search-input-premium {
      transition: box-shadow .25s ease, border-color .25s ease;
    }
    .search-input-premium:focus {
      box-shadow: 0 0 0 3px rgba(201,162,39,.18), 0 4px 24px rgba(20,83,45,.10);
    }

    /* Section entrance animation */
    @keyframes sectionSlideUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: none; }
    }
    .search-section-enter {
      animation: sectionSlideUp .35s cubic-bezier(.22,.61,.36,1) both;
    }

    /* Card stagger */
    @keyframes cardPop {
      from { opacity: 0; transform: translateY(12px) scale(.97); }
      to   { opacity: 1; transform: none; }
    }
    .search-card-stagger {
      animation: cardPop .3s cubic-bezier(.22,.61,.36,1) both;
    }

    /* Highlight match */
    mark.hl {
      background: linear-gradient(135deg, rgba(252,233,189,.72) 0%, rgba(201,162,39,.35) 100%);
      color: #166534;
      border-radius: 4px;
      padding: 0 3px;
      font-weight: 700;
      box-shadow: inset 0 0 0 1px rgba(201,162,39,.16);
    }
    .reader-text mark.hl {
      background: rgba(249,214,150,.35);
      color: #166534;
      padding: 0 2px;
      border-radius: 3px;
      font-weight: 700;
    }

    /* Section badge pill */
    .sec-badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 10px;
      border-radius: 999px;
      background: rgba(201,162,39,.12);
      color: #a07828;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .02em;
      transition: background .2s;
    }

    /* Section header bar */
    .sec-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 1.1rem;
      padding-bottom: .7rem;
      border-bottom: 1px solid rgba(201,162,39,.15);
    }

    /* Loading spinner ring (search) */
    .spin-ring {
      width: 18px; height: 18px;
      border: 2px solid rgba(201,162,39,.25);
      border-top-color: #C9A227;
      border-radius: 50%;
      animation: spin .7s linear infinite;
      display: inline-block;
      vertical-align: middle;
      flex-shrink: 0;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Category chip hover */
    .cat-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 999px;
      border: 1.5px solid rgba(201,162,39,.30);
      background: #fff;
      color: rgba(20,83,45,.8);
      font-size: 13px;
      cursor: pointer;
      transition: background .18s, color .18s, border-color .18s, transform .15s, box-shadow .18s;
      animation: cardPop .28s cubic-bezier(.22,.61,.36,1) both;
    }
    .cat-chip:hover {
      background: #166534;
      color: #fff;
      border-color: #166534;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(20,83,45,.18);
    }

    /* Content snippet card */
    .snippet-bar {
      border-left: 3px solid rgba(201,162,39,.60);
      background: rgba(255,244,223,.85);
      padding: 12px 14px 10px;
      margin-top: 8px;
      font-size: 12px;
      line-height: 1.75;
      color: rgba(20,83,45,.78);
      border-radius: 16px;
      box-shadow: inset 0 1px 1px rgba(255,255,255,.75);
    }

    /* Search stats bar */
    .search-stats {
      font-size: 12px;
      color: rgba(20,83,45,.4);
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      padding: 6px 0 14px;
    }

    /* ── Reader Font Settings ─────────────────────────────────── */
    :root {
      --font-r-latin:  'Lato', sans-serif;
      --font-r-arabic: 'Amiri', 'Noto Naskh Arabic', serif;
      --font-r-size:   18px;
    }

    /* Reader text uses CSS vars + auto-direction per paragraph */
    .reader-text {
      font-size: var(--font-r-size) !important;
      direction: auto;
      unicode-bidi: plaintext;
    }
    /* Arabic paragraphs (first strong char = RTL) → use Arabic font */
    .reader-text { font-family: var(--font-r-arabic); }
    /* Inline spans for detected Latin blocks */
    .reader-latin { font-family: var(--font-r-latin); direction: ltr; unicode-bidi: embed; }

    /* Font settings slide panel */
    .font-panel {
      overflow: hidden;
      max-height: 0;
      opacity: 0;
      transition: max-height .35s cubic-bezier(.22,.61,.36,1), opacity .25s ease;
    }
    .font-panel.open { max-height: 500px; opacity: 1; }

    /* Font chip button */
    .font-chip {
      padding: 5px 10px;
      border-radius: 8px;
      border: 1.5px solid rgba(201,162,39,.2);
      background: #fff;
      font-size: 11px;
      cursor: pointer;
      transition: all .15s ease;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: left;
    }
    .font-chip:hover { border-color: rgba(201,162,39,.5); background: rgba(201,162,39,.06); }
    .font-chip.active { border-color: #C9A227; background: rgba(201,162,39,.14); color: #166534; font-weight: 700; }
    .font-chip.ar { text-align: right; direction: rtl; }

    /* Font size range */
    input[type=range].font-range { accent-color: #C9A227; width: 100%; cursor: pointer; }
    /* ══════════════════════════════════════════════════════
       DARK MODE
    ══════════════════════════════════════════════════════ */
    html.dark body {
      background: #0e1a12 !important;
      color: #d4c5a0 !important;
    }
    /* Navbar & bottom nav */
    html.dark #navbar,
    html.dark nav { background: #0b1510 !important; border-color: rgba(201,162,39,.1) !important; }
    html.dark .bnav-wrap { background: #0b1510 !important; border-color: rgba(201,162,39,.1) !important; }

    /* Cards & surfaces */
    html.dark .bg-white  { background: #162419 !important; }
    html.dark .bg-cream  { background: #0e1a12 !important; }
    html.dark [class*="bg-cream"] { background: #162419 !important; }
    html.dark .bg-surface { background: #162419 !important; }
    html.dark .bg-background { background: #0e1a12 !important; }

    /* Borders */
    html.dark .border-cream-dark,
    html.dark [class*="border-cream"] { border-color: rgba(201,162,39,.12) !important; }
    html.dark .border-border { border-color: rgba(201,162,39,.12) !important; }
    html.dark [class*="border-gold"] { border-color: rgba(201,162,39,.2) !important; }

    /* Text */
    html.dark .text-primary   { color: #d4c5a0 !important; }
    html.dark .text-secondary { color: #a09070 !important; }
    html.dark .text-muted     { color: #7a8e78 !important; }
    html.dark [class*="text-primary/"] { color: rgba(212,197,160,.65) !important; }

    /* Shadows */
    html.dark .shadow-card { box-shadow: 0 2px 20px rgba(0,0,0,.5) !important; }
    html.dark [class*="shadow"] { box-shadow: 0 2px 16px rgba(0,0,0,.4) !important; }

    /* Inputs & selects */
    html.dark input, html.dark textarea, html.dark select {
      background: #0e1a12 !important;
      color: #d4c5a0 !important;
      border-color: rgba(201,162,39,.25) !important;
    }
    html.dark input::placeholder, html.dark textarea::placeholder {
      color: rgba(212,197,160,.35) !important;
    }
    html.dark .book-card { background: #162419 !important; }

    /* Footer */
    html.dark footer { background: #060e08 !important; }

    /* Font chip dark mode */
    html.dark .font-chip { background: #0e1a12 !important; color: #d4c5a0 !important; border-color: rgba(201,162,39,.2) !important; }
    html.dark .font-chip.active { background: rgba(201,162,39,.18) !important; color: #C9A227 !important; }

    .sdw-card {
      background: #fff;
      border: 1px solid rgba(201,162,39,.18);
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 12px;
    }
    .sdw-label {
      font-size: 10px; font-weight: 700;
      letter-spacing: .08em; text-transform: uppercase;
      color: rgba(20,83,45,.45);
      display: flex; align-items: center; gap: 6px;
      margin-bottom: 12px;
    }
    /* Theme toggle pill */
    .theme-pill {
      display: flex; background: rgba(20,83,45,.07);
      border-radius: 12px; padding: 4px; gap: 4px;
    }
    .theme-btn {
      flex: 1; padding: 8px 10px; border-radius: 9px;
      border: none; cursor: pointer; font-size: 12px; font-weight: 600;
      display: flex; align-items: center; justify-content: center; gap: 6px;
      transition: all .2s ease; color: rgba(20,83,45,.5); background: transparent;
    }
    .theme-btn.active {
      background: #fff; color: #166534;
      box-shadow: 0 1px 6px rgba(0,0,0,.12);
    }
    html.dark .theme-pill { background: rgba(212,197,160,.08); }
    html.dark .theme-btn.active { background: #1c2f1e; color: #d4c5a0; }
    html.dark .sdw-label { color: rgba(212,197,160,.45); }
  </style>
</head>

<body class="min-h-screen flex flex-col">
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TTMPGH6R"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

  <!-- ===================== NAVBAR ===================== -->
  <nav id="navbar" class="fixed top-0 left-0 right-0 z-50 bg-cream">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">

        <!-- Logo -->
        <a href="/" data-route="/" class="flex items-center gap-3 group">
          <img src="/favicon.png" alt="Al-Maktabah As-Sunniyyah Logo" class="w-9 h-9 rounded-lg shadow-md group-hover:shadow-lg transition-shadow" width="36" height="36" />
          <div class="leading-tight">
            <div class="arabic text-primary font-bold text-base leading-none">المكتبة السنية</div>
            <div class="text-xs text-primary/60 font-light tracking-wide">Al-Maktabah As-Sunniyyah</div>
          </div>
        </a>

        <!-- Desktop Nav -->
        <div class="hidden md:flex items-center gap-6">
          <a href="/" data-route="/"          class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Beranda</a>
          <!-- Kategori dropdown trigger -->
          <div class="relative" id="nav-cat-wrap">
            <button id="nav-cat-btn" class="nav-cat-btn" aria-haspopup="true" aria-expanded="false">
              Kategori
              <i data-lucide="chevron-down" id="nav-cat-chevron" class="w-3.5 h-3.5 transition-transform duration-200"></i>
            </button>
          </div>
          <a href="/katalog" data-route="/katalog" class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Katalog</a>
          <a href="/favorit" data-route="/favorit" class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Favorit</a>
          <a href="/search-advanced" data-route="/search-advanced" class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Cari Lanjutan</a>
          <a href="/ask" data-route="/ask" class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1 flex items-center gap-1"><i data-lucide="sparkles" class="w-3.5 h-3.5 text-gold"></i> Tanya</a>
          <a href="/about"   data-route="/about"   class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Tentang</a>
          <a href="/feedback" data-route="/feedback" class="nav-link text-sm font-medium text-primary/80 hover:text-primary transition-colors pb-1">Feedback</a>
        </div>

        <!-- Search + Settings + Auth (desktop) -->
        <div class="flex items-center gap-2">
          <button id="nav-search-btn" class="p-2 rounded-lg hover:bg-cream-dark transition-colors" title="Cari Kitab" aria-label="Cari Kitab">
            <i data-lucide="search" class="w-5 h-5 text-primary"></i>
          </button>
          <button id="nav-theme-btn" onclick="window.setTheme(document.documentElement.classList.contains('dark')?'light':'dark')"
            class="p-2 rounded-lg hover:bg-cream-dark transition-colors" title="Ganti Tema" aria-label="Ganti Tema">
            <i id="nav-theme-icon" data-lucide="sun" class="w-5 h-5 text-primary"></i>
          </button>

          <?php if ($sessionUser): ?>
            <!-- User menu (logged in) -->
            <div class="relative" id="user-menu-wrap">
              <button id="user-menu-btn" aria-label="Menu Pengguna" class="flex items-center gap-2 px-3 py-1.5 rounded-xl hover:bg-cream-dark transition-colors">
                <?php if (!empty($sessionUser['picture'])): ?>
                  <img src="<?= htmlspecialchars($sessionUser['picture']) ?>" alt="Avatar"
                       class="w-7 h-7 rounded-full object-cover border-2 border-gold/40" width="28" height="28" />
                <?php else: ?>
                  <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold">
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($sessionUser['name'], 0, 1))) ?>
                  </div>
                <?php endif; ?>
                <span class="text-sm font-medium text-primary max-w-[100px] truncate hidden sm:block">
                  <?= htmlspecialchars($sessionUser['name']) ?>
                </span>
                <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-primary/50"></i>
              </button>

              <!-- Dropdown -->
              <div id="user-dropdown" class="hidden absolute right-0 top-full mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gold/15 py-2 z-50">
                <div class="px-4 py-2 border-b border-cream-dark">
                  <div class="text-sm font-semibold text-primary truncate"><?= htmlspecialchars($sessionUser['name']) ?></div>
                  <div class="text-xs text-primary/50 truncate"><?= htmlspecialchars($sessionUser['email']) ?></div>
                  <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                    <?= $sessionUser['role'] === 'admin' ? 'bg-gold/20 text-gold-dark' : 'bg-primary/8 text-primary/60' ?>">
                    <?= $sessionUser['role'] === 'admin' ? '👑 Admin' : 'User' ?>
                  </span>
                </div>
                <a href="/dashboard" data-route="/dashboard"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="layout-dashboard" class="w-4 h-4 text-primary/50"></i> Dashboard
                </a>
                <?php if ($sessionUser['role'] === 'admin'): ?>
                <div class="border-t border-cream-dark/60 mx-3 my-1"></div>
                <div class="px-4 py-1.5">
                  <span class="text-[10px] font-bold uppercase tracking-widest text-gold/70">Panel Admin</span>
                </div>
                <a href="/admin/books" data-route="/admin/books"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="book" class="w-4 h-4 text-gold/60"></i> Kitab
                </a>
                <a href="/admin/categories" data-route="/admin/categories"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="folder" class="w-4 h-4 text-gold/60"></i> Kategori
                </a>
                <a href="/admin/history" data-route="/admin/history"
                   class="hidden md:flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="history" class="w-4 h-4 text-gold/60"></i> CRUD History
                </a>
                <a href="/admin/activity" data-route="/admin/activity"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="activity" class="w-4 h-4 text-gold/60"></i> Aktivitas
                </a>
                <a href="/admin/search-logs" data-route="/admin/search-logs"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="search" class="w-4 h-4 text-gold/60"></i> Pencarian
                </a>
                <a href="/admin/download-logs" data-route="/admin/download-logs"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="download" class="w-4 h-4 text-gold/60"></i> Download
                </a>
                <a href="/admin/submissions" data-route="/admin/submissions"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="inbox" class="w-4 h-4 text-gold/60"></i> Review Kiriman
                </a>
                <a href="/admin/requests" data-route="/admin/requests"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="help-circle" class="w-4 h-4 text-gold/60"></i> Request Kitab
                </a>
                <a href="/admin/feedback" data-route="/admin/feedback"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-primary hover:bg-cream-dark transition-colors">
                  <i data-lucide="message-square" class="w-4 h-4 text-gold/60"></i> Review Feedback
                </a>
                <?php endif; ?>
                <div class="border-t border-cream-dark mt-1"></div>
                <a href="/auth.php?action=logout"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors">
                  <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
                </a>
              </div>
            </div>
          <?php else: ?>
            <!-- Login button (not logged in) -->
            <a href="/auth.php?action=login"
               class="hidden md:inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-light transition-colors shadow-sm">
              <i data-lucide="log-in" class="w-4 h-4"></i>
              <span>Masuk</span>
            </a>
          <?php endif; ?>
        </div>

      </div>
    </div>

    <!-- Global Search Bar (slide-down) -->
    <div id="search-bar" class="hidden bg-cream border-t border-gold/20 px-4 py-3">
      <div class="max-w-2xl mx-auto relative">
        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-primary/40"></i>
        <input id="global-search-input" type="text" placeholder="Cari judul kitab atau pengarang…"
          class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gold/30 bg-white focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
        <button id="search-bar-close" class="absolute right-3 top-1/2 -translate-y-1/2" aria-label="Tutup pencarian">
          <i data-lucide="x" class="w-4 h-4 text-primary/40 hover:text-primary"></i>
        </button>
      </div>
    </div>
  </nav>

  <!-- ===================== KATEGORI MEGA-DROPDOWN ===================== -->
  <div id="cat-mega-dropdown" aria-label="Pilih Kategori">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
      <!-- Header row -->
      <div class="flex items-center justify-between mb-3">
        <span style="font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:rgba(20,83,45,.4);">
          Pilih Kategori
        </span>
        <a href="/kategori" data-route="/kategori"
           style="font-size:12px;color:#806315;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;"
           onclick="closeCatDropdown()">
          Lihat semua <i data-lucide="arrow-right" style="width:12px;height:12px;"></i>
        </a>
      </div>
      <!-- Grid populated by JS -->
      <div id="cat-mega-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-1">
        <!-- skeleton -->
        <div class="skeleton h-8 rounded-lg"></div>
        <div class="skeleton h-8 rounded-lg"></div>
        <div class="skeleton h-8 rounded-lg"></div>
        <div class="skeleton h-8 rounded-lg"></div>
        <div class="skeleton h-8 rounded-lg"></div>
      </div>
    </div>
  </div>

  <!-- ===================== MAIN SPA CONTAINER ===================== -->
  <main id="app-content" class="flex-1 pt-16 pb-20 md:pb-0">
    <!-- JS renders pages here -->
    <div id="page-loader" class="flex items-center justify-center min-h-[60vh]">
      <div class="flex flex-col items-center gap-3">
        <div class="w-10 h-10 border-3 border-gold/30 border-t-gold rounded-full animate-spin" style="border-width:3px"></div>
        <span class="text-primary/50 text-sm arabic">جارٍ التحميل…</span>
      </div>
    </div>
  </main>

  <!-- ===================== BOTTOM NAV (mobile only) ===================== -->
  <nav id="bottom-nav" class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gold/20">
    <div class="flex items-stretch h-16">

      <a href="/" data-route="/" class="bnav-item flex-1 flex flex-col items-center justify-center gap-0.5 no-underline">
        <i data-lucide="home" class="w-5 h-5"></i>
        <span class="text-[10px] font-medium">Beranda</span>
      </a>

      <a href="/search-advanced" data-route="/search-advanced" class="bnav-item flex-1 flex flex-col items-center justify-center gap-0.5 no-underline">
        <i data-lucide="search-check" class="w-5 h-5"></i>
        <span class="text-[10px] font-medium">Cari</span>
      </a>

      <a href="/ask" data-route="/ask" class="bnav-item flex-1 flex flex-col items-center justify-center gap-0.5 no-underline">
        <i data-lucide="bot" class="w-5 h-5 mb-0.5"></i>
        <span class="text-[10px] font-semibold text-gold">Tanya</span>
      </a>

      <!-- Kategori tab (menggantikan Katalog di mobile) -->
      <a href="/kategori" data-route="/kategori" class="bnav-item flex-1 flex flex-col items-center justify-center gap-0.5 no-underline">
        <i data-lucide="layout-grid" class="w-5 h-5"></i>
        <span class="text-[10px] font-medium tracking-wide">Kategori</span>
      </a>

      <a href="/favorit" data-route="/favorit" class="bnav-item flex-1 flex flex-col items-center justify-center gap-0.5 no-underline">
        <i data-lucide="bookmark" class="w-5 h-5 mb-0.5"></i>
        <span class="text-[10px] font-semibold">Favorit</span>
      </a>

      <a href="/settings" data-route="/settings" class="bnav-item flex-1 flex flex-col items-center justify-center gap-0.5 no-underline">
        <i data-lucide="settings-2" class="w-5 h-5"></i>
        <span class="text-[10px] font-medium">Setting</span>
      </a>

      <?php if ($sessionUser): ?>
      <a href="/dashboard" data-route="/dashboard" class="bnav-item flex-1 flex flex-col items-center justify-center gap-0.5 no-underline">
        <?php if (!empty($sessionUser['picture'])): ?>
          <img src="<?= htmlspecialchars($sessionUser['picture']) ?>" alt="Avatar"
               class="w-5 h-5 rounded-full object-cover border border-gold/40" width="20" height="20" />
        <?php else: ?>
          <i data-lucide="user-circle" class="w-5 h-5"></i>
        <?php endif; ?>
        <span class="text-[10px] font-medium">Akun</span>
      </a>
      <?php else: ?>
      <a href="/about" data-route="/about" class="bnav-item flex-1 flex flex-col items-center justify-center gap-0.5 no-underline">
        <i data-lucide="info" class="w-5 h-5"></i>
        <span class="text-[10px] font-medium">Tentang</span>
      </a>
      <?php endif; ?>

    </div>
  </nav>

  <!-- ===================== APK UPDATE MODAL ===================== -->
  <div id="apk-update-modal-overlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,34,24,.8);backdrop-filter:blur(4px);align-items:center;justify-content:center;opacity:0;transition:opacity 0.3s ease;">
    <div id="apk-update-modal" style="background:#F8FAF9;border-radius:1.5rem;padding:2rem 1.5rem;width:90%;max-width:400px;box-shadow:0 10px 40px rgba(0,0,0,.2);text-align:center;transform:translateY(20px);transition:transform 0.3s ease;">
      <div style="background:#C9A227;color:#fff;width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
        <i data-lucide="download" style="width:32px;height:32px;"></i>
      </div>
      <h3 style="font-family:'Amiri',serif;font-size:1.75rem;color:#14532D;margin-bottom:0.75rem;font-weight:700;">Update Tersedia!</h3>
      <p style="color:rgba(20,83,45,.75);font-size:0.95rem;line-height:1.6;margin-bottom:1.5rem;">
        Versi terbaru aplikasi المكتبة السنية (v1.0.9) telah dirilis.<br>Silakan update aplikasi Anda untuk pengalaman yang lebih baik.
      </p>
      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <a href="https://play.google.com/store/apps/details?id=com.maktabah.premium" target="_blank" onclick="closeApkUpdateModal()" style="background:#14532D;color:#fff;border:none;padding:0.75rem 2rem;border-radius:99px;font-weight:700;font-size:1rem;cursor:pointer;width:100%;box-shadow:0 4px 15px rgba(20,83,45,.2);text-decoration:none;display:inline-block;box-sizing:border-box;">
          Update Sekarang
        </a>
        <button onclick="closeApkUpdateModal()" style="background:transparent;color:#14532D;border:1px solid rgba(20,83,45,.3);padding:0.75rem 2rem;border-radius:99px;font-weight:700;font-size:1rem;cursor:pointer;width:100%;box-sizing:border-box;">
          Nanti Saja
        </button>
      </div>
    </div>
  </div>

  <!-- ===================== FONT SETTINGS MODAL ===================== -->
  <div id="font-modal-overlay" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(15,34,24,.55);backdrop-filter:blur(4px);" onclick="if(event.target===this)closeFontModal()">
    <div id="font-modal" style="position:absolute;bottom:0;left:0;right:0;max-height:90vh;overflow-y:auto;background:#F8FAF9;border-radius:1.5rem 1.5rem 0 0;padding:1.5rem 1.25rem 2rem;box-shadow:0 -8px 40px rgba(20,83,45,.18);">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;gap:8px;">
          <i data-lucide="settings-2" style="width:18px;height:18px;color:#C9A227;"></i>
          <span style="font-weight:700;font-size:15px;color:#166534;">Pengaturan Font Pembaca</span>
        </div>
        <button onclick="closeFontModal()" style="background:none;border:none;cursor:pointer;padding:4px;color:rgba(20,83,45,.4);">
          <i data-lucide="x" style="width:20px;height:20px;"></i>
        </button>
      </div>
      <div id="font-modal-body"><!-- filled by JS --></div>
    </div>
  </div>

  <!-- ===================== PLAYSTORE FLOATING BUTTON (Non-App Mobile) ===================== -->
  <a id="floating-playstore-btn" 
     href="https://play.google.com/store/apps/details?id=com.maktabah.premium" 
     target="_blank" 
     style="display:none; position:fixed; bottom:80px; right:20px; z-index:800; background:#14532D; color:#fff; padding:0 20px; height:50px; border-radius:99px; box-shadow:0 6px 20px rgba(20,83,45,.4); align-items:center; gap:8px; justify-content:center; text-decoration:none; font-weight:700; font-size:14px; transition:transform 0.2s;" 
     title="Download Aplikasi Maktabah">
     <i data-lucide="download" style="width:20px; height:20px;"></i>
     <span style="letter-spacing:0.3px;">Unduh Versi Android</span>
  </a>

  <!-- ===================== FOOTER ===================== -->
  <footer id="app-footer" class="bg-primary text-white mt-16 hidden md:block" style="display:none;">
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
            <li><a href="/feedback" data-route="/feedback" class="hover:text-gold transition-colors">Feedback</a></li>
            <li><a href="/privacy" data-route="/privacy" class="hover:text-gold transition-colors">Kebijakan Privasi</a></li>
          </ul>
        </div>

        <!-- Info -->
        <div>
          <h4 class="text-gold text-sm font-semibold uppercase tracking-wider mb-4">Info</h4>
          <ul class="space-y-2 text-sm text-white/70">
            <li class="flex items-center gap-2"><i data-lucide="globe" class="w-4 h-4 text-gold/60"></i> maktabah.quizb.my.id</li>
            <li class="flex items-center gap-2"><i data-lucide="book-open" class="w-4 h-4 text-gold/60"></i> Ribuan Kitab Tersedia</li>
          </ul>
        </div>

      </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-white/30">
      &copy; <?= date('Y') ?> المكتبة السنية &mdash; Semua hak dilindungi.
      &ensp;&middot;&ensp;
      <a href="/privacy" data-route="/privacy" class="hover:text-white/60 transition-colors">Kebijakan Privasi</a>
    </div>
  </footer>


  <!-- Settings drawer dihapus — digantikan halaman /settings di SPA -->

  <!-- ===================== SCRIPTS ===================== -->
  <script>
    // Inject session user dari PHP ke JS global
    window.SESSION_USER = <?= $sessionUser ? json_encode($sessionUser) : 'null' ?>;

    // CSRF token untuk semua AJAX POST request
    // Dibaca dari meta tag dan di-inject ke header X-CSRF-Token oleh core.js
    window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Init Lucide icons after DOM ready — re-called after each SPA render
    document.addEventListener('DOMContentLoaded', () => {
      lucide.createIcons();

      // ── GLOBAL SETTINGS (tema, font, ukuran) ──────────────────
      const FONTS_LAT = ['Lato','Inter','Roboto','Open Sans','Poppins','Nunito','Raleway','Merriweather','Playfair Display','Source Sans 3'];
      const FONTS_AR  = [
        {k:'Amiri',             l:'أميري — Amiri'},
        {k:'Noto Naskh Arabic', l:'نوتو نسخ'},
        {k:'Cairo',             l:'القاهرة — Cairo'},
        {k:'Tajawal',           l:'تجوّل — Tajawal'},
        {k:'Scheherazade New',  l:'شهرزاد'},
        {k:'Reem Kufi',         l:'ريم كوفي'},
        {k:'Lateef',            l:'لطيف — Lateef'},
        {k:'Aref Ruqaa',        l:'عارف رقعة'},
        {k:'El Messiri',        l:'المسيري'},
        {k:'IBM Plex Sans Arabic', l:'IBM عربي'},
      ];

      // Load saved settings
      let _settings = Object.assign(
        { theme: 'light', latin: 'Lato', arabic: 'Amiri', size: 18 },
        JSON.parse(localStorage.getItem('siteSettings') || '{}')
      );

      function _applySettings(save = true) {
        // Theme
        const isDark = _settings.theme === 'dark';
        document.documentElement.classList.toggle('dark', isDark);
        const lBtn = document.getElementById('sdw-theme-light');
        const dBtn = document.getElementById('sdw-theme-dark');
        if (lBtn) lBtn.classList.toggle('active', !isDark);
        if (dBtn) dBtn.classList.toggle('active', isDark);
        // Update navbar theme icon: sun = terang aktif, moon = gelap aktif
        const themeIcon = document.getElementById('nav-theme-icon');
        if (themeIcon) {
          themeIcon.setAttribute('data-lucide', isDark ? 'moon' : 'sun');
          lucide.createIcons({ nodes: [themeIcon] });
        }
        // Sync with app.js readerFontState if available
        if (window.readerFontState) {
          window.readerFontState.latin  = _settings.latin;
          window.readerFontState.arabic = _settings.arabic;
          window.readerFontState.size   = _settings.size;
          if (typeof window.applyReaderFont === 'function') window.applyReaderFont(false);
        } else {
          // Apply CSS vars directly (before app.js loads)
          const r = document.documentElement;
          r.style.setProperty('--font-r-latin',  `'${_settings.latin}', sans-serif`);
          r.style.setProperty('--font-r-arabic', `'${_settings.arabic}', 'Amiri', serif`);
          r.style.setProperty('--font-r-size',   _settings.size + 'px');
        }
        // Update slider label
        const lbl = document.getElementById('sdw-size-lbl');
        const sld = document.getElementById('sdw-size-slider');
        if (lbl) lbl.textContent = _settings.size + 'px';
        if (sld) sld.value = _settings.size;
        // Update chip actives
        document.querySelectorAll('#sdw-latin-chips .font-chip').forEach(c => {
          c.classList.toggle('active', c.dataset.key === _settings.latin);
        });
        document.querySelectorAll('#sdw-arabic-chips .font-chip').forEach(c => {
          c.classList.toggle('active', c.dataset.key === _settings.arabic);
        });
        if (save) localStorage.setItem('siteSettings', JSON.stringify(_settings));
        // Keep readerFonts in sync (used by reader page in app.js)
        localStorage.setItem('readerFonts', JSON.stringify({ latin: _settings.latin, arabic: _settings.arabic, size: _settings.size }));
      }

      function _buildChips() {
        const latEl = document.getElementById('sdw-latin-chips');
        const arEl  = document.getElementById('sdw-arabic-chips');
        if (!latEl || !arEl) return;
        latEl.innerHTML = FONTS_LAT.map(f => `
          <button class="font-chip ${_settings.latin === f ? 'active' : ''}"
            data-key="${f}" style="font-family:'${f}',sans-serif"
            onclick="window._sdwSetLatin('${f}')">${f}</button>`).join('');
        arEl.innerHTML = FONTS_AR.map(f => `
          <button class="font-chip ar ${_settings.arabic === f.k ? 'active' : ''}"
            data-key="${f.k}" style="font-family:'${f.k}','Amiri',serif"
            onclick="window._sdwSetArabic('${f.k}')">${f.l}</button>`).join('');
      }

      window.setTheme = function(t) {
        _settings.theme = t; _applySettings();
      };
      window._sdwSetLatin = function(k) {
        _settings.latin = k; _applySettings();
        if (typeof window._setLatinFont === 'function') window._setLatinFont(k);
      };
      window._sdwSetArabic = function(k) {
        _settings.arabic = k; _applySettings();
        if (typeof window._setArabicFont === 'function') window._setArabicFont(k);
      };
      window._sdwSetSize = function(n) {
        _settings.size = parseInt(n); _applySettings();
      };

      // openSettings: backward-compat APK lama — arahkan ke halaman /settings
      // (APK lama memanggil openSettings() dari tombol Setting di bottom nav)
      window.openSettings = function() {
        if (typeof window.navigate === 'function') {
          window.navigate('/settings');
        } else {
          // app.js belum load (sangat jarang) — fallback hard navigate
          window.location.href = '/settings';
        }
      };
      window.closeSettings = function() { /* no-op: drawer sudah dihapus */ };

      // Apply on page load (before app.js fully runs)
      _applySettings(false);

      // Keyboard: Escape closes kategori dropdown
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
          window.closeSettings();
          window.closeCatDropdown();
        }
      });

      // ── Kategori mega-dropdown ───────────────────────────────
      let _catDropOpen = false;
      let _catLoaded   = false;

      window.closeCatDropdown = function() {
        const dd  = document.getElementById('cat-mega-dropdown');
        const btn = document.getElementById('nav-cat-btn');
        const chv = document.getElementById('nav-cat-chevron');
        if (!dd) return;
        _catDropOpen = false;
        dd.classList.remove('open');
        if (btn) { btn.setAttribute('aria-expanded','false'); btn.classList.remove('active'); }
        if (chv) chv.style.transform = '';
      };

      window.openCatDropdown = function() {
        const dd  = document.getElementById('cat-mega-dropdown');
        const btn = document.getElementById('nav-cat-btn');
        const chv = document.getElementById('nav-cat-chevron');
        if (!dd) return;
        _catDropOpen = true;
        dd.classList.add('open');
        if (btn) { btn.setAttribute('aria-expanded','true'); btn.classList.add('active'); }
        if (chv) chv.style.transform = 'rotate(180deg)';

        if (!_catLoaded) {
          _catLoaded = true;
          fetch('/api.php?action=categories')
            .then(r => r.json())
            .then(res => {
              const grid = document.getElementById('cat-mega-grid');
              if (!grid) return;
              const cats = (res.data || []).filter(c => c.book_count > 0);
              if (!cats.length) {
                grid.innerHTML = '<p style="font-size:13px;color:rgba(20,83,45,.45);padding:8px 4px;">Belum ada kategori.</p>';
                return;
              }
              grid.innerHTML = cats.map(c => `
                <button class="cat-mega-item"
                  onclick="window.closeCatDropdown(); window.navigate && window.navigate('/kategori?cat=${c.id}')">
                  <span>${c.name}</span>
                  <span style="font-size:11px;color:#806315;font-weight:700;">${c.book_count}</span>
                </button>`).join('');
              if (window.lucide) lucide.createIcons();
            })
            .catch(() => {
              const grid = document.getElementById('cat-mega-grid');
              if (grid) grid.innerHTML = '<p style="font-size:13px;color:#c0392b;padding:8px 4px;">Gagal memuat kategori.</p>';
            });
        }
      };

      const _catBtn = document.getElementById('nav-cat-btn');
      if (_catBtn) {
        _catBtn.addEventListener('click', e => {
          e.stopPropagation();
          _catDropOpen ? window.closeCatDropdown() : window.openCatDropdown();
        });
      }

      // Close on outside click
      document.addEventListener('click', e => {
        if (!_catDropOpen) return;
        const dd   = document.getElementById('cat-mega-dropdown');
        const wrap = document.getElementById('nav-cat-wrap');
        if (dd && !dd.contains(e.target) && wrap && !wrap.contains(e.target)) {
          window.closeCatDropdown();
        }
      });



      // Toggle user dropdown (desktop)
      const menuBtn  = document.getElementById('user-menu-btn');
      const dropdown = document.getElementById('user-dropdown');
      if (menuBtn && dropdown) {
        menuBtn.addEventListener('click', e => {
          e.stopPropagation();
          dropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', e => {
          if (!menuBtn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
          }
        });
      }

      // Mobile menu toggle
      const mobileMenuBtn = document.getElementById('mobile-menu-btn');
      const mobileMenu    = document.getElementById('mobile-menu');
      if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', () => {
          mobileMenu.classList.toggle('hidden-menu');
          lucide.createIcons();
        });
      }

      // Check for APK update (only show once)
      setTimeout(() => {
        const ua = navigator.userAgent || '';
        const isPhpRequestedWithApp = <?= (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') ? 'true' : 'false' ?>;
        const isWebView = isPhpRequestedWithApp || /wv/.test(ua) || (/Android/i.test(ua) && /Version\//i.test(ua));
        
        // Cek apakah versi sudah 1.0.9 (Version Code 10)
        let uaVersionCode = 0;
        const uaMatch = ua.match(/MaktabahApp\/([0-9]+)/);
        if (uaMatch) {
            uaVersionCode = parseInt(uaMatch[1], 10);
        }
        const isNewApk = (window.APP_VERSION_CODE && window.APP_VERSION_CODE >= 10) || (uaVersionCode >= 10);

        if (isWebView && !isNewApk && !localStorage.getItem('apk_update_warned_v10_rev2')) {
          const overlay = document.getElementById('apk-update-modal-overlay');
          const modal = document.getElementById('apk-update-modal');
          if (overlay && modal) {
            overlay.style.display = 'flex';
            // Trigger reflow
            void overlay.offsetWidth;
            overlay.style.opacity = '1';
            modal.style.transform = 'translateY(0)';
            if (window.lucide) lucide.createIcons();
          }
        }

        // Tampilkan floating playstore icon jika diakses dari HP browser biasa
        if (!isWebView && window.innerWidth <= 768) {
          const fab = document.getElementById('floating-playstore-btn');
          if (fab) {
            fab.style.display = 'flex';
            if (window.lucide) lucide.createIcons();
          }
        }
      }, 2000);

      window.closeApkUpdateModal = function() {
        const overlay = document.getElementById('apk-update-modal-overlay');
        const modal = document.getElementById('apk-update-modal');
        if (overlay && modal) {
          overlay.style.opacity = '0';
          modal.style.transform = 'translateY(20px)';
          setTimeout(() => {
            overlay.style.display = 'none';
            localStorage.setItem('apk_update_warned_v10_rev2', '1');
          }, 300);
        }
      };

    }); // end DOMContentLoaded
  </script>

  <script type="module" src="/js/main.js?v=<?= $jsMainVer ?>"></script>
  <?php if (($sessionUser['role'] ?? '') === 'admin'): ?>
    <script type="module" src="/js/admin_main.js?v=<?= $jsAdminVer ?>"></script>
  <?php endif; ?>
</body>
</html>
