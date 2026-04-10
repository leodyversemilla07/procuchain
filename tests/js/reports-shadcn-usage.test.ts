import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('reports page uses card/header spacing patterns aligned with shadcn guidance', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/reports/index.tsx'), 'utf8');

    assert.doesNotMatch(source, /space-y-0/);
    assert.match(source, /<CardHeader className="flex flex-row items-center justify-between pb-2">/);
    assert.match(source, /<div className="flex flex-col gap-2">/);
});
