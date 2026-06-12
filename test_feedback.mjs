// PAGE: FEEDBACK
import { API, FONTS_LATIN, FONTS_ARABIC, readerFontState, applyReaderFont, $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, handleAuthError, UPDATE_NOTICE_SESSION_KEY, isMobileViewport, hasDismissedUpdateNotice, setDismissedUpdateNotice, closeUpdateNotice, showUpdateNoticeIfNeeded, logVisitorActivity, navigate, setActiveNav, updateReaderMenus, skeletonCards, bookCard, escHtml, paginationHtml, recentBookCard, saveToRecentlyOpened, getRecentlyOpened } from '../core/core.js';

export function renderFeedback() {
  app().innerHTML = `
    <div class="max-w-2xl mx-auto px-4 sm:px-6 py-12">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-primary mb-3">Feedback & Masukan</h1>
        <p class="text-sm text-primary/60">Punya saran, kritik, atau menemukan kendala? Beritahu kami agar kami bisa menjadi lebih baik.</p>
        <div class="gold-line mt-6 max-w-xs mx-auto"></div>
      </div>
      <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 border border-gold/10">
        <form id="feedback-form" onsubmit="event.preventDefault(); submitFeedbackForm();" class="flex flex-col gap-5">
          <div id="fb-err" class="hidden bg-red-50 border border-red-200 text-red-600 text-sm p-3 rounded-xl flex items-start gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 mt-0.5"></i>
            <span id="fb-err-msg"></span>
          </div>
          <div id="fb-ok" class="hidden bg-green-50 border border-green-200 text-green-700 text-sm p-3 rounded-xl flex items-start gap-2">
            <i data-lucide="check-circle-2" class="w-4 h-4 shrink-0 mt-0.5"></i>
            <span id="fb-ok-msg"></span>
          </div>

          <div>
            <label class="block text-sm font-semibold text-primary mb-1.5">Email Anda</label>
            <input type="email" name="email" required
                   class="w-full bg-cream-dark/30 border border-gold/20 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50"
                   placeholder="Contoh: user@email.com">
          </div>
          <div>
            <label class="block text-sm font-semibold text-primary mb-1.5">Isi Feedback</label>
            <textarea name="content" rows="6" required
                      class="w-full bg-cream-dark/30 border border-gold/20 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold/50"
                      placeholder="Tulis saran atau masukan Anda di sini..."></textarea>
          </div>
          <button type="submit" id="fb-btn"
                  class="mt-2 w-full bg-primary text-white font-semibold py-3 rounded-xl hover:bg-primary-light transition flex justify-center items-center gap-2">
            <i data-lucide="send" class="w-4 h-4" id="fb-btn-icon"></i>
            <span id="fb-btn-lbl">Kirim Feedback</span>
          </button>
        </form>
      </div>
    </div>`;
  reicons();
}

async function submitFeedbackForm() {
  const form = document.getElementById('feedback-form');
  const btn = document.getElementById('fb-btn');
  const icon = document.getElementById('fb-btn-icon');
  const lbl = document.getElementById('fb-btn-lbl');
  const errEl = document.getElementById('fb-err');
  const errMsg = document.getElementById('fb-err-msg');
  const okEl = document.getElementById('fb-ok');
  const okMsg = document.getElementById('fb-ok-msg');

  errEl.classList.add('hidden');
  okEl.classList.add('hidden');

  btn.disabled = true;
  icon.setAttribute('data-lucide', 'loader-2');
  icon.classList.add('animate-spin');
  lbl.textContent = 'Mengirim...';
  reicons();

  const fd = new FormData(form);
  try {
    const res = await fetch('/api.php?action=submit_feedback', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || 'Gagal mengirim feedback.');
    
    okMsg.textContent = data.message || 'Feedback berhasil dikirim!';
    okEl.classList.remove('hidden');
    form.reset();
  } catch (err) {
    errMsg.textContent = err.message;
    errEl.classList.remove('hidden');
  } finally {
    btn.disabled = false;
    icon.setAttribute('data-lucide', 'send');
    icon.classList.remove('animate-spin');
    lbl.textContent = 'Kirim Feedback';
    reicons();
  }
}
window.submitFeedbackForm = submitFeedbackForm;

// ══════════════════════════════════════════════════════════════
//  SUBMIT FILE
// ══════════════════════════════════════════════════════════════

export function handleSubmitCTA() {
  navigate('/submit-file');
}
window.handleSubmitCTA = handleSubmitCTA;

export async function renderSubmitFile() {
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

export async function submitFileForm(e) {
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
  if (!email || !/^\\S+@\\S+\\.\\S+$/.test(email)) { showErr('Masukkan email yang valid.'); return; }
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

// ══════════════════════════════════════════════════════════════
//  REQUEST KITAB
// ══════════════════════════════════════════════════════════════

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

            <!-- Email pengirim -->
            <div class="mb-4">
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">
                Email Anda <span class="text-red-400">*</span>
              </label>
              <input type="email" id="rq-email" name="user_email" required
                placeholder="email@contoh.com"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 bg-cream focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 text-sm transition-all" />
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

export async function submitRequestForm(e) {
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

  if (!email || !/^\\S+@\\S+\\.\\S+$/.test(email)) { showErr('Masukkan email yang valid.'); return; }
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
