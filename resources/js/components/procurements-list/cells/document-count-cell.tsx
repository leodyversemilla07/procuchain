import { FileIcon } from 'lucide-react';

interface DocumentCountCellProps {
    count: number;
}

export const DocumentCountCell = ({ count }: DocumentCountCellProps) => (
    <div className="flex items-center gap-1.5">
        {count !== undefined ? (
            <div
                className="flex items-center rounded-full bg-primary/10 py-0.5 pr-2 pl-1 dark:bg-primary/20/20"
                role="status"
                aria-label={`${count} ${count === 1 ? 'document' : 'documents'}`}
            >
                <FileIcon className="mr-1 h-3.5 w-3.5 text-primary dark:text-primary" aria-hidden="true" />
                <span className="text-xs font-medium text-primary dark:text-primary">{count}</span>
            </div>
        ) : (
            // Skeleton loader for deferred document counts
            <div
                className="flex animate-pulse items-center rounded-full bg-muted py-0.5 pr-2 pl-1 dark:bg-muted"
                role="status"
                aria-label="Loading document count"
            >
                <div className="mr-1 h-3.5 w-3.5 rounded bg-muted-foreground/50 dark:bg-muted-foreground/50" aria-hidden="true"></div>
                <div className="h-3 w-4 rounded bg-muted-foreground/50 dark:bg-muted-foreground/50" aria-hidden="true"></div>
            </div>
        )}
    </div>
);
