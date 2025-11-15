# ProcuChain Blockchain Stream Data Structures

Complete reference for all data stored in the 8 blockchain streams.

---

## 📋 1. procurement.metadata

**Purpose:** Procurement "birth certificate" - core metadata for each procurement

**Stream Key:** `pr_number` (e.g., `PROC-2024-001`)

**Data Structure:**
```json
{
  "pr_number": "PROC-2024-001",
  "pr_number": "PR-2024-001",
  "ppmp_reference": "PPMP-2024-001",
  "title": "Procurement of Office Supplies",
  "description": "Annual office supplies for FY 2024",
  "abc_amount": "500000.00",
  "funding_source": "General Fund",
  "category": "goods",
  "procurement_mode": "shopping",
  "department": "General Services Office",
  "requesting_office": "Administrative Division",
  "end_user": "All Departments",
  "purpose": "Regular office operations",
  "delivery_location": "Main Office, Warehouse A",
  "delivery_date": "2024-12-31T00:00:00+08:00",
  "delivery_term_days": 30,
  "prepared_by": "Juan dela Cruz",
  "approved_by": "Maria Santos",
  "approval_date": "2024-01-15T10:30:00+08:00",
  "bac_resolution_number": "BAC-RES-2024-001",
  "bac_resolution_date": "2024-01-20T14:00:00+08:00",
  "philgeps_reference": "REF-2024-001",
  "philgeps_posting_date": "2024-01-22T09:00:00+08:00",
  "status": "active",
  "user_id": "user-123",
  "created_at": "2024-01-10T08:00:00+08:00"
}
```

**Attributes:**
- `pr_number` (string, required) - Unique procurement identifier
- `pr_number` (string, nullable) - Purchase Request number
- `ppmp_reference` (string, nullable) - PPMP reference number
- `title` (string, required) - Procurement title
- `description` (string, required) - Detailed description
- `abc_amount` (string, required) - Approved Budget for Contract (stored as string to preserve precision)
- `funding_source` (string, required) - Source of funds
- `category` (enum, required) - `goods`, `infrastructure`, `consulting_services`, `other_services`
- `procurement_mode` (enum, required) - `shopping`, `public_bidding`, `limited_source_bidding`, `direct_contracting`, `negotiated_procurement`, `small_value_procurement`, `alternative_methods`, `emergency_cases`, `highly_technical`
- `department` (string, required) - Procuring department
- `requesting_office` (string, required) - Office requesting procurement
- `end_user` (string, nullable) - Final user of procured items
- `purpose` (string, required) - Purpose of procurement
- `delivery_location` (string, required) - Where items will be delivered
- `delivery_date` (ISO8601, required) - Expected delivery date
- `delivery_term_days` (integer, nullable) - Delivery term in days
- `prepared_by` (string, nullable) - Person who prepared documents
- `approved_by` (string, nullable) - Approving authority
- `approval_date` (ISO8601, nullable) - Date of approval
- `bac_resolution_number` (string, nullable) - BAC resolution reference
- `bac_resolution_date` (ISO8601, nullable) - BAC resolution date
- `philgeps_reference` (string, nullable) - PhilGEPS reference number
- `philgeps_posting_date` (ISO8601, nullable) - PhilGEPS posting date
- `status` (string, required) - Current status
- `user_id` (string, required) - User who created procurement
- `created_at` (ISO8601, required) - Creation timestamp

**Current Usage:** 0 items

---

## 📄 2. procurement.documents

**Purpose:** Document registry linking uploaded files to procurements

**Stream Key:** `pr_number` (e.g., `PROC-2024-001`)

**Data Structure:**
```json
{
  "pr_number": "TEST-FINAL-001",
  "procurement_title": "Final Test - All Filters",
  "user_address": "1G58VnnbEYuJwMdfuxS44hTytKUrRNY2FvLmq",
  "stage": "procurement_initiation",
  "status": "submitted",
  "document_type": "procurement_initiation",
  "file_key": "procurement_documents/PROC-2024-001/procurement_initiation_1234567890.pdf",
  "file_name": "PPMP_2024.pdf",
  "file_size": 245760,
  "mime_type": "application/pdf",
  "hash": "a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3",
  "data_txid": "3c5aa17db85a892dd6ee15fe3279cc8752f19486ebcecc98e75589e9816ed9d6",
  "metadata_txid": "b5d2da06f9409e0997de0f09ddd2bbbea3eb42c7302537fd7472caeaf81c7a5f",
  "uploaded_by": "Juan dela Cruz",
  "timestamp": "2025-10-31T13:07:51+00:00",
  "description": "Annual PPMP for FY 2024"
}
```

**Attributes:**
- `pr_number` (string, required) - Links to procurement
- `procurement_title` (string, required) - Human-readable title
- `user_address` (string, required) - Blockchain address of uploader
- `stage` (enum, required) - Procurement stage: `procurement_initiation`, `pre_procurement`, `advertisement`, `submission_opening`, `evaluation`, `post_qualification`, `award_contract`, `implementation`, `acceptance_turnover`, `completion`
- `status` (string, required) - Document status: `submitted`, `approved`, `rejected`, `pending`
- `document_type` (string, required) - Must match stage for filter validation
- `file_key` (string, required) - Unique file identifier (path)
- `file_name` (string, required) - Original filename
- `file_size` (integer, required) - File size in bytes (max 52,428,800 = 50MB)
- `mime_type` (string, required) - MIME type (typically `application/pdf`)
- `hash` (string, required) - SHA-256 hash of file content
- `data_txid` (string, required) - Transaction ID in `file.data` stream
- `metadata_txid` (string, required) - Transaction ID in `file.metadata` stream
- `uploaded_by` (string, required) - Name of person who uploaded
- `timestamp` (ISO8601, required) - Upload timestamp
- `description` (string, nullable) - Optional document description

**Current Usage:** 11 items

---

## 🔄 3. procurement.status

**Purpose:** Complete status change history with audit trail

**Stream Key:** `pr_number` (e.g., `PROC-2024-001`)

**Data Structure:**
```json
{
  "pr_number": "TEST-2024-001",
  "procurement_title": "Test Procurement for Filter Validation",
  "stage": "procurement_initiation",
  "current_status": "procurement_submitted",
  "user_address": "1G58VnnbEYuJwMdfuxS44hTytKUrRNY2FvLmq",
  "timestamp": "2025-10-31T12:44:22+00:00",
  "previous_status": "draft",
  "metadata": {
    "document_count": 5,
    "transition_reason": "All required documents uploaded"
  }
}
```

**Attributes:**
- `pr_number` (string, required) - Links to procurement
- `procurement_title` (string, required) - Human-readable title
- `stage` (enum, required) - Current stage
- `current_status` (enum, required) - Current status: `procurement_submitted`, `bac_review`, `approved`, `rejected`, `pending_revision`, `for_advertisement`, `for_evaluation`, `for_award`, `contract_signed`, `ongoing`, `completed`, `cancelled`
- `user_address` (string, required) - Address of person making status change
- `timestamp` (ISO8601, required) - When status changed
- `previous_status` (enum, nullable) - Previous status for history tracking
- `metadata` (object, nullable) - Additional context about status change

**Current Usage:** 7 items

---

## 📅 4. procurement.events

**Purpose:** Activity timeline for notifications and audit trail

**Stream Key:** Composite key combining `pr_number` and descriptive suffix

**Data Structure:**
```json
{
  "pr_number": "TEST-2024-EVENTS-001",
  "procurement_title": "Test Procurement for Events Filter",
  "stage": "procurement_initiation",
  "event_type": "document_published",
  "category": "document",
  "severity": "info",
  "details": "Successfully published procurement documents to blockchain",
  "document_count": 5,
  "user_address": "1G58VnnbEYuJwMdfuxS44hTytKUrRNY2FvLmq",
  "timestamp": "2025-10-31T12:58:08+00:00",
  "metadata": {
    "stage_transition": false,
    "files_uploaded": ["PPMP.pdf", "APP.pdf", "PR.pdf"]
  }
}
```

**Attributes:**
- `pr_number` (string, required) - Links to procurement
- `procurement_title` (string, required) - Human-readable title
- `stage` (enum, required) - Stage when event occurred
- `event_type` (string, required) - Type of event: `document_published`, `status_updated`, `stage_transitioned`, `stage_started`, `stage_completed`, `correction_made`, `approval_granted`, `rejection_issued`
- `category` (string, required) - Event category: `document`, `status`, `stage_transition`, `procurement`, `approval`, `correction`
- `severity` (string, required) - Severity level: `info`, `warning`, `error`
- `details` (string, required) - Human-readable event description
- `document_count` (integer, required) - Number of documents involved in event
- `user_address` (string, required) - Address of person triggering event
- `timestamp` (ISO8601, required) - When event occurred
- `metadata` (object, nullable) - Additional event context

**Current Usage:** 10 items

---

## ✏️ 5. procurement.corrections

**Purpose:** Amendment records with full audit trail and reasons

**Stream Key:** `pr_number` (e.g., `PROC-2024-001`)

**Data Structure:**
```json
{
  "pr_number": "PROC-2024-001",
  "procurement_title": "Procurement of Office Supplies",
  "original_txid": "3c5aa17db85a892dd6ee15fe3279cc8752f19486ebcecc98e75589e9816ed9d6",
  "original_document_hash": "a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3",
  "correction_type": "document_correction",
  "action": "replace",
  "reason": "BAC requested updated technical specifications",
  "corrected_by": "Maria Santos",
  "user_address": "1G58VnnbEYuJwMdfuxS44hTytKUrRNY2FvLmq",
  "timestamp": "2025-11-14T10:30:00+08:00",
  "corrected_metadata": {
    "file_name": "PPMP_2024_revised.pdf",
    "file_size": 256000,
    "mime_type": "application/pdf",
    "file_key": "procurement_documents/PROC-2024-001/corrected_1731557400.pdf",
    "hash": "b776a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae4",
    "data_txid": "4d6bb18eb95b992ee7ff26gf4380dd9863g20597fcdfdd09f86690f0927fe0e7",
    "metadata_txid": "c6e3eb17g0510f0008ef1g10eee3cccfb4fc53d8413648ge8583dbfbg92d8b60"
  }
}
```

**Attributes:**
- `pr_number` (string, required) - Links to procurement
- `procurement_title` (string, required) - Human-readable title
- `original_txid` (string, required) - Transaction ID of original document
- `original_document_hash` (string, required) - SHA-256 hash of original document
- `correction_type` (string, required) - Type: `document_correction`, `metadata_correction`, `status_correction`, `timeline_correction`
- `action` (string, required) - Action taken: `replace`, `invalidate`, `update`
- `reason` (string, required) - Detailed reason for correction
- `corrected_by` (string, required) - Name of person making correction
- `user_address` (string, required) - Blockchain address of corrector
- `timestamp` (ISO8601, required) - When correction was made
- `corrected_metadata` (object, required if action=replace) - New file/data information

**Current Usage:** 4 items

---

## 💾 6. file.data

**Purpose:** Actual file content stored as binary hex

**Stream Key:** `data_key` (file_key with slashes replaced by underscores)

**Data Structure:**
```
Raw hex string (e.g., "255044462d312e34..." = PDF bytes)
```

**Attributes:**
- **Key:** Sanitized file path (e.g., `procurement_documents_PROC-2024-001_ppmp.pdf`)
- **Data:** Binary file content converted to hexadecimal string
- **Max Size:** 52,428,800 bytes (50 MB) per file
- **Format:** Hex-encoded binary data

**Example:**
```
Key: "comp-proc-1-Completion_Procurement_Completion__Certificate_of_Completion_pdf"
Data: "255044462d312e34..." (thousands of hex characters representing PDF)
```

**Current Usage:** 46 items

---

## 🏷️ 7. file.metadata

**Purpose:** File information and metadata (filename, size, hash, timestamps)

**Stream Key:** `data_key` (same as file.data)

**Data Structure:**
```json
{
  "filename": "completion.pdf",
  "file_key": "comp-proc-1-Completion_Procurement/Completion/Certificate_of_Completion.pdf",
  "data_txid": "66e8331dd5728176a6dc1938f8e01fc231caeaa9f588d6a3272dff067ee1246e",
  "data_key": "comp-proc-1-Completion_Procurement_Completion__Certificate_of_Completion.pdf",
  "mime_type": "application/pdf",
  "size": 102400,
  "hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "storage_method": "on_chain",
  "stored_at": "2025-11-11T10:31:49+00:00",
  "pr_number": "comp-proc-1",
  "procurement_title": "Completion Procurement",
  "document_type": "Certificate of Completion",
  "stage": "Completion"
}
```

**Attributes:**
- `filename` (string, required) - Original filename
- `file_key` (string, required) - Full file path/key
- `data_txid` (string, required) - Transaction ID where file data is stored
- `data_key` (string, required) - Sanitized key (matches stream key)
- `mime_type` (string, required) - File MIME type
- `size` (integer, required) - File size in bytes
- `hash` (string, required) - SHA-256 hash for integrity verification
- `storage_method` (string, required) - Always `on_chain`
- `stored_at` (ISO8601, required) - When file was stored
- Additional metadata passed during upload (pr_number, procurement_title, document_type, stage, etc.)

**Current Usage:** 48 items

---

## 📦 8. file.chunks

**Purpose:** Large file chunk storage for files over 50MB

**Stream Key:** `file_key` + `_chunk_` + `chunk_number`

**Data Structure:**
```json
{
  "file_key": "procurement_documents/PROC-2024-001/large_file.pdf",
  "chunk_number": 1,
  "total_chunks": 3,
  "chunk_data": "255044462d312e34...",
  "chunk_size": 52428800,
  "chunk_hash": "a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3"
}
```

**Attributes:**
- `file_key` (string, required) - Original file identifier
- `chunk_number` (integer, required) - Chunk sequence number (1-based)
- `total_chunks` (integer, required) - Total number of chunks for file
- `chunk_data` (hex string, required) - Chunk content as hex
- `chunk_size` (integer, required) - Size of this chunk in bytes
- `chunk_hash` (string, required) - SHA-256 hash of chunk

**Current Usage:** 0 items (no files over 50MB uploaded yet)

**Note:** Files are automatically split into 50MB chunks when exceeding the limit. Reassembly is handled by FileStorageService.

---

## Stream Relationships

### Document Upload Flow
```
1. file.data          → Store actual file content (hex)
2. file.metadata      → Store file information
3. procurement.documents → Link document to procurement
4. procurement.status → Update procurement status
5. procurement.events → Log activity to timeline
```

### Correction Flow
```
1. file.data          → Store replacement file (if applicable)
2. file.metadata      → Store replacement file info
3. procurement.corrections → Record correction with reason
4. procurement.events → Log correction event
```

### Status Update Flow
```
1. procurement.status → Record new status
2. procurement.events → Log status change event
```

---

## Data Integrity

All streams ensure data integrity through:

- **Immutability:** Once published, data cannot be modified (only new versions can be added)
- **Cryptographic Hashing:** SHA-256 hashes verify file integrity
- **Transaction Linking:** Related data linked via transaction IDs
- **Blockchain Verification:** All data timestamped and verified by blockchain consensus
- **Audit Trail:** Complete history maintained via append-only streams

---

## Query Examples

### Get All Documents for Procurement
```php
$documents = $multichain->listStreamKeyItems('procurement.documents', 'PROC-2024-001');
```

### Get Procurement Status History
```php
$statusHistory = $multichain->listStreamKeyItems('procurement.status', 'PROC-2024-001');
```

### Get File Content
```php
$fileData = $multichain->listStreamKeyItems('file.data', 'procurement_documents_PROC-2024-001_ppmp.pdf', false, 1);
$hexContent = $fileData[0]['data'];
$fileBytes = hex2bin($hexContent);
```

### Get All Events for Procurement
```php
$events = $multichain->listStreamKeyItems('procurement.events', 'PROC-2024-001');
```

---

## RA 9184 Compliance

These 8 streams implement RA 9184 requirements:

✅ **Separate Audit Trails** - Each stream maintains independent records  
✅ **Immutable Records** - Blockchain ensures no tampering  
✅ **Complete History** - All changes tracked with timestamps  
✅ **Document Integrity** - SHA-256 hashes prevent alterations  
✅ **Permission Control** - Different access levels per stream  
✅ **Transparency** - Public blockchain verification  
✅ **Correction Tracking** - All amendments documented with reasons  

---

*Last Updated: November 14, 2025*  
*Total Streams: 8*  
*Total Items: 126*  
*Active Procurements: ~6*
