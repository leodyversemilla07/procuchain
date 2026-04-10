import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('blockchain explorer uses official tabs and select patterns for tab navigation', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/blockchain-explorer.tsx'), 'utf8');

    assert.match(source, /const tabOptions = \[/);
    assert.match(source, /<SelectValue placeholder="Select a tab">\{\(\) => selectedTabLabel\}<\/SelectValue>/);
    assert.match(source, /<TabsList variant="line" className="hidden md:flex md:flex-wrap md:items-center md:gap-1">/);
    assert.match(source, /<Tabs value=\{selectedTab\} onValueChange=\{setSelectedTab\} className="flex-1 flex-col gap-4">/);
    assert.match(source, /<div className="rounded-lg border p-4 sm:p-6">/);
    assert.doesNotMatch(source, /TabsContent value="overview" className="mt-6"/);
});
