import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('audit log action select uses full width trigger and display label for selected value', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/audit-log.tsx'), 'utf8');

    assert.match(source, /const selectedActionLabel = action && action !== 'all' \? \(ACTION_LABELS\[action\] \?\? action\) : 'All actions';/);
    assert.match(source, /const safeLogs = logs \?\? \{/);
    assert.match(source, /<SelectTrigger className="w-full">/);
    assert.match(source, /<SelectValue placeholder="All actions">\{\(\) => selectedActionLabel\}<\/SelectValue>/);
});
