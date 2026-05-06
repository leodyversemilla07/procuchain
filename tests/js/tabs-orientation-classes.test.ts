import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('tabs component maps orientation to stable layout and data attribute variants', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/ui/tabs.tsx'), 'utf8');

    assert.match(source, /data-orientation=\{orientation\}/);
    assert.match(source, /orientation=\{orientation\}/);
    assert.match(source, /orientation === "horizontal" \? "flex-col" : "flex-row"/);
    assert.match(source, /group-data-\[orientation=horizontal\]\/tabs:h-8/);
    assert.match(source, /group-data-\[orientation=vertical\]\/tabs:flex-col/);
    assert.doesNotMatch(source, /data-horizontal:flex-col|group-data-horizontal\/tabs|group-data-vertical\/tabs/);
});
