import { Skeleton } from '@/components/ui/skeleton';

export const LoadingSkeleton = () => (
    <div className="space-y-4 mt-0">
        <div className="flex justify-between">
            <Skeleton className="h-10 w-[250px] dark:bg-gray-800" />
            <Skeleton className="h-10 w-[120px] dark:bg-gray-800" />
        </div>
        <Skeleton className="h-[400px] w-full dark:bg-gray-800" />
        <Skeleton className="h-10 w-full dark:bg-gray-800" />
    </div>
);