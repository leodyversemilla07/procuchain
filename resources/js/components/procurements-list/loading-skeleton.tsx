import { Skeleton } from '@/components/ui/skeleton';

export const LoadingSkeleton = () => (
    <div className="mt-0 flex flex-col gap-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:justify-between">
            <Skeleton className="h-10 w-full max-w-[250px] dark:bg-muted" />
            <Skeleton className="h-10 w-full max-w-[120px] sm:w-[120px] dark:bg-muted" />
        </div>
        <Skeleton className="h-[400px] w-full dark:bg-muted" />
        <Skeleton className="h-10 w-full dark:bg-muted" />
    </div>
);
