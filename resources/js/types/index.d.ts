/**
 * Main Type Exports (Barrel File)
 *
 * This file re-exports all types from individual modules for convenient importing.
 * Import from '@/types' and all types will be available.
 */

// ============================================================================
// ENUMS
// ============================================================================
export { DocumentType, EventCategory, EventSeverity, EventType, Stage, Status, StreamType, UserRole } from './enums';

// ============================================================================
// BLOCKCHAIN TYPES
// ============================================================================
export type { BlockchainProcurementDocument, BlockchainProcurementEvent, BlockchainProcurementState, StreamPublication } from './blockchain';

// ============================================================================
// DOCUMENT TYPES
// ============================================================================
export type { Document, DocumentMetadata, Event, SignatoryDetails, StageMetadata, TimelineItem } from './document';

// ============================================================================
// PROCUREMENT TYPES
// ============================================================================
export type {
    PrInitiationResponse,
    Procurement,
    ProcurementInitiationDocument,
    ProcurementInitiationDocumentData,
    ProcurementInitiationMetadata,
    ProcurementListItem,
} from './procurement';

// ============================================================================
// AUTHENTICATION & USER TYPES
// ============================================================================
export type { Auth, User } from './auth';

// ============================================================================
// NAVIGATION & UI TYPES
// ============================================================================
export type { BreadcrumbItem, NavGroup, NavItem, SharedData } from './navigation';

// ============================================================================
// ADMIN & USER MANAGEMENT TYPES
// ============================================================================
export type {
  AddressInfo,
  BlockInfo,
  BlockchainExplorerData,
  BlockchainOverview,
  CircuitBreakerState,
  DocumentMetrics,
  ExtendedUser,
  HealthStatus,
  LockedAccount,
  LoginLog,
  LoginPatterns,
  LoginStatistics,
  PeerInfo,
  QueueMetrics,
  RoleActivity,
  SearchResults,
  SecurityMetrics,
  SessionAnalytics,
  StreamInfo,
  TimeRangeKey,
  UserActivityAnalytics,
  UserActivityOverview,
} from './admin';

// ============================================================================
// DASHBOARD TYPES
// ============================================================================
export type {
    DashboardStats,
    DistributionKey,
    PriorityAction,
    ProcurementDistributionItem,
    RecentActivity,
    RecentProcurement,
    StageDistributionItem,
    StatsGridItem,
} from './dashboard';

// ============================================================================
// NOTIFICATION TYPES
// ============================================================================
export type { Notification, NotificationFilterType } from './notification';

// ============================================================================
// VIEWER TYPES
// ============================================================================
export type { CorrectionData, CorrectionRecord, DocumentView, PdfDocument, ProcurementDocument, ViewStats, ViewerUser } from './viewer';

// ============================================================================
// SEARCH TYPES
// ============================================================================
export type { SearchResult, SearchSuggestion } from './search';

// ============================================================================
// WORKFLOW TYPES
// ============================================================================
export type { ProcurementMode, WorkflowInfo, WorkflowProgress, WorkflowStage } from './workflow';

// ============================================================================
// COMPONENT PROPS TYPES
// ============================================================================
export type {
    AffiliationType,
    ChartConfig,
    ErrorStateConfig,
    ErrorStateTone,
    FileUploadConfig,
    PaginationConfig,
    PersonData,
    SEOConfig,
} from './components';

// ============================================================================
// UTILITY TYPES
// ============================================================================
export type {
    Appearance,
    CSVValue,
    CopiedValue,
    CopyFn,
    DateRange,
    FormErrors,
    NavigatorUABrandVersion,
    NavigatorUAData,
    NavigatorWithUAData,
    OSInfo,
    StructuredDataOrganization,
    StructuredDataSoftwareApplication,
    StructuredDataWebSite,
    ValidationError,
    ValidityPeriod,
} from './utils';

// ============================================================================
// CONSTANTS
// ============================================================================
export { MUNICIPAL_OFFICES, STAGE_ORDER, type MunicipalOffice } from './constants';
