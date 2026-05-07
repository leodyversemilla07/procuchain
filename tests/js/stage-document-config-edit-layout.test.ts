import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('stage document config edit uses wrapped card actions, official card composition, and official tabs composition', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/stage-document-config-edit.tsx'), 'utf8');

    assert.match(source, /DocumentItem[\s\S]*flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-start/);
    assert.match(source, /AvailableDocItem[\s\S]*flex flex-col gap-3 rounded-lg border p-3 transition-colors sm:flex-row sm:items-start/);
    assert.match(source, /<CardHeader>\s*<CardTitle className="flex items-center gap-2 text-base">[\s\S]*?Add Document by Name[\s\S]*?<CardDescription>Type the name of the document you want to add<\/CardDescription>\s*<\/CardHeader>/s);
    assert.match(source, /<Tabs[\s\S]*?value=\{selectedDocsTab\}[\s\S]*?className="flex flex-col gap-4"\s*>[\s\S]*?<TabsList variant="line">/s);
    assert.match(source, /<div className="rounded-lg border p-4">/);
    assert.match(source, /flex flex-wrap items-center gap-2/);
});