// PAGE: ABOUT
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js';

export function renderAbout() {
  app().innerHTML = `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <!-- Header -->
      <div class="text-center mb-10">
        <div class="arabic text-primary text-4xl font-bold mb-2">المكتبة السنية</div>
        <div class="text-gold text-sm font-medium tracking-widest uppercase">Al-Maktabah As-Sunniyyah</div>
        <div class="gold-line mt-6 max-w-xs mx-auto"></div>
      </div>

      <div class="bg-white rounded-3xl shadow-card p-8 md:p-10 space-y-8 text-sm leading-relaxed text-primary/80">

        <div>
          <h2 class="text-primary font-bold text-base mb-3 flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4 text-gold"></i> Tentang Kami
          </h2>
          <p>
            <strong>المكتبة السنية</strong> adalah perpustakaan digital Islam yang hadir untuk memudahkan umat dalam mengakses khazanah ilmu Islam, khususnya kitab-kitab dari para ulama salaf. Kami mengumpulkan, menata, dan menyajikan ribuan kitab dalam format digital yang mudah diakses oleh siapa saja, di mana saja, kapan saja — secara <em>gratis</em>.
          </p>
        </div>

        <div>
          <h2 class="text-primary font-bold text-base mb-3 flex items-center gap-2">
            <i data-lucide="eye" class="w-4 h-4 text-gold"></i> Visi
          </h2>
          <p class="arabic text-base text-right text-primary mb-2">"نشر العلم الشرعي وتيسيره للأمة"</p>
          <p>Menjadi portal terdepan dalam menyebarkan ilmu syar'i dan mempermudah akses umat Islam terhadap warisan intelektual para ulama.</p>
        </div>

        <div>
          <h2 class="text-primary font-bold text-base mb-3 flex items-center gap-2">
            <i data-lucide="target" class="w-4 h-4 text-gold"></i> Misi
          </h2>
          <ul class="space-y-2 list-none">
            ${['Menghadirkan kitab-kitab salaf yang otentik dan terpercaya.',
               'Mempermudah pencarian dan akses kitab secara digital.',
               'Mendukung para penuntut ilmu dengan koleksi yang terus berkembang.',
               'Menjaga warisan keilmuan Islam agar tetap lestari dan mudah diakses generasi mendatang.']
              .map(m => `<li class="flex items-start gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-gold mt-0.5 shrink-0"></i><span>${m}</span></li>`).join('')}
          </ul>
        </div>

        <!-- Fitur & Update Terbaru -->
        <div>
          <h2 class="text-primary font-bold text-base mb-4 flex items-center gap-2">
            <i data-lucide="sparkles" class="w-4 h-4 text-gold"></i> Update & Fitur Terbaru
          </h2>
          <div class="grid sm:grid-cols-2 gap-4">
            
            <div class="bg-surface border border-border rounded-2xl p-5 flex items-start gap-4 hover:border-gold/40 transition-all shadow-sm group">
              <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shrink-0 group-hover:bg-primary-light transition-colors">
                <i data-lucide="search" class="w-5 h-5 text-gold"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-primary mb-1">Pencarian Cerdas (Smart Search)</h3>
                <p class="text-xs text-muted leading-relaxed">Mendukung kueri multi-kata fleksibel, abai harokat, dan secara otomatis mengingat preferensi filter kategori Anda pada Pencarian Lanjut (Advanced Search).</p>
              </div>
            </div>

            <div class="bg-surface border border-border rounded-2xl p-5 flex items-start gap-4 hover:border-gold/40 transition-all shadow-sm group">
              <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shrink-0 group-hover:bg-primary-light transition-colors">
                <i data-lucide="send" class="w-5 h-5 text-gold"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-primary mb-1">Pengajuan & Request Anonim</h3>
                <p class="text-xs text-muted leading-relaxed">Kini Anda bisa membagikan dokumen Bahsul Masail atau meminta kitab khusus secara anonim (tanpa perlu akun Google) secara instan.</p>
              </div>
            </div>

            <div class="bg-surface border border-border rounded-2xl p-5 flex items-start gap-4 hover:border-gold/40 transition-all shadow-sm group">
              <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shrink-0 group-hover:bg-primary-light transition-colors">
                <i data-lucide="layout-dashboard" class="w-5 h-5 text-gold"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-primary mb-1">Manajemen Panel Modern</h3>
                <p class="text-xs text-muted leading-relaxed">Ekosistem di balik layar kini didukung panel admin yang sangat rapi untuk meninjau, menyortir, dan mempublikasikan kitab dalam hitungan detik.</p>
              </div>
            </div>

            <div class="bg-surface border border-border rounded-2xl p-5 flex items-start gap-4 hover:border-gold/40 transition-all shadow-sm group">
              <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shrink-0 group-hover:bg-primary-light transition-colors">
                <i data-lucide="zap" class="w-5 h-5 text-gold"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-primary mb-1">Arsitektur Super Cepat</h3>
                <p class="text-xs text-muted leading-relaxed">Dibangun sebagai Single Page Application (SPA), menelusuri ratusan halaman buku dan katalog kini terasa tanpa jeda (zero reload).</p>
              </div>
            </div>

          </div>
        </div>

        <div class="bg-cream rounded-2xl p-6 text-center mt-6">
          <div class="arabic text-primary text-lg font-bold mb-1">طلب العلم فريضة على كل مسلم</div>
          <div class="text-primary/50 text-xs">HR. Ibnu Mājah — Menuntut ilmu adalah kewajiban setiap Muslim</div>
        </div>

        <div>
          <h2 class="text-primary font-bold text-base mb-3 flex items-center gap-2">
            <i data-lucide="globe" class="w-4 h-4 text-gold"></i> Kontak & Akses
          </h2>
          <p class="mb-4">Anda dapat mengakses perpustakaan ini di: <a href="https://maktabah.quizb.my.id" class="text-gold hover:underline font-medium">maktabah.quizb.my.id</a></p>

          <!-- Founder & Developer cards -->
          <div class="grid sm:grid-cols-2 gap-4 mt-4">

            <!-- Founder -->
            <div class="rounded-2xl border border-border bg-surface p-5">
              <div class="flex items-center gap-2 mb-4">
                <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center">
                  <i data-lucide="star" class="w-3.5 h-3.5 text-gold"></i>
                </div>
                <span class="text-xs font-bold tracking-widest uppercase text-gold">Founder</span>
              </div>
              <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Nama</span>
                  <span class="font-semibold text-primary">Cak Zen</span>
                </div>
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Email</span>
                  <a href="mailto:akhmadzaeni535@gmail.com" class="font-semibold text-primary hover:text-gold transition-colors">akhmadzaeni535@gmail.com</a>
                </div>
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Facebook</span>
                  <a href="https://facebook.com/akhnadzaeni" target="_blank" rel="noopener" class="font-semibold text-primary hover:text-gold transition-colors">akhnadzaeni</a>
                </div>
              </div>
            </div>

            <!-- Developer -->
            <div class="rounded-2xl border border-border bg-surface p-5">
              <div class="flex items-center gap-2 mb-4">
                <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center">
                  <i data-lucide="code-2" class="w-3.5 h-3.5 text-gold"></i>
                </div>
                <span class="text-xs font-bold tracking-widest uppercase text-gold">Developer</span>
              </div>
              <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">No Hp</span>
                  <a href="https://wa.me/6285743399595" target="_blank" rel="noopener" class="font-semibold text-primary hover:text-gold transition-colors">085743399595</a>
                </div>
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Website</span>
                  <a href="https://hakimz.site" target="_blank" rel="noopener" class="font-semibold text-primary hover:text-gold transition-colors">https://hakimz.site</a>
                </div>
                <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border">
                  <span class="text-muted">Instagram</span>
                  <a href="https://instagram.com/zainul.hakim" target="_blank" rel="noopener" class="font-semibold text-primary hover:text-gold transition-colors">@zainul.hakim</a>
                </div>
              </div>
            </div>

          </div>

          ${(function() {
            const ua = navigator.userAgent || navigator.vendor || window.opera;
            const match = ua.match(/MaktabahApp\/([0-9]+)/);
            if (match) {
              const appVersion = parseInt(match[1], 10);
              return `
                <div class="mt-4 rounded-2xl border border-border bg-surface p-5">
                  <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center">
                      <i data-lucide="smartphone" class="w-3.5 h-3.5 text-gold"></i>
                    </div>
                    <span class="text-xs font-bold tracking-widest uppercase text-gold">Aplikasi Maktabah Turats</span>
                  </div>
                  <div class="flex items-center justify-between bg-background rounded-xl px-3 py-2 border border-border text-sm">
                    <span class="text-muted">Versi Terinstal</span>
                    <span class="font-semibold text-primary">Build ${appVersion}</span>
                  </div>
                </div>
              `;
            }
            return '';
          })()}

        </div>
        
        <!-- Statistik Web -->
        <div class="mt-8">
          <h2 class="text-primary font-bold text-base mb-4 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-4 h-4 text-gold"></i> Statistik Maktabah
          </h2>
          <div id="about-stats-container" class="flex flex-wrap items-center justify-center gap-4 text-sm text-primary/70">
            <span class="text-gold/50 text-xs">Memuat statistik...</span>
          </div>
        </div>

        <!-- Katalog Kategori -->
        <div class="mt-8">
          <h2 class="text-primary font-bold text-base mb-4 flex items-center gap-2">
            <i data-lucide="library" class="w-4 h-4 text-gold"></i> Katalog & Kategori Kitab
          </h2>
          <p class="text-sm mb-4">Perpustakaan kami membagi ribuan kitab ke dalam beberapa klasifikasi disiplin ilmu (kategori) untuk mempermudah Anda dalam menelusurinya:</p>
          <div id="about-cats-container" class="grid sm:grid-cols-2 md:grid-cols-3 gap-3">
            <span class="text-gold/50 text-xs col-span-full">Memuat katalog...</span>
          </div>
        </div>

      </div>
    </div>
    ${mobileFeedbackBanner}`;
  reicons();
  
  // Load dynamic data (Stats & Cats)
  (async () => {
    try {
      const stats = await apiFetch({ action: 'stats' });
      const formatNum = (n) => (n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      
      const statsContainer = document.getElementById('about-stats-container');
      if (statsContainer) {
        statsContainer.innerHTML = `
          <div class="bg-surface border border-border rounded-xl px-5 py-3 flex items-center gap-3">
            <i data-lucide="book-open" class="w-4 h-4 text-gold"></i>
            <div><div class="font-bold text-primary">${formatNum(stats.total_books)}</div><div class="text-[10px] uppercase tracking-wider text-muted">Kitab</div></div>
          </div>
          <div class="bg-surface border border-border rounded-xl px-5 py-3 flex items-center gap-3">
            <i data-lucide="folder" class="w-4 h-4 text-gold"></i>
            <div><div class="font-bold text-primary">${formatNum(stats.total_categories)}</div><div class="text-[10px] uppercase tracking-wider text-muted">Kategori</div></div>
          </div>
          <div class="bg-surface border border-border rounded-xl px-5 py-3 flex items-center gap-3">
            <i data-lucide="search" class="w-4 h-4 text-gold"></i>
            <div><div class="font-bold text-primary">${formatNum(stats.total_searches)}</div><div class="text-[10px] uppercase tracking-wider text-muted">Pencarian</div></div>
          </div>
          <div class="bg-surface border border-border rounded-xl px-5 py-3 flex items-center gap-3">
            <i data-lucide="eye" class="w-4 h-4 text-gold"></i>
            <div><div class="font-bold text-primary">${formatNum(stats.total_visits)}</div><div class="text-[10px] uppercase tracking-wider text-muted">Kunjungan</div></div>
          </div>
        `;
      }
      reicons();
    } catch(e) { }

    try {
      const res = await apiFetch({ action: 'categories' });
      const cats = res.data || [];
      const catsContainer = document.getElementById('about-cats-container');
      if (catsContainer && cats.length > 0) {
        catsContainer.innerHTML = cats.map(c => `
          <div class="bg-surface border border-border rounded-xl p-3 flex items-start gap-3 shadow-sm hover:border-gold/30 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-primary/5 flex items-center justify-center shrink-0">
              <i data-lucide="folder" class="w-4 h-4 text-gold"></i>
            </div>
            <div>
              <div class="font-semibold text-primary text-xs mb-0.5">${escHtml(c.name)}</div>
              <div class="text-[10px] text-muted">${c.count || 0} Kitab</div>
            </div>
          </div>
        `).join('');
      }
      reicons();
    } catch(e) { }
  })();
}


