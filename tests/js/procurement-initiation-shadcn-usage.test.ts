import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('procurement initiation uses official shadcn composition for select and radio controls', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/bac-secretariat/procurement-initiation.tsx'), 'utf8');

    assert.doesNotMatch(source, /from '@\/components\/ui\/label'/);
    assert.doesNotMatch(source, /space-x-2|space-x-3/);

    assert.match(source, /<SelectValue placeholder="Select description">\{\(\) => selectedDescriptionLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue placeholder="Select office">\{\(\) => selectedOfficeLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue placeholder="Same as Office">\{\(\) => selectedEndUserLabel\}<\/SelectValue>/);

    assert.match(source, /<FieldLabel htmlFor=\{`category-\$\{category\.value\}`\} className="flex-1 cursor-pointer font-medium">/);
    assert.match(source, /<FieldLabel htmlFor=\{`funding-\$\{source\.value\}`\} className="cursor-pointer text-sm font-normal">/);
    assert.match(source, /<FieldLabel htmlFor=\{`mode-\$\{mode\.value\}`\} className="flex-1 cursor-pointer">/);
});
