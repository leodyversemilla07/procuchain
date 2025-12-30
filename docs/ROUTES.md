# Route Documentation

This document lists the available routes in the ProcuChain application, categorized by access level and role.

## Public Routes

| Method | URI | Name | Controller/Action |
| :--- | :--- | :--- | :--- |
| GET | `/` | `home` | Inertia: `home` |
| GET | `/about` | `about` | Inertia: `about` |
| GET | `/workflow` | `workflow` | `WorkflowController` |
| GET | `/team` | `team` | Inertia: `team` |
| GET | `/contact` | `contact` | Inertia: `contact` |
| GET | `/privacy` | `privacy.policy` | Inertia: `privacy` |
| GET | `/terms` | `terms.service` | Inertia: `terms` |
| GET | `/invitation/{token}` | `invitation.show` | `AcceptInvitationController@show` |
| POST | `/invitation/{token}/accept` | `invitation.accept` | `AcceptInvitationController@accept` |

## Authenticated Routes (Shared)

All routes below require the `auth` middleware.

### General
| Method | URI | Name | Controller/Action |
| :--- | :--- | :--- | :--- |
| GET | `/notifications` | `notifications` | `NotificationController@page` |
| POST | `/notifications/{id}/mark-as-read` | `notifications.mark-as-read` | `NotificationController@markAsRead` |
| POST | `/notifications/mark-all-as-read` | `notifications.mark-all-as-read` | `NotificationController@markAllAsRead` |
| GET | `/files/{fileKey}` | `files.download` | `DocumentDownloadController@downloadFile` |
| GET | `/pdf-viewer/{fileKey}` | `pdf.viewer` | `PdfViewerController@showPdfViewer` |
| GET | `/procurements/{pr_number}/blockchain-status` | `procurements.blockchain-status` | `ProcurementListController@getBlockchainStatus` |

### Reports & Search
| Method | URI | Name | Controller/Action |
| :--- | :--- | :--- | :--- |
| GET | `/reports` | `reports.index` | `ReportController@index` |
| POST | `/reports/generate` | `reports.generate` | `ReportController@generate` |
| POST | `/reports/export` | `reports.export` | `ReportController@export` |
| POST | `/search` | `reports.search` | `ReportController@search` |

### Document Corrections & Verification
| Method | URI | Name | Controller/Action | Description |
| :--- | :--- | :--- | :--- | :--- |
| GET | `/procurements/{pr_number}/corrections/history` | `procurements.corrections.history` | `ProcurementCorrectionController@getProcurementCorrectionHistory` | |
| GET | `/procurements/{pr_number}/corrections/check` | `procurements.corrections.check` | `ProcurementCorrectionController@checkProcurementCorrection` | |
| POST | `/procurement/{pr_number}/verify` | `procurement.verify` | `DocumentVerificationController@verify` | |
| POST | `/procurement/{pr_number}/verify/integrity` | `procurement.verify.integrity` | `DocumentVerificationController@verifyIntegrity` | |
| GET | `/procurement/{pr_number}/verification` | `procurement.verification` | `DocumentVerificationController@showVerificationPage` | |
| POST | `/documents/{fileKey}/verify` | `documents.verify` | `DocumentVerificationController@verifyDocument` | |

## BAC Secretariat Routes

Requires `role:bac_secretariat`.

### Dashboard & Lists
| Method | URI | Name | Controller/Action |
| :--- | :--- | :--- | :--- |
| GET | `/bac-secretariat/dashboard` | `bac-secretariat.dashboard` | `BacSecretariatController@dashboard` |
| GET | `/bac-secretariat/procurements-list` | `bac-secretariat.procurements.index` | `ProcurementListController@index` |
| GET | `/bac-secretariat/procurements-list/{pr_number}` | `bac-secretariat.procurements.show` | `ProcurementListController@show` |

### Procurement Management
Most routes follow the pattern `/bac-secretariat/{phase}/{pr_number}/{stage}`.

**Phases:**
- `procurement-initiation`
- `pre-procurement`
- `procurement` (Bidding)
- `post-procurement`

**Actions:**
- `upload-document` (POST)
- `complete` (POST) - Mark stage as complete
- `skip` (POST) - Skip stage
- `repeat` (POST) - Repeat stage (Bidding only)
- `validate-upload` (POST)
- `document-guide` (GET)

**Specific Actions:**
- `initiate-procurement` (POST)
- `publish-pre-procurement-conference-decision` (POST)
- `publish-pre-bid-conference-decision` (POST)
- `publish-supplemental-bid-bulletin-decision` (POST)

### Corrections
| Method | URI | Name | Controller/Action |
| :--- | :--- | :--- | :--- |
| GET | `/procurements/{pr_number}/corrections` | `procurements.corrections.show` | `ProcurementCorrectionController@showProcurementCorrectionsPage` |
| POST | `/procurements/{pr_number}/corrections` | `procurements.corrections.submit` | `ProcurementCorrectionController@correctProcurement` |

## BAC Chairman Routes

Requires `role:bac_chairman`.

| Method | URI | Name | Controller/Action |
| :--- | :--- | :--- | :--- |
| GET | `/bac-chairman/dashboard` | `bac-chairman.dashboard` | `BacChairmanController@index` |
| GET | `/bac-chairman/procurements-list` | `bac-chairman.procurements.index` | `ProcurementListController@index` |
| GET | `/bac-chairman/procurements-list/{pr_number}` | `bac-chairman.procurements.show` | `ProcurementListController@show` |

## HOPE Routes

Requires `role:hope`.

| Method | URI | Name | Controller/Action |
| :--- | :--- | :--- | :--- |
| GET | `/hope/dashboard` | `hope.dashboard` | `HopeController@index` |
| GET | `/hope/procurements-list` | `hope.procurements.index` | `ProcurementListController@index` |
| GET | `/hope/procurements-list/{pr_number}` | `hope.procurements.show` | `ProcurementListController@show` |

## Admin Routes

Requires `role:admin`.

### Dashboard & Lists
| Method | URI | Name | Controller/Action |
| :--- | :--- | :--- | :--- |
| GET | `/admin/dashboard` | `admin.dashboard` | `AdminController@index` |
| GET | `/admin/procurements-list` | `admin.procurements.index` | `ProcurementListController@index` |
| GET | `/admin/procurements-list/{pr_number}` | `admin.procurements.show` | `ProcurementListController@show` |

### User Management
Prefix: `/admin/users`
- CRLUD operations for users.
- `reset-password` (POST)
- `bulk-delete` (DELETE)

### User Invitations
Prefix: `/admin/invitations`
- `index`, `store`, `resend`, `revoke`

### Login Logs
Prefix: `/admin/login-logs`
- Monitoring login activity, suspicious activity.
- `block-ip`, `unblock-ip`.

### Accounts Security
Prefix: `/admin/accounts`
- Manage locked accounts (`lock`, `unlock`, `reset-attempts`).
- Bulk operations.

### Blockchain Explorer
Prefix: `/admin/blockchain-explorer`
- View blocks, transactions, streams, addresses.
- `reset-circuit-breaker` for node health.

### Configuration
- `/admin/workflow-config`: Configure workflow stages per mode.
- `/admin/stage-documents`: Configure required documents per stage.
