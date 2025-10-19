import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';

interface OptimisticFormOptions {
    successMessage?: string;
    errorMessage?: string;
    preserveScroll?: boolean;
    onSuccess?: () => void;
    onError?: () => void;
}

/**
 * Custom hook for optimistic form submissions
 * Updates UI immediately, then syncs with server
 *
 * @example
 * const { submit, isSubmitting } = useOptimisticForm();
 *
 * const handleSubmit = () => {
 *   submit(
 *     '/api/endpoint',
 *     { data: 'value' },
 *     {
 *       successMessage: 'Success!',
 *       errorMessage: 'Failed!'
 *     }
 *   );
 * };
 */
export function useOptimisticForm() {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const submit = useCallback((url: string, data: Record<string, unknown> | FormData, options: OptimisticFormOptions = {}) => {
        const { successMessage = 'Saved successfully', errorMessage = 'Something went wrong', preserveScroll = true, onSuccess, onError } = options;

        // Set optimistic loading state
        setIsSubmitting(true);

        // Make the request
        router.post(url, data as never, {
            preserveScroll,
            onSuccess: () => {
                setIsSubmitting(false);
                toast.success(successMessage);
                onSuccess?.();
            },
            onError: () => {
                setIsSubmitting(false);
                toast.error(errorMessage);
                onError?.();
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    }, []);

    return { submit, isSubmitting };
}

/**
 * Custom hook for optimistic state updates with rollback
 * Perfect for toggle actions, mark as read, etc.
 *
 * @example
 * const { optimisticUpdate } = useOptimisticState(items, setItems);
 *
 * const handleToggle = (id: string) => {
 *   optimisticUpdate(
 *     prev => prev.map(item => item.id === id ? { ...item, active: !item.active } : item),
 *     () => router.post(`/items/${id}/toggle`)
 *   );
 * };
 */
export function useOptimisticState<T>(state: T, setState: React.Dispatch<React.SetStateAction<T>>) {
    const optimisticUpdate = useCallback(
        (
            updateFn: (prev: T) => T,
            requestFn: () => void,
            options: {
                successMessage?: string;
                errorMessage?: string;
            } = {},
        ) => {
            // Store previous state for rollback
            const previousState = state;

            // Apply optimistic update immediately
            setState(updateFn);

            // Make the request
            try {
                requestFn();

                if (options.successMessage) {
                    toast.success(options.successMessage);
                }
            } catch (error) {
                // Rollback on error
                setState(() => previousState);

                toast.error(options.errorMessage || 'Something went wrong');
                console.error('Optimistic update failed:', error);
            }
        },
        [state, setState],
    );

    return { optimisticUpdate };
}

/**
 * Hook for handling optimistic deletions
 * Immediately removes item from UI, rolls back on error
 *
 * @example
 * const { deleteItem, isDeleting } = useOptimisticDelete(items, setItems);
 *
 * const handleDelete = (id: string) => {
 *   deleteItem(
 *     id,
 *     item => item.id === id,
 *     `/items/${id}`
 *   );
 * };
 */
export function useOptimisticDelete<T>(items: T[], setItems: React.Dispatch<React.SetStateAction<T[]>>) {
    const [deletingIds, setDeletingIds] = useState<Set<string>>(new Set());

    const deleteItem = useCallback(
        (
            id: string,
            matchFn: (item: T) => boolean,
            deleteUrl: string,
            options: {
                successMessage?: string;
                errorMessage?: string;
            } = {},
        ) => {
            // Store previous state
            const previousItems = [...items];

            // Mark as deleting
            setDeletingIds((prev) => new Set(prev).add(id));

            // Optimistically remove from UI
            setItems((prev) => prev.filter((item) => !matchFn(item)));

            // Make delete request
            router.delete(deleteUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    setDeletingIds((prev) => {
                        const next = new Set(prev);
                        next.delete(id);
                        return next;
                    });
                    toast.success(options.successMessage || 'Deleted successfully');
                },
                onError: () => {
                    // Rollback on error
                    setItems(previousItems);
                    setDeletingIds((prev) => {
                        const next = new Set(prev);
                        next.delete(id);
                        return next;
                    });
                    toast.error(options.errorMessage || 'Failed to delete');
                },
            });
        },
        [items, setItems],
    );

    const isDeleting = useCallback((id: string) => deletingIds.has(id), [deletingIds]);

    return { deleteItem, isDeleting };
}
