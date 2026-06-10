export const $  = (sel, ctx = document) => ctx.querySelector(sel);
export const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
export const el = (tag, cls, html = '') => {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (html) e.innerHTML = html;
  return e;
};
export const app     = () => $('#app-content');
export const reicons = () => { if (window.lucide) lucide.createIcons(); };

export function isMobileViewport() {
  return window.matchMedia('(max-width: 767px)').matches;
}

export function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
