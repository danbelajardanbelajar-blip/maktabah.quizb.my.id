import { escHtml } from '../core/utils.js';

export const mobileFeedbackBanner = `
  <div class="md:hidden px-4 mb-24 mt-8">
    <a href="/feedback" data-route="/feedback" class="block bg-gradient-to-r from-cream-dark to-cream rounded-2xl p-4 border border-gold/20 shadow-sm relative overflow-hidden group no-underline">
      <div class="absolute inset-0 bg-gold/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
      <div class="flex items-center gap-4 relative z-10">
        <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center shrink-0 shadow-sm text-gold">
          <i data-lucide="message-square-plus" class="w-6 h-6"></i>
        </div>
        <div>
          <h4 class="text-sm font-bold text-primary mb-1">Menemukan Masalah?</h4>
          <p class="text-xs text-primary/70 leading-relaxed">Beritahu kami jika ada error di web ini atau ingin memberikan saran.</p>
        </div>
      </div>
    </a>
  </div>
`;

export function skeletonCards(n = 8) {
  return Array.from({ length: n }, () =>
    `<div class="bg-white rounded-2xl shadow-card p-4 space-y-3">
       <div class="skeleton h-5 rounded-lg w-4/5"></div>
       <div class="skeleton h-4 rounded w-3/5"></div>
       <div class="skeleton h-3 rounded w-2/5"></div>
     </div>`
  ).join('');
}

export function bookCard(b) {
  const title  = b.title  || 'بدون عنوان';
  const author = b.author || 'مجهول';
  const pages  = b.pages  ? b.pages + ' hal.' : '';
  const totalJuz   = b.total_juz || 1;
  const fmtBadge   = totalJuz > 1
    ? `<span class="dl-fmt-badge dl-fmt-zip">ZIP · ${totalJuz} juz</span>`
    : `<span class="dl-fmt-badge dl-fmt-docx">DOCX</span>`;
  const dlTitle    = totalJuz > 1
    ? \`Unduh \${totalJuz} file DOCX dalam ZIP\`
    : 'Unduh sebagai DOCX';
  return \`
    <div class="book-card bg-white rounded-2xl shadow-card p-5 flex flex-col gap-3 cursor-pointer"
         onclick="navigate('/kitab?id=\${b.bkid}')">
      <div class="flex-1">
        <div class="arabic text-primary font-semibold text-base leading-snug line-clamp-2 mb-1">\${escHtml(title)}</div>
        <div class="text-primary/60 text-xs font-medium line-clamp-1">\${escHtml(author)}</div>
      </div>
      <div class="flex items-center gap-2 mt-auto pt-2 border-t border-cream-dark">
        \${pages ? \`<span class="text-xs text-gold font-medium">\${escHtml(pages)}</span>\` : ''}
        <a href="/api.php?action=download_book&id=\${b.bkid}"
           class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-gold/20 text-gold hover:bg-gold/10 transition ml-auto"
           onclick="event.stopPropagation();"
           title="\${dlTitle}"
           aria-label="\${dlTitle}">
          <i data-lucide="download" class="w-3.5 h-3.5 shrink-0"></i>
          \${fmtBadge}
        </a>
      </div>
    </div>\`;
}

export function paginationHtml(current, total, onClickFn) {
  if (total <= 1) return '';
  const pages = [];
  const delta = 2;
  let prev = null;
  for (let p = 1; p <= total; p++) {
    if (p === 1 || p === total || (p >= current - delta && p <= current + delta)) {
      if (prev && p - prev > 1) pages.push('...');
      pages.push(p);
      prev = p;
    }
  }
  const btnBase = 'w-9 h-9 flex items-center justify-center rounded-lg text-sm font-medium transition-colors';
  const btns = pages.map(p => p === '...'
    ? \`<span class="\${btnBase} text-primary/40">…</span>\`
    : \`<button onclick="\${onClickFn}(\${p})"
         class="\${btnBase} \${p === current ? 'bg-primary text-white' : 'bg-white text-primary hover:bg-cream-dark border border-gold/20'}">\${p}</button>\`
  ).join('');
  return \`<div class="flex items-center justify-center gap-1 mt-8 flex-wrap">\${btns}</div>\`;
}
