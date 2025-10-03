import assert from 'node:assert/strict';
import test from 'node:test';

import { formatCSVValue, generateCSVContent } from '../../resources/js/lib/csv.js';
import { Status } from '../../resources/js/types/blockchain.js';

test('formatCSVValue returns empty string for nullish values', () => {
    assert.equal(formatCSVValue(null), '');
    assert.equal(formatCSVValue(undefined), '');
});

test('formatCSVValue escapes quotes and wraps values containing commas', () => {
    assert.equal(formatCSVValue('Simple'), 'Simple');
    assert.equal(formatCSVValue('Value,With,Comma'), '"Value,With,Comma"');
    assert.equal(formatCSVValue('"Quoted"'), '"""Quoted"""');
    assert.equal(formatCSVValue('Multi\nLine'), '"Multi\nLine"');
});

test('generateCSVContent produces header and formatted rows', () => {
    const csv = generateCSVContent([
        {
            id: 'PR-001',
            title: 'Procurement "Alpha"',
            stage: 'Procurement Initiation',
            current_status: Status.PROCUREMENT_SUBMITTED,
            document_count: 5,
            last_updated: '2024-08-15T12:00:00Z',
            timestamp: '2024-08-10T12:00:00Z',
            user_address: '0xabc123',
        },
        {
            id: 'PR-002',
            title: 'Infrastructure, Roads & Bridges',
            stage: 'Pre-Procurement Conference',
            current_status: Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED,
            document_count: 3,
            last_updated: '',
            timestamp: '',
            user_address: '0xdef456',
        },
    ]);

    const rows = csv.split('\r\n');
    assert.equal(rows[0], 'ID,Title,Phase,State,Documents,Last Updated,Timestamp');
    assert.equal(rows[1], 'PR-001,"Procurement ""Alpha""",Procurement Initiation,Procurement Submitted,5,2024-08-15T12:00:00Z,2024-08-10T12:00:00Z');
    assert.equal(rows[2], 'PR-002,"Infrastructure, Roads & Bridges",Pre-Procurement Conference,Pre-Procurement Conference Completed,3,,');
    assert.equal(rows.length, 3);
});
