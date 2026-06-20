import { apiFetch, app, reicons, escHtml, paginationHtml, handleAuthError } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar, autoDir, bindAutoDir } from '../../core/AdminUtils.js';

// ══════════════════════════════════════════════════════════════
//  REVIEW FEEDBACK PENGUNJUNG
// ══════════════════════════════════════════════════════════════
async function renderAdminFeedbacks() {
  const u = window.SESSION_USER;
  if (!u || u.role !== 'admin') { window.location.href = '/'; return; }

  app().innerHTML = `
    ${adminNavBar('/admin/feedback')}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-primary flex items-center gap-2">
          <i data-lucide="message-square" class="w-6 h-6 text-gold"></i>
          Review Feedback
        </h1>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gold/15 p-4 sm:p-6">
        <div id="fb-list" class="space-y-4 text-sm text-primary/70">Memuat...</div>
      </div>
    </div>
  `;
  reicons();

  const listEl = document.getElementById('fb-list');
  try {
    const res = await apiFetch({ action: 'admin_get_feedbacks' });
    const items = res.data || [];
    
    if (!items.length) {
      listEl.innerHTML = '<div class="text-center py-12 text-primary/40">Belum ada feedback.</div>';
      return;
    }

    const statCol = {
      'pending': '<span class="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded text-xs">Pending</span>',
      'read': '<span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-xs">Read</span>',
      'resolved': '<span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs">Resolved</span>',
    };

    let html = '';
    items.forEach(it => {
      const isPending = it.status === 'pending';
      html += `
        <div class="border border-gold/15 rounded-xl p-4 flex flex-col sm:flex-row gap-4 justify-between items-start ${isPending ? 'bg-cream/20' : 'bg-white'}">
          <div>
            <div class="flex items-center gap-3 mb-1">
              <span class="font-bold text-primary">${escHtml(it.email)}</span>
              ${statCol[it.status] || ''}
              <span class="text-xs text-primary/40">${new Date(it.created_at).toLocaleString()}</span>
            </div>
            <p class="text-primary/80 whitespace-pre-wrap">${escHtml(it.content)}</p>
            ${it.admin_reply ? `<div class="mt-2 text-xs bg-gold/10 p-2 rounded border border-gold/20 text-gold-dark font-medium">Balasan: ${escHtml(it.admin_reply)}</div>` : ''}
          </div>
          <div class="flex items-center gap-2 shrink-0">
            ${isPending ? `<button onclick="updateFbStat(${it.id}, 'read')" class="bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg hover:bg-blue-100 text-xs font-semibold">Tandai Dibaca</button>` : ''}
            <button onclick="fbReply(${it.id})" class="bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg hover:bg-indigo-100 text-xs font-semibold">Balas</button>
            ${it.status !== 'resolved' ? `<button onclick="updateFbStat(${it.id}, 'resolved')" class="bg-green-50 text-green-700 px-3 py-1.5 rounded-lg hover:bg-green-100 text-xs font-semibold">Selesai</button>` : ''}
            <button onclick="delFb(${it.id})" class="text-red-500 hover:bg-red-50 p-1.5 rounded-lg"><i data-lucide="trash" class="w-4 h-4"></i></button>
          </div>
        </div>
      `;
    });
    listEl.innerHTML = html;
    reicons();
  } catch (e) {
    if (handleAuthError(e)) return;
    listEl.innerHTML = '<div class="text-center py-12 text-red-500">Gagal memuat: ' + escHtml(e.message) + '</div>';
  }
}

window.updateFbStat = async function(id, status) {
  try {
    await adminPost('admin_update_feedback_status', { id, status });
    adminToast('Status diperbarui');
    renderAdminFeedbacks();
  } catch(e) {
    alert('Gagal: ' + e.message);
  }
};

window.fbReply = async function(id) {
  const reply = prompt('Masukkan balasan untuk feedback ini:');
  if (reply === null || reply.trim() === '') return;
  try {
    const res = await adminPost('admin_reply_feedback', { id: id, reply: reply.trim() });
    if (res.error) throw new Error(res.error);
    adminToast('Balasan terkirim ✓');
    renderAdminFeedbacks();
  } catch(e) {
    alert('Gagal: ' + e.message);
  }
};

window.delFb = async function(id) {
  if (!confirm('Hapus feedback ini?')) return;
  try {
    await adminPost('admin_delete_feedback', { id });
    adminToast('Feedback dihapus');
    renderAdminFeedbacks();
  } catch(e) {
    alert('Gagal: ' + e.message);
  }
};

export { renderAdminFeedbacks };
