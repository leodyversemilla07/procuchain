import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('stage document configs select renders the mode display label instead of the raw value', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/stage-document-configs.tsx'), 'utf8');

    assert.match(source, /const selectedModeLabel = modes\.find\(\(mode\) => mode\.value === selectedMode\)\?\.display_name \?\? selectedModeDisplayName;/);
    assert.match(source, /<SelectValue placeholder="Select mode">\{\(\) => selectedModeLabel\}<\/SelectValue>/);
});
