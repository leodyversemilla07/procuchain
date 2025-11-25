# ProcuChain Type System - Quick Reference

## Type Files Structure

```
resources/js/types/
├── index.ts                  # Main barrel export file
├── index.d.ts               # TypeScript declarations barrel file
├── README.md                # Comprehensive documentation
│
├── Core Types
│   ├── auth.ts              # Authentication & User types
│   ├── navigation.ts        # Navigation & UI structure
│   └── enums.ts             # Application-wide enumerations
│
├── Domain Types
│   ├── procurement.ts       # Procurement business logic
│   ├── document.ts          # Document metadata & events
│   └── blockchain.ts        # Blockchain data structures
│
├── Feature Types
│   ├── admin.ts             # Admin panel & user management
│   ├── dashboard.ts         # Dashboard components
│   ├── notification.ts      # Notification system
│   ├── viewer.ts            # Document viewer & viewing stats
│   └── search.ts            # Search functionality
│
├── UI Types
│   └── components.ts        # Reusable component props
│
└── Utility Types
    ├── utils.ts             # General utilities & helpers
    └── constants.ts         # Application constants
```

## Quick Import Guide

### Import Everything from @/types

```typescript
import {
    // Enums
    Stage,
    Status,
    UserRole,
    DocumentType,

    // Core Types
    User,
    Auth,
    BreadcrumbItem,

    // Domain Types
    Procurement,
    Document,
    Event,

    // Feature Types
    DashboardStats,
    Notification,
    PdfDocument,

    // Component Types
    PaginationConfig,
    ErrorStateConfig,

    // Utility Types
    Appearance,
    DateRange,
} from '@/types';
```

## Type Count by File

| File              | Type Count          | Purpose                       |
| ----------------- | ------------------- | ----------------------------- |
| `enums.ts`        | 8 enums             | Application-wide enumerations |
| `auth.ts`         | 2 types             | User authentication           |
| `blockchain.ts`   | 4 types             | Blockchain structures         |
| `document.ts`     | 6 types             | Document metadata             |
| `procurement.ts`  | 6 types             | Procurement logic             |
| `navigation.ts`   | 4 types             | Navigation structure          |
| `admin.ts`        | 20+ types           | Admin features                |
| `dashboard.ts`    | 8 types             | Dashboard components          |
| `notification.ts` | 2 types             | Notifications                 |
| `viewer.ts`       | 7 types             | Document viewing              |
| `search.ts`       | 2 types             | Search functionality          |
| `components.ts`   | 10+ types           | Component props               |
| `utils.ts`        | 15+ types           | Utilities & helpers           |
| `constants.ts`    | 1 constant + 1 type | App constants                 |

**Total: 90+ types and enums**

## Most Commonly Used Types

### Core

- `User` - User model
- `Auth` - Authentication context
- `Stage` - Procurement stages enum
- `Status` - Procurement statuses enum
- `UserRole` - User roles enum

### Domain

- `Procurement` - Complete procurement data
- `Document` - Document with metadata
- `Event` - Procurement event
- `ProcurementListItem` - List view item

### Features

- `DashboardStats` - Dashboard statistics
- `RecentActivity` - Activity timeline item
- `Notification` - User notification
- `PdfDocument` - PDF document data

### UI

- `BreadcrumbItem` - Breadcrumb navigation
- `PaginationConfig` - Pagination props
- `ErrorStateConfig` - Error state props
- `BadgeVariant` - Badge color variants

### Utilities

- `Appearance` - Theme mode
- `DateRange` - Date range picker
- `MunicipalOffice` - Office selection

## Type Relationships

```
User ──────────────┐
                   ├──> Auth ──> SharedData ──> Page Props
                   │
LoginLog ──────────┘

Procurement ───────┬──> ProcurementListItem
                   ├──> Document ──> StageMetadata
                   ├──> Event
                   └──> TimelineItem

DashboardStats ────┬──> RecentActivity
                   ├──> RecentProcurement
                   └──> PriorityAction
```

## Enum Value Reference

### Stage (14 values)

- PROCUREMENT_INITIATION
- PRE_PROCUREMENT_CONFERENCE
- BIDDING_DOCUMENTS
- PRE_BID_CONFERENCE
- SUPPLEMENTAL_BID_BULLETIN
- BID_OPENING
- BID_EVALUATION
- POST_QUALIFICATION
- BAC_RESOLUTION
- NOTICE_OF_AWARD
- PERFORMANCE_BOND_CONTRACT_AND_PO
- NOTICE_TO_PROCEED
- MONITORING
- COMPLETED

### Status (18 values)

- PROCUREMENT_SUBMITTED
- PRE_PROCUREMENT_CONFERENCE_HELD
- BIDDING_DOCUMENTS_PUBLISHED
- BIDS_OPENED
- AWARDED
- COMPLETED
- ... and more

### UserRole (4 values)

- BAC_SECRETARIAT
- BAC_CHAIRMAN
- HOPE
- ADMIN

### DocumentType (20+ values)

- PROCUREMENT_INITIATION_DOCUMENT
- PRE_PROCUREMENT_MINUTES
- BIDDING_DOCUMENT
- BID_DOCUMENT
- EVALUATION_SUMMARY
- NOTICE_OF_AWARD
- CONTRACT
- PURCHASE_ORDER
- ... and more

## Usage Examples

### Component Props

```typescript
import type { PaginationConfig } from '@/types';

interface TableProps {
    data: unknown[];
    pagination: PaginationConfig;
}
```

### Page Props

```typescript
import type { SharedData, Procurement, User } from '@/types';

interface ShowProcurementProps extends SharedData {
    procurement: Procurement;
    canEdit: boolean;
}
```

### Enum Usage

```typescript
import { Stage, Status, UserRole } from '@/types';

if (procurement.stage === Stage.PROCUREMENT_INITIATION) {
    // Handle initiation stage
}

if (user.role === UserRole.BAC_SECRETARIAT) {
    // Show secretariat features
}
```

### Type Guards

```typescript
import type { Notification } from '@/types';

function isUnread(notification: Notification): boolean {
    return notification.read_at === null;
}
```

## Migration Status

### ✅ Extracted & Organized

- Admin types (user management, login logs, analytics)
- Dashboard types (stats, activities, priorities)
- Notification types
- Viewer types (PDF viewer, document viewing)
- Search types
- Component prop types
- Utility types

### ✅ Already Well-Organized

- Authentication types
- Blockchain types
- Document types
- Procurement types
- Navigation types
- Enums
- Constants

### 📝 Recommendations

1. Continue using the barrel import pattern (`@/types`)
2. Extract component props only when reused
3. Keep page-specific props local to the page
4. Document complex types with JSDoc comments
5. Update this reference when adding new types

---

**Generated:** November 11, 2025
**Version:** 1.0.0
