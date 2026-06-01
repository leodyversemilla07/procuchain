import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('procurement initiation uses official shadcn composition for select and radio controls', () => {
 const pageSource = readFileSync(path.join(process.cwd(), 'resources/js/pages/bac-secretariat/procurement-initiation.tsx'), 'utf8');
 const basicInfoSource = readFileSync(path.join(process.cwd(), 'resources/js/components/procurement-initiation/basic-info-section.tsx'), 'utf8');
 const officePurposeSource = readFileSync(path.join(process.cwd(), 'resources/js/components/procurement-initiation/office-purpose-section.tsx'), 'utf8');
 const classificationSource = readFileSync(path.join(process.cwd(), 'resources/js/components/procurement-initiation/classification-budget-section.tsx'), 'utf8');

 assert.doesNotMatch(pageSource, /from '@\/components\/ui\/label'/);
 assert.doesNotMatch(basicInfoSource, /from '@\/components\/ui\/label'/);
 assert.doesNotMatch(officePurposeSource, /from '@\/components\/ui\/label'/);
 assert.doesNotMatch(classificationSource, /from '@\/components\/ui\/label'/);

 assert.doesNotMatch(pageSource, /space-x-2|space-x-3/);
 assert.doesNotMatch(basicInfoSource, /space-x-2|space-x-3/);
 assert.doesNotMatch(officePurposeSource, /space-x-2|space-x-3/);
 assert.doesNotMatch(classificationSource, /space-x-2|space-x-3/);

 assert.match(basicInfoSource, /<SelectValue placeholder="Select description">\{\(\) => selectedDescriptionLabel\}<\/SelectValue>/);
 assert.match(officePurposeSource, /<SelectValue placeholder="Select office">\{\(\) => selectedOfficeLabel\}<\/SelectValue>/);
 assert.match(officePurposeSource, /<SelectValue placeholder="Same as Office">\{\(\) => selectedEndUserLabel\}<\/SelectValue>/);

 assert.match(classificationSource, /<FieldLabel htmlFor=\{`category-\$\{category\.value\}`\} className="flex-1 cursor-pointer font-medium">/);
 assert.match(classificationSource, /<FieldLabel htmlFor=\{`funding-\$\{source\.value\}`\} className="cursor-pointer text-sm font-normal">/);
 assert.match(classificationSource, /<FieldLabel htmlFor=\{`mode-\$\{mode\.value\}`\} className="flex-1 cursor-pointer">/);
});
