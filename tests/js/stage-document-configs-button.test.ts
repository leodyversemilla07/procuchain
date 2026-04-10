import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('stage document config action button disables nativeButton when rendering a Link', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/stage-document-configs.tsx'), 'utf8');

    assert.match(source, /nativeButton=\{false\}[\s\S]*render=\{<Link href=\{stageDocumentsEdit/);
});
