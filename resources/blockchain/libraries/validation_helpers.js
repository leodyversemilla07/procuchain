/**
 * ProcuChain Validation Helpers Library
 *
 * Version: 1.0.0
 * Update Mode: approved (requires admin consensus for updates)
 *
 * Purpose: Provide reusable validation functions for ProcuChain smart filters.
 * This library can be imported by multiple filters to ensure consistent
 * validation logic across all blockchain streams.
 *
 * Usage in filters:
 * - Filters must include this library in their options.libraries array
 * - All functions are deterministic and safe for blockchain execution
 *
 * @see https://www.multichain.com/developers/json-rpc-api/#libraries
 */

/* eslint-disable @typescript-eslint/no-unused-vars */
/**
 * ProcuChain Blockchain Validation Helpers
 *
 * Shared validation utilities for MultiChain smart filters
 * These functions provide common validation logic used across filters
 *
 * @version 1.0.0
 */

/**
 * Validates that a hash is a valid SHA-256 format (64 hex characters)
 * @param {string} hash - Hash to validate
 * @returns {boolean}
 */
function validateHash(hash) {
    if (!hash || typeof hash !== 'string') {
        return { valid: false, error: 'Hash is required and must be a string' };
    }

    var hashPattern = /^[a-f0-9]{64}$/i;
    if (!hashPattern.test(hash)) {
        return {
            valid: false,
            error: 'Invalid hash format. Expected 64-character SHA-256 hex string, got: ' + hash,
        };
    }

    return { valid: true, error: null };
}

/**
 * Validate required fields exist in an object
 * @param {object} data - The data object to validate
 * @param {array} requiredFields - Array of field names that must exist
 * @returns {object} { valid: boolean, error: string|null, missingField: string|null }
 */
function validateRequiredFields(data, requiredFields) {
    if (!data || typeof data !== 'object') {
        return { valid: false, error: 'Data object is required', missingField: null };
    }

    if (!requiredFields || !Array.isArray(requiredFields)) {
        return { valid: false, error: 'requiredFields must be an array', missingField: null };
    }

    for (var i = 0; i < requiredFields.length; i++) {
        var field = requiredFields[i];
        if (!data[field] || data[field] === '') {
            return {
                valid: false,
                error: 'Missing required field: ' + field,
                missingField: field,
            };
        }
    }

    return { valid: true, error: null, missingField: null };
}

/**
 * Validate file size is within limits
 * @param {number|string} fileSize - The file size in bytes
 * @param {number} maxSize - Maximum allowed size in bytes (default: 10MB)
 * @returns {object} { valid: boolean, error: string|null }
 */
function validateFileSize(fileSize, maxSize) {
    if (!maxSize) {
        maxSize = 10485760; // 10MB default
    }

    var size = parseInt(fileSize, 10);

    if (isNaN(size) || size <= 0) {
        return {
            valid: false,
            error: 'Invalid file size: ' + fileSize,
        };
    }

    if (size > maxSize) {
        return {
            valid: false,
            error: 'File size exceeds maximum allowed (' + maxSize + ' bytes). Size: ' + size + ' bytes',
        };
    }

    return { valid: true, error: null };
}

/**
 * Validate a value is in an allowed list
 * @param {string} value - The value to validate
 * @param {array} allowedValues - Array of allowed values
 * @param {string} fieldName - Name of the field being validated (for error messages)
 * @returns {object} { valid: boolean, error: string|null }
 */
function validateEnum(value, allowedValues, fieldName) {
    if (!value) {
        return {
            valid: false,
            error: fieldName + ' is required',
        };
    }

    if (!allowedValues || !Array.isArray(allowedValues)) {
        return {
            valid: false,
            error: 'allowedValues must be an array',
        };
    }

    var found = false;
    for (var i = 0; i < allowedValues.length; i++) {
        if (value === allowedValues[i]) {
            found = true;
            break;
        }
    }

    if (!found) {
        return {
            valid: false,
            error: 'Invalid ' + fieldName + ": '" + value + "'. Must be one of the allowed values.",
        };
    }

    return { valid: true, error: null };
}

/**
 * Validate blockchain address format
 * @param {string} address - The blockchain address to validate
 * @returns {object} { valid: boolean, error: string|null }
 */
function validateBlockchainAddress(address) {
    if (!address || typeof address !== 'string') {
        return {
            valid: false,
            error: 'Blockchain address is required and must be a string',
        };
    }

    // MultiChain addresses are typically 20-50 alphanumeric characters
    var addressPattern = /^[a-zA-Z0-9]{20,50}$/;
    if (!addressPattern.test(address)) {
        return {
            valid: false,
            error: 'Invalid blockchain address format: ' + address,
        };
    }

    return { valid: true, error: null };
}

/**
 * Validate ISO 8601 timestamp format (basic check)
 * @param {string} timestamp - The timestamp string to validate
 * @returns {object} { valid: boolean, error: string|null }
 */
function validateTimestamp(timestamp) {
    if (!timestamp || typeof timestamp !== 'string') {
        return {
            valid: false,
            error: 'Timestamp is required and must be a string',
        };
    }

    // Minimum length for ISO 8601: "2024-01-01T00:00:00"
    if (timestamp.length < 19) {
        return {
            valid: false,
            error: 'Invalid timestamp format. Expected ISO 8601 format.',
        };
    }

    // Basic pattern check for ISO 8601
    var isoPattern = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/;
    if (!isoPattern.test(timestamp)) {
        return {
            valid: false,
            error: 'Invalid timestamp format. Expected ISO 8601 format (YYYY-MM-DDTHH:mm:ss).',
        };
    }

    return { valid: true, error: null };
}

/**
 * Validate string length is within bounds
 * @param {string} value - The string to validate
 * @param {number} minLength - Minimum allowed length
 * @param {number} maxLength - Maximum allowed length
 * @param {string} fieldName - Name of the field (for error messages)
 * @returns {object} { valid: boolean, error: string|null }
 */
function validateStringLength(value, minLength, maxLength, fieldName) {
    if (!value || typeof value !== 'string') {
        return {
            valid: false,
            error: fieldName + ' is required and must be a string',
        };
    }

    if (value.length < minLength) {
        return {
            valid: false,
            error: fieldName + ' too short. Minimum ' + minLength + ' characters required.',
        };
    }

    if (value.length > maxLength) {
        return {
            valid: false,
            error: fieldName + ' too long. Maximum ' + maxLength + ' characters allowed.',
        };
    }

    return { valid: true, error: null };
}

/**
 * Get valid procurement document types
 * @returns {array} Array of valid document type strings
 */
function getValidDocumentTypes() {
    return [
        'procurement_initiation',
        'pre_procurement_conference',
        'bidding_documents',
        'request_for_quotation',
        'pre_bid_conference',
        'supplemental_bid_bulletin',
        'bid_opening',
        'abstract_of_quotations',
        'bid_evaluation',
        'post_qualification',
        'bac_resolution',
        'notice_of_award',
        'performance_bond_contract_and_po',
        'notice_to_proceed',
        'monitoring',
        'completion',
        'completed',
    ];
}

/**
 * Get valid procurement status values
 * @returns {array} Array of valid status strings
 */
function getValidStatuses() {
    return [
        'procurement_initiated',
        'procurement_submitted',
        'pre_procurement_conference_held',
        'pre_procurement_conference_skipped',
        'pre_procurement_conference_completed',
        'bidding_documents_published',
        'bidding_documents_submitted',
        'pre_bid_conference_held',
        'pre_bid_conference_skipped',
        'pre_bid_conference_completed',
        'supplemental_bulletins_ongoing',
        'supplemental_bulletins_completed',
        'bids_opened',
        'bids_evaluated',
        'post_qualification_verified',
        'post_qualification_failed',
        'resolution_recorded',
        'awarded',
        'performance_bond_contract_and_po_recorded',
        'ntp_recorded',
        'monitoring_completed',
        'completion_documents_uploaded',
        'completed',
        // Alternative Procurement Methods (SVP, Direct Contracting, etc.)
        'quotations_received',
        'abstract_prepared',
        // Lifecycle states
        'stage_on_hold',
        'stage_cancelled',
        'stage_rejected',
        'stage_pending_correction',
        'stage_skipped',
    ];
}

/**
 * Get valid procurement stage values
 * @returns {array} Array of valid stage strings
 */
function getValidStages() {
    return [
        'procurement_initiation',
        'pre_procurement_conference',
        'bidding_documents',
        'request_for_quotation',
        'pre_bid_conference',
        'supplemental_bid_bulletin',
        'bid_opening',
        'abstract_of_quotations',
        'bid_evaluation',
        'post_qualification',
        'bac_resolution',
        'notice_of_award',
        'performance_bond_contract_and_po',
        'notice_to_proceed',
        'monitoring',
        'completion',
        'completed',
    ];
}
