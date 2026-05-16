import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('tabs component maps orientation to stable layout and data attribute variants', () => {
  const source = readFileSync(
    path.join(process.cwd(), 'resources/js/components/ui/tabs.tsx'),
    'utf8',
  );

  // Official shadcn/ui v4 uses data-orientation + group-data variant classes
  assert.match(source, /data-orientation=\{orientation\}/);
  assert.match(source, /orientation=\{orientation\}/);

  // v4 uses Tailwind group-data variants (e.g., group-data-horizontal/tabs:flex-col)
  // instead of ternary expressions
  assert.match(source, /group-data-horizontal\/tabs/);
  assert.match(source, /group-data-vertical\/tabs/);
});
