import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('notifications filter select renders selected label in trigger', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/notifications.tsx'), 'utf8');

    assert.match(source, /const filterLabels: Record<FilterType, string> = \{/);
    assert.match(source, /const selectedFilterLabel = filterLabels\[filter\] \?\? 'Filter';/);
    assert.match(source, /<SelectValue placeholder="Filter">\{\(\) => selectedFilterLabel\}<\/SelectValue>/);
});
