# TypeScript Types Documentation

This folder contains all reusable TypeScript type definitions for the ProcuChain application. Types are organized by domain to maintain clarity and reusability.

## File Organization

### Core Types

#### `index.ts` / `index.d.ts`

Barrel files that re-export all types from individual modules. Import from `@/types` to access all types.

```typescript
import { User, Stage, DashboardStats } from '@/types';
```

### Domain-Specific Types

#### `auth.ts`

Authentication and user-related types.

**Types:**

- `Auth` - Authentication context with user, roles, and permissions
- `User` - User model with account details and locking fields

**Usage:**

```typescript
import type { User, Auth } from '@/types';
```

#### `blockchain.ts`

Blockchain-specific data structures.

**Types:**

- `BlockchainProcurementDocument` - Document stored on blockchain
- `BlockchainProcurementState` - Procurement state on blockchain
- `BlockchainProcurementEvent` - Events published to blockchain
- `StreamPublication` - MultiChain stream publication structure

**Usage:**

```typescript
import type { BlockchainProcurementDocument } from '@/types';
```

#### `document.ts`

Document metadata and related structures.

**Types:**

- `Document` - Procurement document with metadata
- `DocumentMetadata` - Document metadata fields
- `SignatoryDetails` - Signatory information
- `StageMetadata` - Stage-specific metadata
- `Event` - Procurement event
- `TimelineItem` - Timeline tracking item

**Usage:**

```typescript
import type { Document, StageMetadata } from '@/types';
```

#### `procurement.ts`

Procurement-related types.

**Types:**

- `Procurement` - Complete procurement data
- `ProcurementListItem` - Procurement list item
- `PrInitiationResponse` - PR initiation response
- `ProcurementInitiationMetadata` - PR metadata
- `ProcurementInitiationDocument` - PR document structure
- `ProcurementInitiationDocumentData` - PR document with file

**Usage:**

```typescript
import type { Procurement, ProcurementListItem } from '@/types';
```

#### `enums.ts`

All application enums.

**Enums:**

- `StreamType` - Blockchain stream types
- `Stage` - Procurement stages
- `Status` - Procurement statuses
- `EventType` - Event types
- `EventCategory` - Event categories
- `EventSeverity` - Event severity levels
- `UserRole` - User roles
- `DocumentType` - Document types

**Usage:**

```typescript
import { Stage, Status, UserRole } from '@/types';
```

### Feature-Specific Types

#### `admin.ts`

Admin panel and user management types.

**Types:**

- `LoginLog` - Login attempt record
- `LoginStatistics` - Login statistics
- `UserActivityAnalytics` - User activity analytics
- `ExtendedUser` - User with extended fields
- `LockedAccount` - Locked account details
- `BlockchainExplorerData` - Blockchain explorer data
- `BlockchainOverview`, `BlockInfo`, `StreamInfo`, etc.

**Usage:**

```typescript
import type { LoginLog, UserActivityAnalytics } from '@/types';
```

#### `dashboard.ts`

Dashboard-related types.

**Types:**

- `DashboardStats` - Dashboard statistics
- `RecentActivity` - Recent activity item
- `RecentProcurement` - Recent procurement item
- `PriorityAction` - Priority action item
- `StatsGridItem` - Statistics grid item
- `ProcurementDistributionItem` - Distribution chart data
- `StageDistributionItem` - Stage distribution data

**Usage:**

```typescript
import type { DashboardStats, RecentActivity } from '@/types';
```

#### `notification.ts`

Notification system types.

**Types:**

- `Notification` - User notification
- `NotificationFilterType` - Notification filter options

**Usage:**

```typescript
import type { Notification, NotificationFilterType } from '@/types';
```

#### `viewer.ts`

Document viewer and viewing statistics.

**Types:**

- `PdfDocument` - PDF document data
- `DocumentView` - Document view record
- `ViewStats` - Viewing statistics
- `ViewerUser` - Viewer user info
- `CorrectionRecord` - Document correction record
- `CorrectionData` - Correction data
- `ProcurementDocument` - Procurement document reference

**Usage:**

```typescript
import type { PdfDocument, ViewStats } from '@/types';
```

#### `search.ts`

Search functionality types.

**Types:**

- `SearchResult` - Search result item
- `SearchSuggestion` - Search suggestion

**Usage:**

```typescript
import type { SearchResult, SearchSuggestion } from '@/types';
```

### UI Component Types

#### `components.ts`

Reusable component prop types.

**Types:**

- `PersonData`, `AffiliationType` - People input component
- `PaginationConfig` - Pagination component
- `FileUploadConfig` - File upload component
- `ErrorStateConfig`, `ErrorStateTone` - Error state component
- `SEOConfig` - SEO component
- `BadgeVariant` - Badge variants
- `ChartConfig` - Chart configuration

**Usage:**

```typescript
import type { PaginationConfig, ErrorStateConfig } from '@/types';
```

#### `navigation.ts`

Navigation and UI structure types.

**Types:**

- `BreadcrumbItem` - Breadcrumb item
- `NavGroup` - Navigation group
- `NavItem` - Navigation item
- `SharedData` - Shared Inertia page props

**Usage:**

```typescript
import type { BreadcrumbItem, NavItem, SharedData } from '@/types';
```

### Utility Types

#### `utils.ts`

General utility types.

**Types:**

- `Appearance` - Theme appearance mode
- `CopiedValue`, `CopyFn` - Clipboard utilities
- `OSInfo`, `NavigatorUAData` - Device detection
- `StructuredDataOrganization`, `StructuredDataWebSite` - SEO structured data
- `CSVValue` - CSV export utilities
- `ValidationError`, `FormErrors` - Form validation
- `DateRange`, `ValidityPeriod` - Date utilities

**Usage:**

```typescript
import type { Appearance, DateRange, OSInfo } from '@/types';
```

#### `constants.ts`

Application constants.

**Constants:**

- `MUNICIPAL_OFFICES` - List of municipal offices
- `MunicipalOffice` - Municipal office type

**Usage:**

```typescript
import { MUNICIPAL_OFFICES, type MunicipalOffice } from '@/types';
```

## Best Practices

### 1. Import from the Barrel File

Always import from `@/types` instead of individual files:

```typescript
// ✅ Good
import { User, Stage, DashboardStats } from '@/types';

// ❌ Avoid
import { User } from '@/types/auth';
import { Stage } from '@/types/enums';
```

### 2. Use Type Imports When Possible

Use `type` imports for better tree-shaking:

```typescript
import type { User, Procurement } from '@/types';
import { Stage, Status } from '@/types'; // Enums need regular imports
```

### 3. Extend Types When Needed

Extend existing types for page-specific props:

```typescript
import type { SharedData, Procurement } from '@/types';

interface PageProps extends SharedData {
    procurement: Procurement;
    canEdit: boolean;
}
```

### 4. Keep Component Props Local

Only extract component props to the types folder if they're reused across multiple files. Local component props can stay inline:

```typescript
// Local component - keep inline
interface ButtonProps {
    label: string;
    onClick: () => void;
}

// Reused across multiple files - extract to types
import type { PaginationConfig } from '@/types';
```

### 5. Document Complex Types

Add JSDoc comments for complex types:

```typescript
/**
 * Complete procurement data including documents, status, and events
 */
export interface Procurement {
    pr_number: string;
    // ...
}
```

## Type Naming Conventions

- **Interfaces**: Use PascalCase - `User`, `DashboardStats`
- **Types**: Use PascalCase - `BadgeVariant`, `Appearance`
- **Enums**: Use PascalCase - `Stage`, `UserRole`
- **Enum Values**: Use SCREAMING_SNAKE_CASE - `BAC_SECRETARIAT`, `PROCUREMENT_INITIATION`
- **Props Interfaces**: Suffix with `Props` or `Config` - `PaginationConfig`, `ErrorStateConfig`

## Adding New Types

When adding new types:

1. Determine the appropriate file based on the domain
2. Add the type to the file with proper JSDoc comments
3. Export the type from the file
4. Add the export to `index.ts` and `index.d.ts`
5. Update this README with the new type

## Migration Guide

When migrating inline types to this folder:

1. Find inline type definitions in component files
2. Determine if the type is reused across multiple files
3. If reused, extract to appropriate types file
4. Update imports in all files using the type
5. Remove the inline definition

## Type Categories Summary

| Category     | Files                                          | Purpose                       |
| ------------ | ---------------------------------------------- | ----------------------------- |
| **Core**     | `auth`, `user`, `navigation`                   | Essential app types           |
| **Domain**   | `procurement`, `document`, `blockchain`        | Business logic types          |
| **Features** | `admin`, `dashboard`, `notification`, `viewer` | Feature-specific types        |
| **UI**       | `components`, `navigation`                     | Component props and UI types  |
| **Utils**    | `utils`, `constants`                           | Helpers and shared utilities  |
| **System**   | `enums`                                        | Application-wide enumerations |

---

**Last Updated:** November 11, 2025
**Maintainer:** ProcuChain Development Team
