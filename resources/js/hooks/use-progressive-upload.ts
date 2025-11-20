import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { useState, useEffect, useRef } from 'react';
import { uploadSingleDocument as uploadPreProcurement } from '@/actions/App/Http/Controllers/Procurement/PreProcurementController';
import { uploadSingleDocument as uploadProcurement } from '@/actions/App/Http/Controllers/Procurement/ProcurementController';
import { uploadSingleDocument as uploadPostProcurement } from '@/actions/App/Http/Controllers/Procurement/PostProcurementController';
import { uploadSingleDocument as uploadInitiation } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';

type ProgressiveUploadOptions =
    | {
          procurementId: string;
          phase: 'initiation';
          onUploadStart?: (documentName: string) => void;
          onUploadComplete?: (documentValue: string) => void;
          onUploadError?: (error: string) => void;
          onProgress?: (percentage: number) => void;
      }
    | {
          procurementId: string;
          stage: string;
          phase: 'pre-procurement' | 'procurement' | 'post-procurement';
          onUploadStart?: (documentName: string) => void;
          onUploadComplete?: (documentValue: string) => void;
          onUploadError?: (error: string) => void;
          onProgress?: (percentage: number) => void;
      };

export function useProgressiveUpload(options: ProgressiveUploadOptions) {
    const [isUploading, setIsUploading] = useState(false);
    const [currentUpload, setCurrentUpload] = useState<string | null>(null);
    const [uploadProgress, setUploadProgress] = useState<number>(0);
    const activeRequestRef = useRef<(() => void) | null>(null);

    // Cleanup on unmount to prevent memory leaks
    useEffect(() => {
        return () => {
            // Cancel any active upload when component unmounts
            if (activeRequestRef.current) {
                activeRequestRef.current();
                activeRequestRef.current = null;
            }
        };
    }, []);

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

    const getUploadRoute = () => {
        if (options.phase === 'initiation') {
            return {
                route: uploadInitiation,
                params: { pr_number: options.procurementId } as const,
            };
        }

        if (options.phase === 'pre-procurement') {
            return {
                route: uploadPreProcurement,
                params: { pr_number: options.procurementId, stage: options.stage } as const,
            };
        }

        if (options.phase === 'procurement') {
            return {
                route: uploadProcurement,
                params: { pr_number: options.procurementId, stage: options.stage } as const,
            };
        }

        if (options.phase === 'post-procurement') {
            return {
                route: uploadPostProcurement,
                params: { pr_number: options.procurementId, stage: options.stage } as const,
            };
        }

        throw new Error(`Unknown phase: ${options.phase}`);
    };

    const handleDocumentUpload = (documentValue: string, documentName: string) => {
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'application/pdf';
        fileInput.setAttribute('aria-label', `Upload ${documentName}`);

        fileInput.onchange = (e) => {
            const target = e.target as HTMLInputElement;
            const file = target.files?.[0];

            if (!file) {
                // Clean up file input after use
                setTimeout(() => fileInput.remove(), 100);
                return;
            }

            if (!validateFile(file)) {
                // Clean up file input after validation failure
                setTimeout(() => fileInput.remove(), 100);
                return;
            }

            setIsUploading(true);
            setCurrentUpload(documentName);
            setUploadProgress(0);
            options.onUploadStart?.(documentName);

            const { route, params } = getUploadRoute();

            const formData = {
                document_file: file,
                document_type: documentValue,
                description: `${documentName} for procurement ${options.procurementId}`,
            };

            const cancelToken = router.post(route(params as any).url, formData, {
                preserveScroll: true,
                forceFormData: true,
                onProgress: (progress) => {
                    if (progress?.percentage) {
                        setUploadProgress(progress.percentage);
                        options.onProgress?.(progress.percentage);
                    }
                },
                onSuccess: () => {
                    toast.success('Document uploaded', {
                        description: `${documentName} has been uploaded successfully.`,
                    });
                    
                    // Safely call onUploadComplete and ensure state cleanup
                    try {
                        options.onUploadComplete?.(documentValue);
                    } catch (error) {
                        console.error('Error in onUploadComplete callback:', error);
                    }
                },
                onError: (errors) => {
                    let errorMsg = 'Failed to upload document.';
                    
                    if (typeof errors === 'object' && errors !== null) {
                        // Handle various error object shapes
                        const errorValues = Object.values(errors);
                        if (errorValues.length > 0) {
                            errorMsg = errorValues
                                .flat()
                                .filter((msg): msg is string => typeof msg === 'string')
                                .join(', ') || errorMsg;
                        }
                    } else if (typeof errors === 'string') {
                        errorMsg = errors;
                    }
                    
                    toast.error('Upload failed', {
                        description: errorMsg,
                    });
                    
                    // Safely call onUploadError and ensure state cleanup
                    try {
                        options.onUploadError?.(errorMsg);
                    } catch (error) {
                        console.error('Error in onUploadError callback:', error);
                    }
                },
                onFinish: () => {
                    setIsUploading(false);
                    setCurrentUpload(null);
                    setUploadProgress(0);
                    activeRequestRef.current = null;
                    // Clean up file input after upload completes
                    setTimeout(() => fileInput.remove(), 100);
                },
            });

            // Store cancel function for cleanup on unmount
            activeRequestRef.current = () => {
                if (typeof cancelToken === 'object' && 'cancel' in cancelToken) {
                    (cancelToken as { cancel: () => void }).cancel();
                }
            };
        };

        fileInput.click();
    };

    return {
        isUploading,
        currentUpload,
        uploadProgress,
        handleDocumentUpload,
    };
}
