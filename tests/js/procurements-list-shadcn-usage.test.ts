import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('procurements list and toolbar follow base-ui composition patterns', () => {
    const pageSource = readFileSync(path.join(process.cwd(), 'resources/js/pages/procurements/procurements-list.tsx'), 'utf8');
    const toolbarSource = readFileSync(
        path.join(process.cwd(), 'resources/js/components/procurements-list/procurement-filters-toolbar.tsx'),
        'utf8',
    );

    assert.match(pageSource, /<div className="flex flex-col gap-1">/);
    assert.match(pageSource, /<Button\s+nativeButton=\{false\}[\s\S]*render=\{<Link href=\{procurement\.initiation\.index\.url\(\)\}/);

    assert.match(toolbarSource, /className=\{cn\('flex flex-col gap-4 pb-4', className\)\}/);
    assert.match(toolbarSource, /className="flex h-10 w-full items-center gap-2 sm:w-auto"/);
    assert.doesNotMatch(toolbarSource, /space-x-2|space-y-4/);
});
