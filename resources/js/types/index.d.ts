import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    csrf_token?: string;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    blockchain_address: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    // Account locking fields
    account_locked?: boolean;
    locked_at?: string | null;
    lock_expires_at?: string | null;
    failed_login_attempts?: number;
    last_failed_login_at?: string | null;
    locked_reason?: string | null;
    is_currently_locked?: boolean;
    lock_time_remaining?: string | null;
    [key: string]: unknown; // This allows for additional properties...
}
