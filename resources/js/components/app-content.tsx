import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import * as React from 'react';

interface AppContentProps {
    variant?: 'header' | 'sidebar';
    children?: React.ReactNode;
    className?: string;
}

export function AppContent({ variant = 'header', children, className, ...props }: AppContentProps) {
    if (variant === 'sidebar') {
        return (
            <SidebarInset className={cn('min-w-0 overflow-x-hidden', className)} {...props}>
                {children}
            </SidebarInset>
        );
    }

    return (
        <main className={cn('mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl', className)} {...props}>
            {children}
        </main>
    );
}
