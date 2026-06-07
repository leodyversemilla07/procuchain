import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export interface HeroCardProps {
    icon: LucideIcon;
    title: string | ReactNode;
    description: string | ReactNode;
    actions?: ReactNode;
    children?: ReactNode;
    className?: string;
    iconWrapperClassName?: string;
    iconClassName?: string;
}

export const HeroCard = ({
    icon: Icon,
    title,
    description,
    actions,
    children,
    className,
    iconWrapperClassName = 'bg-primary/10',
    iconClassName = 'text-primary',
}: HeroCardProps) => {
    return (
        <Card className={cn(className)}>
            <CardContent className="p-3 sm:p-4 md:p-6">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div className="flex min-w-0 items-center gap-2 sm:gap-3 md:gap-4">
                        <div className={cn('rounded-lg p-1.5 sm:p-2', iconWrapperClassName)}>
                            <Icon className={iconClassName} />
                        </div>
                        <div className="min-w-0">
                            {typeof title === 'string' ? (
                                <h1 className="text-foreground text-lg font-bold sm:text-xl md:text-2xl">{title}</h1>
                            ) : (
                                <div className="text-foreground text-lg font-bold sm:text-xl md:text-2xl">{title}</div>
                            )}
                            <div className="text-muted-foreground mt-0.5 text-xs sm:mt-1 sm:text-sm">{description}</div>
                        </div>
                    </div>
                    {actions ? <div className="flex w-full flex-wrap items-center gap-2 xl:w-auto xl:justify-end">{actions}</div> : null}
                </div>
                {children && <div className="mt-2">{children}</div>}
            </CardContent>
        </Card>
    );
};
