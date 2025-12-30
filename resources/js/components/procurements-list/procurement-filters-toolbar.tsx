import { router } from '@inertiajs/react';
import { RefreshCw, Search } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type ProcurementFilterOption = {
    value: string;
    label: string;
};

export interface ProcurementFiltersToolbarProps {
    searchValue: string;
    onSearchChange: (value: string) => void;
    stageValue: string;
    onStageChange: (value: string) => void;
    stageOptions: ProcurementFilterOption[];
    onRefresh: () => void;
    refreshDisabled?: boolean;
    isRefreshing?: boolean;
    lastRefreshed?: Date;
    isArchived?: boolean;
    className?: string;
}

const formatTimeAgo = (date: Date): string => {
    const seconds = Math.floor((new Date().getTime() - date.getTime()) / 1000);

    if (seconds < 10) return 'just now';
    if (seconds < 60) return `${seconds}s ago`;

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;

    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
};

export function ProcurementFiltersToolbar({
    searchValue,
    onSearchChange,
    stageValue,
    onStageChange,
    stageOptions,
    onRefresh,
    refreshDisabled = false,
    isRefreshing = false,
    lastRefreshed,
    isArchived,
    className,
}: ProcurementFiltersToolbarProps) {
    return (
        <div className={cn('pb-4', className)}>
            <div className="space-y-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="relative max-w-md flex-1">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                            <Input
                                id="procurement-search"
                                name="search"
                                type="text"
                                placeholder="Search procurements..."
                                value={searchValue}
                                onChange={(event) => onSearchChange(event.target.value)}
                                className="h-10 pl-10"
                                autoComplete="off"
                            />
                        </div>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Select value={stageValue} onValueChange={onStageChange}>
                            <SelectTrigger className="h-10 w-full sm:w-[180px]">
                                <SelectValue placeholder={stageOptions[0]?.label ?? 'Select stage'} />
                            </SelectTrigger>
                            <SelectContent>
                                {stageOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {/* Archive Toggle */}
                        <div className="flex items-center bg-muted/50 rounded-lg p-1 text-sm shadow-inner self-start sm:self-center border border-border/50">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    router.get(window.location.pathname, { archived: null }, { preserveState: true, preserveScroll: true });
                                }}
                                className={cn(
                                    "h-8 px-3 rounded-md transition-all text-xs font-medium",
                                    !isArchived ? "bg-background shadow-sm text-foreground" : "text-muted-foreground hover:bg-background/40"
                                )}
                            >
                                Active
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    router.get(window.location.pathname, { archived: 1 }, { preserveState: true, preserveScroll: true });
                                }}
                                className={cn(
                                    "h-8 px-3 rounded-md transition-all text-xs font-medium",
                                    isArchived ? "bg-background shadow-sm text-foreground" : "text-muted-foreground hover:bg-background/40"
                                )}
                            >
                                Archived
                            </Button>
                        </div>
                    </div>
                    <div className="flex flex-col items-center gap-2 sm:flex-row sm:justify-end">
                        {lastRefreshed && (
                            <span className="text-muted-foreground text-xs" role="status" aria-live="polite">
                                Updated {formatTimeAgo(lastRefreshed)}
                            </span>
                        )}
                        <Button
                            onClick={onRefresh}
                            disabled={refreshDisabled}
                            variant="outline"
                            size="default"
                            className="flex h-10 w-full items-center space-x-2 sm:w-auto"
                            aria-label={isRefreshing ? 'Refreshing data' : 'Refresh procurement data'}
                        >
                            <RefreshCw className={cn('h-4 w-4', isRefreshing ? 'animate-spin' : undefined)} aria-hidden="true" />
                            <span>Refresh</span>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
