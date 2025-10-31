# MultiChain Smart Contract Plan for ProcuChain

This document outlines the smart-contract-style enforcement that can be implemented on top of the existing MultiChain integration. MultiChain does not execute Turing-complete contracts, but its **libraries** and **stream filters** allow us to enforce business rules directly on-chain. The goal is to publish reusable JavaScript helpers and attach filters to the relevant streams so that invalid transactions are rejected before they are committed.

## Implementation Status

### ✅ Phase 2 - Implemented (October 2025)

**Smart Filters Deployed:**
- ✅ **Document Validation Filter** (`documents_filter_v1_standalone.js`)
  - Validates document hashes (SHA-256 format)
  - Enforces metadata integrity
  - Checks file size limits (10MB max)
  - Validates document types against allowed stages
  
- ✅ **Status Transition Filter** (`status_filter_v1_standalone.js`)
  - Enforces legal workflow transitions
  - Validates status and stage enumerations
  - Ensures timestamp progression
  
- ✅ **Event Integrity Filter** (`events_filter_v1_standalone.js`)
  - Validates event types and categories
  - Ensures consistency with procurement state
  
- ✅ **Corrections Filter** (`corrections_filter_v1_standalone.js`)
  - Validates document correction metadata
  - Ensures proper correction workflow

**Supporting Infrastructure:**
- ✅ **Validation Helpers Library** (`validation_helpers.js`)
  - Shared validation functions for hash, enum, file size, timestamps
  - Reusable across all filters
  
- ✅ **Deployment Automation** (`SmartContractSetup` Artisan command)
  - `php artisan smartcontract:setup` - Full deployment
  - `php artisan smartcontract:setup --check` - Status verification
  - `php artisan smartcontract:setup --deploy-libraries` - Libraries only
  - `php artisan smartcontract:setup --deploy-filters` - Filters only

**Implementation Notes:**
- Filters implemented as **standalone versions** for MultiChain Community Edition compatibility
- Validation rules are hardcoded in filters rather than stored in blockchain variables
- All filters return human-readable rejection messages surfaced to Laravel logs/UI

### ⏭️ Phase 3 - Future Enhancement (Not Implemented)

**Configuration Guard Filter:**
- ❌ **Not currently implemented**
- Would protect on-chain configuration variables from unauthorized modification
- Would enable dynamic validation rule changes without code redeployment
- **Current approach**: Validation rules are hardcoded in filter JavaScript files
- **Rationale for skipping**: 
  - System currently uses Laravel config files, not blockchain variables for settings
  - Hardcoded validation rules are sufficient for current requirements
  - Would add complexity without immediate benefit
  - Recommended for future production enterprise deployment where governance over validation rules is critical

**Migration Path (if needed in future):**
1. Move validation thresholds from hardcoded values to blockchain variables
2. Implement variable schema validation
3. Create config filter targeting variable write operations
4. Update filters to read configuration from `getvariablevalue()` callbacks
5. Establish admin approval workflow for configuration changes

## MultiChain Smart Contract Model

MultiChain approaches smart contracts as deterministic validation rather than general-purpose computation. Instead of executing arbitrary bytecode on every node, MultiChain stores rules as JavaScript **Smart Filters** that run inside a sandboxed V8 engine whenever a transaction or stream item is processed.[^1] Filters can only accept or reject data, which keeps consensus fast while still enforcing business logic close to the ledger.

The platform exposes a small set of building blocks for composing richer behaviour:[^2][^3]

- **Smart Filters** (`txfilter`, `streamfilter`): Deterministic validators that inspect transaction inputs/outputs or stream items before they are committed. They can call helper callbacks such as `getfiltertransaction()`, `getfilterstreamitem()`, `getvariablevalue()`, and stream query helpers.
- **Libraries**: On-chain JavaScript modules whose functions can be imported into filters via `options.libraries`. Libraries support immutable, instant-update, or admin-approved update modes, enabling controlled rollouts of shared logic.
- **Variables**: Shared JSON blobs stored on-chain, readable from filters and writable by authorised addresses. We use them to store configuration like allowed document types, size thresholds, or role mappings.
- **Permissions & Approvals**: Deployment requires the `create` permission; activating filters or library updates relies on admin consensus via `approvefrom`. Per-stream, per-variable, and per-library permissions gate who may publish or update.

Implementation in ProcuChain layers domain-specific validation on these primitives: we publish reusable helpers as libraries, encapsulate rule parameters in variables, and attach filters to the relevant streams so every node enforces the same workflow.

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

## References

[^1]: Gideon Greenspan, “Smart contracts: The good, the bad and the lazy,” MultiChain Blog (2015) – <https://www.multichain.com/blog/2015/11/smart-contracts-good-bad-lazy/>.
[^2]: Gideon Greenspan, “MultiChain 2.1: Variables and Libraries,” MultiChain Blog (2020) – <https://www.multichain.com/blog/2020/10/multichain-2-1-variables-libraries/>.
[^3]: MultiChain, “JSON-RPC API commands,” Developer Documentation – <https://www.multichain.com/developers/json-rpc-api/>.
