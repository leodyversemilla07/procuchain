import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('login log details sheet uses scroll-safe header/content layout and prevents horizontal overflow', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/admin/login-log-details-sheet.tsx'), 'utf8');

    assert.match(source, /<SheetContent className="flex w-full flex-col overflow-x-hidden sm:max-w-\[700px\]" side="right">/);
    assert.match(source, /<SheetHeader className="space-y-2 pr-8">/);
    assert.match(source, /<Badge variant="destructive" className="mt-1 flex w-fit items-center gap-1">/);
    assert.match(source, /<div className="flex-1 min-h-0 space-y-6 overflow-y-auto overflow-x-hidden py-6 pr-1">/);
    assert.match(source, /<p className="mt-1 break-all font-medium">\{log\.user\?\.email \|\| 'Unknown Email'\}<\/p>/);
});
