import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('reports page select filters render readable labels and full-width triggers', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/reports/index.tsx'), 'utf8');

    assert.match(source, /const filterTypeLabels: Record<ReportFilters\['filter_type'\], string> = \{/);
    assert.match(source, /const selectedFilterTypeLabel = filterTypeLabels\[filters\.filter_type\] \?\? 'Filter Type';/);
    assert.match(source, /const selectedMonthLabel = filters\.month \? \(months\[filters\.month - 1\] \?\? 'Month'\) : 'Month';/);
    assert.match(source, /const selectedQuarterLabel = filters\.quarter \? \(quarterLabels\[filters\.quarter\] \?\? 'Quarter'\) : 'Quarter';/);

    assert.match(source, /<SelectValue>\{\(\) => selectedFilterTypeLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue>\{\(\) => selectedMonthLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue>\{\(\) => selectedQuarterLabel\}<\/SelectValue>/);

    assert.match(source, /<SelectTrigger className="w-full">/);
});
