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

