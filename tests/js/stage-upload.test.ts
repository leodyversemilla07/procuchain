import assert from 'node:assert/strict';
import test from 'node:test';

import { resolveStageActionPhase } from '../../resources/js/lib/stage-action-map.js';
import {
    getStageCompletionPercentage,
    getUploadedRequiredCount,
    hasUploadedAllRequiredDocuments,
} from '../../resources/js/lib/stage-upload.js';

test('resolveStageActionPhase returns the initiation branch for procurement initiation', () => {
    assert.equal(resolveStageActionPhase('procurement_initiation', 'procurement'), 'procurement_initiation');
});

test('resolveStageActionPhase keeps known workflow phases and falls back to procurement', () => {
    assert.equal(resolveStageActionPhase('pre_bid_conference', 'pre_procurement'), 'pre_procurement');
    assert.equal(resolveStageActionPhase('notice_to_proceed', 'post_procurement'), 'post_procurement');
    assert.equal(resolveStageActionPhase('bid_opening', 'unexpected'), 'procurement');
});

test('stage upload progress helpers calculate completion from required documents only', () => {
    const documentGuide = {
        required_documents: [
            { value: 'purchase_request', display_name: 'Purchase Request', description: '' },
            { value: 'procurement_initiation_document', display_name: 'Initiation Document', description: '' },
        ],
        optional_documents: [{ value: 'market_study', display_name: 'Market Study', description: '' }],
        counts: {
            required_count: 2,
            optional_count: 1,
            total_count: 3,
        },
    };

    const uploadedRequiredCount = getUploadedRequiredCount(documentGuide as never, ['purchase_request', 'market_study']);

    assert.equal(uploadedRequiredCount, 1);
    assert.equal(getStageCompletionPercentage(documentGuide as never, uploadedRequiredCount), 50);
    assert.equal(hasUploadedAllRequiredDocuments(documentGuide as never, uploadedRequiredCount), false);
    assert.equal(hasUploadedAllRequiredDocuments(documentGuide as never, 2), true);
});
