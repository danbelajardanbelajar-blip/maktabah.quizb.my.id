
export const _RTL_RE = /[֑-߿יִ-﷽ﹰ-ﻼ]/;
export function autoDir(el, text) {
  if (!el) return;
  const val = text !== undefined ? text : el.value;
  el.dir = _RTL_RE.test(val.trim()) ? 'rtl' : 'ltr';
}
export function bindAutoDir(el) {
  if (!el) return;
  autoDir(el);
  el.addEventListener('input', () => autoDir(el));
}
export function adminGuard() {
  const u = window.SESSION_USER;
  if (!u) { window.location.href = '/auth.php?action=login'; return false; }
  if (u.role !== 'admin') {
    app().innerHTML = `
      <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4 gap-4">
        <i data-lucide="shield-alert" class="w-16 h-16 text-red-300"></i>
        <h1 class="text-xl font-bold text-primary">Akses Ditolak</h1>
        <p class="text-primary/50 text-sm">Halaman ini hanya untuk admin.</p>
        <a href="/" data-route="/" class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-medium">Kembali</a>
      </div>`;
    reicons(); return false;
  }
  return true;
}
export async function adminPost(action, body) {
  const res = await fetch(`/api.php?action=${action}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await res.json();
  if (!res.ok) {
    const error = new Error(data.error || `Request failed with status ${res.status}`);
    error.status = res.status;
    throw error;
  }
  return data;
}
export function adminToast(msg, type = 'success') {
  let t = document.getElementById('admin-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'admin-toast';
    Object.assign(t.style, {
      position: 'fixed', bottom: '84px', left: '50%',
      transform: 'translateX(-50%)', zIndex: '9999',
      transition: 'opacity .3s ease', pointerEvents: 'none',
    });
    document.body.appendChild(t);
  }
  const bg = type === 'success' ? '#1a3a2a' : '#dc2626';
  t.innerHTML = `<div style="display:flex;align-items:center;gap:10px;padding:12px 22px;border-radius:14px;font-size:13px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,.2);background:${bg};color:#fff;white-space:nowrap;">${type === 'success' ? '✓' : '✕'} ${escHtml(msg)}</div>`;
  t.style.opacity = '1';
  clearTimeout(t._tmr);
  t._tmr = setTimeout(() => { t.style.opacity = '0'; }, 3200);
}
export function adminSpinner() {
  return `<div class="p-10 flex justify-center"><div class="w-8 h-8 border-[3px] border-gold/20 border-t-gold rounded-full animate-spin"></div></div>`;
}
export function adminNavBar(active) {
  const u = window.SESSION_USER;
  const isAdmin = u && u.role === 'admin';
  const tabs = [
    { r: '/dashboard',        icon: 'layout-dashboard', label: 'Dashboard',       adminOnly: false },
    { r: '/admin/books',      icon: 'book',             label: 'Kitab',           adminOnly: false },
    { r: '/admin/categories', icon: 'folder',           label: 'Kategori',        adminOnly: false },
    { r: '/admin/history',    icon: 'history',          label: 'CRUD History',    adminOnly: true,  desktopOnly: true },
    { r: '/admin/activity',   icon: 'activity',         label: 'Aktivitas',       adminOnly: true },
    { r: '/admin/search-logs', icon: 'search',          label: 'Pencarian',       adminOnly: true },
    { r: '/admin/download-logs', icon: 'download',      label: 'Download',        adminOnly: true },
    { r: '/admin/submissions', icon: 'inbox',           label: 'Review Kiriman',  adminOnly: true },
    { r: '/admin/requests',    icon: 'help-circle',     label: 'Request Kitab',   adminOnly: true },
    { r: '/admin/feedback',    icon: 'message-square',  label: 'Review Feedback', adminOnly: true },
  ];
  return `
    <div class="bg-white border-b border-gold/15 sticky top-16 z-40 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center overflow-x-auto no-scrollbar">
        ${tabs.filter(t => (!t.adminOnly || isAdmin)).map(t => {
          const on = active === t.r;
          const deskOnly = t.desktopOnly ? 'hidden md:flex' : 'flex';
          return \`<a href="${t.r}" data-route="${t.r}"
            class="${deskOnly} items-center gap-1.5 px-3 sm:px-4 py-3.5 text-[13px] font-semibold whitespace-nowrap border-b-2 transition-colors no-underline shrink-0
              ${on ? 'border-gold text-primary' : 'border-transparent text-primary/45 hover:text-primary hover:border-gold/30'}">
            <i data-lucide="${t.icon}" class="w-4 h-4"></i>${t.label}
          </a>\`;
        }).join('')}
      </div>
    </div>`;
}

window.adminPost = adminPost;
window.adminToast = adminToast;
window.adminSpinner = adminSpinner;
