import { apiFetch, app, reicons, escHtml, paginationHtml, handleAuthError } from '../../core/core.js';
import { adminGuard, adminPost, adminToast, adminSpinner, adminNavBar, autoDir, bindAutoDir } from '../../core/AdminUtils.js';

// ══════════════════════════════════════════════════════════════
//  KELOLA KATEGORI  /admin/categories
// ══════════════════════════════════════════════════════════════

async function renderAdminCategories() {
  if (!adminGuard()) return;

  app().innerHTML = adminNavBar('/admin/categories') + `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-xl font-bold text-primary flex items-center gap-2">
            <i data-lucide="folder" class="w-5 h-5 text-gold"></i> Kelola Kategori
          </h1>
          <p class="text-primary/40 text-xs mt-1">Tambah, edit, dan hapus kategori kitab</p>
        </div>
        <button onclick="openCatModal(null)"
          class="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors shadow-sm">
          <i data-lucide="plus" class="w-4 h-4"></i> Tambah Kategori
        </button>
      </div>
      <div id="cat-grid"></div>
    </div>
    ${catModalHtml()}`;

  reicons();
  document.getElementById('cat-form')?.addEventListener('submit', async e => { e.preventDefault(); await catSubmit(); });
  await loadCatGrid();
}

async function loadCatGrid() {
  const grid = document.getElementById('cat-grid');
  if (!grid) return;
  grid.innerHTML = adminSpinner();
  try {
    const res = await apiFetch({ action: 'categories' });
    const cats = res.data || [];
    if (!cats.length) {
      grid.innerHTML = `<div class="bg-white rounded-2xl shadow-card py-16 text-center text-primary/25 text-sm"><i data-lucide="folder-open" class="w-10 h-10 mx-auto mb-3 opacity-25"></i><p>Belum ada kategori.</p></div>`;
      reicons(); return;
    }
    grid.innerHTML = `
      <div class="bg-white rounded-2xl shadow-card overflow-hidden">
        <div class="px-5 py-3.5 bg-cream/40 border-b border-cream-dark flex items-center justify-between">
          <span class="text-xs font-medium text-primary/50">${cats.length} kategori</span>
          <span class="text-xs text-primary/30">Urut berdasarkan catord ASC</span>
        </div>
        <div class="divide-y divide-cream-dark">
          ${cats.map(c => `
            <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-cream/40 transition-colors">
              <div class="w-9 h-9 rounded-xl bg-primary/7 flex items-center justify-center shrink-0">
                <i data-lucide="folder" class="w-4 h-4 text-primary/40"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-primary text-sm">${escHtml(c.name)}</div>
                <div class="text-xs text-primary/35 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                  <span class="flex items-center gap-1"><i data-lucide="book" class="w-3 h-3"></i>${c.book_count} kitab</span>
                  <span>Ord: ${c.catord}</span>
                  <span>Lvl: ${c.lvl}</span>
                </div>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <button onclick='openCatModal(${JSON.stringify(c).replace(/'/g,"\\'")})'
                  class="p-2 rounded-lg hover:bg-primary/8 text-primary/40 hover:text-primary transition-colors" title="Edit">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </button>
                <button onclick="catDelete(${c.id},'${escHtml(c.name).replace(/'/g,"\\'")}',${c.book_count})"
                  class="p-2 rounded-lg hover:bg-red-50 text-red-300 hover:text-red-600 transition-colors" title="Hapus">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </div>
            </div>`).join('')}
        </div>
      </div>`;
    reicons();
  } catch(e) {
    if (handleAuthError(e)) return;
    grid.innerHTML = `<div class="bg-white rounded-2xl shadow-card p-6 text-red-500 text-sm">${escHtml(e.message)}</div>`;
  }
}

function catModalHtml() {
  return `
    <div id="cat-modal" class="fixed inset-0 z-[300] hidden" style="background:rgba(15,34,24,.6);backdrop-filter:blur(5px);">
      <div class="flex items-center justify-center min-h-full p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b border-cream-dark">
            <h2 id="cat-modal-ttl" class="font-bold text-primary">Tambah Kategori</h2>
            <button onclick="closeCatModal()" class="p-2 rounded-lg hover:bg-cream-dark text-primary/40 transition-colors">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>
          <form id="cat-form" class="p-6 space-y-4">
            <input type="hidden" id="cm-id" />
            <div>
              <label class="block text-xs font-semibold text-primary/55 mb-1.5">Nama Kategori <span class="text-red-400">*</span></label>
              <input id="cm-name" type="text" placeholder="Nama kategori"
                class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-primary/55 mb-1.5">Urutan tampil</label>
                <input id="cm-catord" type="number" value="0" min="0"
                  class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-primary/55 mb-1.5">Level (1–5)</label>
                <input id="cm-lvl" type="number" value="1" min="1" max="5"
                  class="w-full px-4 py-2.5 rounded-xl border border-gold/30 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/15" />
              </div>
            </div>
            <div id="cm-msg" class="hidden text-sm rounded-xl px-4 py-2.5"></div>
            <div class="flex gap-3 pt-2">
              <button type="submit" id="cat-submit-btn"
                class="flex-1 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-light transition-colors flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan
              </button>
              <button type="button" onclick="closeCatModal()"
                class="px-5 py-2.5 rounded-xl border border-gold/25 text-sm text-primary/60 hover:bg-cream-dark transition-colors">
                Batal
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>`;
}

window.openCatModal = function(cat) {
  document.getElementById('cat-modal-ttl').textContent = cat ? 'Edit Kategori' : 'Tambah Kategori Baru';
  document.getElementById('cm-id').value     = cat?.id     || '';
  document.getElementById('cm-name').value   = cat?.name   || '';
  document.getElementById('cm-catord').value = cat?.catord ?? 0;
  document.getElementById('cm-lvl').value    = cat?.lvl    ?? 1;
  const msg = document.getElementById('cm-msg');
  if (msg) msg.className = 'hidden text-sm rounded-xl px-4 py-2.5';
  document.getElementById('cat-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('cm-name')?.focus(), 60);
  reicons();
};
window.closeCatModal = function() { document.getElementById('cat-modal')?.classList.add('hidden'); };

async function catSubmit() {
  const id     = document.getElementById('cm-id').value;
  const name   = document.getElementById('cm-name').value.trim();
  const catord = +document.getElementById('cm-catord').value || 0;
  const lvl    = +document.getElementById('cm-lvl').value    || 1;
  const msg    = document.getElementById('cm-msg');

  if (!name) {
    msg.textContent = 'Nama tidak boleh kosong.';
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
    return;
  }
  const btn = document.getElementById('cat-submit-btn');
  btn.disabled = true; btn.textContent = 'Menyimpan…';

  try {
    const data = await adminPost('admin_save_category', { id: id ? +id : 0, name, catord, lvl });
    if (data.success) {
      closeCatModal();
      adminToast(id ? 'Kategori diperbarui ✓' : 'Kategori ditambahkan ✓');
      await loadCatGrid();
    } else {
      msg.textContent = data.error || 'Gagal.';
      msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
    }
  } catch(e) {
    if (handleAuthError(e)) return;
    msg.textContent = 'Error: ' + e.message;
    msg.className = 'text-sm rounded-xl px-4 py-2.5 bg-red-50 text-red-600 border border-red-200';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Simpan'; reicons();
  }
}
window.catSubmit = catSubmit;

export { renderAdminCategories };
