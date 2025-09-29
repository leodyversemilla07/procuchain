# MultiChain Smart Contract Plan for ProcuChain

This document outlines the smart-contract-style enforcement that can be implemented on top of the existing MultiChain integration. MultiChain does not execute Turing-complete contracts, but its **libraries** and **stream filters** allow us to enforce business rules directly on-chain. The goal is to publish reusable JavaScript helpers and attach filters to the relevant streams so that invalid transactions are rejected before they are committed.

## Guiding Principles

- **Re-use existing streams**: `procurement.documents`, `procurement.status`, `procurement.events`, and configuration variables already contain the data we need.
- **Centralize validation logic**: Author a shared JavaScript library (e.g. `document_validation_lib.js`) that exposes helpers for validating hashes, metadata, role permissions, and workflow transitions.
- **Surface rejections in Laravel**: Jobs and services (e.g. `PublishProcurementDocumentsJob`, `HandleStageTransitionJob`) should catch filter errors and relay human-readable messages to the UI/logs/tests.
- **Automate deployment**: Extend `App\Console\Commands\SmartContractSetup` to push the library and filters to MultiChain, subscribe streams, and report deployment status.

## Smart Contract Candidates

### 1. Document Validation Filter (`procurement.documents` stream)

| Aspect               | Details |
| -------------------- | ------- |
| **Purpose**          | Enforce document metadata integrity, hash uniqueness per procurement, and adherence to allowed document types. |
| **Triggers**         | Writes from `PublishProcurementDocumentsJob` and any direct `MultichainService::publish*` calls targeting the documents stream. |
| **Validation Rules** | - `hash` must be 64-character hex (`SHA-256`).<br>- `document_type` must match entries in `App\Enums\StageEnums`/allowed list.<br>- `file_size` within configured bounds.<br>- Required metadata (`file_key`, `user_address`, `timestamp`, `procurement_id`, `procurement_title`).<br>- Reject duplicates by checking existing stream items for the same `procurement_id` + `hash`. |
| **Implementation Notes** | - Library helper: `validateDocumentEntry(item, helpers)`.<br>- Filter JS uses `streamkeyitem` lookups to detect duplicates.<br>- Configurable thresholds (max size, allowed types) pulled from blockchain variables (`document_validation_config`). |

**Deployment Steps**
1. Add JavaScript library under `resources/blockchain/libraries/document_validation_lib.js`.
2. Add filter script under `resources/blockchain/filters/documents_filter.js` requiring the library and calling `validateDocumentEntry`.
3. Update `SmartContractSetup` to:
   - Publish the library via `create('library', 'document_validation', params, code)`.
   - Attach the filter via `create('streamfilter', 'documents_validator', {"for":"procurement.documents"}, code)`.
   - Subscribe to `procurement.documents` and log the TXIDs.
4. Adjust Laravel jobs to handle filter rejection messages.

### 2. Status Transition Filter (`procurement.status` stream)

| Aspect               | Details |
| -------------------- | ------- |
| **Purpose**          | Guarantee legal workflow transitions defined in `App\Enums\StatusEnums` and `App\Enums\StageEnums`. |
| **Triggers**         | `HandleStageTransitionJob`, `StatusUpdaterService`, and any manual status updates. |
| **Validation Rules** | - Transition must exist in a predefined map (previous status → allowed next statuses).<br>- `stage` must align with the resulting status.<br>- `timestamp` must be >= previous entry timestamp.<br>- Publishing address must belong to a role permitted to trigger the transition (BAC Secretariat, BAC Chairman, HOPE, Admin). |
| **Implementation Notes** | - Library helper: `validateStatusTransition(previousItem, newItem, roleMap)`.<br>- Filter fetches latest status entry for the procurement using `liststreamkeyitems`.<br>- Role addresses read from `config('multichain.addresses.*')` or stored on-chain in a variable. |

**Deployment Steps**
1. Extend the shared library with `allowedTransitions` map and role validation helper.
2. Create `resources/blockchain/filters/status_filter.js` to call `validateStatusTransition`.
3. Deploy via `SmartContractSetup` and subscribe to `procurement.status`.
4. Update jobs/controllers to gracefully report validation failures.

### 3. Event Integrity Filter (`procurement.events` stream)

| Aspect               | Details |
| -------------------- | ------- |
| **Purpose**          | Ensure logged events are consistent with the current procurement state and use approved enums. |
| **Triggers**         | `LogBlockchainEventJob` and `BlockchainEventLoggerService`. |
| **Validation Rules** | - `event_type`, `category`, `severity` must match enumerations (`App\Enums\StreamEnums`, FE types).<br>- Reference status/stage must equal the latest status entry.<br>- `document_count` consistency check (non-negative, matches uploaded docs when provided).<br>- Only allowed roles may log specific event categories (e.g., system events by Admin). |
| **Implementation Notes** | - Library helper: `validateEventEntry(eventItem, currentStatus, roleMap)`.<br>- Filter queries status stream to align event with current stage. |

**Deployment Steps**
1. Add helper functions for events within the library.
2. Create `resources/blockchain/filters/events_filter.js` referencing those helpers.
3. Deploy and subscribe similarly to the other filters.
4. Update `LogBlockchainEventJob` to expose errors back to notifications/UI.

### 4. Configuration Guard Filter (variables namespace)

| Aspect               | Details |
| -------------------- | ------- |
| **Purpose**          | Restrict modifications to on-chain configuration variables (validation settings, allowed types) to authorized admin addresses and validate JSON schema. |
| **Triggers**         | `SmartContractService::setVariableValue` and `createVariable`. |
| **Validation Rules** | - Publisher must match `MULTICHAIN_ADMIN_ADDRESS` (or list stored in variable).<br>- JSON payload must follow schema (e.g., for `document_validation_config`).<br>- Version bump required (include `version` field to avoid stale updates). |
| **Implementation Notes** | - Filter may be attached through `create('streamfilter', …)` using the `"for":"variable"` scope.<br>- Reject if schema validation fails or version decreases. |

**Deployment Steps**
1. Extend library with schema validation for configuration payloads.
2. Create `resources/blockchain/filters/config_filter.js` targeting variables interactions.
3. Deploy via `SmartContractSetup` and test by attempting unauthorized updates.

## Project Structure Recommendations

```
resources/
  blockchain/
    libraries/
      document_validation_lib.js
    filters/
      documents_filter.js
      status_filter.js
      events_filter.js
      config_filter.js
```

- Keep the JavaScript files version-controlled.
- Include metadata headers (version, checksum) inside each JS file for easy audit.
- Consider adding a JSON manifest (e.g., `resources/blockchain/manifest.json`) to map library/filter names to file paths and versions for automated deployment.

## Deployment Automation Updates

Modify `App\Console\Commands\SmartContractSetup` to:

1. Detect whether the library/filter is already deployed (via `listlibraries`, `liststreamfilters`).
2. Publish or update artifacts with retry logic (reuse `MultichainService::create` helpers).
3. Subscribe to streams with `rescan=false` after successful deployment to avoid replay.
4. Output a summary table (artifact name, version, txid, status) for the panel demo.

## Testing Strategy

- **Unit Tests (Pest)**: Mock `MultichainService` to ensure deployment command issues the correct RPC calls and handles errors.
- **Integration Tests**: Spin up a MultiChain test node (or mock) to publish sample transactions; verify invalid payloads are rejected with expected error messages.
- **Manual Verification**: Use `multichain-cli procuchain publish …` to attempt invalid writes and confirm rejection.

## Limitations and Future Enhancements

- Filters can only accept or reject transactions; they cannot mutate external state or perform complex cross-stream operations beyond available RPC queries.
- JavaScript environment is limited—no external libraries or network access. Logic must remain deterministic and lightweight.
- If future requirements demand richer on-chain logic (private channels, chaincode state machines), evaluate migrating to Hyperledger Fabric or an EVM-compatible chain.

## Next Steps Checklist

1. Draft the JavaScript library and filter files described above.
2. Update `SmartContractSetup` to deploy and report on these artifacts.
3. Modify Laravel jobs/services to surface filter rejection errors to the UI/tests.
4. Add Pest tests covering deployment logic and representative rejection scenarios.
5. Prepare demo scripts showing successful vs. rejected transactions for the capstone defense.
