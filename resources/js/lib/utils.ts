import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB to match backend limits

export function formatBytes(bytes: number, decimals = 2): string {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

export function isPdfFile(file: File | null): boolean {
    if (!file) return false;

    if (file.type && file.type === 'application/pdf') {
        return true;
    }

    if (!file.type || file.type === 'application/octet-stream') {
        return file.name ? file.name.toLowerCase().endsWith('.pdf') : false;
    }

    return false;
}

export function validateFile(file: File | null): { isValid: boolean; errorMessage?: string } {
    if (!file) {
        return { isValid: true };
    }

    if (file.size > MAX_FILE_SIZE) {
        return {
            isValid: false,
            errorMessage: `File size exceeds the limit of ${formatBytes(MAX_FILE_SIZE)}`,
        };
    }

    if (!isPdfFile(file)) {
        return {
            isValid: false,
            errorMessage: 'Only PDF files are accepted',
        };
    }

    return { isValid: true };
}

export const formatRelativeDate = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) {
        return 'Just now';
    }

    if (diffInSeconds < 3600) {
        return `${Math.floor(diffInSeconds / 60)} min ago`;
    }

    if (diffInSeconds < 86400) {
        return `${Math.floor(diffInSeconds / 3600)} hr ago`;
    }

    if (diffInSeconds < 604800) {
        return `${Math.floor(diffInSeconds / 86400)} day ago`;
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
