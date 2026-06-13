import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('reports filter form select filters render readable labels and full-width triggers', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/components/reports/report-filter-form.tsx'), 'utf8');

    assert.match(source, /const FILTER_TYPE_LABELS: Record<ReportFilters\['filter_type'\], string> = \{/);
    assert.match(source, /const selectedFilterTypeLabel = FILTER_TYPE_LABELS\[filters\.filter_type\] \?\? 'Filter Type';/);
    assert.match(source, /const selectedMonthLabel = filters\.month \? \(MONTHS\[filters\.month - 1\] \?\? 'Month'\) : 'Month';/);
    assert.match(source, /const selectedQuarterLabel = filters\.quarter \? \(QUARTER_LABELS\[filters\.quarter\] \?\? 'Quarter'\) : 'Quarter';/);

    assert.match(source, /<SelectValue>\{\(\) => selectedFilterTypeLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue>\{\(\) => selectedMonthLabel\}<\/SelectValue>/);
    assert.match(source, /<SelectValue>\{\(\) => selectedQuarterLabel\}<\/SelectValue>/);

    assert.match(source, /<SelectTrigger className="w-full">/);
});
