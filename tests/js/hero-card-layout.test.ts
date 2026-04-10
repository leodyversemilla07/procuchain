import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('hero card layout allows content and actions to wrap instead of overflowing horizontally', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/hero-card.tsx'), 'utf8');

    assert.match(source, /flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between/);
    assert.match(source, /flex w-full flex-wrap items-center gap-2 xl:w-auto xl:justify-end/);
    assert.match(source, /className="min-w-0"/);
});
