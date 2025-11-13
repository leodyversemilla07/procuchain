/**
 * Main Type Exports
 * Barrel file that re-exports all types from individual modules
 */

// ============================================================================
// ENUMS
// ============================================================================
export {
    StreamType,
    Stage,
    Status,
    EventType,
    EventCategory,
    EventSeverity,
    UserRole,
    DocumentType,
} from './enums';

// ============================================================================
// BLOCKCHAIN TYPES
// ============================================================================
export type {
    BlockchainProcurementDocument,
    BlockchainProcurementState,
    BlockchainProcurementEvent,
    StreamPublication,
} from './blockchain';

// ============================================================================
// DOCUMENT TYPES
// ============================================================================
export type {
    DocumentMetadata,
    SignatoryDetails,
    StageMetadata,
    Document,
    Event,
    TimelineItem,
} from './document';

// ============================================================================
// PROCUREMENT TYPES
// ============================================================================
export type {
    ProcurementListItem,
    Procurement,
    PrInitiationResponse,
    ProcurementInitiationMetadata,
    ProcurementInitiationDocument,
    ProcurementInitiationDocumentData,
} from './procurement';

// ============================================================================
// AUTHENTICATION & USER TYPES
// ============================================================================
export type {
    Auth,
    User,
} from './auth';

// ============================================================================
// NAVIGATION & UI TYPES
// ============================================================================
export type {
    BreadcrumbItem,
    NavGroup,
    NavItem,
    SharedData,
} from './navigation';

// ============================================================================
// ADMIN & USER MANAGEMENT TYPES
// ============================================================================
export type {
    LoginLog,
    LoginStatistics,
    TimeRangeKey,
    UserActivityOverview,
    LoginPatterns,
    RoleActivity,
    SessionAnalytics,
    SecurityMetrics,
    UserActivityAnalytics,
    ExtendedUser,
    LockedAccount,
    BlockchainOverview,
    BlockInfo,
    StreamInfo,
    AddressInfo,
    PeerInfo,
    CircuitBreakerState,
    QueueMetrics,
    DocumentMetrics,
    HealthStatus,
    BlockchainExplorerData,
} from './admin';

// ============================================================================
// DASHBOARD TYPES
// ============================================================================
export type {
    DashboardStats,
    RecentActivity,
    RecentProcurement,
    PriorityAction,
    StatsGridItem,
    ProcurementDistributionItem,
    DistributionKey,
    StageDistributionItem,
} from './dashboard';

// ============================================================================
// NOTIFICATION TYPES
// ============================================================================
export type {
    Notification,
    NotificationFilterType,
} from './notification';

// ============================================================================
// VIEWER TYPES
// ============================================================================
export type {
    ViewerUser,
    DocumentView,
    ViewStats,
    PdfDocument,
    CorrectionRecord,
    CorrectionData,
    ProcurementDocument,
} from './viewer';

// ============================================================================
// SEARCH TYPES
// ============================================================================
export type {
    SearchResult,
    SearchSuggestion,
} from './search';

// ============================================================================
// COMPONENT PROPS TYPES
// ============================================================================
export type {
    PersonData,
    AffiliationType,
    PaginationConfig,
    FileUploadConfig,
    ErrorStateTone,
    ErrorStateConfig,
    SEOConfig,
    ChartConfig,
} from './components';

// ============================================================================
// UTILITY TYPES
// ============================================================================
export type {
    Appearance,
    CopiedValue,
    CopyFn,
    NavigatorUABrandVersion,
    NavigatorUAData,
    NavigatorWithUAData,
    OSInfo,
    StructuredDataOrganization,
    StructuredDataWebSite,
    StructuredDataSoftwareApplication,
    CSVValue,
    ValidationError,
    FormErrors,
    DateRange,
    ValidityPeriod,
} from './utils';

// ============================================================================
// CONSTANTS
// ============================================================================
export {
    MUNICIPAL_OFFICES,
    STAGE_ORDER,
    type MunicipalOffice,
} from './constants';
