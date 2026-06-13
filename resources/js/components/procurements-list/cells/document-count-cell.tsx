import { FileIcon } from 'lucide-react';

interface DocumentCountCellProps {
    count: number;
}

export const DocumentCountCell = ({ count }: DocumentCountCellProps) => (
    <div className="flex items-center gap-1.5">
        {count !== undefined ? (
            <div
                className="bg-primary/10 dark:bg-primary/20/20 flex items-center rounded-full py-0.5 pr-2 pl-1"
                role="status"
                aria-label={`${count} ${count === 1 ? 'document' : 'documents'}`}
            >
                <FileIcon className="text-primary dark:text-primary mr-1 h-3.5 w-3.5" aria-hidden="true" />
                <span className="text-primary dark:text-primary text-xs font-medium">{count}</span>
            </div>
        ) : (
            // Skeleton loader for deferred document counts
            <div
                className="bg-muted dark:bg-muted flex animate-pulse items-center rounded-full py-0.5 pr-2 pl-1"
                role="status"
                aria-label="Loading document count"
            >
                <div className="bg-muted-foreground/50 dark:bg-muted-foreground/50 mr-1 h-3.5 w-3.5 rounded" aria-hidden="true"></div>
                <div className="bg-muted-foreground/50 dark:bg-muted-foreground/50 h-3 w-4 rounded" aria-hidden="true"></div>
            </div>
        )}
    </div>
);
