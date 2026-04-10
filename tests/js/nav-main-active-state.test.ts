import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('nav main active state compares normalized pathnames so query strings do not break highlighting', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/nav-main.tsx'), 'utf8');

    assert.match(source, /const normalizePathname = \(url: string\): string => \{/);
    assert.match(source, /const currentPath = normalizePathname\(page\.url\);/);
    assert.match(source, /isActive=\{normalizePathname\(item\.href\) === currentPath\}/);
});
