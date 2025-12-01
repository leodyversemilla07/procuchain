# Bug Reports

## Search Functionality Issue
**Description**: When performing a search, the page always reloads, preventing users from scrolling down to view the search results. This is annoying and disrupts the user experience.

**Root Cause**:
- Procurement list search uses `router.visit()` causing page reloads.
- Blockchain explorer search uses `router.get()` causing page reloads.
- Page reloads reset scroll position, preventing users from scrolling through results.
- `usePoll` hooks were missing `preserveScroll: true`, causing scroll reset during auto-refresh.
- `router.reload()` calls were missing `preserveScroll: true`.

**Fix Applied**:
- Added `preserveScroll: true` to `router.visit()` calls in `procurements-list.tsx` to maintain scroll position on reload.
- Added `preserveScroll: true` to `usePoll` hook in `procurements-list.tsx` to prevent scroll reset during auto-refresh.
- Changed blockchain explorer search to use AJAX (`fetch`) to load results without page reload, displaying them in a new "Search" tab.
- Added `preserveScroll: true` to `usePoll` hook in `blockchain-explorer.tsx`.
- Added `preserveScroll: true` to all `router.reload()` calls in `blockchain-explorer.tsx`.

**Status**: Fixed - search now preserves scroll position or loads results without reload.

## Dashboard Loading Performance
**Description**: The dashboard takes a long time to load, which will become a significant problem as more data is added to the system.

**Root Cause**:
- Dashboards load large datasets from blockchain streams without pagination.
- Heavy calculations for stats and procurements on every load.
- Deferred props help but initial load is still slow.

**Fix Applied**:
- Reduced stream query limits (status_items: 10k→1k, document_items: 2k→500).
- Optimized service methods with pagination and filtering.
- Implemented deferred loading for heavy analytics calculations.
- Added proper caching and performance monitoring.

**Status**: Fixed - dashboard now loads significantly faster with reduced memory usage and deferred analytics.