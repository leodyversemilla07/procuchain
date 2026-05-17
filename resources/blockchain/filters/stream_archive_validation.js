/**
 * ProcuChain Stream Filter: Procurement Archive Validation
 *
 * Validates archive items before they are accepted into the
 * procurement.archive stream. Enforces archive action integrity
 * aligned with the ProcurementArchiveRepository.
 *
 * @author ProcuChain Development Team
 * @version 1.0.0
 * @license MIT
 */

function filterstreamitem() {
    var item = getfilterstreamitem();
    var data = item.data;

    if (!data || !data.json) {
        return 'Archive item must contain JSON data';
    }

    var json = data.json;

    // PR number is required for archive operations
    if (!json.pr_number) {
        return 'Archive item missing required field: pr_number';
    }

    // Validate PR number format (PR-YYYY-XXXX-NNNN)
    if (!/^PR-\d{4}-\d{4}-\d{4}$/.test(json.pr_number)) {
        return 'Invalid PR number format. Expected: PR-YYYY-XXXX-NNNN';
    }

    // Action must be 'archived' or 'restored'
    if (json.action !== 'archived' && json.action !== 'restored') {
        return 'Archive action must be "archived" or "restored"';
    }

    return;
}
