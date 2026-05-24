# ProcuChain Data Dictionary

Generated from ProcuChain codebase — Laravel 13 + MultiChain CE
Aligned with RA 12009 (New Government Procurement Act) compliance requirements

---

| # | Data Element | Description | Data Type | Length | Example |
|---|---|---|---|---|---|
| 1 | `user_id` | Unique identifier of the user | Integer | — | 7001 |
| 2 | `full_name` | Name of the system user | Varchar | 255 | Juan Del Cruz |
| 3 | `email` | User email address | Varchar | 255 | user@gmail.com |
| 4 | `password` | Encrypted password of the user (bcrypt/hash) | Varchar | 255 | Encrypted Value |
| 5 | `role_id` | Identifier of user role (FK → roles) | Integer | — | 1 |
| 6 | `role_name` | Type of user role (e.g., BAC Secretary, BAC Chair, HOPE) | Varchar | 100 | BAC Secretary |
| 7 | `procurement_id` | Unique procurement record ID | Integer | — | 2001 |
| 8 | `procurement_title` | Title of procurement project | Varchar | 255 | Office Supplies Procurement |
| 9 | `document_type` | Type of uploaded procurement document (per RA 12009 stages) | Varchar | 700 | Notice of Award |
| 10 | `document_file_path` | Uploaded document file key/path on blockchain | Varchar | 255 | uploaded/noa.pdf |
| 11 | `hash_value` | SHA-256 hash generated from document | Text | — | a1b2c3d4e5f6... |
| 12 | `blockchain_tid` | Blockchain transaction ID (on-chain receipt) | Varchar | 255 | 7f3a9b2c1d... |
| 13 | `status` | Current procurement status | Varchar | 700 | Approved |
| 14 | `uploaded_by` | User who uploaded the document (FK → users) | Integer | — | 7001 |
| 15 | `upload_date` | Date uploaded | Datetime | — | 2026-05-24 |
| 16 | `notification_message` | Email notification content | Text | — | Document Approved |
| 17 | `audit_log` | Records of user activities (action + old/new values) | Text | — | Uploaded Document |
| 18 | `node_id` | Blockchain node identifier | Integer | — | 3 |

---

## Source Mapping (Codebase → Data Element)

| Data Element | Database Column | Table | On-Chain Stream | On-Chain Key |
|---|---|---|---|---|
| `user_id` | `id` | `users` | — | — |
| `full_name` | `name` | `users` | — | — |
| `email` | `email` | `users` | — | — |
| `password` | `password` | `users` | — | — |
| `role_id` | (via Spatie permissions) | `model_has_roles` | — | — |
| `role_name` | (via `primary_role` accessor) | `roles` | — | — |
| `procurement_id` | `pr_number` | (on-chain only) | `procurement.metadata` | `{pr_number}` |
| `procurement_title` | `procurement_title` | (on-chain only) | `procurement.metadata` | `{pr_number}` |
| `document_type` | `document_type` | (on-chain only) | `file.metadata` | `{data_key}` |
| `document_file_path` | `file_key` | (on-chain only) | `file.data` | `{data_key}` |
| `hash_value` | `hash` | (on-chain only) | `file.metadata` | `{data_key}` |
| `blockchain_tid` | `data_txid` / `metadata_txid` | (on-chain only) | `file.data` + `file.metadata` | `{data_key}` |
| `status` | `current_status` | (on-chain only) | `procurement.status` | `{pr_number}` |
| `uploaded_by` | `uploaded_by` (FK → users.id) | (on-chain only) | `file.metadata` | `{data_key}` |
| `upload_date` | `stored_at` | (on-chain only) | `file.metadata` | `{data_key}` |
| `notification_message` | `notification_message` | (on-chain only) | — | — |
| `audit_log` | `action` + `old_values` + `new_values` | `audit_logs` | `file.metadata` | varies |
| `node_id` | (config-driven) | `multichain_nodes` | `file.metadata` | `node_{id}_*` |

---

## Blockchain Streams (RA 12009 Compliance)

| Stream | Purpose | NGPA Reference |
|---|---|---|
| `procurement.metadata` | Procurement creation + metadata updates | Sec. 12 (Procurement Planning) |
| `procurement.status` | Status transitions (Draft → Approved → Completed) | Sec. 38 (Transparency) |
| `procurement.events` | Stage events, document uploads, phase transitions | Sec. 20 (Electronic Records) |
| `procurement.corrections` | Amendments and corrections to procurement records | Sec. 23 (Amendments) |
| `procurement.metadata.corrections` | Metadata-level corrections | Sec. 23 |
| `file.data` | Raw document file content (binary on-chain) | Sec. 20 |
| `file.metadata` | File metadata, hashes, purge/resync events | Sec. 20 + Sec. 3 (Accountability) |
| `file.chunks` | Chunked file data for large documents | Sec. 20 |
| `procurement.archive` | Archive/restore status markers | Sec. 38 |

---

## Audit Trail Actions (Critical — NGPA Sec. 3)

| Action Key | Label | Critical |
|---|---|---|
| `node.full_purge` | Full node purge — all data removed | ✅ |
| `node.file_purge` | File-level node purge | ✅ |
| `node.resync` | Node resync — data restored from peers | ✅ |
| `procurement.initiated` | Procurement initiated | ✅ |
| `procurement.corrected` | Procurement corrected/amended | ✅ |
| `procurement.archived` | Procurement archived | ✅ |
| `document.corrected` | Document correction recorded | ✅ |
| `account.locked` | Account locked | ✅ |
| `user.deleted` | User deleted | ✅ |

---

*Generated: 2026-05-24 | ProcuChain v1.0 | RA 12009 NGPA-Compliant Blockchain Procurement System*
