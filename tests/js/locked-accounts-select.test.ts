import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('locked accounts filters show readable select labels and full-width triggers', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/locked-accounts.tsx'), 'utf8');

    assert.match(source, /const roleFilterLabels: Record<string, string> = \{/);
    assert.match(source, /const statusFilterLabels: Record<string, string> = \{/);
    assert.match(source, /<SelectTrigger className="w-full sm:w-\[180px\]">\s*<SelectValue placeholder="Filter by role">\s*\{\(\) => roleFilterLabels\[roleFilter\] \?\? 'Filter by role'\}\s*<\/SelectValue>/s);
    assert.match(source, /<SelectTrigger className="w-full sm:w-\[180px\]">\s*<SelectValue placeholder="Filter by status">\s*\{\(\) => statusFilterLabels\[statusFilter\] \?\? 'Filter by status'\}\s*<\/SelectValue>/s);
});