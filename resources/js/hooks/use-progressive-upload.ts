import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { useState } from 'react';
import { uploadSingleDocument as uploadPreProcurement } from '@/actions/App/Http/Controllers/Procurement/PreProcurementController';
import { uploadSingleDocument as uploadProcurement } from '@/actions/App/Http/Controllers/Procurement/ProcurementController';
import { uploadSingleDocument as uploadPostProcurement } from '@/actions/App/Http/Controllers/Procurement/PostProcurementController';

interface ProgressiveUploadOptions {
    procurementId: string;
    stage: string;
    phase: 'pre-procurement' | 'procurement' | 'post-procurement';
    onUploadStart?: (documentName: string) => void;
    onUploadComplete?: (documentValue: string) => void;
    onUploadError?: (error: string) => void;
}

export function useProgressiveUpload(options: ProgressiveUploadOptions) {
    const [isUploading, setIsUploading] = useState(false);
    const [currentUpload, setCurrentUpload] = useState<string | null>(null);

    const validateFile = (file: File): boolean => {
        if (file.type !== 'application/pdf') {
            toast.error('Invalid file type', {
                description: 'Only PDF files are allowed.',
            });
            return false;
        }

        if (file.size > 10 * 1024 * 1024) {
            toast.error('File too large', {
                description: 'Maximum file size is 10MB.',
            });
            return false;
        }

        return true;
    };

    const handleDocumentUpload = (documentValue: string, documentName: string) => {
        // Create hidden file input
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'application/pdf';

        fileInput.onchange = (e) => {
            const target = e.target as HTMLInputElement;
            const file = target.files?.[0];

            if (!file) return;

            // Validate file
            if (!validateFile(file)) return;

            setIsUploading(true);
            setCurrentUpload(documentName);
            options.onUploadStart?.(documentName);

            // Get the appropriate Wayfinder route based on phase
            const uploadRoute =
                options.phase === 'pre-procurement'
                    ? uploadPreProcurement
                    : options.phase === 'procurement'
                      ? uploadProcurement
                      : uploadPostProcurement;

            // Create FormData for Inertia file upload
            const formData = {
                document_file: file,
                document_type: documentValue,
                description: `${documentName} for procurement ${options.procurementId}`,
            };

            // Submit using Inertia router with file upload support
            router.post(uploadRoute({ pr_number: options.procurementId, stage: options.stage }).url, formData, {
                preserveScroll: true,
                forceFormData: true, // Ensure FormData encoding for file upload
                onSuccess: () => {
                    toast.success('Document uploaded', {
                        description: `${documentName} has been uploaded successfully.`,
                    });
                    options.onUploadComplete?.(documentValue);
                    setIsUploading(false);
                    setCurrentUpload(null);
                },
                onError: (errors) => {
                    const errorMsg = Object.values(errors).flat().join(', ') || 'Failed to upload document.';
                    toast.error('Upload failed', {
                        description: errorMsg,
                    });
                    options.onUploadError?.(errorMsg);
                    setIsUploading(false);
                    setCurrentUpload(null);
                },
            });
        };

        fileInput.click();
    };

    return {
        isUploading,
        currentUpload,
        handleDocumentUpload,
    };
}
