# Auth Error Handling Implementation

## Overview
Implemented automatic error detection and handling for 403 (Forbidden/Access Denied) errors across the app. When a 403 error occurs, the user is redirected to the login page with the error message displayed.

## How It Works

### 1. Error Detection (app.js & admin.js)
- **apiFetch()**: Modified to attach `status` property to error object
- **adminPost()**: Modified to attach `status` property to error object
- Both functions now throw errors with complete context

### 2. Error Handling (`handleAuthError()` function)
Located in **app.js** (line ~75-120), this helper function:
- Receives error object and fallback message
- Detects 403 errors by checking:
  - HTTP status code: `error.status === 403`
  - Error message patterns: "403", "Akses ditolak", "admin", "Diperlukan"
- Parses error message to extract clean readable text
- Stores message in localStorage: `localStorage.setItem('authErrorMsg', displayMsg)`
- Redirects to login: `window.location.href = '/auth.php?action=login&error=1'`
- Returns `true` if 403 handled, `false` otherwise

### 3. Catch Block Updates
All significant catch blocks now:
```javascript
catch(e) {
  if (handleAuthError(e)) return;  // Check for 403, redirect if needed
  // ... normal error handling ...
}
```

Updated locations:
- **app.js**: renderHome, loadKatalogBooks, renderDetail, loadReaderPage
- **admin.js**: renderAdminBooks, bkSubmit, bkDelete, contLoadEditor, contSaveContent, contDeletePage, contAddPage, _renderImportStep2, bkImportStep3, catSubmit, histLoad, actLoad, slLoad, subLoad, subReview

## Integration with auth.php

### Required Implementation in auth.php

The login page (auth.php) needs to:

1. **Read localStorage error message**:
```javascript
<script>
  const errorMsg = localStorage.getItem('authErrorMsg');
  if (errorMsg) {
    // Display error message to user
    // Then clear it so it doesn't persist
    localStorage.removeItem('authErrorMsg');
  }
</script>
```

2. **Show error notification** (recommended UI):
```html
<!-- Add this in the login form, right after <h1> -->
<div id="auth-error" style="display:none; margin-bottom:1rem;">
  <div style="background-color:#fee2e2; border:1px solid #fca5a5; padding:1rem; border-radius:0.5rem; color:#991b1b;">
    <strong>⚠️ Akses Ditolak</strong><br>
    <span id="auth-error-msg"></span>
  </div>
</div>

<script>
  const errorMsg = localStorage.getItem('authErrorMsg');
  if (errorMsg) {
    document.getElementById('auth-error-msg').textContent = errorMsg;
    document.getElementById('auth-error').style.display = 'block';
    localStorage.removeItem('authErrorMsg');
  }
</script>
```

3. **Alternative with Tailwind** (if using project design system):
```html
<div id="auth-error" class="hidden mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
  <div class="flex items-start gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
    <div>
      <strong>Akses Ditolak</strong><br>
      <span id="auth-error-msg"></span>
    </div>
  </div>
</div>

<script>
  const errorMsg = localStorage.getItem('authErrorMsg');
  if (errorMsg) {
    document.getElementById('auth-error-msg').textContent = errorMsg;
    document.getElementById('auth-error').classList.remove('hidden');
    localStorage.removeItem('authErrorMsg');
  }
</script>
```

## Error Message Examples

### Message Formats Expected:
1. `"Akses ditolak. Diperlukan hak admin."` - From API
2. `"API error 403: {\"error\":\"Akses ditolak. Diperlukan hak admin.\"}"` - From apiFetch
3. `"Akses ditolak"` - Fallback

### Parser Strategy:
- Tries to extract JSON error: `error.message.match(/:\s*(\{[^}]+\})/)`
- Falls back to extracting text after "Akses ditolak": `match(/Akses ditolak[.\s]*(.*?)$/)`
- Uses error message tail if nothing matches: `match(/:\s*(.+)$/)`
- Final fallback: Uses provided fallback message

## Testing

### Test Cases:

1. **Access admin page without permission**:
   - Navigate to `/admin/books` as non-admin user
   - Should redirect to `/auth.php?action=login&error=1`
   - Error message should display

2. **API call with 403 response**:
   - Admin trying to perform operation they don't have access to
   - Should see error message and redirect

3. **Session expired**:
   - If auth session expires during operation
   - Should catch 403 and redirect appropriately

## Security Considerations

- Error messages are stored in localStorage (client-side only)
- Message is cleared immediately after display
- No sensitive data in error messages
- Redirect happens server-side check (via 403 HTTP response)
- Each page load checks for auth independently

## Fallback Message

Default: `"Akses ditolak. Diperlukan hak admin."`

Can be customized per call:
```javascript
handleAuthError(error, 'Anda tidak memiliki izin untuk aksi ini');
```
