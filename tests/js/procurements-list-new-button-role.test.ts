import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('procurements list shows New Procurement action only for BAC Secretariat role', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/procurements/procurements-list.tsx'), 'utf8');

    assert.match(source, /if \(userRole === 'bac_secretariat' && auth\?\.can\?\.manageProcurement\) \{/);
    assert.match(source, /!isPollArchived && userRole === 'bac_secretariat' && auth\?\.can\?\.manageProcurement && \(/);
    assert.match(source, /!isPollArchived && userRole === 'bac_secretariat' && \(/);
});
