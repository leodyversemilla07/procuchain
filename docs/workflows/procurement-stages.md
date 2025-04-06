### **System Overview**

- **Users & Roles**:
    - **BAC Secretariat**: Uploads finalized documents, logs metadata/states/events via MultiChain APIs.
    - **BAC Chairman & HOPE**: Monitor via a read-only dashboard querying MultiChain streams.
- **Streams**:
    - **`procurement.documents`**: Stores general and stage-specific metadata for each document.
    - **`procurement.status`**: Tracks procurement statuses with history.
    - **`procurement.events`**: Logs actions with document counts.
- **Storage**: Finalized documents in DigitalOcean Spaces; hashes/metadata on MultiChain.

---

### **Detailed Workflow**

#### **Stage 1: Procurement Initiation**

- **Objective**: Record finalized PR and supporting documents
- **BAC Secretariat Actions**:
    - Uploads finalized procurement documents to Spaces via dashboard.
    - System auto-generates procurement ID (e.g., "PR-2025-001-001").
- **System Actions**: - Generates document metadata and logs to MultiChain: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/ProcurementInitiation/Purchase_Request.pdf` - **Value**: `{
    "document_type": "Purchase Request",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/ProcurementInitiation/Purchase_Request.pdf",
    "timestamp": "2025-04-01T10:15:33+00:00",
    "stage": "Procurement Initiation",
    "stage_metadata": {
        "submission_date": "2025-04-01",
        "municipal_offices": "MO",
        "signatory_details": "Leodyver Semilla"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/PR-2025-001-001-Office_Supplies_Procurement/ProcurementInitiation/Purchase_Request.pdf",
    "hash": "dbca9a1dd7f9c3ef7672dc4a8424fc9c048494cb5ab839ccb055ffce9ab4eb03",
    "file_size": 380397
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Procurement Initiation",
    "current_status": "Procurement Submitted",
    "timestamp": "2025-04-01T10:15:33+00:00"
}` - **`procurement.events`**: - **Key**: `PR-2025-001-001-<timestamp>` - **Value**: `{
    "event_type": "document_upload",
    "details": "Uploaded 1 finalized Procurement Initiation documents",
    "timestamp": "2025-04-01T10:15:33+00:00",
    "stage": "Procurement Initiation",
    "category": "workflow",
    "document_count": 1
}`

#### **Stage 2: Pre-Procurement Conference**

- **Objective**: Record conference decision and documents
- **BAC Secretariat Actions**:
    - Records conference decision via dashboard.
    - Uploads conference documents if held.
- **System Actions**: - Records decision and updates status: - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Pre-Procurement Conference",
    "current_status": "Pre-Procurement Conference Held",
    "timestamp": "2025-04-04T15:21:02+00:00"
}` - **`procurement.events`**: - **Key**: `PR-2025-001-001-<timestamp>` - **Value**: `{
    "event_type": "decision",
    "details": "Pre-procurement conference held - documents pending",
    "timestamp": "2025-04-04T15:21:02+00:00",
    "stage": "Pre-Procurement Conference",
    "category": "workflow",
    "document_count": 0
}`

#### **Stage 3: Bidding Documents**

- **Objective**: Record and publish finalized bidding documents
- **BAC Secretariat Actions**:
    - Uploads finalized bidding documents to Spaces via dashboard.
    - Enters metadata:
        - **General**: Document type—auto-filled: timestamp, user.
        - **Stage-Specific**: `issuance_date`, `signatory_details`.
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/BiddingDocuments/Bidding_Documents.pdf` - **Value**: `{
    "document_type": "Bidding Documents",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/BiddingDocuments/Bidding_Documents.pdf",  
    "timestamp": "2025-04-05T09:30:00+00:00",
    "stage": "Bidding Documents",
    "stage_metadata": {
        "issuance_date": "2025-04-05",
        "signatory_details": "Jane Smith"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Bidding Documents", 
    "current_status": "Bidding Documents Published",
    "timestamp": "2025-04-05T09:30:00+00:00"
}`

#### **Stage 4: Pre-Bid Conference**

- **Objective**: Record pre-bid conference documentation
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/PreBidConference/Minutes.pdf` - **Value**: `{
    "document_type": "Pre-Bid Conference Minutes",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/PreBidConference/Minutes.pdf",
    "timestamp": "2025-04-06T10:00:00+00:00",
    "stage": "Pre-Bid Conference",
    "stage_metadata": {
        "conference_date": "2025-04-06",
        "attendees": ["John Doe", "Jane Smith"]
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Pre-Bid Conference",
    "current_status": "Pre-Bid Conference Held",
    "timestamp": "2025-04-06T10:00:00+00:00"
}`

#### **Stage 5: Supplemental Bid Bulletin**

- **Objective**: Record any supplemental bulletins
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/SupplementalBidBulletin/Bulletin_001.pdf` - **Value**: `{
    "document_type": "Supplemental Bulletin",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/SupplementalBidBulletin/Bulletin_001.pdf",
    "timestamp": "2025-04-07T14:30:00+00:00",
    "stage": "Supplemental Bid Bulletin",
    "stage_metadata": {
        "bulletin_number": "001",
        "issuance_date": "2025-04-07"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 262144
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Supplemental Bid Bulletin",
    "current_status": "Supplemental Bulletins Completed",
    "timestamp": "2025-04-07T14:30:00+00:00"
}`

#### **Stage 6: Bid Opening**

- **Objective**: Record multiple finalized bid documents post-opening
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/BidOpening/Bid_Document_001.pdf` - **Value**: `{
    "document_type": "Bid Document",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/BidOpening/Bid_Document_001.pdf",
    "timestamp": "2025-04-08T10:00:00+00:00",
    "stage": "Bid Opening",
    "stage_metadata": {
        "bidder_name": "Company A",
        "bid_value": "1000000",
        "opening_date_time": "2025-04-08T10:00:00+00:00"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 419430
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Bid Opening",
    "current_status": "Bids Opened",
    "timestamp": "2025-04-08T10:00:00+00:00"
}`

#### **Stage 7: Bid Evaluation**

- **Objective**: Record finalized evaluation reports
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/BidEvaluation/Evaluation_Report.pdf` - **Value**: `{
    "document_type": "Evaluation Report",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/BidEvaluation/Evaluation_Report.pdf",
    "timestamp": "2025-04-09T10:00:00+00:00",
    "stage": "Bid Evaluation",
    "stage_metadata": {
        "evaluation_date": "2025-04-09",
        "evaluator_names": ["Alice", "Bob"]
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Bid Evaluation",
    "current_status": "Evaluation Completed",
    "timestamp": "2025-04-09T10:00:00+00:00"
}`

#### **Stage 8: Post-Qualification**

- **Objective**: Record finalized post-qualification documents and outcome
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/PostQualification/Verification_Report.pdf` - **Value**: `{
    "document_type": "Verification Report",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/PostQualification/Verification_Report.pdf",
    "timestamp": "2025-04-10T10:00:00+00:00",
    "stage": "Post-Qualification",
    "stage_metadata": {
        "submission_date": "2025-04-10",
        "outcome": "Verified"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Post-Qualification",
    "current_status": "Post-Qualification Verified",
    "timestamp": "2025-04-10T10:00:00+00:00"
}`

#### **Stage 9: BAC Resolution**

- **Objective**: Record finalized BAC resolution document(s)
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/BACResolution/Resolution.pdf` - **Value**: `{
    "document_type": "BAC Resolution",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/BACResolution/Resolution.pdf",
    "timestamp": "2025-04-11T10:00:00+00:00",
    "stage": "BAC Resolution",
    "stage_metadata": {
        "issuance_date": "2025-04-11",
        "signatory_details": "Jane Smith"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "BAC Resolution",
    "current_status": "Resolution Recorded",
    "timestamp": "2025-04-11T10:00:00+00:00"
}`

#### **Stage 10: Notice of Award**

- **Objective**: Record and publish finalized NOA document(s)
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/NoticeOfAward/NOA.pdf` - **Value**: `{
    "document_type": "Notice of Award",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/NoticeOfAward/NOA.pdf",
    "timestamp": "2025-04-12T10:00:00+00:00",
    "stage": "Notice of Award",
    "stage_metadata": {
        "issuance_date": "2025-04-12",
        "signatory_details": "John Doe"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Notice of Award",
    "current_status": "Awarded",
    "timestamp": "2025-04-12T10:00:00+00:00"
}`

#### **Stage 11: Performance Bond, Contract and PO**

- **Objective**: Record finalized performance bond, contract, and PO documents
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/PerformanceBond/Bond.pdf` - **Value**: `{
    "document_type": "Performance Bond",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/PerformanceBond/Bond.pdf",
    "timestamp": "2025-04-13T10:00:00+00:00",
    "stage": "Performance Bond",
    "stage_metadata": {
        "submission_date": "2025-04-13",
        "bond_amount": "500000"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Performance Bond",
    "current_status": "Bond Recorded",
    "timestamp": "2025-04-13T10:00:00+00:00"
}`

#### **Stage 12: Notice to Proceed**

- **Objective**: Record and publish finalized NTP document(s)
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/NoticeToProceed/NTP.pdf` - **Value**: `{
    "document_type": "Notice to Proceed",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/NoticeToProceed/NTP.pdf",
    "timestamp": "2025-04-14T10:00:00+00:00",
    "stage": "Notice to Proceed",
    "stage_metadata": {
        "issuance_date": "2025-04-14"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Notice to Proceed",
    "current_status": "NTP Recorded",
    "timestamp": "2025-04-14T10:00:00+00:00"
}`

#### **Stage 13: Monitoring**

- **Objective**: Record optional finalized compliance documents and enable monitoring
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/Monitoring/Compliance_Report.pdf` - **Value**: `{
    "document_type": "Compliance Report",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/Monitoring/Compliance_Report.pdf",
    "timestamp": "2025-04-15T10:00:00+00:00",
    "stage": "Monitoring",
    "stage_metadata": {
        "report_date": "2025-04-15"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Monitoring",
    "current_status": "Monitoring",
    "timestamp": "2025-04-15T10:00:00+00:00"
}`

#### **Stage 14: Completion**

- **Objective**: Record final completion documents and close procurement
- **System Actions**: - **`procurement.documents`**: - **Key**: `PR-2025-001-001-Office_Supplies_Procurement/Completion/Completion_Certificate.pdf` - **Value**: `{
    "document_type": "Completion Certificate",
    "file_key": "PR-2025-001-001-Office_Supplies_Procurement/Completion/Completion_Certificate.pdf",
    "timestamp": "2025-04-16T10:00:00+00:00",
    "stage": "Completion",
    "stage_metadata": {
        "completion_date": "2025-04-16",
        "final_amount": "1000000"
    },
    "spaces_url": "https://procuchain-docs.sgp1.digitaloceanspaces.com/...",
    "hash": "<SHA-256>",
    "file_size": 524288
}` - **`procurement.status`**: - **Key**: `PR-2025-001-001` - **Value**: `{
    "stage": "Completion",
    "current_status": "Completed",
    "timestamp": "2025-04-16T10:00:00+00:00"
}`

---

### **Configuration Details**

#### **MultiChain Setup**

- **Permissions**:
    - **BAC Secretariat**: `connect`, `send`, `receive`, `create`, `write`, `admin`.
    - **BAC Chairman & HOPE**: `connect`, `receive`, `read`.
- **Streams**:
    - Created with `create stream` (e.g., `create stream procurement.documents true`).
    - Data written via `publish` with JSON payloads for each document.
- **Consensus**: Round-robin mining ensures lightweight validation.

#### **DigitalOcean Spaces**

- **BAC Secretariat**: Batch uploads via Spaces API, encryption pre-upload.
- **BAC Chairman & HOPE**: Access via pre-signed URLs (24-hour expiration).
- **Security**: AES-256 encryption; hash/file_size verification on retrieval.

#### **Dashboard**

- **BAC Secretariat**:
    - Upload interface: Batch support, fields for general (auto-filled) and stage-specific metadata (custom per stage).
    - Status selection dropdown.
- **BAC Chairman & HOPE**:
    - Real-time view: Table with columns for general (e.g., `document_type`, `hash`) and stage-specific metadata (e.g., `bidder_name`).
    - Filters: `procurement_id`, stage, date range.
    - Visuals: Timeline of statuses/events.
- **Notifications**: Alerts for stage completions via email/dashboard.

---

### **Key Features in Action**

- **Multiple Documents**: Each document has a unique key (`-1`, `-2`, etc.) with its hash and metadata.
- **Metadata**:
    - **General**: Ensures consistency (e.g., `hash`, `spaces_url`) across all stages.
    - **Stage-Specific**: Captures unique details (e.g., `bid_value` in Stage 4, `bond_amount` in Stage 9).
- **Monitoring**: Dashboard separates metadata types for clarity.

This detailed workflow fully supports general and stage-specific metadata, ensuring all documents are tracked with their hashes across the 14 stages. Let me know if you need implementation code or further refinements!
