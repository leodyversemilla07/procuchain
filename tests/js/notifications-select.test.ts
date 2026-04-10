import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('notifications filter select uses SelectValue without custom children', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/notifications.tsx'), 'utf8');

    assert.match(source, /<SelectValue placeholder="Filter" \/>/);
    assert.doesNotMatch(source, /<SelectValue placeholder="Filter">/);
});
