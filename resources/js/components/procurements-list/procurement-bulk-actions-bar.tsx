import { Download } from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export interface ProcurementBulkActionsBarProps {
    selectedCount: number;
    onExport?: () => void;
    disabled?: boolean;
    className?: string;
    exportLabel?: ReactNode;
}

export function ProcurementBulkActionsBar({
    selectedCount,
    onExport,
    disabled = false,
    className,
    exportLabel = 'Export to CSV',
    children,
}: PropsWithChildren<ProcurementBulkActionsBarProps>) {
    if (selectedCount <= 0) {
        return null;
    }

    return (
        <div className={cn('flex w-full items-center justify-between', className)}>
            <div className="bg-muted/30 flex w-full flex-1 flex-col items-start justify-between gap-3 rounded-lg border p-3 sm:flex-row sm:items-center">
                <Badge variant="secondary" className="bg-primary/10 text-primary border-primary/20 px-2.5 py-1">
                    {selectedCount} row{selectedCount > 1 ? 's' : ''} selected
                </Badge>
                <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    {children}
                    {onExport ? (
                        <Button
                            variant="default"
                            size="sm"
                            disabled={disabled}
                            className="w-full whitespace-nowrap sm:w-auto"
                            onClick={onExport}
                        >
                            <Download className="h-4 w-4" />
                            <span className="ml-2">{exportLabel}</span>
                        </Button>
                    ) : null}
                </div>
            </div>
        </div>
    );
}
