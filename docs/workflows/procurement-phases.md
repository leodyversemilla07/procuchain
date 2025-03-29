### **System Overview**

-   **Users & Roles**:
    -   **BAC Secretariat**: Uploads finalized documents, logs metadata/states/events via MultiChain APIs.
    -   **BAC Chairman & HOPE**: Monitor via a read-only dashboard querying MultiChain streams.
-   **Streams**:
    -   **`procurement.documents`**: Stores general and phase-specific metadata for each document.
    -   **`procurement.state`**: Tracks procurement states with history.
    -   **`procurement.events`**: Logs actions with document counts.
-   **Storage**: Finalized documents in DigitalOcean Spaces; hashes/metadata on MultiChain.

---

### **Detailed Workflow**

#### **Phase 1: Purchase Request (PR) Initiation**

-   **Objective**: Record finalized PR and supporting documents with general and phase-specific metadata.
-   **BAC Secretariat Actions**:
    -   Uploads three finalized documents (PR, Certificate of Availability of Funds, Annual Investment Plan) to Spaces via dashboard (batch upload).
    -   Enters metadata:
        -   **General**: Document type ("PR", "Certificate", "AIP")—auto-filled: timestamp, user.
        -   **Phase-Specific**: `submission_date`, `unit_department`, `signatory_details`.
-   **System Actions**:
    -   Generates SHA-256 hashes for each document.
    -   Encrypts (AES-256) and uploads to Spaces, generating pre-signed URLs.
    -   Logs to MultiChain via APIs (`publish` command):
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-PRInitiation-BACSecretariat-20250221123456-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "PR Initiation", "timestamp": "2025-02-21T12:34:56Z", "document_type": "PR", "hash": "<SHA-256-1>", "spaces_url": "<URL1>", "user": "BACSecretariat", "file_size": 524288, "phase_metadata": { "submission_date": "2025-02-20", "unit_department": "Finance", "signatory_details": "John Doe" } }`
            -   **Key**: `PROC-001-PRInitiation-BACSecretariat-20250221123456-2`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "PR Initiation", "timestamp": "2025-02-21T12:34:56Z", "document_type": "Certificate", "hash": "<SHA-256-2>", "spaces_url": "<URL2>", "user": "BACSecretariat", "file_size": 262144, "phase_metadata": { "submission_date": "2025-02-20", "unit_department": "Finance", "signatory_details": "John Doe" } }`
            -   **Key**: `PROC-001-PRInitiation-BACSecretariat-20250221123456-3`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "PR Initiation", "timestamp": "2025-02-21T12:34:56Z", "document_type": "AIP", "hash": "<SHA-256-3>", "spaces_url": "<URL3>", "user": "BACSecretariat", "file_size": 262144, "phase_metadata": { "submission_date": "2025-02-20", "unit_department": "Finance", "signatory_details": "John Doe" } }`
        -   **`procurement.state`** (atomic with event):
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "PR Submitted", "phase_identifier": "PR Initiation", "timestamp": "2025-02-21T12:34:56Z", "user": "BACSecretariat", "history": [] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221123456-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:34:56Z", "user": "BACSecretariat", "details": "Uploaded 3 finalized PR documents", "category": "document", "severity": "info", "document_count": 3 }`
    -   Consensus validates entries; immutability ensures permanence.
    -   Notifies BAC Chairman and HOPE via dashboard/email.
-   **Monitoring**: BAC Chairman/HOPE query `liststreamitems` for `PROC-001-PRInitiation-*`, view documents via URLs, see metadata in dashboard.

#### **Phase 2: Pre-Procurement Conference Decision**

-   **Objective**: Record optional finalized conference documents or decision to skip.
-   **BAC Secretariat Actions**:
    -   Answers dashboard prompt: "Pre-procurement conference held?" (Yes/No).
    -   **If Yes**: Uploads two finalized documents (minutes, attendance), enters `meeting_date`, `participants`.
-   **System Actions**:
    -   **If Yes**:
        -   Hashes and uploads each document:
            -   **`procurement.documents`**:
                -   **Key**: `PROC-001-PreProcurement-BACSecretariat-20250221123500-1`
                -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Pre-Procurement", "timestamp": "2025-02-21T12:35:00Z", "document_type": "Minutes", "hash": "<SHA-256-1>", "spaces_url": "<URL1>", "user": "BACSecretariat", "file_size": 524288, "phase_metadata": { "meeting_date": "2025-02-21", "participants": "Jane Smith, John Doe" } }`
                -   **Key**: `PROC-001-PreProcurement-BACSecretariat-20250221123500-2`
                -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Pre-Procurement", "timestamp": "2025-02-21T12:35:00Z", "document_type": "Attendance", "hash": "<SHA-256-2>", "spaces_url": "<URL2>", "user": "BACSecretariat", "file_size": 262144, "phase_metadata": { "meeting_date": "2025-02-21", "participants": "Jane Smith, John Doe" } }`
            -   **`procurement.state`**:
                -   **Key**: `PROC-001`
                -   **Value**: `{ "current_state": "Pre-Procurement Completed", "phase_identifier": "Pre-Procurement", "timestamp": "2025-02-21T12:35:00Z", "user": "BACSecretariat", "history": [{"state": "PR Submitted", "timestamp": "2025-02-21T12:34:56Z", "user": "BACSecretariat"}] }`
            -   **`procurement.events`**:
                -   **Key**: `PROC-001-BACSecretariat-20250221123500-upload`
                -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:35:00Z", "user": "BACSecretariat", "details": "Uploaded 2 finalized conference documents", "category": "document", "severity": "info", "document_count": 2 }`
    -   **If No**:
        -   Logs decision:
            -   **`procurement.events`**:
                -   **Key**: `PROC-001-BACSecretariat-20250221123500-decision`
                -   **Value**: `{ "event_type": "decision", "timestamp": "2025-02-21T12:35:00Z", "user": "BACSecretariat", "details": "Pre-procurement conference skipped", "category": "workflow", "severity": "info", "document_count": 0 }`
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring**: BAC Chairman/HOPE see decision or documents with metadata.

#### **Phase 3: Bid Invitation Publication**

-   **Objective**: Record and publish finalized bid invitation document(s).
-   **BAC Secretariat Actions**:
    -   Uploads one finalized bid invitation to Spaces, enters `submission_date`, `signatory_details`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-BidInvitation-BACSecretariat-20250221123510-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Bid Invitation", "timestamp": "2025-02-21T12:35:10Z", "document_type": "Bid Invitation", "hash": "<SHA-256>", "spaces_url": "<URL>", "user": "BACSecretariat", "file_size": 786432, "phase_metadata": { "submission_date": "2025-02-21", "signatory_details": "Jane Smith" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Bid Invitation Published", "phase_identifier": "Bid Invitation", "timestamp": "2025-02-21T12:35:10Z", "user": "BACSecretariat", "history": [{"state": "Pre-Procurement Completed", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221123510-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:35:10Z", "user": "BACSecretariat", "details": "Uploaded 1 finalized bid invitation", "category": "document", "severity": "info", "document_count": 1 }`
            -   **Key**: `PROC-001-BACSecretariat-20250221123512-publication`
            -   **Value**: `{ "event_type": "publication", "timestamp": "2025-02-21T12:35:12Z", "user": "BACSecretariat", "details": "Published bid invitation to PhilGEPS", "category": "workflow", "severity": "info", "document_count": 1 }`
    -   Publishes to PhilGEPS/agency website via API.
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring**: BAC Chairman/HOPE view bid invitation and publication status.

#### **Phase 4: Bid Submission and Opening**

-   **Objective**: Record multiple finalized bid documents post-opening.
-   **BAC Secretariat Actions**:
    -   Uploads five finalized bid documents (one per bidder) to Spaces, enters `bidder_name`, `bid_value`, `opening_date_time` for each.
-   **System Actions**:
    -   Hashes and uploads each:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-BidOpening-BACSecretariat-20250221123600-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Bid Opening", "timestamp": "2025-02-21T12:36:00Z", "document_type": "Bid Document", "hash": "<SHA-256-1>", "spaces_url": "<URL1>", "user": "BACSecretariat", "file_size": 419430, "phase_metadata": { "bidder_name": "Company A", "bid_value": "1000000", "opening_date_time": "2025-02-21T10:00:00Z" } }`
            -   **Key**: `PROC-001-BidOpening-BACSecretariat-20250221123600-2`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Bid Opening", "timestamp": "2025-02-21T12:36:00Z", "document_type": "Bid Document", "hash": "<SHA-256-2>", "spaces_url": "<URL2>", "user": "BACSecretariat", "file_size": 419430, "phase_metadata": { "bidder_name": "Company B", "bid_value": "950000", "opening_date_time": "2025-02-21T10:00:00Z" } }`
            -   **Key**: `PROC-001-BidOpening-BACSecretariat-20250221123600-3` (and so on up to `-5`)
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Bids Opened", "phase_identifier": "Bid Opening", "timestamp": "2025-02-21T12:36:00Z", "user": "BACSecretariat", "history": [{"state": "Bid Invitation Published", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221123600-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:36:00Z", "user": "BACSecretariat", "details": "Uploaded 5 finalized opened bid documents", "category": "document", "severity": "info", "document_count": 5 }`
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring**: BAC Chairman/HOPE see all five bids with bidder-specific metadata.

#### **Phase 5: Bid Evaluation**

-   **Objective**: Record finalized evaluation reports.
-   **BAC Secretariat Actions**:
    -   Uploads two finalized reports (summary, abstract) to Spaces, enters `evaluation_date`, `evaluator_names`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-BidEvaluation-BACSecretariat-20250221123700-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Bid Evaluation", "timestamp": "2025-02-21T12:37:00Z", "document_type": "Evaluation Summary", "hash": "<SHA-256-1>", "spaces_url": "<URL1>", "user": "BACSecretariat", "file_size": 655360, "phase_metadata": { "evaluation_date": "2025-02-21", "evaluator_names": "Alice, Bob" } }`
            -   **Key**: `PROC-001-BidEvaluation-BACSecretariat-20250221123700-2`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Bid Evaluation", "timestamp": "2025-02-21T12:37:00Z", "document_type": "Abstract", "hash": "<SHA-256-2>", "spaces_url": "<URL2>", "user": "BACSecretariat", "file_size": 655360, "phase_metadata": { "evaluation_date": "2025-02-21", "evaluator_names": "Alice, Bob" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Bids Evaluated", "phase_identifier": "Bid Evaluation", "timestamp": "2025-02-21T12:37:00Z", "user": "BACSecretariat", "history": [{"state": "Bids Opened", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221123700-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:37:00Z", "user": "BACSecretariat", "details": "Uploaded 2 finalized evaluation reports", "category": "document", "severity": "info", "document_count": 2 }`
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring**: BAC Chairman/HOPE review evaluation details.

#### **Phase 6: Post-Qualification**

-   **Objective**: Record finalized post-qualification documents and outcome.
-   **BAC Secretariat Actions**:
    -   Uploads three finalized documents (tax return, financial statement, verification report) to Spaces, enters `submission_date`, `outcome`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-PostQualification-BACSecretariat-20250221123800-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Post-Qualification", "timestamp": "2025-02-21T12:38:00Z", "document_type": "Tax Return", "hash": "<SHA-256-1>", "spaces_url": "<URL1>", "user": "BACSecretariat", "file_size": 524288, "phase_metadata": { "submission_date": "2025-02-21", "outcome": "Verified" } }`
            -   **Key**: `PROC-001-PostQualification-BACSecretariat-20250221123800-2`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Post-Qualification", "timestamp": "2025-02-21T12:38:00Z", "document_type": "Financial Statement", "hash": "<SHA-256-2>", "spaces_url": "<URL2>", "user": "BACSecretariat", "file_size": 524288, "phase_metadata": { "submission_date": "2025-02-21", "outcome": "Verified" } }`
            -   **Key**: `PROC-001-PostQualification-BACSecretariat-20250221123800-3`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Post-Qualification", "timestamp": "2025-02-21T12:38:00Z", "document_type": "Verification Report", "hash": "<SHA-256-3>", "spaces_url": "<URL3>", "user": "BACSecretariat", "file_size": 524288, "phase_metadata": { "submission_date": "2025-02-21", "outcome": "Verified" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Post-Qualification Verified", "phase_identifier": "Post-Qualification", "timestamp": "2025-02-21T12:38:00Z", "user": "BACSecretariat", "history": [{"state": "Bids Evaluated", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221123800-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:38:00Z", "user": "BACSecretariat", "details": "Uploaded 3 finalized post-qualification documents (Verified)", "category": "document", "severity": "info", "document_count": 3 }`
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring**: BAC Chairman/HOPE see verification outcome and documents.

#### **Phase 7: BAC Resolution**

-   **Objective**: Record finalized BAC resolution document(s).
-   **BAC Secretariat Actions**:
    -   Uploads one finalized BAC resolution to Spaces, enters `issuance_date`, `signatory_details`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-BACResolution-BACSecretariat-20250221123900-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "BAC Resolution", "timestamp": "2025-02-21T12:39:00Z", "document_type": "BAC Resolution", "hash": "<SHA-256>", "spaces_url": "<URL>", "user": "BACSecretariat", "file_size": 1048576, "phase_metadata": { "issuance_date": "2025-02-21", "signatory_details": "Jane Smith" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Resolution Recorded", "phase_identifier": "BAC Resolution", "timestamp": "2025-02-21T12:39:00Z", "user": "BACSecretariat", "history": [{"state": "Post-Qualification Verified", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221123900-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:39:00Z", "user": "BACSecretariat", "details": "Uploaded 1 finalized BAC resolution", "category": "document", "severity": "info", "document_count": 1 }`
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring**: BAC Chairman/HOPE review resolution details.

#### **Phase 8: Notice of Award (NOA)**

-   **Objective**: Record and publish finalized NOA document(s).
-   **BAC Secretariat Actions**:
    -   Uploads one finalized NOA to Spaces, enters `issuance_date`, `signatory_details`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-NOA-BACSecretariat-20250221124000-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "NOA", "timestamp": "2025-02-21T12:40:00Z", "document_type": "Notice of Award", "hash": "<SHA-256>", "spaces_url": "<URL>", "user": "BACSecretariat", "file_size": 524288, "phase_metadata": { "issuance_date": "2025-02-21", "signatory_details": "John Doe" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Awarded", "phase_identifier": "NOA", "timestamp": "2025-02-21T12:40:00Z", "user": "BACSecretariat", "history": [{"state": "Resolution Recorded", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221124000-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:40:00Z", "user": "BACSecretariat", "details": "Uploaded 1 finalized NOA", "category": "document", "severity": "info", "document_count": 1 }`
            -   **Key**: `PROC-001-BACSecretariat-20250221124002-publication`
            -   **Value**: `{ "event_type": "publication", "timestamp": "2025-02-21T12:40:02Z", "user": "BACSecretariat", "details": "Published NOA to PhilGEPS", "category": "workflow", "severity": "info", "document_count": 1 }`
    -   Publishes via API.
    -   Notifies BAC Chairman, HOPE, and bidder.
-   **Monitoring**: BAC Chairman/HOPE track award status.

#### **Phase 9: Performance Bond**

-   **Objective**: Record finalized performance bond document(s).
-   **BAC Secretariat Actions**:
    -   Uploads one finalized performance bond to Spaces, enters `submission_date`, `bond_amount`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-PerformanceBond-BACSecretariat-20250221124100-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Performance Bond", "timestamp": "2025-02-21T12:41:00Z", "document_type": "Performance Bond", "hash": "<SHA-256>", "spaces_url": "<URL>", "user": "BACSecretariat", "file_size": 786432, "phase_metadata": { "submission_date": "2025-02-21", "bond_amount": "500000" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Performance Bond Recorded", "phase_identifier": "Performance Bond", "timestamp": "2025-02-21T12:41:00Z", "user": "BACSecretariat", "history": [{"state": "Awarded", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221124100-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:41:00Z", "user": "BACSecretariat", "details": "Uploaded 1 finalized performance bond", "category": "document", "severity": "info", "document_count": 1 }`
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring**: BAC Chairman/HOPE verify bond details.

#### **Phase 10: Contract and Purchase Order (PO)**

-   **Objective**: Record finalized contract and PO documents.
-   **BAC Secretariat Actions**:
    -   Uploads two finalized documents (contract, PO) to Spaces, enters `signing_date`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-ContractPO-BACSecretariat-20250221124200-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Contract & PO", "timestamp": "2025-02-21T12:42:00Z", "document_type": "Contract", "hash": "<SHA-256-1>", "spaces_url": "<URL1>", "user": "BACSecretariat", "file_size": 917504, "phase_metadata": { "signing_date": "2025-02-21" } }`
            -   **Key**: `PROC-001-ContractPO-BACSecretariat-20250221124200-2`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Contract & PO", "timestamp": "2025-02-21T12:42:00Z", "document_type": "PO", "hash": "<SHA-256-2>", "spaces_url": "<URL2>", "user": "BACSecretariat", "file_size": 917504, "phase_metadata": { "signing_date": "2025-02-21" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Contract & PO Recorded", "phase_identifier": "Contract & PO", "timestamp": "2025-02-21T12:42:00Z", "user": "BACSecretariat", "history": [{"state": "Performance Bond Recorded", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221124200-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:42:00Z", "user": "BACSecretariat", "details": "Uploaded 2 finalized contract and PO documents", "category": "document", "severity": "info", "document_count": 2 }`
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring**: BAC Chairman/HOPE review contract details.

#### **Phase 11: Notice to Proceed (NTP)**

-   **Objective**: Record and publish finalized NTP document(s).
-   **BAC Secretariat Actions**:
    -   Uploads one finalized NTP to Spaces, enters `issuance_date`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-NTP-BACSecretariat-20250221124300-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "NTP", "timestamp": "2025-02-21T12:43:00Z", "document_type": "Notice to Proceed", "hash": "<SHA-256>", "spaces_url": "<URL>", "user": "BACSecretariat", "file_size": 524288, "phase_metadata": { "issuance_date": "2025-02-21" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "NTP Recorded", "phase_identifier": "NTP", "timestamp": "2025-02-21T12:43:00Z", "user": "BACSecretariat", "history": [{"state": "Contract & PO Recorded", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221124300-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:43:00Z", "user": "BACSecretariat", "details": "Uploaded 1 finalized NTP", "category": "document", "severity": "info", "document_count": 1 }`
            -   **Key**: `PROC-001-BACSecretariat-20250221124302-publication`
            -   **Value**: `{ "event_type": "publication", "timestamp": "2025-02-21T12:43:02Z", "user": "BACSecretariat", "details": "Published NTP to PhilGEPS", "category": "workflow", "severity": "info", "document_count": 1 }`
    -   Publishes via API.
    -   Notifies BAC Chairman, HOPE, and supplier.
-   **Monitoring**: BAC Chairman/HOPE track NTP issuance.

#### **Phase 12: Monitoring and Compliance**

-   **Objective**: Record optional finalized compliance documents and enable monitoring.
-   **BAC Secretariat Actions**:
    -   Uploads one finalized compliance report to Spaces, enters `report_date`.
-   **System Actions**:
    -   Hashes and uploads:
        -   **`procurement.documents`**:
            -   **Key**: `PROC-001-Monitoring-BACSecretariat-20250221124400-1`
            -   **Value**: `{ "procurement_id": "PROC-001", "phase_identifier": "Monitoring", "timestamp": "2025-02-21T12:44:00Z", "document_type": "Compliance Report", "hash": "<SHA-256>", "spaces_url": "<URL>", "user": "BACSecretariat", "file_size": 1048576, "phase_metadata": { "report_date": "2025-02-21" } }`
        -   **`procurement.state`**:
            -   **Key**: `PROC-001`
            -   **Value**: `{ "current_state": "Monitoring", "phase_identifier": "Monitoring", "timestamp": "2025-02-21T12:44:00Z", "user": "BACSecretariat", "history": [{"state": "NTP Recorded", ...}] }`
        -   **`procurement.events`**:
            -   **Key**: `PROC-001-BACSecretariat-20250221124400-upload`
            -   **Value**: `{ "event_type": "document_upload", "timestamp": "2025-02-21T12:44:00Z", "user": "BACSecretariat", "details": "Uploaded 1 finalized compliance report", "category": "document", "severity": "info", "document_count": 1 }`
    -   Notifies BAC Chairman and HOPE.
-   **Monitoring (All Users)**:
    -   Dashboard queries streams (`liststreamitems`):
        -   Displays states, document links, event timelines.
        -   Filters by `procurement_id`, phase, date.
        -   Shows general (e.g., hash) and phase-specific metadata (e.g., `bid_value`) per document.
        -   Exports reports (e.g., phase durations).

---

### **Configuration Details**

#### **MultiChain Setup**

-   **Permissions**:
    -   **BAC Secretariat**: `connect`, `send`, `receive`, `create`, `write`, `admin`.
    -   **BAC Chairman & HOPE**: `connect`, `receive`, `read`.
-   **Streams**:
    -   Created with `create stream` (e.g., `create stream procurement.documents true`).
    -   Data written via `publish` with JSON payloads for each document.
-   **Consensus**: Round-robin mining ensures lightweight validation.

#### **DigitalOcean Spaces**

-   **BAC Secretariat**: Batch uploads via Spaces API, encryption pre-upload.
-   **BAC Chairman & HOPE**: Access via pre-signed URLs (24-hour expiration).
-   **Security**: AES-256 encryption; hash/file_size verification on retrieval.

#### **Dashboard**

-   **BAC Secretariat**:
    -   Upload interface: Batch support, fields for general (auto-filled) and phase-specific metadata (custom per phase).
    -   State selection dropdown.
-   **BAC Chairman & HOPE**:
    -   Real-time view: Table with columns for general (e.g., `document_type`, `hash`) and phase-specific metadata (e.g., `bidder_name`).
    -   Filters: `procurement_id`, phase, date range.
    -   Visuals: Timeline of states/events.
-   **Notifications**: Alerts for phase completions via email/dashboard.

---

### **Key Features in Action**

-   **Multiple Documents**: Each document has a unique key (`-1`, `-2`, etc.) with its hash and metadata.
-   **Metadata**:
    -   **General**: Ensures consistency (e.g., `hash`, `spaces_url`) across all phases.
    -   **Phase-Specific**: Captures unique details (e.g., `bid_value` in Phase 4, `bond_amount` in Phase 9).
-   **Monitoring**: Dashboard separates metadata types for clarity.

This detailed workflow fully supports general and phase-specific metadata, ensuring all documents are tracked with their hashes across the 12 phases. Let me know if you need implementation code or further refinements!
