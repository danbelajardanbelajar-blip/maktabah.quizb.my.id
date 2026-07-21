let askState = { query: '', answerHTML: '', referencesHTML: '' };

export function renderAsk() {
  const { app, $, reicons } = window;
  
  app().innerHTML = `
    <div class="search-section-enter px-4 md:px-0 max-w-3xl mx-auto py-6">
      <div class="bg-white dark:bg-ink rounded-3xl shadow-card p-6 border border-[rgba(201,162,39,0.15)] relative overflow-hidden">
        
        <!-- Decoration -->
        <div class="absolute -right-16 -top-16 opacity-5 pointer-events-none">
          <svg width="200" height="200" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
          </svg>
        </div>

        <div class="relative z-10">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-full bg-gold/10 flex items-center justify-center text-gold">
              <i data-lucide="bot" class="w-5 h-5"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-bold text-primary dark:text-cream arabic">اسأل الذكاء الاصطناعي</h2>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 font-semibold">Tanya Maktabah Bot</p>

          <form id="ask-form" class="relative mb-6">
            <textarea id="ask-input" rows="3" class="w-full bg-gray-50 dark:bg-[#1a231f] border border-gray-200 dark:border-[rgba(201,162,39,0.15)] rounded-2xl py-4 pl-4 pr-14 text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-gold/30 resize-none transition-shadow text-primary dark:text-cream placeholder-gray-400" placeholder="Ketik pertanyaan Anda tentang hukum, fatwa, atau materi kitab di sini..."></textarea>
            <button type="submit" id="ask-btn" class="absolute bottom-4 right-4 p-2 bg-primary text-white rounded-xl hover:bg-primary-light transition-colors flex items-center justify-center shadow-md">
              <i data-lucide="send" class="w-4 h-4"></i>
            </button>
          </form>

          <!-- Result Area -->
          <div id="ask-result-container" class="hidden">
            <div id="ask-loading" class="hidden flex-col items-center justify-center py-8">
              <div class="spin-ring mb-3"></div>
              <p class="text-xs text-gray-500 animate-pulse">Sedang mencari referensi dan merangkum jawaban...</p>
            </div>

            <div id="ask-response" class="hidden">
              <div class="p-5 bg-islamic-soft dark:bg-[#122319] rounded-2xl border border-primary/10 dark:border-primary/20 mb-4">
                <div class="flex items-center gap-2 mb-3">
                  <i data-lucide="sparkles" class="w-4 h-4 text-gold"></i>
                  <span class="text-xs font-bold text-primary uppercase tracking-widest">Jawaban AI</span>
                </div>
                <div id="ask-answer-text" class="text-sm md:text-base leading-relaxed text-gray-800 dark:text-gray-200 whitespace-pre-wrap"></div>
              </div>

              <div id="ask-references-container" class="hidden">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 ml-1">Sumber Referensi Kitab:</h4>
                <div id="ask-references-list" class="flex flex-col gap-2"></div>
              </div>
            </div>
          </div>
          
        </div>
      </div>
    </div>
  `;

  reicons();

  const form = $('#ask-form');
  const input = $('#ask-input');
  const btn = $('#ask-btn');
  const resultContainer = $('#ask-result-container');
  const loading = $('#ask-loading');
  const responseBox = $('#ask-response');
  const answerText = $('#ask-answer-text');
  const refContainer = $('#ask-references-container');
  const refList = $('#ask-references-list');

  // Restore state if exists
  if (askState.query) {
    input.value = askState.query;
    setTimeout(() => {
      input.style.height = 'auto';
      input.style.height = (input.scrollHeight) + 'px';
    }, 10);
  }
  if (askState.answerHTML) {
    resultContainer.classList.remove('hidden');
    responseBox.classList.remove('hidden');
    answerText.innerHTML = askState.answerHTML;
    if (askState.referencesHTML) {
      refContainer.classList.remove('hidden');
      refList.innerHTML = askState.referencesHTML;
    }
  }

  // Textarea auto-resize
  input.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
  });

  // Submit on Enter (Shift+Enter for new line)
  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const query = input.value.trim();
    if (query.length < 5) {
      alert('Pertanyaan terlalu pendek.');
      return;
    }

    // UI Update
    resultContainer.classList.remove('hidden');
    responseBox.classList.add('hidden');
    refContainer.classList.add('hidden');
    loading.classList.remove('hidden');
    btn.disabled = true;
    btn.innerHTML = `<div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>`;

    try {
      const fd = new FormData();
      fd.append('q', query);
      // Asumsikan apiFetch mendukung FormData untuk POST / GET
      // apiFetch di core.js biasanya: fetch(url, options)
      // Kita panggil manual agar mudah POST
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      
      const res = await fetch('/api.php?action=ask_ai', {
        method: 'POST',
        headers: {
          'X-CSRF-Token': csrf
        },
        body: fd
      });
      
      const data = await res.json();
      
      loading.classList.add('hidden');
      responseBox.classList.remove('hidden');

      if (data.status === 'success') {
        let html = window.escHtml(data.answer);
        
        // Parse basic markdown: bold
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-primary dark:text-cream">$1</strong>');
        
        // Parse list items and paragraphs
        let lines = html.split('\n');
        let parsed = [];
        let inUl = false;
        let inOl = false;
        
        for (let line of lines) {
            let ulMatch = line.match(/^(\s*)[\*\-]\s+(.*)/);
            let olMatch = line.match(/^(\s*)(\d+)\.\s+(.*)/);
            
            if (ulMatch) {
                if (!inUl) { parsed.push('<ul class="list-disc ml-5 my-2 space-y-1">'); inUl = true; }
                parsed.push('<li>' + ulMatch[2] + '</li>');
            } else if (olMatch) {
                if (!inOl) { parsed.push('<ol class="list-decimal ml-5 my-2 space-y-1" start="' + olMatch[2] + '">'); inOl = true; }
                parsed.push('<li>' + olMatch[3] + '</li>');
            } else {
                if (inUl) { parsed.push('</ul>'); inUl = false; }
                if (inOl) { parsed.push('</ol>'); inOl = false; }
                parsed.push(line + (line.trim() === '' ? '' : '<br>'));
            }
        }
        if (inUl) parsed.push('</ul>');
        if (inOl) parsed.push('</ol>');
        
        answerText.innerHTML = parsed.join('\n');
        askState.answerHTML = answerText.innerHTML;
        askState.query = query;
        askState.referencesHTML = '';
        
        if (data.references && data.references.length > 0) {
          refContainer.classList.remove('hidden');
          refList.innerHTML = data.references.map(r => `
            <a href="/kitab?id=${r.bkid}&juz=${r.juz}&page=${r.page}" onclick="window.navigate('/kitab?id=${r.bkid}&juz=${r.juz}&page=${r.page}'); return false;" class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 p-2 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 transition cursor-pointer no-underline">
              <i data-lucide="book" class="w-3.5 h-3.5 text-primary"></i>
              <span class="font-bold font-arabic text-sm">${window.escHtml(r.title)}</span>
              <span class="mx-1">•</span>
              <span>Juz ${r.juz}, Hlm ${r.page}</span>
            </a>
          `).join('');
          askState.referencesHTML = refList.innerHTML;
          reicons();
        } else {
          refContainer.classList.add('hidden');
        }
      } else {
        answerText.innerHTML = `<span class="text-red-500">${window.escHtml(data.message || 'Terjadi kesalahan')}</span>`;
        askState.answerHTML = answerText.innerHTML;
      }
    } catch (err) {
      loading.classList.add('hidden');
      responseBox.classList.remove('hidden');
      answerText.innerHTML = `<span class="text-red-500">Koneksi terputus atau server bermasalah.</span>`;
    } finally {
      btn.disabled = false;
      btn.innerHTML = `<i data-lucide="send" class="w-4 h-4"></i>`;
      reicons();
    }
  });
}
