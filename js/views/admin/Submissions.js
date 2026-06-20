import { apiFetch, $, app, reicons, escHtml, paginationHtml, handleAuthError, showPromptModal } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar, autoDir, bindAutoDir } from '../../core/AdminUtils.js';

// ══════════════════════════════════════════════════════════════
//  ADMIN: REVIEW KIRIMAN FILE
// ══════════════════════════════════════════════════════════════
async function renderAdminSubmissions() {
  app().innerHTML = adminNavBar('/admin/submissions') + `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-bold text-primary flex items-center gap-2">
            <i data-lucide="inbox" class="w-5 h-5 text-gold"></i> Review Kiriman
          </h1>
          <p class="text-primary/45 text-xs mt-0.5">Approve atau tolak kiriman Hasil Bahsul Masail &amp; Kitab dari pengguna</p>
        </div>
        <div class="flex gap-2 flex-wrap" id="sub-tabs"></div>
      </div>
      <div class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div id="sub-table-wrap">
          <div class="py-16 text-center"><i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin text-gold/50"></i></div>
        </div>
        <div id="sub-pagination" class="px-4 py-3 border-t border-cream-dark flex items-center justify-between text-xs text-primary/40"></div>
      </div>
    </div>
    <div id="sub-modal-overlay" class="hidden fixed inset-0 z-50 bg-ink/40 backdrop-blur-sm flex items-end sm:items-center justify-center p-4">
      <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-6">
        <h3 id="sub-modal-title" class="font-bold text-primary mb-1 text-base"></h3>
        <p  id="sub-modal-meta"  class="text-xs text-primary/45 mb-4"></p>
        <label class="block text-xs font-semibold text-primary/55 mb-1.5">Catatan Review <span class="text-primary/30 font-normal">(opsional)</span></label>
        <textarea id="sub-modal-note" rows="3" placeholder="Tulis catatan untuk pengirim…"
          class="w-full px-3 py-2 rounded-xl border border-gold/30 bg-cream text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 resize-none mb-5"></textarea>
        <div class="flex gap-3">
          <button onclick="subReview('approve')" class="flex-1 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition-colors flex items-center justify-center gap-1.5">
            <i data-lucide="check-circle" class="w-4 h-4"></i> Setujui
          </button>
          <button onclick="subReview('reject')" class="flex-1 py-2.5 rounded-xl bg-red-500 text-white text-sm font-semibold hover:bg-red-600 transition-colors flex items-center justify-center gap-1.5">
            <i data-lucide="x-circle" class="w-4 h-4"></i> Tolak
          </button>
          <button onclick="closeSubModal()" class="px-4 py-2.5 rounded-xl border border-gold/30 text-sm text-primary/60 hover:bg-cream-dark transition-colors">Batal</button>
        </div>
      </div>
    </div>
    
    <!-- Modal Review (Paginated) -->
    <div id="sub-review-modal" class="hidden fixed inset-0 z-[60] bg-ink/40 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white w-full max-w-4xl max-h-[92vh] rounded-2xl shadow-xl flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-cream-dark shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center">
              <i data-lucide="clipboard-check" class="w-4 h-4 text-gold"></i>
            </div>
            <div>
              <h2 id="sub-rev-title" class="font-bold text-primary text-sm">Review Kiriman</h2>
              <p class="text-xs text-primary/40">Pratinjau konten & persetujuan</p>
            </div>
          </div>
          <button onclick="closeSubReviewModal()" class="p-2 rounded-lg hover:bg-cream-dark text-primary/40 transition-colors">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
        <div id="sub-rev-body" class="flex-1 overflow-y-auto px-6 pb-6 pt-4">
          <!-- Diisi via JS -->
        </div>
      </div>
    </div>`;

  reicons();

  let _subStatus = '';
  let _subPage   = 1;
  let _subTarget = null;

  // Build status tabs
  const tabDefs = [
    { s: '',         label: 'Semua' },
    { s: 'pending',  label: 'Menunggu' },
    { s: 'approved', label: 'Disetujui' },
    { s: 'rejected', label: 'Ditolak' },
  ];
  const tabsEl = document.getElementById('sub-tabs');
  tabsEl.innerHTML = tabDefs.map(t =>
    `<button data-sub-tab="${t.s}" onclick="window.subSetStatus('${t.s}')"
      class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors
      ${t.s === '' ? 'bg-primary text-white border-primary' : 'bg-white text-primary/60 border-gold/30 hover:border-primary/30'}">
      ${t.label}</button>`
  ).join('');

  window.subSetStatus = function(s) {
    _subStatus = s;
    _subPage   = 1;
    tabsEl.querySelectorAll('[data-sub-tab]').forEach(btn => {
      const active = btn.dataset.subTab === s;
      btn.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors '
        + (active ? 'bg-primary text-white border-primary' : 'bg-white text-primary/60 border-gold/30 hover:border-primary/30');
    });
    subLoad();
  };

  window.openSubModal = function(id, name, type) {
    _subTarget = { id, name, type };
    document.getElementById('sub-modal-title').textContent = name;
    document.getElementById('sub-modal-meta').textContent  = type === 'bahsul_masail' ? 'Hasil Bahsul Masail' : 'File Kitab';
    document.getElementById('sub-modal-note').value = '';
    document.getElementById('sub-modal-overlay').classList.remove('hidden');
    reicons();
  };
  window.closeSubModal = function() {
    document.getElementById('sub-modal-overlay').classList.add('hidden');
    _subTarget = null;
  };
  window.subReview = async function(action) {
    if (!_subTarget) return;
    const note = document.getElementById('sub-modal-note').value.trim();
    try {
      const res = await adminPost('admin_review_submission', { id: _subTarget.id, review_action: action, note });
      if (res.error) throw new Error(res.error);
      closeSubModal();
      adminToast(action === 'approve' ? 'Kiriman disetujui ✓' : 'Kiriman ditolak');
      subLoad();
    } catch(e) { 
      if (handleAuthError(e)) return;
      adminToast('Gagal: ' + e.message, 'error'); 
    }
  };

  window.subDirectReview = async function(id, action) {
    try {
      const res = await adminPost('admin_review_submission', { id: id, review_action: action, note: '' });
      if (res.error) throw new Error(res.error);
      
      if (typeof closeSubReviewModal === 'function') {
        closeSubReviewModal();
      }
      
      adminToast(action === 'approve' ? 'Kiriman disetujui ✓' : 'Kiriman ditolak');
      subLoad();
    } catch(e) {
      if (handleAuthError(e)) return;
      adminToast('Gagal: ' + e.message, 'error'); 
    }
  };

  window.subReply = function(id) {
    showPromptModal('Balas Kiriman', 'Masukkan pesan tambahan untuk kiriman ini:', async (reply) => {
      if (!reply || reply.trim() === '') return;
      try {
        const res = await adminPost('admin_reply_submission', { id: id, reply: reply.trim() });
        if (res.error) throw new Error(res.error);
        adminToast('Pesan terkirim ✓');
        subLoad();
      } catch(e) {
        if (handleAuthError(e)) return;
        adminToast('Gagal: ' + e.message, 'error'); 
      }
    });
  };

  window.subDelete = async function(id) {
    if (!confirm('Yakin ingin menghapus kiriman ini permanen? File fisik juga akan dihapus.')) return;
    try {
      const res = await adminPost('admin_delete_submission', { id });
      if (res.error) throw new Error(res.error);
      adminToast('Kiriman dihapus ✓');
      subLoad();
    } catch(e) {
      if (handleAuthError(e)) return;
      adminToast('Gagal: ' + e.message, 'error');
    }
  };

  let _subRev = { id: 0, title: '', pages: [], currentPage: 0 };

  window.openSubReviewModal = async function(id, name) {
    const modal = document.getElementById('sub-review-modal');
    const body  = document.getElementById('sub-rev-body');
    document.getElementById('sub-rev-title').textContent = 'Review: ' + name;
    
    _subRev = { id, title: name, pages: [], currentPage: 0 };
    
    body.innerHTML = '<div class="text-center py-10"><div class="w-6 h-6 border-2 border-primary/30 border-t-primary rounded-full animate-spin mx-auto mb-3"></div><p class="text-sm text-primary/50">Mengekstrak file...</p></div>';
    modal.classList.remove('hidden');
    reicons();

    try {
      const res = await apiFetch({ action: 'admin_get_submission_content', id: id });
      if (res.error) throw new Error(res.error);
      if (!res.pages || res.pages.length === 0) {
        body.innerHTML = '<div class="text-center py-10 text-sm text-primary/50">Tidak ada teks yang dapat diekstrak atau file kosong/terenkripsi.</div>';
      } else {
        _subRev.pages = res.pages;
        _renderSubReviewBody();
      }
    } catch (e) {
      if (handleAuthError(e)) return;
      body.innerHTML = '<div class="text-center py-10 text-sm text-red-500">Gagal memuat pratinjau: ' + escHtml(e.message) + '</div>';
    }
  };

  window._renderSubReviewBody = function() {
    const total = _subRev.pages.length;
    const pg    = _subRev.currentPage;
    const wordCount = _subRev.pages[pg]?.split(/\s+/).filter(Boolean).length || 0;

    document.getElementById('sub-rev-body').innerHTML = `
      <div class="flex flex-col md:flex-row gap-4 h-full min-h-[400px]">
        <!-- Sidebar navigasi halaman -->
        <div class="w-full md:w-48 bg-white rounded-xl border border-cream-dark overflow-y-auto" style="max-height:450px;">
          <div class="px-3 py-2 bg-cream/50 border-b border-cream-dark text-xs font-semibold text-primary/40 sticky top-0">Hal.</div>
          <div class="flex flex-col">
            ${Array.from({length:total},(_,i)=>`
              <button onclick="window._subRevGoPage(${i})"
                class="w-full px-3 py-2 text-left text-xs transition-colors flex items-center gap-1.5
                  ${i===pg ? 'bg-primary text-white font-bold' : 'hover:bg-cream/60 text-primary/60'}">
                <span class="font-mono ${i===pg?'text-white/60':'text-primary/25'}">${String(i+1).padStart(3,'0')}</span>
                Hal ${i+1}
              </button>`).join('')}
          </div>
        </div>

        <!-- Editor halaman -->
        <div class="flex-1 flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-primary/50">
              Halaman <span class="text-primary font-bold">${pg+1}</span> dari ${total}
              <span class="text-primary/30 ml-2">(${wordCount} kata)</span>
            </span>
            <div class="flex items-center gap-1">
              <button onclick="window._subRevGoPage(${pg-1})" ${pg===0?'disabled':''} class="p-1.5 rounded-lg text-primary/40 hover:bg-cream-dark disabled:opacity-25 transition-colors">
                <i data-lucide="chevron-up" class="w-3.5 h-3.5"></i>
              </button>
              <button onclick="window._subRevGoPage(${pg+1})" ${pg>=total-1?'disabled':''} class="p-1.5 rounded-lg text-primary/40 hover:bg-cream-dark disabled:opacity-25 transition-colors">
                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
              </button>
            </div>
          </div>
          <textarea id="sub-rev-page-text" dir="auto" rows="12" readonly
            class="w-full flex-1 px-4 py-3 rounded-xl border border-gold/25 bg-cream/20 text-sm focus:outline-none resize-y leading-relaxed font-arabic"
            style="min-height:280px;">${escHtml(_subRev.pages[pg]||'')}</textarea>
          
          <!-- Tombol Aksi -->
          <div class="flex gap-3 pt-3 mt-auto">
            <button onclick="subDirectReview(${_subRev.id}, 'approve')" class="flex-1 py-3 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 transition-colors flex items-center justify-center gap-2">
              <i data-lucide="check" class="w-4 h-4"></i> Approve
            </button>
            <button onclick="subDirectReview(${_subRev.id}, 'reject')" class="flex-1 py-3 bg-red-500 text-white rounded-xl text-sm font-semibold hover:bg-red-600 transition-colors flex items-center justify-center gap-2">
              <i data-lucide="x" class="w-4 h-4"></i> Disapprove
            </button>
          </div>
        </div>
      </div>`;
    reicons();
  };

  window._subRevGoPage = function(i) {
    if (i < 0 || i >= _subRev.pages.length) return;
    _subRev.currentPage = i;
    _renderSubReviewBody();
  };

  window.closeSubReviewModal = function() {
    document.getElementById('sub-review-modal').classList.add('hidden');
  };

  const fmtSize = b => b < 1024 ? b + ' B' : b < 1048576 ? (b/1024).toFixed(1) + ' KB' : (b/1048576).toFixed(1) + ' MB';
  const statusBadge = s => {
    const cls = { pending: 'bg-yellow-100 text-yellow-700', approved: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-600' };
    const lbl = { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak' };
    return '<span class="px-2 py-0.5 rounded-full text-xs font-semibold ' + (cls[s]||'') + '">' + (lbl[s]||s) + '</span>';
  };

  async function subLoad() {
    const wrap = document.getElementById('sub-table-wrap');
    const pag  = document.getElementById('sub-pagination');
    wrap.innerHTML = '<div class="py-16 text-center"><i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin text-gold/50"></i></div>';
    reicons();
    try {
      const params = { action: 'admin_get_submissions', page: _subPage };
      if (_subStatus) params.status = _subStatus;
      const res  = await apiFetch(params);
      const rows = res.data || [];
      if (!rows.length) {
        wrap.innerHTML = '<div class="py-16 text-center"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 text-primary/15"></i><p class="text-primary/30 text-sm">Tidak ada kiriman ditemukan.</p></div>';
        pag.innerHTML = '';
        reicons(); return;
      }
      const rowsHtml = rows.map(r => {
        const escName = escHtml(r.file_name);
        const reviewBtn = '<button onclick="openSubReviewModal(' + r.id + ', this.getAttribute(\'data-name\'))" data-name="' + escName + '" title="Review" class="p-1.5 rounded-lg hover:bg-cream-dark transition-colors text-blue-500 hover:text-blue-700 flex items-center gap-1.5 px-2 font-medium text-xs"><i data-lucide="clipboard-check" class="w-4 h-4"></i> Review</button>';
        const viewBtn = '<button onclick="openSubReviewModal(' + r.id + ', this.getAttribute(\'data-name\'))" data-name="' + escName + '" title="Lihat file" class="p-1.5 rounded-lg hover:bg-cream-dark transition-colors text-blue-500 hover:text-blue-700"><i data-lucide="eye" class="w-4 h-4"></i></button>';
        const replyBtn = '<button onclick="subReply(' + r.id + ')" title="Balas/Pesan" class="p-1.5 rounded-lg hover:bg-indigo-100 text-indigo-700 transition-colors"><i data-lucide="message-square" class="w-4 h-4"></i></button>';
        const deleteBtn  = '<button onclick="subDelete(' + r.id + ')" title="Hapus" class="p-1.5 rounded-lg hover:bg-red-100 text-red-700 transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>';
        
        let actBtns = (r.status === 'pending' ? reviewBtn : viewBtn) + replyBtn + deleteBtn;

        return '<tr class="hover:bg-cream/60 transition-colors">'
          + '<td class="px-4 py-3"><div class="font-medium text-primary line-clamp-1">' + escHtml(r.file_name) + '</div>'
          + '<div class="text-primary/40 text-xs mt-0.5">' + fmtSize(r.file_size) + ' · ' + (r.created_at||'').slice(0,10) + '</div>'
          + (r.review_note ? '<div class="text-primary/40 text-xs italic mt-0.5 line-clamp-1">“' + escHtml(r.review_note) + '”</div>' : '')
          + '</td>'
          + '<td class="px-4 py-3 hidden sm:table-cell"><div class="text-primary/70 text-xs line-clamp-1">' + escHtml(r.user_name) + '</div><div class="text-primary/35 text-xs">' + escHtml(r.user_email) + '</div></td>'
          + '<td class="px-4 py-3 hidden md:table-cell text-primary/55 text-xs">' + (r.file_type === 'bahsul_masail' ? 'Bahsul Masail' : 'Kitab') + '</td>'
          + '<td class="px-4 py-3 hidden lg:table-cell text-primary/55 text-xs">' + escHtml(r.category_name || '—') + '</td>'
          + '<td class="px-4 py-3 text-center">' + statusBadge(r.status) + '</td>'
          + '<td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1">' + actBtns + '</div></td>'
          + '</tr>';
      }).join('');
      wrap.innerHTML = '<div class="overflow-x-auto"><table class="w-full text-sm">'
        + '<thead class="bg-cream border-b border-cream-dark text-primary/50 text-xs"><tr>'
        + '<th class="px-4 py-3 text-left font-semibold">Nama File</th>'
        + '<th class="px-4 py-3 text-left font-semibold hidden sm:table-cell">Pengirim</th>'
        + '<th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Tipe</th>'
        + '<th class="px-4 py-3 text-left font-semibold hidden lg:table-cell">Kategori</th>'
        + '<th class="px-4 py-3 text-center font-semibold">Status</th>'
        + '<th class="px-4 py-3 text-center font-semibold w-24">Aksi</th>'
        + '</tr></thead><tbody class="divide-y divide-cream-dark">' + rowsHtml + '</tbody></table></div>';
      pag.innerHTML = res.pages > 1
        ? '<span>' + res.total + ' kiriman</span><div class="flex gap-1">'
          + (_subPage > 1 ? '<button onclick="window._subPageG=' + (_subPage-1) + ';window.subLoadG&&window.subLoadG()" class="px-2 py-1 rounded bg-cream hover:bg-cream-dark text-primary/60">‹</button>' : '')
          + '<span class="px-2 py-1 text-primary/50">' + _subPage + '/' + res.pages + '</span>'
          + (_subPage < res.pages ? '<button onclick="window._subPageG=' + (_subPage+1) + ';window.subLoadG&&window.subLoadG()" class="px-2 py-1 rounded bg-cream hover:bg-cream-dark text-primary/60">›</button>' : '')
          + '</div>'
        : '<span>' + res.total + ' kiriman</span>';
      reicons();
    } catch(e) {
      if (handleAuthError(e)) return;
      wrap.innerHTML = '<p class="text-center py-12 text-red-500 text-sm">Gagal memuat: ' + escHtml(e.message) + '</p>';
    }
  }

  window.subLoadG  = subLoad;
  window._subPageG = _subPage;
  await subLoad();
}

export { renderAdminSubmissions };
