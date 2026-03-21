# Blockchain Schema

This document describes the current application-facing MultiChain streams used by ProcuChain.

## Overview

ProcuChain uses MultiChain as the immutable record of:

- procurement metadata
- procurement documents
- workflow status transitions
- audit events
- correction history
- archive flags
- file storage metadata and content

Application code should normally access chain data through repositories and services rather than raw RPC calls.

## Stream Catalog

| Stream | Purpose | Typical Key | Written By |
| --- | --- | --- | --- |
| `procurement.metadata` | core procurement records | `pr_number` | procurement initiation/orchestration services |
| `procurement.documents` | procurement-facing document records | `pr_number` | `DocumentRepository` via `DocumentPublisher` |
| `procurement.status` | workflow and stage status history | `pr_number` | `StatusPublisher` |
| `procurement.events` | audit/event log | composite key from `pr_number` and title | `EventRepository` via event publishers |
| `procurement.corrections` | document correction trail | `pr_number` | correction services/publishers |
| `procurement.metadata.corrections` | procurement metadata corrections | `pr_number` | procurement correction flows |
| `procurement.archive` | archive/restore flags | `pr_number` | archive controller/service |
| `file.data` | raw small-file payloads | normalized `data_key` | `FileUploader` |
| `file.metadata` | file metadata and retrieval references | normalized `data_key` | `FileUploader` |
| `file.chunks` | chunk payloads and chunk metadata for large files | `<data_key>_chunk_<index>` | `FileUploader` |

## `procurement.metadata`

Immutable procurement header data.

Common fields:

- `pr_number`
- `app_reference`
- `title`
- `description`
- `abc_amount`
- `funding_source`
- `category`
- `procurement_mode`
- `office`
- `end_user`
- `delivery_location`
- `delivery_date`
- `delivery_term_days`
- `prepared_by`
- `bac_resolution_number`
- `bac_resolution_date`
- `philgeps_reference`
- `philgeps_posting_date`
- `approved_by`
- `approval_date`
- `status`
- `user_id`
- `created_at`

## `procurement.documents`

Immutable procurement document records. These are the business-facing document entries used by procurement pages and verification flows.

Common fields:

- `pr_number`
- `procurement_title`
- `user_address`
- `stage`
- `status`
- `document_type`
- `file_key`
- `file_name`
- `file_size`
- `mime_type`
- `hash`
- `data_txid`
- `metadata_txid`
- `uploaded_by`
- `timestamp`
- `description`
- `stage_metadata`

Notes:

- the stream key is currently the procurement number, not the file key
- `file_key` points into the blockchain file storage structure
- the actual bytes live in `file.data` or `file.chunks`

## `procurement.status`

Immutable workflow history.

Common fields:

- `pr_number`
- `procurement_title`
- `stage`
- `current_status`
- `previous_status`
- `user_address`
- `timestamp`
- `metadata`

This stream is the durable source for stage and status progression history.

## `procurement.events`

Immutable event log for procurement activity.

Common fields:

- `pr_number`
- `procurement_title`
- `stage`
- `event_type`
- `category`
- `severity`
- `details`
- `document_count`
- `user_address`
- `timestamp`
- `metadata`

Current publisher behavior uses a composite-style key:

```text
<pr_number>_<sanitized_procurement_title>
```

Consumers should not depend on the stream key alone for filtering. The JSON payload is the durable contract.

## Correction Streams

### `procurement.corrections`

Used for document-level corrections. Common fields include:

- `pr_number`
- `procurement_title`
- `original_txid`
- `original_document_hash`
- `correction_type`
- `action`
- `reason`
- `corrected_by`
- `user_address`
- `timestamp`
- `corrected_metadata`

### `procurement.metadata.corrections`

Used for procurement metadata corrections. Common fields include:

- `pr_number`
- `procurement_title`
- `correction_type`
- `reason`
- `corrected_by`
- `user_address`
- `timestamp`
- `original_*`
- `corrected_*`

## `procurement.archive`

Archive/restore history for procurement records.

Common fields:

- `pr_number`
- `archived`
- `archived_by`
- `archived_at`
- `reason`

## File Storage Streams

### `file.data`

Stores raw hex-encoded content for smaller files in a single publish operation.

Key:

- normalized `data_key` derived from the canonical `file_key`

### `file.metadata`

Stores the retrieval and integrity metadata for a file.

Common fields:

- `filename`
- `file_key`
- `data_txid`
- `data_key`
- `mime_type`
- `size`
- `hash`
- `storage_method`
- `stored_at`
- additional metadata such as `pr_number`, `stage_id`, `phase`, `document_type`

### `file.chunks`

Stores large files in chunked form.

Current behavior:

- each chunk is written with key `<data_key>_chunk_<index>`
- chunk metadata is written with key `<data_key>_chunk_<index>_meta`
- metadata tracks `chunk_index`, `total_chunks`, `chunk_hash`, and `chunk_size`

## Address and Permission Model

`php artisan multichain:setup` manages role-scoped blockchain addresses for:

- `admin`
- `bac_secretariat`
- `bac_chairman`
- `hope`

Those addresses are persisted back to Laravel users and granted permissions according to the MultiChain config.

## Operational Notes

- local development uses Dockerized MultiChain plus the `multichain:setup` command
- `workflow:sync-defaults` affects MySQL workflow tables, not blockchain streams
- after a full local Docker volume reset, stream counts will be zero until new procurements are created

## Source of Truth in Code

Useful implementation references:

- `App\Enums\StreamEnums`
- `App\Services\Blockchain\FileUploader`
- `App\Repositories\DocumentRepository`
- `App\Repositories\EventRepository`
- `App\Services\Publishers\DocumentPublisher`
- `App\Services\Publishers\StatusPublisher`
