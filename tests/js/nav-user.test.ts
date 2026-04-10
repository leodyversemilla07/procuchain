import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('nav user dropdown uses the current anchor width CSS variable', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/nav-user.tsx'), 'utf8');

    assert.match(source, /w-\(--anchor-width\)/);
    assert.doesNotMatch(source, /radix-dropdown-menu-trigger-width/);
});
