import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('user management filters show readable select labels', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/user-management.tsx'), 'utf8');

    assert.match(source, /const roleFilterLabels: Record<string, string> = \{/);
    assert.match(source, /const verificationFilterLabels: Record<string, string> = \{/);
    assert.match(source, /const twoFactorFilterLabels: Record<string, string> = \{/);

    assert.match(source, /<SelectValue placeholder="Filter by role">\{\(\) => roleFilterLabels\[roleFilter\] \?\? 'Filter by role'\}<\/SelectValue>/);
    assert.match(source, /<SelectValue placeholder="Email status">\{\(\) => verificationFilterLabels\[verificationFilter\] \?\? 'Email status'\}<\/SelectValue>/);
    assert.match(source, /<SelectValue placeholder="2FA status">\{\(\) => twoFactorFilterLabels\[twoFactorFilter\] \?\? '2FA status'\}<\/SelectValue>/);
});
