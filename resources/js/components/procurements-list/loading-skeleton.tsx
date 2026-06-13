import { Skeleton } from '@/components/ui/skeleton';

export const LoadingSkeleton = () => (
    <div className="mt-0 flex flex-col gap-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:justify-between">
            <Skeleton className="dark:bg-muted h-10 w-full max-w-[250px]" />
            <Skeleton className="dark:bg-muted h-10 w-full max-w-[120px] sm:w-[120px]" />
        </div>
        <Skeleton className="dark:bg-muted h-[400px] w-full" />
        <Skeleton className="dark:bg-muted h-10 w-full" />
    </div>
);
