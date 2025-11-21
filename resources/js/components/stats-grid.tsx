import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export interface StatsGridItem {
    id?: string;
    label: string;
    value: ReactNode;
    icon: LucideIcon;
    iconClassName?: string;
    roles?: string[];
}

export interface StatsGridProps {
    items: StatsGridItem[];
    userRole?: string | null;
    className?: string;
    gridClassName?: string;
}

export const StatsGrid = ({ items, userRole, className, gridClassName }: StatsGridProps) => {
    const visibleItems = items.filter((item) => {
        if (!item.roles || item.roles.length === 0) {
            return true;
        }

        if (!userRole) {
            return false;
        }

        return item.roles.includes(userRole);
    });

    if (visibleItems.length === 0) {
        return null;
    }

    const columnClassName = gridClassName
        ? gridClassName
        : visibleItems.length >= 4
          ? 'md:grid-cols-4'
          : visibleItems.length === 3
            ? 'md:grid-cols-3'
            : 'md:grid-cols-2';

    return (
        <div className={cn('grid grid-cols-1 gap-3 sm:gap-4 sm:grid-cols-2', columnClassName, className)}>
            {visibleItems.map((item) => {
                const IconComponent = item.icon;

                return (
                    <Card key={item.id ?? item.label} className="shadow-sm">
                        <CardContent className="p-3 sm:p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-muted-foreground text-xs sm:text-sm font-medium">{item.label}</p>
                                    <p className="text-xl sm:text-2xl font-bold">{item.value}</p>
                                </div>
                                <div className={cn('rounded-full p-1.5 sm:p-2', item.iconClassName)}>
                                    <IconComponent className="h-4 w-4 sm:h-5 sm:w-5" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
};
