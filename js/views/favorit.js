import { $, $$, el, app, reicons, mobileFeedbackBanner, apiFetch, bookCard, navigate } from '../core/core.js';

export async function renderFavorit() {
  app().innerHTML = `
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-3xl font-bold text-primary mb-2 flex items-center gap-3">
            <i data-lucide="star" class="w-8 h-8 text-gold"></i> Kitab Favorit
          </h1>
          <p class="text-primary/60 text-sm">Daftar kitab yang Anda simpan sebagai favorit.</p>
        </div>
      </div>
      
      <div id="fav-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <!-- skeleton -->
        ${Array.from({length:4}, ()=>`
          <div class="bg-white rounded-2xl shadow-card p-5 animate-pulse">
            <div class="h-5 bg-cream-dark rounded w-3/4 mb-3"></div>
            <div class="h-4 bg-cream-dark rounded w-1/2 mb-4"></div>
            <div class="h-10 bg-cream-dark rounded-xl w-full mt-4"></div>
          </div>
        `).join('')}
      </div>
    </div>
    ${mobileFeedbackBanner}
  `;
  reicons();

  try {
    const favs = JSON.parse(localStorage.getItem('favorite_books') || '[]');
    const container = $('#fav-list');

    if (favs.length === 0) {
      container.innerHTML = `
        <div class="col-span-full py-16 text-center">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gold/10 mb-4">
            <i data-lucide="star" class="w-8 h-8 text-gold"></i>
          </div>
          <h3 class="text-xl font-bold text-primary mb-2">Belum ada favorit</h3>
          <p class="text-primary/60 text-sm max-w-md mx-auto mb-6">Anda belum menambahkan kitab apapun ke daftar favorit.</p>
          <button onclick="window.navigate('/katalog')" class="px-6 py-2.5 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary-light transition-colors">
            Jelajahi Katalog
          </button>
        </div>
      `;
      reicons();
      return;
    }

    // Fetch details for all favorite books
    const promises = favs.map(id => apiFetch({ action: 'book', id }).catch(() => null));
    const results = await Promise.all(promises);
    
    // Filter out failed requests or missing books
    const validBooks = results.filter(res => res && res.data).map(res => res.data);
    
    if (validBooks.length === 0) {
      container.innerHTML = '<p class="col-span-full text-center text-primary/60 py-10">Gagal memuat data kitab favorit.</p>';
      return;
    }

    container.innerHTML = validBooks.map(b => bookCard(b)).join('');
    reicons();

  } catch (error) {
    console.error('Error loading favorites:', error);
    $('#fav-list').innerHTML = '<p class="col-span-full text-center text-red-500 py-10">Terjadi kesalahan saat memuat favorit.</p>';
  }
}
