import assert from 'node:assert/strict';
import test from 'node:test';

import {
    formatBlockchainDate,
    formatBytes,
    formatPingTime,
    getSyncStatus,
    truncateHash,
} from '../../resources/js/lib/blockchain-explorer.js';

test('formatBytes renders human-readable sizes', () => {
    assert.equal(formatBytes(0), '0 Bytes');
    assert.equal(formatBytes(1024), '1 KB');
    assert.equal(formatBytes(1536), '1.5 KB');
});

test('truncateHash shortens long hashes without changing short ones', () => {
    assert.equal(truncateHash('1234567890abcdef', 8), '12345678...');
    assert.equal(truncateHash('1234', 8), '1234');
});

test('formatPingTime and getSyncStatus render explorer status labels', () => {
    assert.equal(formatPingTime(), 'N/A');
    assert.equal(formatPingTime(0.01234), '12.34ms');
    assert.equal(getSyncStatus(120, 120), 'Fully Synced');
    assert.equal(getSyncStatus(118, 120), '118/120 blocks');
});

test('formatBlockchainDate returns a non-empty localized timestamp', () => {
    assert.notEqual(formatBlockchainDate(1_710_000_000), '');
});
