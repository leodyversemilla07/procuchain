import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('app content constrains sidebar layout width to prevent page-level horizontal overflow', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/app-content.tsx'), 'utf8');

    assert.match(source, /SidebarInset className=\{cn\('min-w-0 overflow-x-hidden', className\)\}/);
    assert.match(source, /main className=\{cn\('mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl', className\)\}/);
});
