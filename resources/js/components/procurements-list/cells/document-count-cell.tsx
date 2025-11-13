import { FileIcon } from 'lucide-react';

interface DocumentCountCellProps {
    count: number;
}

export const DocumentCountCell = ({ count }: DocumentCountCellProps) => (
    <div className="flex items-center gap-1.5">
        {count !== undefined ? (
            <div 
                className="flex items-center rounded-full bg-blue-50 py-0.5 pr-2 pl-1 dark:bg-blue-900/20"
                role="status"
                aria-label={`${count} ${count === 1 ? 'document' : 'documents'}`}
            >
                <FileIcon className="mr-1 h-3.5 w-3.5 text-blue-500 dark:text-blue-400" aria-hidden="true" />
                <span className="text-xs font-medium text-blue-700 dark:text-blue-300">{count}</span>
            </div>
        ) : (
            // Skeleton loader for deferred document counts
            <div 
                className="flex animate-pulse items-center rounded-full bg-gray-100 py-0.5 pr-2 pl-1 dark:bg-gray-800"
                role="status"
                aria-label="Loading document count"
            >
                <div className="mr-1 h-3.5 w-3.5 rounded bg-gray-300 dark:bg-gray-600" aria-hidden="true"></div>
                <div className="h-3 w-4 rounded bg-gray-300 dark:bg-gray-600" aria-hidden="true"></div>
            </div>
        )}
    </div>
);
