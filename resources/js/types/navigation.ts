/**
 * Navigation & UI Types
 * Contains interfaces for navigation and UI components
 */

import type { LucideIcon } from 'lucide-react';
import type { Auth } from './auth';

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
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    csrf_token?: string;
    [key: string]: unknown;
}
