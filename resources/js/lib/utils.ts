import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export const MAX_FILE_SIZE = 10 * 1024 * 1024;

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
            errorMessage: `File size exceeds the limit of ${formatBytes(MAX_FILE_SIZE)}`
        };
    }

    if (!isPdfFile(file)) {
        return {
            isValid: false,
            errorMessage: 'Only PDF files are accepted'
        };
    }

    return { isValid: true };
}
