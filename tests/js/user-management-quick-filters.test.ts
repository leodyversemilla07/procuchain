import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('user management quick filter chips show text labels with their icons', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/user-management.tsx'), 'utf8');

    assert.doesNotMatch(source, /xs:inline hidden/);
    assert.match(source, /<span>Verified<\/span>/);
    assert.match(source, /<span>2FA<\/span>/);
    assert.match(source, /<span>Admin<\/span>/);
    assert.match(source, /<span>Unverified<\/span>/);
});
