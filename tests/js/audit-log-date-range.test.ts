import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('audit log uses a shadcn calendar range picker for date filters', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/admin/audit-log.tsx'), 'utf8');

    assert.match(source, /import \{ type DateRange \} from 'react-day-picker';/);
    assert.match(source, /const \[dateRange, setDateRange\] = useState<DateRange \| undefined>\(\{/);

    assert.match(source, /<Calendar[\s\S]*mode="range"[\s\S]*selected=\{dateRange\}[\s\S]*onSelect=\{setDateRange\}[\s\S]*numberOfMonths=\{2\}/);

    assert.doesNotMatch(source, /From date/);
    assert.doesNotMatch(source, /To date/);
});
