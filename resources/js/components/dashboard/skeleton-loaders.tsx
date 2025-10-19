import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function TableSkeleton() {
    return (
        <Card className="shadow-sm">
            <CardHeader>
                <Skeleton className="h-6 w-48" />
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    {[...Array(5)].map((_, i) => (
                        <div key={i} className="flex gap-4">
                            <Skeleton className="h-12 flex-1" />
                            <Skeleton className="h-12 w-32" />
                            <Skeleton className="h-12 w-24" />
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export function CardListSkeleton({ count = 3 }: { count?: number }) {
    return (
        <div className="space-y-3">
            {[...Array(count)].map((_, i) => (
                <div key={i} className="flex gap-3 rounded-lg border p-3">
                    <Skeleton className="h-10 w-10 rounded-full" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-3 w-1/2" />
                    </div>
                </div>
            ))}
        </div>
    );
}

export function ChartSkeleton() {
    return (
        <Card className="shadow-sm">
            <CardHeader>
                <Skeleton className="h-6 w-48" />
                <Skeleton className="mt-2 h-4 w-64" />
            </CardHeader>
            <CardContent>
                <Skeleton className="h-64 w-full" />
            </CardContent>
        </Card>
    );
}

export function PriorityActionsSkeleton() {
    return (
        <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
                <div key={i} className="flex items-center gap-3 rounded-lg border p-3">
                    <Skeleton className="h-8 w-8 rounded" />
                    <Skeleton className="h-4 flex-1" />
                </div>
            ))}
        </div>
    );
}
