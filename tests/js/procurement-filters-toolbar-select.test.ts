import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('procurement filters toolbar select renders readable stage label', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/procurements-list/procurement-filters-toolbar.tsx'), 'utf8');

    assert.match(source, /const selectedStageLabel = stageOptions\.find\(\(option\) => option\.value === stageValue\)\?\.label \?\? stageOptions\[0\]\?\.label \?\? 'Select stage';/);
    assert.match(source, /<SelectValue placeholder=\{stageOptions\[0\]\?\.label \?\? 'Select stage'\}>\{\(\) => selectedStageLabel\}<\/SelectValue>/);
});
