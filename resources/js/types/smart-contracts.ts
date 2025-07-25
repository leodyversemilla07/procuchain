// Smart Contract Types for Pure PHP Implementation
export interface SmartContractValidationResult {
    compliant: boolean;
    missing_fields: string[];
    invalid_fields: string[];
    stage: string;
    validation_timestamp: string;
}

export interface DocumentIntegrityResult {
    valid: boolean;
    blockchain_hash?: string;
    file_size?: number;
    file_key?: string;
    document_type?: string;
    timestamp?: string;
    user_address?: string;
    txid?: string;
    block_time?: number;
    integrity_details?: {
        hash_length: number;
        file_size: number | null;
        timestamp: string | null;
    };
    error?: string;
    searched_procurement?: string;
    searched_hash?: string;
}

export interface StorageConsistencyResult {
    consistent: boolean;
    total_documents: number;
    validated_documents: number;
    consistency_percentage: number;
    inconsistencies: Array<{
        txid: string;
        document_hash: string;
        errors: string[];
        blockchain_data: Record<string, unknown>;
    }>;
    validation_details: Array<{
        hash: string;
        valid: boolean;
        checks_performed: string[];
    }>;
    error?: string;
}

export interface AuditTrailEntry {
    txid: string;
    block_time: number | null;
    formatted_time: string | null;
    stream_type: 'documents' | 'status' | 'events' | 'unknown';
    user_address: string;
    timestamp: string | null;
    action: 'document_upload' | 'status_update' | 'event_log' | 'unknown';
    data: Record<string, unknown>;
    document_hash?: string;
    document_type?: string;
    file_size?: number | null;
    stage?: string;
}

export interface AuditTrailResult {
    procurement_id: string;
    total_entries: number;
    audit_trail: AuditTrailEntry[];
    generated_at: string;
}

export interface SmartContractSystemStatus {
    success: boolean;
    library_created: boolean;
    filters_created: boolean;
    configuration_set: boolean;
    php_validation_ready: boolean;
    errors: string[];
    validation_setup?: {
        message: string;
        php_validation_ready: boolean;
        blockchain_config_updated: boolean;
    };
}

export interface DocumentMetadata {
    hash: string;
    file_key: string;
    file_size: number;
    document_type: string;
    user_address: string;
    timestamp: string;
    procurement_id?: string;
    stage_metadata?: Record<string, unknown>;
}

export interface SmartContractApiResponse<T> {
    success: boolean;
    data: T;
    message?: string;
    error?: string;
}

// Document validation request types
export interface ValidateIntegrityRequest {
    procurement_id: string;
    document_hash: string;
}

export interface CheckComplianceRequest {
    metadata: DocumentMetadata;
    stage: string;
}

export interface ValidateStorageRequest {
    procurement_id: string;
}

// Enhanced file upload with validation states
export interface ValidatedFile extends File {
    validationStatus?: 'pending' | 'validating' | 'valid' | 'invalid' | 'error';
    validationResult?: SmartContractValidationResult;
    integrityResult?: DocumentIntegrityResult;
    hash?: string;
    validationErrors?: string[];
}

// Smart contract hook return types
export interface UseSmartContractValidation {
    validateMetadata: (metadata: DocumentMetadata, stage: string) => Promise<SmartContractValidationResult>;
    validateIntegrity: (procurementId: string, hash: string) => Promise<DocumentIntegrityResult>;
    validateStorage: (procurementId: string) => Promise<StorageConsistencyResult>;
    getAuditTrail: (procurementId: string) => Promise<AuditTrailResult>;
    getSystemStatus: () => Promise<SmartContractSystemStatus>;
    isLoading: boolean;
    error: string | null;
}

// Component props for smart contract components
export interface DocumentValidationIndicatorProps {
    file: ValidatedFile;
    onRetry?: () => void;
    showDetails?: boolean;
}

export interface AuditTrailViewerProps {
    procurementId: string;
    autoRefresh?: boolean;
    refreshInterval?: number;
}

export interface StorageConsistencyDashboardProps {
    procurementId: string;
    showDetails?: boolean;
}

export interface SmartContractStatusPanelProps {
    showInitializeButton?: boolean;
    onInitialize?: () => void;
}

// Validation severity levels
export type ValidationSeverity = 'success' | 'warning' | 'error' | 'info';

export interface ValidationMessage {
    type: ValidationSeverity;
    message: string;
    field?: string;
    details?: string;
}

// Enhanced form validation
export interface SmartContractFormValidation {
    isValid: boolean;
    messages: ValidationMessage[];
    fieldErrors: Record<string, string[]>;
    canSubmit: boolean;
}

// Document type constraints from PHP backend
export const ALLOWED_DOCUMENT_TYPES = [
    'Purchase Request',
    'Minutes',
    'Attendance',
    'Bidding Documents',
    'Evaluation Report',
    'BAC Resolution',
    'Notice of Award',
    'Performance Bond',
    'Contract',
    'Purchase Order',
    'Notice to Proceed',
    'Certificate of Completion'
] as const;

export type AllowedDocumentType = typeof ALLOWED_DOCUMENT_TYPES[number];

// Validation constraints from PHP backend
export const VALIDATION_CONSTRAINTS = {
    MAX_FILE_SIZE: 10485760, // 10MB
    HASH_LENGTH: 64,
    USER_ADDRESS_MIN_LENGTH: 25,
    MAX_STRING_LENGTHS: {
        file_key: 500,
        document_type: 100,
        user_address: 100
    }
} as const;
