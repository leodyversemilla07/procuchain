import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('login logs filters render readable selected labels in select triggers', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/login-logs.tsx'), 'utf8');

    assert.match(source, /const categoryFilterLabels: Record<'all' \| 'recent' \| 'suspicious', string> = \{/);
    assert.match(source, /const statusFilterLabels: Record<'all' \| 'success' \| 'failed', string> = \{/);

    assert.match(source, /<SelectValue placeholder="Category">\{\(\) => selectedCategoryLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue placeholder="Status">\{\(\) => selectedStatusLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue placeholder="Role">\{\(\) => selectedRoleLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue placeholder="Device">\{\(\) => selectedDeviceTypeLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue placeholder="Browser">\{\(\) => selectedBrowserLabel\}<\/SelectValue>/);
});
