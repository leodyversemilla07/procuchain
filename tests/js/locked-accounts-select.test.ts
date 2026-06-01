import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('locked accounts filters show readable select labels and full-width triggers', () => {
 const hookSource = readFileSync(path.join(process.cwd(), 'resources/js/hooks/use-locked-accounts.tsx'), 'utf8');
 const filterBarSource = readFileSync(path.join(process.cwd(), 'resources/js/components/admin/locked-accounts-filter-bar.tsx'), 'utf8');

 assert.match(hookSource, /const roleFilterLabels: Record<string, string> = \{/);
 assert.match(hookSource, /const statusFilterLabels: Record<string, string> = \{/);
 assert.match(filterBarSource, /<SelectTrigger className="w-full sm:w-\[180px\]">\s*<SelectValue placeholder="Filter by role">\s*\{\(\) => roleFilterLabels\[roleFilter\] \?\? 'Filter by role'\}\s*<\/SelectValue>/s);
 assert.match(filterBarSource, /<SelectTrigger className="w-full sm:w-\[180px\]">\s*<SelectValue placeholder="Filter by status">\s*\{\(\) => statusFilterLabels\[statusFilter\] \?\? 'Filter by status'\}\s*<\/SelectValue>/s);
});