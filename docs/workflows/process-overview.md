## Phase 1: Purchase Request (PR) Initiation

### Upload & Metadata Input:

The user uploads scanned copies of the signed Purchase Request documents and manually enters the required metadata (e.g., document type, submission date/time, unit/department, signatory details, unique document identifier, and stage identifier) with the supporting documents such as Certificate of Availability of Funds, Annual Investment Plan (AIP), Annual Procurement Plan (APP), Relevant Ordinances/Resolutions, Detailed Engineering Designs, etc.

### System Actions:

-   **Blockchain Logging:** Generates a cryptographic hash for each uploaded document along with its entered metadata and records it on the blockchain.
-   **Status Update:** Sets the PR stage to "PR Submitted" and sends notifications to relevant stakeholders.

---

## Phase 2: Pre-Procurement Conference

(The user will be prompted with yes or no for the question of whether a pre-procurement conference is required. If yes, proceed with the pre-conference; if no, proceed to bid invitation without doing a pre-conference.)

### Upload & Metadata Input:

The user uploads scanned copies of conference documents (e.g., meeting minutes, attendance sheets) and manually provides metadata (e.g., meeting date, participant details, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates and records a cryptographic hash (with the user-provided metadata) on the blockchain.
-   **Status Update:** Updates the stage to "Pre-Procurement Completed."

---

## Phase 3: Bid Invitation Publication

### Upload & Metadata Input:

The user uploads the scanned copy of the signed bid invitation and enters metadata such as document type, submission date, signatory details, and stage identifier.

### System Actions:

-   **Online Publication & Scheduling:** Publishes the bid invitation on required platforms (e.g., PhilGEPS, agency website) and, for procurements above PHP 1M, schedules any pre-bid conferences and supplemental bid bulletins.
-   **Blockchain Logging:** Records a cryptographic hash on the blockchain.
-   **Status Update:** Sets the stage to "Bid Invitation Published."

---

## Phase 4: Bid Submission and Bid Opening

### Sealed Bid Submission (Record Only):

The system logs each bid envelope received by assigning a unique identifier (e.g., barcode or serial number) along with externally provided information (such as bidder name and receipt date/time).

### Bid Opening – Upload & Metadata Input:

When the bid envelopes are opened, the user uploads scanned copies of the bid documents and manually inputs metadata (e.g., document type, opening date/time, bid values, attendee list, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates new cryptographic hashes for the bid documents and records the bid opening event on the blockchain.
-   **Status Update:** Updates the stage to "Bids Opened."

---

## Phase 5: Bid Evaluation and Abstract Preparation

### Upload & Metadata Input:

The user uploads scanned copies of the evaluation reports and abstract of bids, manually entering metadata (e.g., evaluation date, evaluator names, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates and records a cryptographic hash on the blockchain.
-   **Status Update:** Sets the stage to "Bids Evaluated."

---

## Phase 6: Post-Qualification Process

### Upload & Metadata Input:

The user uploads scanned copies of additional supporting documents (e.g., income/business tax returns, audited financial statements) and enters metadata (e.g., submission date, document type, stage identifier).

### System Actions:

-   **Blockchain Logging:** Records a cryptographic hash on the blockchain.
-   **Status Update:** Updates the stage to "Post-Qualification Verified" (or "Post-Qualification Rejected" if discrepancies are noted).

---

## Phase 7: BAC Resolution and HOPE Approval

### Upload & Metadata Input:

The user uploads the scanned copy of the signed BAC resolution (and any HOPE approval document) and manually inputs metadata (e.g., resolution issuance date, signatory details, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates and records a cryptographic hash on the blockchain.
-   **Status Update:** Sets the stage to "Resolution Endorsed & HOPE Approved."

---

## Phase 8: Notice of Award (NOA) Issuance

### Upload & Metadata Input:

The user uploads the scanned copy of the signed Notice of Award and manually enters metadata (e.g., issuance date, signatory details, stage identifier).

### System Actions:

-   **Online Publication:** Publishes the NOA on required platforms (e.g., PhilGEPS, agency website).
-   **Blockchain Logging:** Records a cryptographic hash on the blockchain.
-   **Status Update:** Updates the stage to "Awarded" and sends notifications to the winning bidder.

---

## Phase 9: Performance Bond Submission

### Upload & Metadata Input:

The user uploads the scanned copy of the performance bond and inputs metadata (e.g., bond amount, submission date, stage identifier).

### System Actions:

-   **Blockchain Logging:** Generates and logs a cryptographic hash on the blockchain.
-   **Status Update:** Updates the stage to "Performance Bond Verified."

---

## Phase 10: Contract Signing and Purchase Order (PO) Issuance

### Upload & Metadata Input:

The user uploads scanned copies of the signed contract and Purchase Order, manually entering metadata (e.g., document type, timestamps, stage identifier).

### System Actions:

-   **Blockchain Logging:** Records a cryptographic hash on the blockchain.
-   **Status Update:** Sets the stage to "Contract Executed & PO Issued."

---

## Phase 11: Issuance of Notice to Proceed (NTP)

### Upload & Metadata Input:

The user uploads the scanned copy of the signed Notice to Proceed and manually enters metadata (e.g., issuance date, stage identifier).

### System Actions:

-   **Online Publication:** Publishes the NTP on required platforms (e.g., PhilGEPS, agency website).
-   **Blockchain Logging:** Generates and records a cryptographic hash on the blockchain.
-   **Status Update:** Once confirmed by the supplier, updates the stage to "NTP Issued & Work Commenced."

---

## Phase 12: Ongoing Monitoring, Audit, and Compliance

### Dashboard & Real-Time Tracking:

The system displays all procurement stages and current statuses (e.g., "PR Submitted," "Bid Invitation Published," "Sealed Bids Received," "Bids Opened," etc.) on a centralized dashboard.

### Automated Notifications:

Sends alerts and reminders based on stage-specific deadlines and requirements.

### Immutable Audit Trail & Reporting:

-   Every key action—upload, metadata entry, and cryptographic hash generation—is recorded on the blockchain to ensure a tamper-evident audit trail.
-   Detailed reports (capturing timelines, status updates, evaluator remarks, and any delay justifications) are generated to support compliance audits and continuous process improvement.

---

## Final Summary

1. **Upload & Manual Metadata Entry:**

    - Users upload scanned copies of signed documents and manually input all required metadata.

2. **System Processing:**

    - The system logs each document by generating cryptographic hashes (recorded on the blockchain), updates process stages and sends notifications.

3. **Digital Publication & Monitoring:**
    - Documents such as the bid invitation, NOA, and NTP are published on required platforms.
    - The system maintains a centralized dashboard for real-time tracking, ensuring secure, transparent, and auditable processing.
