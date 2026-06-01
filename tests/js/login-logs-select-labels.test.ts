import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('login logs filters render readable selected labels in select triggers', () => {
 const source = readFileSync(path.join(process.cwd(), 'resources/js/components/admin/login-log-filter-bar.tsx'), 'utf8');

 assert.match(source, /const categoryLabels = \{ all: 'All Categories', recent: 'Recent', suspicious: 'Suspicious' \}/);
 assert.match(source, /const statusLabels: Record<string, string> = \{ all: 'All Statuses', success: 'Success', failed: 'Failed' \}/);

 assert.match(source, /<SelectValue placeholder="Category">\{\(\) => categoryLabels\[selectedCategory\]\}<\/SelectValue>/);
 assert.match(source, /<SelectValue placeholder="Status">\{\(\) => statusLabels\[selectedStatus\]\}<\/SelectValue>/);
 assert.match(source, /<SelectValue placeholder="Role">/);
 assert.match(source, /<SelectValue placeholder="Device">/);
 assert.match(source, /<SelectValue placeholder="Browser">/);
});
