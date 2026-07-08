import { apiFetch, navigate, app, reicons, escHtml, showPromptModal } from '../core/core.js';

export async function renderMyActivity() {
  if (!window.SESSION_USER) {
    navigate('/');
    return;
  }

  app().innerHTML = `
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
      <div class="flex items-center gap-3 mb-8">
        <button onclick="navigate('/dashboard')" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gold/30 hover:bg-cream-dark transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 text-primary"></i>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-primary">Aktivitas Saya</h1>
          <p class="text-sm text-primary/60">Pantau status kiriman, request, dan masukan Anda.</p>
        </div>
      </div>

      <div id="activity-content" class="space-y-6">
        <div class="flex justify-center py-10">
          <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-gold"></i>
        </div>
      </div>
    </div>
  `;
  reicons();

  try {
    const res = await apiFetch({ action: 'get_my_activities' });
    const { submissions, requests, feedbacks } = res.data;

    let html = '';

    const renderStatusBadge = (status) => {
        const s = status.toLowerCase();
        if (s === 'pending') return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 border border-yellow-200">Pending</span>';
        if (s === 'approved' || s === 'fulfilled' || s === 'resolved') return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 border border-green-200">Selesai</span>';
        if (s === 'rejected') return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 border border-red-200">Ditolak</span>';
        if (s === 'read') return '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200">Dibaca</span>';
        return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">${escHtml(status)}</span>`;
    };

    const renderList = (title, icon, items, typeLabel, apiType) => {
        if (!items || items.length === 0) return '';
        let listHtml = `<div class="bg-white rounded-2xl shadow-sm border border-gold/10 p-5 sm:p-6 mb-6">
            <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                <i data-lucide="${icon}" class="w-5 h-5 text-gold"></i> ${title}
            </h2>
            <div class="space-y-4">`;
        
        items.forEach(item => {
            const date = new Date(item.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' });
            listHtml += `
                <div class="p-4 rounded-xl border ${item.admin_reply ? 'border-gold/30 bg-cream/30' : 'border-gray-100 bg-gray-50/50'}">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-2">
                        <div>
                            <h3 class="font-semibold text-primary text-sm">${escHtml(item.title)}</h3>
                            <p class="text-xs text-primary/50 mt-1">${date} • ${typeLabel}</p>
                        </div>
                        <div class="shrink-0">
                            ${renderStatusBadge(item.status)}
                        </div>
                    </div>
                    ${item.admin_reply ? `
                        <div class="mt-3 p-3 bg-white rounded-lg border border-gold/20 text-sm">
                            <p class="text-xs font-bold text-gold mb-1">Histori Pesan:</p>
                            <p class="text-primary/80 whitespace-pre-wrap">${escHtml(item.admin_reply)}</p>
                            <div class="mt-3 pt-3 border-t border-gold/10 flex justify-end">
                                <button onclick="window.replyActivity('${apiType}', ${item.id})" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gold-dark hover:text-white bg-gold/10 hover:bg-gold border border-gold/20 rounded-lg transition-all shadow-sm hover:shadow-gold/30">
                                    <i data-lucide="reply" class="w-3 h-3"></i> Balas Pesan
                                </button>
                            </div>
                        </div>
                    ` : ''}
                </div>`;
        });
        
        listHtml += `</div></div>`;
        return listHtml;
    };

    html += renderList('Kiriman File', 'upload-cloud', submissions, 'Upload', 'submissions');
    html += renderList('Request Kitab', 'help-circle', requests, 'Request', 'requests');
    html += renderList('Feedback', 'message-square', feedbacks, 'Saran/Masukan', 'feedbacks');

    if (!html) {
        html = `
            <div class="bg-white rounded-2xl border border-gold/10 p-10 text-center">
                <i data-lucide="inbox" class="w-12 h-12 text-primary/20 mx-auto mb-3"></i>
                <h3 class="text-lg font-semibold text-primary">Belum ada aktivitas</h3>
                <p class="text-sm text-primary/50 mt-1">Anda belum pernah mengirim file, request, atau feedback.</p>
            </div>
        `;
    }

    document.getElementById('activity-content').innerHTML = html;
    reicons();
  } catch (err) {
    document.getElementById('activity-content').innerHTML = `
      <div class="p-4 bg-red-50 text-red-600 rounded-xl text-center text-sm">
        Gagal memuat aktivitas. Silakan coba lagi.
      </div>
    `;
  }
}
window.renderMyActivity = renderMyActivity;

window.replyActivity = function(apiType, id) {
  showPromptModal('Balas Pesan', 'Masukkan balasan Anda ke Admin di bawah ini:', async (replyText) => {
    try {
      const res = await window.postWithCsrf('user_reply_activity', { type: apiType, id: id, reply: replyText });
      const data = await res.json();
      if (data.success) {
        alert('Balasan berhasil dikirim.');
        renderMyActivity();
      } else {
        alert('Gagal: ' + (res.error || 'Unknown error'));
      }
    } catch (e) {
      alert('Terjadi kesalahan jaringan.');
    }
  });
};
