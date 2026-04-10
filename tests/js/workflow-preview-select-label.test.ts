import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('workflow preview select shows the mode display name instead of the raw value', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/workflow-preview.tsx'), 'utf8');

    assert.match(source, /<SelectValue placeholder="Select procurement mode">\{\(\) => mode\.display_name\}<\/SelectValue>/);
});
