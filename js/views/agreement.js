export function renderAgreement() {
  const u = window.SESSION_USER;
  if (!u) {
    navigate('/', false);
    return;
  }
  if (u.agreed_tos == 1) {
    navigate('/dashboard', false);
    return;
  }

  app().innerHTML = `
    <div class="max-w-3xl mx-auto py-12 px-4 sm:px-6 animate-fade-in relative z-10 pt-24 pb-32">
      <div class="bg-white/80 backdrop-blur-xl border border-gold/30 rounded-3xl shadow-2xl p-8 sm:p-12 relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute top-0 right-0 -mt-12 -mr-12 w-48 h-48 bg-gold/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 -mb-12 -ml-12 w-48 h-48 bg-gold/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="text-center mb-10 relative z-10">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gold/20 text-gold-dark mb-6 shadow-inner border border-gold/40">
            ${reicons.shield_check}
          </div>
          <h1 class="text-3xl sm:text-4xl font-bold text-primary mb-4">Persetujuan Layanan</h1>
          <p class="text-primary/70 text-lg">Ahlan wa sahlan, ${escHtml(u.name)}. Mohon baca dan setujui persyaratan berikut untuk melanjutkan.</p>
        </div>

        <div class="prose prose-gold max-w-none text-primary/80 bg-cream/50 p-6 sm:p-8 rounded-2xl border border-gold/20 shadow-inner max-h-96 overflow-y-auto mb-10 text-sm sm:text-base leading-relaxed relative z-10 custom-scrollbar">
          <h3 class="text-primary font-bold mb-4 text-lg">1. Pendahuluan</h3>
          <p class="mb-4">Selamat datang di Maktabah As-Sunniyyah. Layanan perpustakaan digital ini disediakan untuk memfasilitasi pencarian dan pengkajian literatur keislaman. Dengan menekan tombol setuju, Anda memahami dan mengikatkan diri pada syarat dan ketentuan yang berlaku.</p>
          
          <h3 class="text-primary font-bold mb-4 text-lg">2. Penggunaan Layanan</h3>
          <p class="mb-4">Anda setuju untuk menggunakan layanan ini semata-mata untuk tujuan yang sah, edukatif, dan tidak melanggar hak kekayaan intelektual (HAKI) dari penerbit maupun penulis. Segala bentuk komersialisasi terhadap file yang diunduh dari situs ini dilarang keras tanpa izin resmi.</p>
          
          <h3 class="text-primary font-bold mb-4 text-lg">3. Privasi & Data Pengguna</h3>
          <p class="mb-4">Kami menghargai privasi Anda. Informasi seperti alamat email dan nama yang Anda berikan melalui login Google akan digunakan sebatas untuk komunikasi layanan (notifikasi sistem) dan administrasi akun. Kami tidak akan menjual atau menyebarkan data pribadi Anda ke pihak ketiga.</p>
          
          <h3 class="text-primary font-bold mb-4 text-lg">4. Pengiriman & Permintaan (Feedback/Submit/Request)</h3>
          <p class="mb-4">Setiap file atau teks yang Anda kirimkan melalui fitur portal ini akan ditinjau oleh Admin. Admin berhak untuk menyetujui, menolak, atau memodifikasi masukan demi menjaga kualitas dan validitas data dalam perpustakaan.</p>

          <h3 class="text-primary font-bold mb-4 text-lg">5. Perubahan Kebijakan</h3>
          <p>Maktabah As-Sunniyyah berhak untuk memperbarui persyaratan ini sewaktu-waktu. Perubahan akan diinformasikan melalui website atau email terdaftar.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-6 relative z-10 pt-6 border-t border-gold/20">
          <button onclick="window.location.href='/auth.php?action=logout'" class="w-full sm:w-auto px-6 py-3 text-red-600 hover:bg-red-50 font-medium rounded-xl transition-all border border-transparent hover:border-red-200">
            Batal & Keluar
          </button>
          
          <button id="btnAgreeTOS" class="w-full sm:w-auto px-10 py-3.5 bg-gradient-to-r from-gold-dark to-gold text-white font-bold rounded-xl shadow-lg hover:shadow-gold/40 hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
            Saya Setuju & Lanjutkan
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
          </button>
        </div>
      </div>
    </div>
  `;

  $('#btnAgreeTOS').addEventListener('click', async () => {
    const btn = $('#btnAgreeTOS');
    const originalText = btn.innerHTML;
    btn.innerHTML = `<span class="animate-spin inline-block mr-2 border-2 border-white/30 border-t-white rounded-full w-4 h-4"></span> Memproses...`;
    btn.disabled = true;

    try {
      const response = await fetch('/api.php?action=agree_tos', { method: 'POST' });
      const res = await response.json();
      if (res.success) {
        // Update session lokal
        window.SESSION_USER.agreed_tos = 1;
        // Animasi sukses
        btn.innerHTML = `${reicons.check} Berhasil`;
        btn.classList.replace('from-gold-dark', 'from-green-600');
        btn.classList.replace('to-gold', 'to-green-500');
        btn.classList.replace('hover:shadow-gold/40', 'hover:shadow-green-500/40');
        
        setTimeout(() => {
          navigate('/dashboard');
        }, 1000);
      } else {
        alert('Gagal memproses persetujuan: ' + (res.error || 'Unknown error'));
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    } catch (err) {
      alert('Terjadi kesalahan jaringan.');
      btn.innerHTML = originalText;
      btn.disabled = false;
    }
  });
}
