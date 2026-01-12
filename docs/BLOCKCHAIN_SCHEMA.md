# Blockchain Schema Documentation

This document outlines the blockchain data structures for the ProcuChain application using MultiChain.

## Streams

| Stream Name | Purpose | Key |
|-------------|---------|-----|
| `procurement.metadata` | Core procurement records | `pr_number` |
| `procurement.documents` | Document registry | `file_key` |
| `procurement.status` | Status audit trail | `pr_number` |
| `procurement.events` | Activity log | `pr_number` |
| `procurement.corrections` | Document amendments | `pr_number` |
| `procurement.metadata.corrections` | Procurement amendments | `pr_number` |
| `procurement.archive` | Archive flags | `pr_number` |
| `file.metadata` | File registry | `file_key` |
| `file.data` | File content (hex) | `data_key` |
| `file.chunks` | Large file parts | `chunk_key` |

---

## `procurement.metadata`

Stores core procurement metadata.

| Field | Type | Nullable | Description |
|:------|:-----|:---------|:------------|
| `pr_number` | string | No | Primary Key (e.g., `PR-2026-0001`) |
| `app_reference` | string | Yes | Annual Procurement Plan reference |
| `title` | string | No | Procurement title |
| `description` | string | No | Detailed description |
| `abc_amount` | string | No | Approved Budget for Contract |
| `funding_source` | string | No | Source of funds |
| `category` | enum | No | `goods`, `services`, `infrastructure_projects`, `consulting_services` |
| `procurement_mode` | enum | No | See Procurement Modes |
| `office` | string | No | Requesting office |
| `end_user` | string | Yes | End-user department |
| `delivery_location` | string | Yes | Delivery address |
| `delivery_date` | ISO8601 | Yes | Expected delivery date |
| `delivery_term_days` | integer | Yes | Delivery period in days |
| `prepared_by` | string | Yes | Name of preparer |
| `bac_resolution_number` | string | Yes | BAC Resolution reference |
| `bac_resolution_date` | ISO8601 | Yes | BAC Resolution date |
| `philgeps_reference` | string | Yes | PhilGEPS posting ID |
| `philgeps_posting_date` | ISO8601 | Yes | PhilGEPS posting date |
| `approved_by` | string | Yes | HoPE name |
| `approval_date` | ISO8601 | Yes | Approval timestamp |
| `status` | string | No | Current procurement status |
| `user_id` | string | No | Creator's user ID |
| `created_at` | ISO8601 | No | Creation timestamp |

---

## `procurement.documents`

Stores document metadata linked to procurements.

| Field | Type | Nullable | Description |
|:------|:-----|:---------|:------------|
| `pr_number` | string | No | FK to procurement.metadata |
| `procurement_title` | string | No | Procurement title |
| `user_address` | string | No | Blockchain address of uploader |
| `stage` | enum | No | Procurement stage |
| `status` | string | No | Document status at upload |
| `document_type` | enum | No | Document classification |
| `file_key` | string | No | Primary Key - unique file path |
| `file_name` | string | No | Original filename |
| `file_size` | integer | No | Size in bytes |
| `mime_type` | string | No | MIME type |
| `hash` | string(64) | No | SHA-256 hash |
| `data_txid` | string | No | TXID of file data |
| `metadata_txid` | string | No | TXID of this record |
| `uploaded_by` | string | No | Uploader's name |
| `timestamp` | ISO8601 | No | Upload timestamp |
| `description` | string | Yes | Document description |
| `stage_metadata` | JSON | Yes | Stage-specific data |

---

## `procurement.status`

Audit trail of status transitions.

| Field | Type | Nullable | Description |
|:------|:-----|:---------|:------------|
| `pr_number` | string | No | FK to procurement.metadata |
| `procurement_title` | string | Yes | Procurement title |
| `stage` | enum | No | Current stage |
| `current_status` | enum | No | New status value |
| `previous_status` | enum | Yes | Previous status |
| `user_address` | string | No | Blockchain address of actor |
| `timestamp` | ISO8601 | No | Transition timestamp |
| `metadata` | JSON | Yes | Additional context |

---

## `procurement.events`

Activity log for audit.

| Field | Type | Nullable | Description |
|:------|:-----|:---------|:------------|
| `pr_number` | string | No | FK to procurement.metadata |
| `procurement_title` | string | No | Procurement title |
| `stage` | enum | No | Related stage |
| `event_type` | string | No | Event classification |
| `category` | string | No | Event category |
| `severity` | enum | No | `info`, `warning`, `error` |
| `details` | string | No | Event description |
| `document_count` | integer | No | Documents involved |
| `user_address` | string | No | Blockchain address of actor |
| `timestamp` | ISO8601 | No | Event timestamp |
| `metadata` | JSON | Yes | Additional data |

---

## `procurement.corrections`

Document correction records.

| Field | Type | Nullable | Description |
|:------|:-----|:---------|:------------|
| `pr_number` | string | No | FK to procurement.metadata |
| `procurement_title` | string | No | Procurement title |
| `original_txid` | string | No | TXID of original document |
| `original_document_hash` | string | No | Hash of original document |
| `correction_type` | string | No | Type of correction |
| `action` | string | No | Action taken |
| `reason` | string | No | Justification |
| `corrected_by` | string | No | Corrector's name |
| `user_address` | string | No | Blockchain address |
| `timestamp` | ISO8601 | No | Correction timestamp |
| `corrected_metadata` | JSON | Yes | Updated metadata |

---

## `procurement.metadata.corrections`

Procurement metadata correction records.

| Field | Type | Nullable | Description |
|:------|:-----|:---------|:------------|
| `pr_number` | string | No | FK to procurement.metadata |
| `procurement_title` | string | No | Procurement title |
| `correction_type` | string | No | `metadata`, `financial`, `dates`, `approval` |
| `reason` | string | No | Justification |
| `corrected_by` | string | No | Corrector's name |
| `user_address` | string | No | Blockchain address |
| `timestamp` | ISO8601 | No | Correction timestamp |
| `original_*` | various | Yes | Original field values |
| `corrected_*` | various | Yes | New field values |

---

## `file.metadata`

File storage registry.

| Field | Type | Nullable | Description |
|:------|:-----|:---------|:------------|
| `filename` | string | No | Original filename |
| `file_key` | string | No | Primary Key - unique path |
| `data_txid` | string | No | TXID of file content |
| `data_key` | string | No | Key in file.data stream |
| `mime_type` | string | No | File MIME type |
| `size` | integer | No | Size in bytes |
| `hash` | string(64) | No | SHA-256 hash |
| `storage_method` | enum | No | `single` or `chunked` |
| `stored_at` | ISO8601 | No | Storage timestamp |

---

## `file.data`

Raw file content (hex-encoded).

| Field | Type | Description |
|:------|:-----|:------------|
| `key` | string | Data key |
| `data` | hex | Hex-encoded file content |

---

## `file.chunks`

Chunked file parts for large files.

| Field | Type | Description |
|:------|:-----|:------------|
| `chunk_key` | string | `{file_key}_chunk_{index}` |
| `chunk_index` | integer | Zero-based index |
| `total_chunks` | integer | Total chunk count |
| `data` | hex | Hex-encoded chunk content |

---

## `procurement.archive`

Archive flags for soft delete.

| Field | Type | Nullable | Description |
|:------|:-----|:---------|:------------|
| `pr_number` | string | No | FK to procurement.metadata |
| `archived` | boolean | No | Archive flag |
| `archived_by` | string | No | User who archived |
| `archived_at` | ISO8601 | No | Archive timestamp |
| `reason` | string | Yes | Reason for archiving |

---

## Enumerations

### Procurement Modes (RA 12009 NGPA IRR)

| Value | Display Name |
|:------|:-------------|
| `competitive_bidding` | Competitive Bidding |
| `limited_source_bidding` | Limited Source Bidding |
| `competitive_dialogue` | Competitive Dialogue |
| `unsolicited_offer_with_bid_matching` | Unsolicited Offer with Bid Matching |
| `direct_contracting` | Direct Contracting |
| `direct_acquisition` | Direct Acquisition (≤₱200,000) |
| `repeat_order` | Repeat Order |
| `small_value_procurement` | Small Value Procurement |
| `negotiated_procurement` | Negotiated Procurement |
| `direct_sales` | Direct Sales |
| `direct_procurement_for_sti` | Direct Procurement for STI |

### Procurement Categories

| Value | Display Name |
|:------|:-------------|
| `goods` | Goods |
| `services` | General Support Services |
| `infrastructure_projects` | Infrastructure Projects |
| `consulting_services` | Consulting Services |

### Procurement Stages

| Value | Display Name | Phase |
|:------|:-------------|:------|
| `procurement_initiation` | Procurement Initiation | Pre-Procurement |
| `pre_procurement_conference` | Pre-Procurement Conference | Pre-Procurement |
| `bidding_documents` | Bidding Documents | Pre-Procurement |
| `request_for_quotation` | Request for Quotation | Procurement |
| `pre_bid_conference` | Pre-Bid Conference | Procurement |
| `supplemental_bid_bulletin` | Supplemental Bid Bulletin | Procurement |
| `bid_opening` | Bid Opening | Procurement |
| `abstract_of_quotations` | Abstract of Quotations | Procurement |
| `bid_evaluation` | Bid Evaluation | Procurement |
| `post_qualification` | Post-Qualification | Procurement |
| `bac_resolution` | BAC Resolution | Post-Procurement |
| `notice_of_award` | Notice of Award | Post-Procurement |
| `performance_bond_contract_and_po` | Performance Bond, Contract and PO | Post-Procurement |
| `notice_to_proceed` | Notice to Proceed | Post-Procurement |
| `monitoring` | Monitoring | Post-Procurement |
| `completion` | Completion | Post-Procurement |
| `completed` | Completed | Post-Procurement |

### Procurement Statuses

| Value | Display Name |
|:------|:-------------|
| `procurement_initiated` | Procurement Initiated |
| `procurement_submitted` | Procurement Submitted |
| `pre_procurement_conference_held` | Pre-Procurement Conference Held |
| `pre_procurement_conference_skipped` | Pre-Procurement Conference Skipped |
| `bidding_documents_published` | Bidding Documents Published |
| `quotations_received` | Quotations Received |
| `abstract_prepared` | Abstract Prepared |
| `bids_opened` | Bids Opened |
| `bids_evaluated` | Bids Evaluated |
| `post_qualification_verified` | Post-Qualification Verified |
| `resolution_recorded` | Resolution Recorded |
| `awarded` | Awarded |
| `performance_bond_contract_and_po_recorded` | Performance Bond, Contract and PO Recorded |
| `ntp_recorded` | NTP Recorded |
| `monitoring_completed` | Monitoring Completed |
| `completion_documents_uploaded` | Completion Documents Uploaded |
| `completed` | Completed |
| `stage_on_hold` | Stage On Hold |
| `stage_cancelled` | Stage Cancelled |
| `stage_skipped` | Stage Skipped |
