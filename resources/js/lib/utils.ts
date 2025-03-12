import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Maximum file size allowed for uploads (10MB)
 */
export const MAX_FILE_SIZE = 10 * 1024 * 1024;

/**
 * Formats a file size in bytes to a human-readable string
 * @param bytes - File size in bytes
 * @param decimals - Number of decimal places to show
 * @returns Formatted string (e.g., "5.25 MB")
 */
export function formatBytes(bytes: number, decimals = 2): string {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Validates if a file is a PDF based on MIME type and extension
 * @param file - File to validate
 * @returns boolean indicating if the file is a valid PDF
 */
export function isPdfFile(file: File | null): boolean {
    if (!file) return false;

    // Prioritize MIME type check
    if (file.type && file.type === 'application/pdf') {
        return true;
    }

    // Only fall back to extension check if MIME type is missing or generic
    if (!file.type || file.type === 'application/octet-stream') {
        return file.name ? file.name.toLowerCase().endsWith('.pdf') : false;
    }

    return false;
}

/**
 * Validates a file against size and type requirements
 * @param file - File to validate
 * @returns Object containing validation result and any error message
 */
export function validateFile(file: File | null): { isValid: boolean; errorMessage?: string } {
    if (!file) {
        return { isValid: true }; // No file is valid (just means none selected yet)
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
