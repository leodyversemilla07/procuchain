import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('bac secretariat dashboard avoids space-y utility in card header row composition', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/bac-secretariat/dashboard.tsx'), 'utf8');

    assert.doesNotMatch(source, /space-y-0/);
    assert.match(source, /<CardHeader className="flex flex-row items-center justify-between pb-2">/);
});
