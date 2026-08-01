import { app, reicons, escHtml, apiFetch } from '../../core/core.js?v=2';
import { adminNavBar } from '../../core/AdminUtils.js?v=2';

export async function renderAnalytics() {
  const u = window.SESSION_USER;
  if (!u || u.role !== 'admin') {
    window.location.href = '/';
    return;
  }

  app().innerHTML = `
    ${adminNavBar('/admin/analytics')}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-primary">Analisis Sistem</h1>
          <p class="text-sm text-slate-500 mt-1">Laporan keberhasilan Pencarian & AI</p>
        </div>
      </div>
      <div id="analytics-container" class="space-y-8">
        <div class="flex items-center justify-center p-12">
          <i data-lucide="loader-2" class="w-8 h-8 text-primary animate-spin"></i>
        </div>
      </div>
    </div>
  `;
  reicons();

  try {
    const res = await apiFetch({ action: 'admin_get_analytics' });
    if (!res.success) throw new Error(res.error || 'Gagal memuat data');
    
    renderCharts(res.data);
  } catch (err) {
    document.getElementById('analytics-container').innerHTML = `
      <div class="bg-red-50 text-red-600 p-4 rounded-xl border border-red-200">
        ${escHtml(err.message)}
      </div>
    `;
  }
}

function renderCharts(data) {
  const container = document.getElementById('analytics-container');
  
  let searchHtml = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
  for (const [type, stats] of Object.entries(data.search)) {
    const total = stats.total;
    const pSuccess = total > 0 ? Math.round((stats.success / total) * 100) : 0;
    const pFail = total > 0 ? 100 - pSuccess : 0;
    
    searchHtml += `
      <div class="bg-white p-6 rounded-2xl shadow-card border border-slate-100 flex flex-col items-center">
        <h3 class="font-bold text-primary mb-4 text-center">${escHtml(type)}</h3>
        ${renderPieChart(
            [pSuccess, pFail], 
            ['#22c55e', '#ef4444'], // Green for success, Red for fail
            ['Ditemukan', 'Tidak Ditemukan']
        )}
        <div class="mt-4 w-full space-y-2 text-sm">
          <div class="flex justify-between items-center text-slate-600">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> Ditemukan</span>
            <span class="font-semibold text-slate-800">${stats.success} (${pSuccess}%)</span>
          </div>
          <div class="flex justify-between items-center text-slate-600">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Tidak Ditemukan</span>
            <span class="font-semibold text-slate-800">${stats.failure} (${pFail}%)</span>
          </div>
          <div class="pt-2 border-t border-slate-100 flex justify-between items-center font-bold text-slate-700">
            <span>Total Pencarian</span>
            <span>${total}</span>
          </div>
        </div>
      </div>
    `;
  }
  searchHtml += '</div>';

  const ai = data.ai;
  const totalAi = ai.total;
  const pTerjawab = totalAi > 0 ? Math.round((ai.terjawab / totalAi) * 100) : 0;
  const pError = totalAi > 0 ? Math.round((ai.error / totalAi) * 100) : 0;
  const pTidakBisa = totalAi > 0 ? 100 - pTerjawab - pError : 0;

  const aiHtml = `
    <div class="bg-white p-6 rounded-2xl shadow-card border border-slate-100 flex flex-col items-center max-w-md mx-auto mt-8">
      <h3 class="font-bold text-primary mb-4 text-center">Respons AI (Ask AI)</h3>
      ${renderPieChart(
          [pTerjawab, pTidakBisa, pError], 
          ['#22c55e', '#f59e0b', '#ef4444'], // Green, Orange, Red
          ['Terjawab', 'Tidak Bisa', 'Error']
      )}
      <div class="mt-4 w-full space-y-2 text-sm">
        <div class="flex justify-between items-center text-slate-600">
          <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> Terjawab</span>
          <span class="font-semibold text-slate-800">${ai.terjawab} (${pTerjawab}%)</span>
        </div>
        <div class="flex justify-between items-center text-slate-600">
          <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Tdk Bisa Menjawab</span>
          <span class="font-semibold text-slate-800">${ai.tidak_bisa_menjawab} (${pTidakBisa}%)</span>
        </div>
        <div class="flex justify-between items-center text-slate-600">
          <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Error</span>
          <span class="font-semibold text-slate-800">${ai.error} (${pError}%)</span>
        </div>
        <div class="pt-2 border-t border-slate-100 flex justify-between items-center font-bold text-slate-700">
          <span>Total Pertanyaan</span>
          <span>${totalAi}</span>
        </div>
      </div>
    </div>
  `;

  container.innerHTML = `
    <div>
        <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i data-lucide="search" class="w-5 h-5 text-primary"></i> Pencarian
        </h2>
        ${searchHtml}
    </div>
    <div class="mt-12">
        <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2 justify-center">
            <i data-lucide="bot" class="w-5 h-5 text-primary"></i> Asisten AI
        </h2>
        ${aiHtml}
    </div>
  `;
  reicons();
}

/**
 * Render Pie Chart using CSS conic-gradient
 * percentages: array of ints summing to 100
 * colors: array of CSS color strings
 */
function renderPieChart(percentages, colors, labels) {
    if (percentages.reduce((a,b) => a+b, 0) === 0) {
        return `<div class="w-40 h-40 rounded-full bg-slate-100 flex items-center justify-center text-xs text-slate-400 text-center p-4">Tidak ada data</div>`;
    }

    let gradientStops = [];
    let currentDegree = 0;
    
    for (let i = 0; i < percentages.length; i++) {
        if (percentages[i] === 0) continue;
        const start = currentDegree;
        const end = currentDegree + percentages[i];
        gradientStops.push(`${colors[i]} ${start}% ${end}%`);
        currentDegree = end;
    }

    const gradient = gradientStops.join(', ');

    return `
      <div class="relative w-40 h-40 rounded-full shadow-inner" style="background: conic-gradient(${gradient});">
      </div>
    `;
}
