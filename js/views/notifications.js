import { apiFetch, navigate, app, reicons, escHtml } from '../core/core.js';

export async function renderNotifications() {
  if (!window.SESSION_USER) {
    navigate('/');
    return;
  }

  app().innerHTML = `
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-10">
      <div class="flex items-center gap-3 mb-8">
        <button onclick="navigate('/dashboard')" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gold/30 hover:bg-cream-dark transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 text-primary"></i>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-primary">Notifikasi</h1>
          <p class="text-sm text-primary/60">Pembaruan terbaru untuk Anda.</p>
        </div>
      </div>

      <div id="notif-content" class="space-y-4">
        <div class="flex justify-center py-10">
          <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-gold"></i>
        </div>
      </div>
    </div>
  `;
  reicons();

  loadNotifications();
}

async function loadNotifications() {
  try {
    const res = await apiFetch({ action: 'get_my_notifications' });
    const notifs = res.data || [];

    if (notifs.length === 0) {
      document.getElementById('notif-content').innerHTML = `
        <div class="bg-white rounded-2xl border border-gold/10 p-10 text-center">
            <i data-lucide="bell-off" class="w-12 h-12 text-primary/20 mx-auto mb-3"></i>
            <h3 class="text-lg font-semibold text-primary">Belum ada notifikasi</h3>
        </div>
      `;
      reicons();
      return;
    }

    let html = '';
    notifs.forEach(n => {
      const isUnread = parseInt(n.is_read) === 0;
      const date = new Date(n.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
      
      let icon = 'bell';
      if (n.type === 'submission') icon = 'upload-cloud';
      if (n.type === 'request') icon = 'help-circle';
      if (n.type === 'feedback') icon = 'message-square';

      html += `
        <div class="relative p-4 rounded-xl border ${isUnread ? 'bg-white border-gold shadow-sm' : 'bg-gray-50 border-gray-100'} transition-all cursor-pointer hover:shadow-md"
             onclick="handleNotifClick(${n.id}, ${isUnread})">
            ${isUnread ? '<div class="absolute top-4 right-4 w-2.5 h-2.5 rounded-full bg-red-500"></div>' : ''}
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 shrink-0 rounded-full bg-cream flex items-center justify-center border border-gold/20">
                    <i data-lucide="${icon}" class="w-5 h-5 text-gold"></i>
                </div>
                <div class="pr-6">
                    <h3 class="font-semibold text-primary text-sm">${escHtml(n.title)}</h3>
                    <p class="text-xs text-primary/50 mt-0.5 mb-2">${date}</p>
                    <p class="text-sm text-primary/80 whitespace-pre-wrap">${escHtml(n.message)}</p>
                </div>
            </div>
        </div>
      `;
    });

    document.getElementById('notif-content').innerHTML = html;
    reicons();
  } catch (err) {
    document.getElementById('notif-content').innerHTML = `
      <div class="p-4 bg-red-50 text-red-600 rounded-xl text-center text-sm">
        Gagal memuat notifikasi.
      </div>
    `;
  }
}

window.handleNotifClick = async function(id, isUnread) {
    if (isUnread) {
        try {
            const fd = new FormData();
            fd.append('id', id);
            const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
            await fetch('/api.php?action=mark_notification_read', {
              method: 'POST',
              headers: { 'X-CSRF-Token': csrfToken },
              body: fd,
            });
        } catch (e) {
            console.error(e);
        }
    }
    // Navigate to my-activity
    navigate('/my-activity');
};

window.renderNotifications = renderNotifications;
