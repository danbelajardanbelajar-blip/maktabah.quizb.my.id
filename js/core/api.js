import { API } from './config.js';

export async function apiFetch(params) {
  const url = API + '?' + new URLSearchParams(params).toString();
  const res = await fetch(url);
  if (!res.ok) {
    const text = await res.text();
    const error = new Error('API error ' + res.status + ': ' + text);
    error.status = res.status;
    error.responseText = text;
    throw error;
  }
  return res.json();
}

export function handleAuthError(error, fallbackMsg = 'Akses ditolak. Diperlukan hak admin.') {
  const errorMsg = error?.message || error?.toString() || '';
  const statusCode = error?.status || 0;
  
  const is403 = statusCode === 403 || 
                errorMsg.includes('403') || 
                errorMsg.includes('Akses ditolak') ||
                errorMsg.includes('admin') ||
                errorMsg.includes('Diperlukan');
  
  if (is403) {
    let displayMsg = fallbackMsg;
    try {
      const jsonMatch = errorMsg.match(/:\s*(\{[^}]+\})/);
      if (jsonMatch) {
        const parsed = JSON.parse(jsonMatch[1]);
        if (parsed.error) displayMsg = parsed.error;
      } else if (errorMsg.includes('Akses ditolak')) {
        const match = errorMsg.match(/Akses ditolak[.\s]*(.*?)$/);
        if (match) displayMsg = match[0];
      }
    } catch (e) {
      const match = errorMsg.match(/:\s*(.+)$/);
      if (match) displayMsg = match[1];
    }
    
    localStorage.setItem('authErrorMsg', displayMsg);
    window.location.href = '/auth.php?action=login&error=1';
    return true;
  }
  
  return false;
}
