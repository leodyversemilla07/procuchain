/**
 * Main Type Exports (Barrel File)
 * 
 * This file re-exports all types from individual modules for convenient importing.
 * Import from '@/types' and all types will be available.
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
// CONSTANTS
// ============================================================================
export {
    MUNICIPAL_OFFICES,
    type MunicipalOffice,
} from './constants';
