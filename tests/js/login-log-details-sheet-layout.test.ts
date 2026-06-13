import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('login log details sheet uses scroll-safe header/content layout and prevents horizontal overflow', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/admin/login-log-details-sheet.tsx'), 'utf8');

    assert.match(source, /<SheetContent className="flex w-full flex-col overflow-x-hidden sm:max-w-\[700px\]" side="right">/);
    assert.match(source, /<SheetHeader className="flex flex-col gap-2 pr-8">/);
    assert.match(source, /<Badge variant="destructive" className="mt-1 flex w-fit items-center gap-1">/);
    assert.match(source, /className="flex min-h-0 flex-1 flex-col gap-6 overflow-x-hidden overflow-y-auto py-6 pr-1"/);
    assert.match(source, /<p className="mt-1 font-medium break-all">\{log\.user\?\.email \|\| 'Unknown Email'\}<\/p>/);
});