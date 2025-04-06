## Stage 1: Procurement Initiation

### Upload & Metadata Input:

The user uploads scanned copies of the signed Purchase Request documents and manually enters the required metadata (e.g., document type, submission date/time, unit/department, signatory details, unique document identifier, and stage identifier) with the supporting documents such as Certificate of Availability of Funds, Annual Investment Plan (AIP), Annual Procurement Plan (APP), Relevant Ordinances/Resolutions, Detailed Engineering Designs, etc.

### System Actions:

-   **Blockchain Logging:** Generates a cryptographic hash for each uploaded document along with its entered metadata and records it on the blockchain.
-   **Status Update:** Sets the PR stage to "PR Submitted" and sends notifications to relevant stakeholders.

---

## Stage 2: Pre-Procurement Conference

(The user will be prompted with yes or no for the question of whether a pre-procurement conference is required. If yes, proceed with the pre-conference; if no, proceed to bid invitation without doing a pre-conference.)

### Upload & Metadata Input:

The user uploads scanned copies of conference documents (e.g., meeting minutes, attendance sheets) and manually provides metadata (e.g., meeting date, participant details, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates and records a cryptographic hash (with the user-provided metadata) on the blockchain.
-   **Status Update:** Updates the stage to "Pre-Procurement Completed."

---

## Stage 3: Bidding Documents

### Upload & Metadata Input:

The user uploads the scanned copy of the signed bid invitation and enters metadata such as document type, submission date, signatory details, and stage identifier.

### System Actions:

-   **Online Publication & Scheduling:** Publishes the bid invitation on required platforms (e.g., PhilGEPS, agency website) and, for procurements above PHP 1M, schedules any pre-bid conferences and supplemental bid bulletins.
-   **Blockchain Logging:** Records a cryptographic hash on the blockchain.
-   **Status Update:** Sets the stage to "Bid Invitation Published."

---

## Stage 4: Pre-Bid Conference

### Sealed Bid Submission (Record Only):

The system logs each bid envelope received by assigning a unique identifier (e.g., barcode or serial number) along with externally provided information (such as bidder name and receipt date/time).

### Bid Opening – Upload & Metadata Input:

When the bid envelopes are opened, the user uploads scanned copies of the bid documents and manually inputs metadata (e.g., document type, opening date/time, bid values, attendee list, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates new cryptographic hashes for the bid documents and records the bid opening event on the blockchain.
-   **Status Update:** Updates the stage to "Bids Opened."

---

## Stage 5: Supplemental Bid Bulletin

### Upload & Metadata Input:

The user uploads scanned copies of the evaluation reports and abstract of bids, manually entering metadata (e.g., evaluation date, evaluator names, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates and records a cryptographic hash on the blockchain.
-   **Status Update:** Sets the stage to "Bids Evaluated."

---

## Stage 6: Bid Opening

### Upload & Metadata Input:

The user uploads scanned copies of additional supporting documents (e.g., income/business tax returns, audited financial statements) and enters metadata (e.g., submission date, document type, stage identifier).

### System Actions:

-   **Blockchain Logging:** Records a cryptographic hash on the blockchain.
-   **Status Update:** Updates the stage to "Post-Qualification Verified" (or "Post-Qualification Rejected" if discrepancies are noted).

---

## Stage 7: Bid Evaluation

### Upload & Metadata Input:

The user uploads the scanned copy of the signed BAC resolution (and any HOPE approval document) and manually inputs metadata (e.g., resolution issuance date, signatory details, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates and records a cryptographic hash on the blockchain.
-   **Status Update:** Sets the stage to "Resolution Endorsed & HOPE Approved."

---

## Stage 8: Post-Qualification Process

### Upload & Metadata Input:

The user uploads the scanned copy of the signed Notice of Award and manually enters metadata (e.g., issuance date, signatory details, stage identifier).

### System Actions:

-   **Online Publication:** Publishes the NOA on required platforms (e.g., PhilGEPS, agency website).
-   **Blockchain Logging:** Records a cryptographic hash on the blockchain.
-   **Status Update:** Updates the stage to "Awarded" and sends notifications to the winning bidder.

---

## Stage 9: BAC Resolution

### Upload & Metadata Input:

The user uploads the scanned copy of the performance bond and inputs metadata (e.g., bond amount, submission date, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates and logs a cryptographic hash on the blockchain.
-   **Status Update:** Updates the stage to "Performance Bond Verified."

---

## Stage 10: Notice of Award

### Upload & Metadata Input:

The user uploads scanned copies of the signed contract and Purchase Order, manually entering metadata (e.g., document type, timestamps, stage identifier).

### System Actions:

-   **Blockchain Logging:** Records a cryptographic hash on the blockchain.
-   **Status Update:** Sets the stage to "Contract Executed & PO Issued."

---

## Stage 11: Performance Bond, Contract and PO

### Upload & Metadata Input:

The user uploads the scanned copy of the signed Notice to Proceed and manually enters metadata (e.g., issuance date, stage identifier).

### System Actions:

-   **Online Publication:** Publishes the NTP on required platforms (e.g., PhilGEPS, agency website).
-   **Blockchain Logging:** Generates and records a cryptographic hash on the blockchain.
-   **Status Update:** Once confirmed by the supplier, updates the stage to "NTP Issued & Work Commenced."

---

## Stage 12: Notice to Proceed

### Dashboard & Real-Time Tracking:

The system displays all procurement stages and current statuses (e.g., "PR Submitted," "Bid Invitation Published," "Sealed Bids Received," "Bids Opened," etc.) on a centralized dashboard.

### Automated Notifications:

Sends alerts and reminders based on stage-specific deadlines and requirements.

### Immutable Audit Trail & Reporting:

-   Every key action—upload, metadata entry, and cryptographic hash generation—is recorded on the blockchain to ensure a tamper-evident audit trail.
-   Detailed reports (capturing timelines, status updates, evaluator remarks, and any delay justifications) are generated to support compliance audits and continuous process improvement.

---

## Stage 13: Monitoring

### Upload & Metadata Input:
The user uploads the final completion documents including completion certificates and enters metadata (e.g., completion date, final amount, stage identifier).

### System Actions:
- **Blockchain Logging:** Records final cryptographic hashes on the blockchain.
- **Status Update:** Sets the stage to "Completed" and closes the procurement process.

---

## Final Summary

1. **Upload & Manual Metadata Entry:**

    - Users upload scanned copies of signed documents and manually input all required metadata.

2. **System Processing:**

    - The system logs each document by generating cryptographic hashes (recorded on the blockchain), updates process stages and sends notifications.

3. **Digital Publication & Monitoring:**
    - Documents such as the bid invitation, NOA, and NTP are published on required platforms.
    - The system maintains a centralized dashboard for real-time tracking, ensuring secure, transparent, and auditable processing.
