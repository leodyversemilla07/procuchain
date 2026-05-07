import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('workflow config cards use official card composition and wrap card content safely', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/workflow-configs.tsx'), 'utf8');

    assert.match(source, /<CardHeader>\s*<CardTitle className="text-lg">\{config\.display_name\}<\/CardTitle>\s*<CardDescription>\{config\.irr_section\}\s*<\/CardDescription>\s*<\/CardHeader>/s);
    assert.match(source, /CardContent className="flex flex-1 flex-col gap-4"/);
    assert.match(source, /<div className="flex flex-wrap gap-2">/);
    assert.match(source, /CardFooter className="flex flex-wrap items-center gap-2 border-t"/);
    assert.match(source, /<Eye data-icon="inline-start" \/>/);
    assert.match(source, /<Edit data-icon="inline-start" \/>/);
    assert.match(source, /className="min-w-\[140px\] flex-1"/);
    assert.match(source, /<div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">\s*\{alternativeModes\.map/);
});