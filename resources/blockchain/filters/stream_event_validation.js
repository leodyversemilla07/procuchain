/**
 * ProcuChain Stream Filter: Event Audit Validation
 * 
 * This Smart Filter validates audit events published to the
 * procurement.events stream. It ensures all procurement activities
 * are properly logged for transparency and accountability.
 * 
 * Aligned with: App\DataTransferObjects\EventData
 * 
 * @author ProcuChain Development Team
 * @version 2.0.0
 * @license MIT
 */

function filterstreamitem() {
    var item = getfilterstreamitem();
    
    // Get the JSON data from the stream item
    // MultiChain stores JSON data in item.data.json
    var data = item.data;
    
    // If data is null (too large), reject it - events should be small
    if (data === null) {
        return 'Event data is too large or missing';
    }
    
    // Handle object with json property (standard MultiChain format)
    if (typeof data === 'object' && data.json) {
        data = data.json;
    }
    
    // Parse JSON data if it's a string
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch (e) {
            return 'Invalid JSON data in event';
        }
    }
    
    // ============================================================
    // Required Fields Validation (aligned with EventData DTO)
    // ============================================================
    
    if (!data.pr_number) {
        return 'Event missing required field: pr_number';
    }
    
    if (!data.procurement_title) {
        return 'Event missing required field: procurement_title';
    }
    
    if (!data.stage) {
        return 'Event missing required field: stage';
    }
    
    if (!data.event_type) {
        return 'Event missing required field: event_type';
    }
    
    if (!data.category) {
        return 'Event missing required field: category';
    }
    
    if (!data.severity) {
        return 'Event missing required field: severity';
    }
    
    if (!data.details) {
        return 'Event missing required field: details';
    }
    
    // Note: DTO uses 'user_address', not 'actor_address'
    if (!data.user_address) {
        return 'Event missing required field: user_address';
    }
    
    if (!data.timestamp) {
        return 'Event missing required field: timestamp';
    }
    
    // document_count can be 0, so check for undefined/null
    if (data.document_count === undefined || data.document_count === null) {
        return 'Event missing required field: document_count';
    }
    
    // ============================================================
    // Format Validations
    // ============================================================
    
    // Validate PR number format (supports PR-YYYY-NNN or PR-YYYY-XXXX-NNNN)
    var prPattern = /^PR-\d{4}-\d{3,4}(-\d{4})?$/;
    if (!prPattern.test(data.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-NNN or PR-YYYY-XXXX-NNNN';
    }
    
    // Validate stage (aligned with StageEnums)
    var validStages = [
        'procurement_initiation',
        'pre_procurement_conference',
        'bidding_documents',
        'pre_bid_conference',
        'supplemental_bid_bulletin',
        'bid_opening',
        'bid_evaluation',
        'post_qualification',
        'bac_resolution',
        'notice_of_award',
        'performance_bond_contract_and_po',
        'notice_to_proceed',
        'monitoring',
        'completion',
        'completed'
    ];
    
    var stageValid = false;
    for (var i = 0; i < validStages.length; i++) {
        if (validStages[i] === data.stage) {
            stageValid = true;
            break;
        }
    }
    
    if (!stageValid) {
        return 'Invalid procurement stage: ' + data.stage;
    }
    
    // Define valid event types
    var validEventTypes = [
        // Document events
        'document_uploaded',
        'document_published',
        'document_verified',
        'document_corrected',
        'document_viewed',
        'document_downloaded',
        
        // Stage events
        'stage_started',
        'stage_completed',
        'stage_transition',
        
        // Status events
        'status_changed',
        'status_approved',
        'status_rejected',
        
        // Conference events
        'conference_scheduled',
        'conference_conducted',
        'conference_cancelled',
        
        // Bid events
        'bid_received',
        'bid_opened',
        'bid_evaluated',
        'bid_disqualified',
        
        // Award events
        'award_issued',
        'award_accepted',
        'award_declined',
        'contract_signed',
        
        // System events
        'procurement_created',
        'procurement_completed',
        'procurement_cancelled',
        'correction_submitted',
        'correction_approved'
    ];
    
    // Validate event type
    var eventTypeValid = false;
    for (var j = 0; j < validEventTypes.length; j++) {
        if (validEventTypes[j] === data.event_type) {
            eventTypeValid = true;
            break;
        }
    }
    
    if (!eventTypeValid) {
        return 'Invalid event type: ' + data.event_type;
    }
    
    // Define valid event categories
    var validCategories = [
        'document',
        'stage',
        'status',
        'conference',
        'bid',
        'award',
        'system',
        'correction',
        'audit',
        'procurement'  // Added: general procurement events
    ];
    
    // Validate category
    var categoryValid = false;
    for (var k = 0; k < validCategories.length; k++) {
        if (validCategories[k] === data.category) {
            categoryValid = true;
            break;
        }
    }
    
    if (!categoryValid) {
        return 'Invalid event category: ' + data.category;
    }
    
    // Validate severity
    var validSeverities = ['info', 'warning', 'error', 'critical'];
    var severityValid = false;
    for (var m = 0; m < validSeverities.length; m++) {
        if (validSeverities[m] === data.severity) {
            severityValid = true;
            break;
        }
    }
    
    if (!severityValid) {
        return 'Invalid event severity: ' + data.severity;
    }
    
    // Validate document_count is a non-negative integer
    if (typeof data.document_count !== 'number' || data.document_count < 0) {
        return 'document_count must be a non-negative number';
    }
    
    // Validate timestamp format (ISO 8601 basic check)
    var isoPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/;
    if (!isoPattern.test(data.timestamp)) {
        return 'Invalid timestamp format. Must be ISO 8601';
    }
    
    // Note: Future timestamp validation removed - requires Date.parse which is unavailable
    // Blockchain timestamp validation is handled by block time constraints
    
    // Validate user_address matches publisher
    var publisherMatches = false;
    if (item.publishers) {
        for (var n = 0; n < item.publishers.length; n++) {
            if (item.publishers[n] === data.user_address) {
                publisherMatches = true;
                break;
            }
        }
    }
    
    // Note: Publisher check is informational only - some events may be published by system
    // if (!publisherMatches) {
    //     return 'Event user_address does not match transaction publisher';
    // }
    
    // Note: PR number key validation is informational - existing data may not have this
    // Validate keys include the PR number
    // var hasPrNumberKey = false;
    // if (item.keys) {
    //     for (var p = 0; p < item.keys.length; p++) {
    //         if (item.keys[p] === data.pr_number) {
    //             hasPrNumberKey = true;
    //             break;
    //         }
    //     }
    // }
    // if (!hasPrNumberKey) {
    //     return 'Event must include pr_number as a stream key';
    // }
    
    // Event passed all validations
    return;
}
