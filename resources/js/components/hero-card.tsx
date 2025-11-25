import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export interface HeroCardProps {
    icon: LucideIcon;
    title: string;
    description: string | ReactNode;
    actions?: ReactNode;
    className?: string;
    iconWrapperClassName?: string;
    iconClassName?: string;
}

export const HeroCard = ({
    icon: Icon,
    title,
    description,
    actions,
    className,
    iconWrapperClassName = 'bg-primary/10',
    iconClassName = 'text-primary',
}: HeroCardProps) => {
    return (
        <Card className={cn(className)}>
            <CardContent className="p-3 sm:p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2 sm:gap-3 md:gap-4">
                        <div className={cn('rounded-lg p-1.5 sm:p-2', iconWrapperClassName)}>
                            <Icon className={cn('h-4 w-4 sm:h-5 sm:w-5 md:h-6 md:w-6', iconClassName)} />
                        </div>
                        <div>
                            <h1 className="text-foreground text-lg font-bold sm:text-xl md:text-2xl">{title}</h1>
                            <p className="text-muted-foreground mt-0.5 text-xs sm:mt-1 sm:text-sm">{description}</p>
                        </div>
                    </div>
                    {actions ? <div className="flex items-center gap-2 sm:gap-3 md:gap-4">{actions}</div> : null}
                </div>
            </CardContent>
        </Card>
    );
};
