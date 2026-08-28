import * as AdminBooks from './views/admin/Books.js';
import * as AdminCategories from './views/admin/Categories.js';
import * as History from './views/admin/History.js';
import * as Activity from './views/admin/Activity.js';
import * as SearchLogs from './views/admin/SearchLogs.js';
import * as AskLogs from './views/admin/AskLogs.js';
import * as DownloadLogs from './views/admin/DownloadLogs.js';
import * as Submissions from './views/admin/Submissions.js';
import * as Requests from './views/admin/Requests.js';
import * as Feedbacks from './views/admin/Feedbacks.js';
import * as Analytics from './views/admin/Analytics.js';
import * as ExportData from './views/admin/Export.js';

export const adminRoutes = {
  '/admin':            () => window.navigate('/admin/books', true),
  '/admin/books':      AdminBooks.renderAdminBooks,
  '/admin/categories': AdminCategories.renderAdminCategories,
  '/admin/content':    () => window.navigate('/admin/books', true),
  '/admin/history':     History.renderAdminHistory,
  '/admin/activity':    Activity.renderAdminActivity,
  '/admin/search-logs': SearchLogs.renderAdminSearchLogs,
  '/admin/ask-logs':    AskLogs.renderAdminAskLogs,
  '/admin/download-logs': DownloadLogs.renderAdminDownloadLogs,
  '/admin/submissions': Submissions.renderAdminSubmissions,
  '/admin/requests':    Requests.renderAdminRequests,
  '/admin/feedback':    Feedbacks.renderAdminFeedbacks,
  '/admin/analytics':   Analytics.renderAnalytics,
  '/admin/export':      ExportData.renderAdminExport
};

if (typeof window.routes !== 'undefined') {
  Object.assign(window.routes, adminRoutes);
}

// Ensure first load routes properly if on an admin page
document.addEventListener('DOMContentLoaded', () => {
  const base = location.pathname.split('?')[0];
  if (adminRoutes[base] && typeof window.navigate === 'function') {
    // If core.js?v=2 hasn't triggered navigation yet, this will cover it.
    window.navigate(location.pathname + location.search, false);
  }
});
