import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('workflow page uses shadcn tabs composition without manually sizing trigger icons', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/workflow.tsx'), 'utf8');

    assert.match(source, /<Tabs defaultValue="competitive" className="mx-auto flex w-full max-w-6xl flex-col gap-4 sm:gap-6">/);
    assert.match(source, /<TabsList className="mx-auto grid w-full max-w-xl grid-cols-2">/);
    assert.match(source, /<Scale data-icon="inline-start" \/>/);
    assert.match(source, /<Layers data-icon="inline-start" \/>/);
    assert.match(source, /unsolicited_offer_with_bid_matching: \{/);
    assert.match(source, /direct_procurement_for_sti: \{/);
    assert.doesNotMatch(source, /<Scale className="h-3\.5 w-3\.5 sm:h-4 sm:w-4" \/>/);
    assert.doesNotMatch(source, /<Layers className="h-3\.5 w-3\.5 sm:h-4 sm:w-4" \/>/);
    assert.doesNotMatch(source, /grid h-auto w-full grid-cols-2/);
    assert.doesNotMatch(source, /unsolicited_offer: \{|direct_procurement_sti: \{/);
});
