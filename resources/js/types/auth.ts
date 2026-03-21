/**
 * Authentication & User Types
 * Contains interfaces for user authentication and user data
 */

export interface Auth {
    user: User | null;
    roles: string[];
    permissions: string[];
    can: {
        manageProcurement: boolean;
        approveProcurement: boolean;
        manageDocuments: boolean;
        viewDocuments: boolean;
        manageStages: boolean;
        accessBlockchain: boolean;
        manageUsers: boolean;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    blockchain_address: string | null;
    avatar?: string;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    account_locked?: boolean;
    locked_at?: string | null;
    lock_expires_at?: string | null;
    failed_login_attempts?: number;
    last_failed_login_at?: string | null;
    locked_reason?: string | null;
    is_currently_locked?: boolean;
    lock_time_remaining?: string | null;
    two_factor_enabled?: boolean;
    [key: string]: unknown;
}
