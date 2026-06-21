// PAGE: KEBIJAKAN PRIVASI
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js';

export function renderPrivacy() {
  const LAST_UPDATED = '21 Juni 2026';
  const SITE_URL     = 'https://maktabah.quizb.my.id';
  const CONTACT      = 'zenhkm@gmail.com';

  app().innerHTML = `
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-12 pb-20">

      <!-- Header -->
      <div class="mb-10">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shadow-sm">
            <i data-lucide="shield-check" class="w-5 h-5 text-gold"></i>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-primary leading-tight">Kebijakan Privasi</h1>
            <p class="text-sm text-muted">Al-Maktabah As-Sunniyyah</p>
          </div>
        </div>
        <p class="text-sm text-muted">Terakhir diperbarui: <span class="font-medium text-primary">${LAST_UPDATED}</span></p>
        <p class="mt-3 text-base text-secondary leading-relaxed">
          Kebijakan Privasi ini menjelaskan bagaimana Al-Maktabah As-Sunniyyah
          (<a href="${SITE_URL}" class="text-gold hover:underline font-medium">${SITE_URL}</a>)
          mengumpulkan, menggunakan, dan melindungi informasi pribadi Anda ketika menggunakan layanan kami.
        </p>
      </div>

      <!-- Sections -->
      <div class="space-y-6">

        ${privacySection('database', 'Informasi yang Kami Kumpulkan', `
          <p class="text-secondary mb-3 font-semibold text-primary">1. Melalui Autentikasi Google</p>
          <p class="text-secondary mb-3">Saat Anda masuk menggunakan akun Google, kami menerima informasi berikut dari Google:</p>
          <div class="grid sm:grid-cols-2 gap-3 mb-6">
            ${privacySubCard('user', 'Nama Lengkap', 'Nama yang terdaftar pada akun Google Anda.')}
            ${privacySubCard('mail', 'Alamat Email', 'Email utama akun Google Anda.')}
            ${privacySubCard('image', 'Foto Profil', 'URL foto profil publik Google Anda.')}
            ${privacySubCard('key', 'ID Google', 'Identitas unik dari Google.')}
          </div>
          
          <p class="text-secondary mb-3 font-semibold text-primary">2. Formulir Interaktif (Kirim / Request)</p>
          <p class="text-secondary mb-4">Ketika Anda menggunakan fitur <strong>Kirimkan File</strong> atau <strong>Request Kitab</strong> tanpa login, kami mengumpulkan alamat email yang Anda ketikkan secara manual agar kami dapat menghubungi Anda terkait status permintaan tersebut.</p>

          <p class="text-secondary mb-3 font-semibold text-primary">3. Riwayat & Preferensi Pencarian</p>
          <p class="text-secondary mb-4">Sistem mencatat riwayat kueri pencarian Anda (termasuk kata kunci dan preferensi centang kategori di Pencarian Lanjut). Data ini kami gunakan secara eksklusif untuk menyajikan histori pencarian yang personal dan meningkatkan akurasi sistem rekomendasi pencarian.</p>

          <p class="text-secondary mt-5 text-sm bg-gold/5 border border-gold/20 p-3 rounded-xl">Kami <strong>tidak</strong> menyimpan kata sandi Anda. Autentikasi sepenuhnya ditangani oleh Google dengan protokol yang aman.</p>
        `)}

        ${privacySection('settings', 'Cara Kami Menggunakan Informasi', `
          <ul class="space-y-2 text-secondary">
            ${privacyItem('Membuat dan mengelola akun pengguna Anda di platform ini.')}
            ${privacyItem('Menampilkan nama dan foto profil Anda di antarmuka aplikasi.')}
            ${privacyItem('Menghubungi Anda untuk merespons status pengajuan "Kirim File" atau "Request Kitab".')}
            ${privacyItem('Menyimpan riwayat dan preferensi pencarian Anda untuk fitur pengingat (Advanced Search) dan rekomendasi yang lebih pintar.')}
            ${privacyItem('Menentukan hak akses Anda (pengguna biasa atau administrator).')}
            ${privacyItem('Mencatat waktu login terakhir untuk keperluan keamanan.')}
            ${privacyItem('Kami <strong>tidak menggunakan</strong> data Anda untuk iklan, analitik pihak ketiga, atau tujuan komersial lainnya.')}
          </ul>
        `)}

        ${privacySection('share-2', 'Berbagi Data dengan Pihak Ketiga', `
          <p class="text-secondary mb-3">Kami <strong>tidak menjual, menyewakan, atau membagikan</strong> data pribadi Anda kepada pihak ketiga, kecuali:</p>
          <ul class="space-y-2 text-secondary">
            ${privacyItem('Google LLC — sebagai penyedia layanan autentikasi OAuth 2.0.')}
            ${privacyItem('Penyedia hosting server — hanya memiliki akses teknis ke infrastruktur, bukan data pengguna secara individual.')}
            ${privacyItem('Kewajiban hukum — apabila diwajibkan oleh peraturan perundang-undangan yang berlaku.')}
          </ul>
        `)}

        ${privacySection('cookie', 'Cookie & Sesi', `
          <p class="text-secondary mb-3">Kami menggunakan cookie sesi PHP standar untuk:</p>
          <ul class="space-y-2 text-secondary">
            ${privacyItem('Mempertahankan status login Anda selama sesi aktif.')}
            ${privacyItem('Melindungi dari serangan CSRF (Cross-Site Request Forgery) selama proses login.')}
          </ul>
          <p class="text-secondary mt-4 text-sm">Cookie sesi dihapus otomatis ketika Anda keluar (logout) atau menutup browser. Kami tidak menggunakan cookie pelacak atau cookie pihak ketiga.</p>
        `)}

        ${privacySection('shield', 'Keamanan Data', `
          <div class="grid sm:grid-cols-3 gap-3">
            ${privacyRightCard('lock', 'Enkripsi HTTPS', 'Seluruh komunikasi antara browser dan server dienkripsi menggunakan TLS/SSL.')}
            ${privacyRightCard('database', 'PDO Prepared Statements', 'Semua kueri database menggunakan prepared statements untuk mencegah SQL injection.')}
            ${privacyRightCard('shield-check', 'CSRF Protection', 'Token state divalidasi pada setiap proses autentikasi OAuth.')}
          </div>
        `)}

        ${privacySection('user-check', 'Hak Pengguna', `
          <p class="text-secondary mb-3">Anda memiliki hak atas data pribadi Anda, termasuk:</p>
          <ul class="space-y-2 text-secondary">
            ${privacyItem('Hak akses: Melihat informasi yang kami simpan tentang Anda melalui halaman profil.')}
            ${privacyItem('Hak penghapusan: Menghubungi kami untuk menghapus akun dan seluruh data Anda secara permanen.')}
            ${privacyItem('Hak pencabutan: Mencabut izin akses aplikasi ini dari pengaturan akun Google Anda kapan saja.')}
            ${privacyItem('Hak koreksi: Data profil diperbarui otomatis dari Google setiap kali Anda login.')}
          </ul>
        `)}

        ${privacySection('users', 'Pengguna di Bawah Umur', `
          <p class="text-secondary">
            Layanan ini tidak ditujukan secara khusus untuk anak-anak di bawah 13 tahun.
            Kami tidak secara sengaja mengumpulkan data dari anak di bawah umur.
            Jika Anda adalah orang tua atau wali dan mengetahui bahwa anak Anda telah memberikan informasi pribadi kepada kami,
            silakan hubungi kami agar kami dapat menghapus data tersebut.
          </p>
        `)}

        ${privacySection('mail', 'Hubungi Kami', `
          <p class="text-secondary mb-4">Jika Anda memiliki pertanyaan, permintaan, atau kekhawatiran mengenai kebijakan privasi ini, silakan hubungi kami:</p>
          <div class="bg-surface rounded-xl p-4 border border-border">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-primary flex items-center justify-center">
                <i data-lucide="mail" class="w-4 h-4 text-gold"></i>
              </div>
              <div>
                <p class="text-sm text-muted">Email</p>
                <a href="mailto:${CONTACT}" class="font-medium text-primary hover:text-gold transition-colors">${CONTACT}</a>
              </div>
            </div>
          </div>
        `)}

        ${privacySection('refresh-cw', 'Perubahan Kebijakan', `
          <p class="text-secondary">
            Kami dapat memperbarui Kebijakan Privasi ini dari waktu ke waktu.
            Setiap perubahan akan dipublikasikan di halaman ini dengan tanggal pembaruan yang baru.
            Penggunaan layanan secara berkelanjutan setelah perubahan diterbitkan berarti Anda menyetujui kebijakan yang diperbarui.
          </p>
          <p class="text-secondary mt-3 text-sm">
            Versi saat ini berlaku sejak <strong>${LAST_UPDATED}</strong>.
          </p>
        `)}

      </div>

      <!-- Footer note -->
      <div class="mt-12 pt-6 border-t border-border text-center">
        <p class="text-sm text-muted">
          <i data-lucide="heart" class="w-3.5 h-3.5 inline text-gold mr-1"></i>
          Al-Maktabah As-Sunniyyah — perpustakaan digital kitab-kitab Islam klasik
        </p>
        <p class="text-xs text-muted mt-1">
          <a href="/" data-route="/" class="hover:text-gold transition-colors">Kembali ke Beranda</a>
          <span class="mx-2">·</span>
          <a href="/katalog" data-route="/katalog" class="hover:text-gold transition-colors">Jelajahi Katalog</a>
        </p>
      </div>

    </div>`;
  reicons();
}

// ── Helper: section card ──────────────────────────────────────
function privacySection(icon, title, bodyHtml) {
  return `
    <div class="bg-surface rounded-2xl border border-border p-6 shadow-sm">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center flex-shrink-0">
          <i data-lucide="${icon}" class="w-4 h-4 text-gold"></i>
        </div>
        <h2 class="text-lg font-semibold text-primary">${title}</h2>
      </div>
      <div class="text-sm leading-relaxed">${bodyHtml}</div>
    </div>`;
}

// ── Helper: sub info card ─────────────────────────────────────
function privacySubCard(icon, title, bodyHtml) {
  return `
    <div class="flex items-start gap-3 bg-background rounded-xl p-3 border border-border">
      <div class="w-7 h-7 rounded-lg bg-primary flex items-center justify-center flex-shrink-0 mt-0.5">
        <i data-lucide="${icon}" class="w-3.5 h-3.5 text-gold"></i>
      </div>
      <div>
        <p class="text-sm font-medium text-primary">${title}</p>
        <p class="text-xs text-muted mt-0.5">${bodyHtml}</p>
      </div>
    </div>`;
}

// ── Helper: list item ─────────────────────────────────────────
function privacyItem(text) {
  return `
    <li class="flex items-start gap-2">
      <i data-lucide="check-circle" class="w-4 h-4 text-gold mt-0.5 flex-shrink-0"></i>
      <span>${text}</span>
    </li>`;
}

// ── Helper: right card (security pillars etc.) ────────────────
function privacyRightCard(icon, title, desc) {
  return `
    <div class="bg-background rounded-xl p-4 border border-border text-center">
      <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center mx-auto mb-3">
        <i data-lucide="${icon}" class="w-5 h-5 text-gold"></i>
      </div>
      <p class="text-sm font-semibold text-primary mb-1">${title}</p>
      <p class="text-xs text-muted leading-relaxed">${desc}</p>
    </div>`;
}

// ══════════════════════════════════════════════════════════════
//  SUBMIT FILE — CTA helper & halaman kirim file
// ══════════════════════════════════════════════════════════════

/** Dipanggil dari tombol CTA di beranda.
 *  Jika sudah login → langsung ke /submit-file
 *  Jika belum       → simpan tujuan di sessionStorage, lalu ke halaman login */
function handleSubmitCTA() {
  // Allow anonymous users to access submit page; no login required anymore
  navigate('/submit-file');
}
window.handleSubmitCTA = handleSubmitCTA;

// Setelah login, cek apakah ada pending redirect
(function checkPostLoginRedirect() {
  const target = sessionStorage.getItem('_afterLoginRedirect');
  if (!target) return;
  apiFetch({ action: 'auth_me' }).then(res => {
    if (res.loggedIn) {
      sessionStorage.removeItem('_afterLoginRedirect');
      navigate(target);
    }
  }).catch(() => {});
})();

export async function renderSubmitFile() {
  // Sekarang halaman dapat diakses tanpa login; anonymous submit diperbolehkan dengan email

  // Muat kategori
  let cats = [];
  try {
    const r = await apiFetch({ action: 'categories' });
    cats = r.data || [];
  } catch { /* ignore */ }

  app().innerHTML = `
    <div class="min-h-screen bg-cream py-10 px-4">
      <div class="max-w-lg mx-auto">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-7">
          <button onclick="navigate('/')" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gold/30 hover:bg-cream-dark transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4 text-primary"></i>
          </button>
          <div>
            <h1 class="text-lg font-bold text-primary">Kirimkan File</h1>
            <p class="text-xs text-primary/50">Hasil Bahsul Masail atau Kitab</p>
          </div>
        </div>

        <!-- Card form -->
        <div class="bg-white rounded-2xl shadow-card p-6">

          <div id="submit-error"   class="hidden mb-4 p-3 rounded-xl bg-red-50 text-red-600 text-sm"></div>
          <div id="submit-success" class="hidden mb-4 p-4 rounded-xl bg-green-50 text-green-700 text-sm font-medium"></div>

          <form id="submit-form" enctype="multipart/form-data" onsubmit="submitFileForm(event)">

            <!-- Nama File -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                Nama File <span class="text-red-400">*</span>
              </label>
              <input type="text" id="sf-name" name="file_name" required
                placeholder="Contoh: Bahsul Masail Pesantren Al-Falah 2024"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
            </div>

            <!-- Tipe File -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                Tipe File <span class="text-red-400">*</span>
              </label>
              <select id="sf-type" name="file_type" required
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all appearance-none">
                <option value="">— Pilih Tipe —</option>
                <option value="bahsul_masail">Hasil Bahsul Masail</option>
                <option value="kitab">File Kitab</option>
              </select>
            </div>

            <!-- Kategori -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Kategori</label>
              <select id="sf-cat" name="category_id"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all appearance-none">
                <option value="">— Pilih Kategori (opsional) —</option>
                ${cats.map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('')}
              </select>
            </div>

            <!-- Deskripsi -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Deskripsi <span class="text-primary/30 font-normal">(opsional)</span></label>
              <textarea id="sf-desc" name="description" rows="3"
                placeholder="Keterangan singkat tentang isi file…"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all resize-none"></textarea>
            </div>

            <!-- Email pengirim (jika tidak login) -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Email Anda <span class="text-red-400">*</span></label>
              <input type="email" id="sf-email" name="submitter_email" required
                placeholder="email@contoh.com"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
            </div>

            <!-- Upload File -->
            <div class="mb-6">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                File <span class="text-red-400">*</span>
                <span class="text-primary/30 font-normal ml-1">PDF / Word · maks. 20 MB</span>
              </label>
              <label class="flex flex-col items-center justify-center gap-2 w-full border-2 border-dashed border-gold/40 rounded-xl py-7 px-4 bg-cream cursor-pointer hover:border-gold hover:bg-gold/5 transition-all" id="file-drop-zone">
                <i data-lucide="upload-cloud" class="w-8 h-8 text-gold/60"></i>
                <span class="text-sm text-primary/50" id="file-label">Klik untuk pilih file atau seret ke sini</span>
                <input type="file" id="sf-file" name="file" accept=".pdf,.doc,.docx" required class="hidden"
                  onchange="document.getElementById('file-label').textContent = this.files[0]?.name || 'Pilih file'" />
              </label>
            </div>

            <button type="submit" id="sf-submit-btn"
              class="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-primary text-white font-semibold text-sm hover:bg-primary-light transition-colors shadow-sm">
              <i data-lucide="send" class="w-4 h-4"></i>
              <span id="sf-btn-label">Kirim untuk Direview</span>
            </button>

          </form>
        </div>

        <p class="text-xs text-center text-primary/35 mt-5">File akan direview oleh admin sebelum ditampilkan di perpustakaan.</p>
      </div>
    </div>`;

  reicons();
}
window.renderSubmitFile = renderSubmitFile;

async function submitFileForm(e) {
  e.preventDefault();
  const errEl  = document.getElementById('submit-error');
  const okEl   = document.getElementById('submit-success');
  const btn    = document.getElementById('sf-submit-btn');
  const lbl    = document.getElementById('sf-btn-label');

  const showErr = (msg) => { errEl.textContent = msg; errEl.classList.remove('hidden'); okEl.classList.add('hidden'); };
  errEl.classList.add('hidden'); okEl.classList.add('hidden');

  const name   = document.getElementById('sf-name').value.trim();
  const email  = document.getElementById('sf-email')?.value.trim() || '';
  const type   = document.getElementById('sf-type').value;
  const catId  = document.getElementById('sf-cat').value;
  const desc   = document.getElementById('sf-desc').value.trim();
  const fileEl = document.getElementById('sf-file');

  if (!name)         { showErr('Nama file wajib diisi.'); return; }
  if (!email || !/^\S+@\S+\.\S+$/.test(email)) { showErr('Masukkan email yang valid.'); return; }
  if (!type)         { showErr('Pilih tipe file terlebih dahulu.'); return; }
  if (!fileEl.files?.length) { showErr('Pilih file yang akan dikirim.'); return; }

  btn.disabled = true;
  lbl.textContent = 'Mengirim…';

  const fd = new FormData();
  fd.append('file_name',   name);
  fd.append('submitter_email', email);
  fd.append('file_type',   type);
  fd.append('category_id', catId);
  fd.append('description', desc);
  fd.append('file',        fileEl.files[0]);

  try {
    const res = await fetch('/api.php?action=submit_file', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || 'Gagal mengirim.');
    okEl.textContent = data.message || 'Kiriman berhasil dikirim!';
    okEl.classList.remove('hidden');
    document.getElementById('submit-form').reset();
    document.getElementById('file-label').textContent = 'Klik untuk pilih file atau seret ke sini';
  } catch (err) {
    showErr(err.message);
  } finally {
    btn.disabled = false;
    lbl.textContent = 'Kirim untuk Direview';
  }
}
window.submitFileForm = submitFileForm;

// ── Request Kitab ─────────────────────────────────────────────
export async function renderRequestKitab() {
  app().innerHTML = `
    <div class="min-h-screen bg-cream py-10 px-4">
      <div class="max-w-lg mx-auto">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-7">
          <button onclick="navigate('/')" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gold/30 hover:bg-cream-dark transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4 text-primary"></i>
          </button>
          <div>
            <h1 class="text-lg font-bold text-primary">Request Kitab</h1>
            <p class="text-xs text-primary/50">Ajukan kitab atau hasil bahsul masail</p>
          </div>
        </div>

        <!-- Card form -->
        <div class="bg-white rounded-2xl shadow-card p-6">

          <div id="req-error"   class="hidden mb-4 p-3 rounded-xl bg-red-50 text-red-600 text-sm"></div>
          <div id="req-success" class="hidden mb-4 p-4 rounded-xl bg-green-50 text-green-700 text-sm font-medium"></div>

          <form id="req-form" onsubmit="submitRequestForm(event)">

            <!-- Email -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                Email Anda <span class="text-red-400">*</span>
              </label>
              <input type="email" id="rq-email" name="user_email" required
                placeholder="email@contoh.com"
                value="${window.SESSION_USER ? window.SESSION_USER.email : ''}"
                ${window.SESSION_USER ? 'readonly' : ''}
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream text-sm transition-all ${window.SESSION_USER ? 'opacity-60 cursor-not-allowed' : 'focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20'}" />
              <p class="text-[10px] text-primary/40 mt-1">Kami akan menghubungi Anda jika kitab sudah tersedia.</p>
            </div>

            <!-- Tipe Request -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                Tipe Request <span class="text-red-400">*</span>
              </label>
              <select id="rq-type" name="request_type" required
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all appearance-none">
                <option value="">— Pilih Tipe —</option>
                <option value="bahsul_masail">Hasil Bahsul Masail</option>
                <option value="kitab">File Kitab</option>
              </select>
            </div>

            <!-- Judul -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                Judul <span class="text-red-400">*</span>
              </label>
              <input type="text" id="rq-title" name="title" required
                placeholder="Contoh: Sahih Al-Bukhari atau Keputusan PCNU"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
            </div>

            <!-- Pengarang / Kategori -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Pengarang / Penerbit <span class="text-primary/30 font-normal">(opsional)</span></label>
              <input type="text" id="rq-author" name="author_or_category"
                placeholder="Contoh: Imam Al-Bukhari"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
            </div>

            <!-- Deskripsi -->
            <div class="mb-6">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Alasan / Keterangan <span class="text-primary/30 font-normal">(opsional)</span></label>
              <textarea id="rq-desc" name="description" rows="3"
                placeholder="Berikan keterangan tambahan jika ada..."
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all resize-none"></textarea>
            </div>

            <button type="submit" id="rq-submit-btn"
              class="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gold text-primary font-semibold text-sm hover:bg-gold-light transition-colors shadow-sm">
              <i data-lucide="help-circle" class="w-4 h-4"></i>
              <span id="rq-btn-label">Kirim Request</span>
            </button>

          </form>
        </div>

        <p class="text-xs text-center text-primary/35 mt-5">Semua permohonan akan ditinjau. Tidak semua permohonan dapat segera dipenuhi bergantung ketersediaan.</p>
      </div>
    </div>`;

  reicons();
}
window.renderRequestKitab = renderRequestKitab;

async function submitRequestForm(e) {
  e.preventDefault();
  const errEl  = document.getElementById('req-error');
  const okEl   = document.getElementById('req-success');
  const btn    = document.getElementById('rq-submit-btn');
  const lbl    = document.getElementById('rq-btn-label');

  const showErr = (msg) => { errEl.textContent = msg; errEl.classList.remove('hidden'); okEl.classList.add('hidden'); };
  errEl.classList.add('hidden'); okEl.classList.add('hidden');

  const email  = document.getElementById('rq-email').value.trim();
  const type   = document.getElementById('rq-type').value;
  const title  = document.getElementById('rq-title').value.trim();
  const author = document.getElementById('rq-author').value.trim();
  const desc   = document.getElementById('rq-desc').value.trim();

  if (!email || !/^\S+@\S+\.\S+$/.test(email)) { showErr('Masukkan email yang valid.'); return; }
  if (!type)  { showErr('Pilih tipe request terlebih dahulu.'); return; }
  if (!title) { showErr('Judul wajib diisi.'); return; }

  btn.disabled = true;
  lbl.textContent = 'Mengirim…';

  const fd = new FormData();
  fd.append('user_email', email);
  fd.append('request_type', type);
  fd.append('title', title);
  fd.append('author_or_category', author);
  fd.append('description', desc);

  try {
    const res = await fetch('/api.php?action=submit_request', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || 'Gagal mengirim.');
    okEl.textContent = data.message || 'Request berhasil dikirim!';
    okEl.classList.remove('hidden');
    document.getElementById('req-form').reset();
  } catch (err) {
    showErr(err.message);
  } finally {
    btn.disabled = false;
    lbl.textContent = 'Kirim Request';
  }
}
window.submitRequestForm = submitRequestForm;

