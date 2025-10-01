import React, { useState } from 'react';

interface UseFileDropProps {
    validateFile: (file: File) => boolean;
    setFile: (file: File | null) => void;
}

export function useFileDrop({ validateFile, setFile }: UseFileDropProps) {
    const [isDragging, setIsDragging] = useState(false);

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            const file = e.dataTransfer.files[0];
            if (validateFile(file)) {
                setFile(file);
            }
        }
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (!isDragging) setIsDragging(true);
    };

    return {
        isDragging,
        handleDrop,
        handleDragEnter,
        handleDragLeave,
        handleDragOver,
        setIsDragging,
    };
}

// Multi-file drop hook for dynamic lists (e.g., bidders)
export function useMultiFileDrop<T = unknown>(
    items: T[],
    validateFile: (file: File) => boolean,
    setFileAt: (index: number, file: File | null) => void,
) {
    // One drag state per item
    const [dragStates, setDragStates] = useState<boolean[]>(Array(items.length).fill(false));

    // Update drag state array if items length changes
    React.useEffect(() => {
        setDragStates((prev) => {
            if (prev.length !== items.length) {
                return Array(items.length).fill(false);
            }
            return prev;
        });
    }, [items.length]);

    return items.map((_, index) => ({
        isDragging: !!dragStates[index],
        handleDrop: (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => {
                const updated = [...prev];
                updated[index] = false;
                return updated;
            });
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                if (validateFile(file)) {
                    setFileAt(index, file);
                }
            }
        },
        handleDragEnter: (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => {
                const updated = [...prev];
                updated[index] = true;
                return updated;
            });
        },
        handleDragLeave: (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => {
                const updated = [...prev];
                updated[index] = false;
                return updated;
            });
        },
        handleDragOver: (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => {
                const updated = [...prev];
                updated[index] = true;
                return updated;
            });
        },
        setIsDragging: (val: boolean) => {
            setDragStates((prev) => {
                const updated = [...prev];
                updated[index] = val;
                return updated;
            });
        },
    }));
}
