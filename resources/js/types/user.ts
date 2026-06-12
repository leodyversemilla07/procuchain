export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    blockchain_address?: string;
    email_verified_at?: string;
    two_factor_enabled?: boolean;
    two_factor_confirmed_at?: string;
    two_factor_recovery_codes?: string;
    backup_codes?: string[];
    backup_codes_generated_at?: string;
    created_at: string;
    updated_at?: string;
    roles?: Array<{ id: number; name: string }>;
}

export const getRoleBadgeColor = (role: string) => {
    switch (role) {
        case 'admin':
            return 'bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 hover:bg-red-200 dark:hover:bg-red-900/30 border border-red-200 dark:border-red-800/30';
        case 'bac_chairman':
            return 'bg-blue-100 dark:bg-blue-900/20 text-blue-800 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-900/30 border border-blue-200 dark:border-blue-800/30';
        case 'hope':
            return 'bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 hover:bg-green-200 dark:hover:bg-green-900/30 border border-green-200 dark:border-green-800/30';
        case 'bac_secretariat':
            return 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800/30';
        default:
            return 'bg-gray-100 dark:bg-gray-800/50 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700/50 border border-gray-200 dark:border-gray-700/50';
    }
};

export const getRoleDisplayName = (role: string) => {
    switch (role) {
        case 'bac_secretariat':
            return 'BAC Secretariat';
        case 'bac_chairman':
            return 'BAC Chairman';
        case 'hope':
            return 'HOPE';
        case 'admin':
            return 'Administrator';
        default:
            return role;
    }
};

export const formatDate = (dateValue: string | undefined) => {
    if (!dateValue || dateValue === '' || dateValue === 'null' || dateValue === 'undefined') return null;
    const date = new Date(dateValue);
    return isNaN(date.getTime()) ? null : date;
};
