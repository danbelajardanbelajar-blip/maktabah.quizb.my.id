import { apiFetch, app, reicons, escHtml, paginationHtml, handleAuthError } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar, autoDir, bindAutoDir } from '../../core/AdminUtils.js';

// ══════════════════════════════════════════════════════════════
//  ADMIN: REVIEW REQUEST KITAB
// ══════════════════════════════════════════════════════════════
async function renderAdminRequests() {
  app().innerHTML = adminNavBar('/admin/requests') + `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-bold text-primary flex items-center gap-2">
            <i data-lucide="help-circle" class="w-5 h-5 text-gold"></i> Request Kitab
          </h1>
          <p class="text-primary/45 text-xs mt-0.5">Kelola permohonan kitab dari pengguna</p>
        </div>
        <div class="flex gap-2 flex-wrap" id="req-tabs"></div>
      </div>
      <div class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div id="req-table-wrap">
          <div class="py-16 text-center"><i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin text-gold/50"></i></div>
        </div>
        <div id="req-pagination" class="px-4 py-3 border-t border-cream-dark flex items-center justify-between text-xs text-primary/40"></div>
      </div>
    </div>`;

  reicons();

  let _reqStatus = '';
  let _reqPage   = 1;

  // Build status tabs
  const tabDefs = [
    { s: '',         label: 'Semua' },
    { s: 'pending',  label: 'Menunggu' },
    { s: 'fulfilled',label: 'Dipenuhi' },
    { s: 'rejected', label: 'Ditolak' },
  ];
  const tabsEl = document.getElementById('req-tabs');
  tabsEl.innerHTML = tabDefs.map(t =>
    `<button data-req-tab="${t.s}" onclick="window.reqSetStatus('${t.s}')"
      class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors
      ${t.s === '' ? 'bg-primary text-white border-primary' : 'bg-white text-primary/60 border-gold/30 hover:border-primary/30'}">
      ${t.label}</button>`
  ).join('');

  window.reqSetStatus = function(s) {
    _reqStatus = s;
    _reqPage   = 1;
    tabsEl.querySelectorAll('[data-req-tab]').forEach(btn => {
      const active = btn.dataset.reqTab === s;
      btn.className = 'px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors '
        + (active ? 'bg-primary text-white border-primary' : 'bg-white text-primary/60 border-gold/30 hover:border-primary/30');
    });
    reqLoad();
  };

  window.reqUpdateStatus = async function(id, action) {
    let confirmMsg = action === 'fulfilled' ? 'Tandai request ini sebagai Dipenuhi?' : 'Tolak request ini?';
    if (!confirm(confirmMsg)) return;
    try {
      const res = await adminPost('admin_update_request_status', { id: id, status: action });
      if (res.error) throw new Error(res.error);
      
      adminToast(action === 'fulfilled' ? 'Request ditandai sebagai Dipenuhi ✓' : 'Request ditolak');
      reqLoad();
    } catch(e) {
      if (handleAuthError(e)) return;
      adminToast('Gagal: ' + e.message, 'error'); 
    }
  };

  window.reqReply = async function(id) {
    const reply = prompt('Masukkan balasan/catatan untuk request ini:');
    if (reply === null || reply.trim() === '') return;
    try {
      const res = await adminPost('admin_reply_request', { id: id, reply: reply.trim() });
      if (res.error) throw new Error(res.error);
      adminToast('Balasan terkirim ✓');
      reqLoad();
    } catch(e) {
      if (handleAuthError(e)) return;
      adminToast('Gagal: ' + e.message, 'error'); 
    }
  };

  const statusBadge = s => {
    const cls = { pending: 'bg-yellow-100 text-yellow-700', fulfilled: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-600' };
    const lbl = { pending: 'Menunggu', fulfilled: 'Dipenuhi', rejected: 'Ditolak' };
    return '<span class="px-2 py-0.5 rounded-full text-xs font-semibold ' + (cls[s]||'') + '">' + (lbl[s]||s) + '</span>';
  };

  async function reqLoad() {
    const wrap = document.getElementById('req-table-wrap');
    const pag  = document.getElementById('req-pagination');
    wrap.innerHTML = '<div class="py-16 text-center"><i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin text-gold/50"></i></div>';
    reicons();
    try {
      const params = { action: 'admin_get_requests', page: _reqPage };
      if (_reqStatus) params.status = _reqStatus;
      const res  = await apiFetch(params);
      const rows = res.data || [];
      if (!rows.length) {
        wrap.innerHTML = '<div class="py-16 text-center"><i data-lucide="help-circle" class="w-10 h-10 mx-auto mb-3 text-primary/15"></i><p class="text-primary/30 text-sm">Tidak ada request ditemukan.</p></div>';
        pag.innerHTML = '';
        reicons(); return;
      }
      const rowsHtml = rows.map(r => {
        const fulfillBtn = '<button onclick="reqUpdateStatus(' + r.id + ', \'fulfilled\')" title="Tandai Dipenuhi" class="p-1.5 rounded-lg hover:bg-green-100 text-green-700 transition-colors flex items-center gap-1.5 px-2 font-medium text-xs"><i data-lucide="check-circle" class="w-4 h-4"></i> Penuhi</button>';
        const rejectBtn  = '<button onclick="reqUpdateStatus(' + r.id + ', \'rejected\')" title="Tolak" class="p-1.5 rounded-lg hover:bg-red-100 text-red-700 transition-colors"><i data-lucide="x-circle" class="w-4 h-4"></i></button>';
        const replyBtn   = '<button onclick="reqReply(' + r.id + ')" title="Balas" class="p-1.5 rounded-lg hover:bg-blue-100 text-blue-700 transition-colors"><i data-lucide="message-square" class="w-4 h-4"></i></button>';
        
        let actBtns = replyBtn;
        if (r.status === 'pending') {
          actBtns += fulfillBtn + rejectBtn;
        }

        return '<tr class="hover:bg-cream/60 transition-colors">'
          + '<td class="px-4 py-3"><div class="font-medium text-primary line-clamp-1">' + escHtml(r.title) + '</div>'
          + '<div class="text-primary/40 text-xs mt-0.5">' + (r.author_or_category ? escHtml(r.author_or_category) + ' · ' : '') + (r.created_at||'').slice(0,10) + '</div>'
          + (r.description ? '<div class="text-primary/40 text-xs mt-0.5 line-clamp-1">“' + escHtml(r.description) + '”</div>' : '')
          + (r.admin_reply ? '<div class="text-gold text-[10px] mt-1 bg-gold/5 p-1 rounded border border-gold/20">Balasan: ' + escHtml(r.admin_reply) + '</div>' : '')
          + '</td>'
          + '<td class="px-4 py-3"><div class="text-primary/70 text-xs">' + escHtml(r.user_email) + '</div></td>'
          + '<td class="px-4 py-3 hidden md:table-cell text-primary/55 text-xs">' + (r.request_type === 'bahsul_masail' ? 'Bahsul Masail' : 'Kitab') + '</td>'
          + '<td class="px-4 py-3 text-center">' + statusBadge(r.status) + '</td>'
          + '<td class="px-4 py-3 text-center"><div class="flex items-center justify-center gap-1">' + actBtns + '</div></td>'
          + '</tr>';
      }).join('');
      wrap.innerHTML = '<div class="overflow-x-auto"><table class="w-full text-sm">'
        + '<thead class="bg-cream border-b border-cream-dark text-primary/50 text-xs"><tr>'
        + '<th class="px-4 py-3 text-left font-semibold">Judul Request</th>'
        + '<th class="px-4 py-3 text-left font-semibold">Email</th>'
        + '<th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Tipe</th>'
        + '<th class="px-4 py-3 text-center font-semibold">Status</th>'
        + '<th class="px-4 py-3 text-center font-semibold w-32">Aksi</th>'
        + '</tr></thead><tbody class="divide-y divide-cream-dark">' + rowsHtml + '</tbody></table></div>';
      pag.innerHTML = res.pages > 1
        ? '<span>' + res.total + ' request</span><div class="flex gap-1">'
          + (_reqPage > 1 ? '<button onclick="window._reqPageG=' + (_reqPage-1) + ';window.reqLoadG&&window.reqLoadG()" class="px-2 py-1 rounded bg-cream hover:bg-cream-dark text-primary/60">‹</button>' : '')
          + '<span class="px-2 py-1 text-primary/50">' + _reqPage + '/' + res.pages + '</span>'
          + (_reqPage < res.pages ? '<button onclick="window._reqPageG=' + (_reqPage+1) + ';window.reqLoadG&&window.reqLoadG()" class="px-2 py-1 rounded bg-cream hover:bg-cream-dark text-primary/60">›</button>' : '')
          + '</div>'
        : '<span>' + res.total + ' request</span>';
      reicons();
    } catch(e) {
      if (handleAuthError(e)) return;
      wrap.innerHTML = '<p class="text-center py-12 text-red-500 text-sm">Gagal memuat: ' + escHtml(e.message) + '</p>';
    }
  }

  window.reqLoadG  = reqLoad;
  window._reqPageG = _reqPage;
  await reqLoad();
}

export { renderAdminRequests };
