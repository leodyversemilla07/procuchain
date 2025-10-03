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
    statusValue: string;
    onStatusChange: (value: string) => void;
    statusOptions: ProcurementFilterOption[];
    stageValue: string;
    onStageChange: (value: string) => void;
    stageOptions: ProcurementFilterOption[];
    onRefresh: () => void;
    refreshDisabled?: boolean;
    isRefreshing?: boolean;
    className?: string;
}

export function ProcurementFiltersToolbar({
    searchValue,
    onSearchChange,
    statusValue,
    onStatusChange,
    statusOptions,
    stageValue,
    onStageChange,
    stageOptions,
    onRefresh,
    refreshDisabled = false,
    isRefreshing = false,
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
                                type="text"
                                placeholder="Search procurements..."
                                value={searchValue}
                                onChange={(event) => onSearchChange(event.target.value)}
                                className="h-10 pl-10"
                            />
                        </div>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Select value={statusValue} onValueChange={onStatusChange}>
                            <SelectTrigger className="h-10 w-full sm:w-[180px]">
                                <SelectValue placeholder={statusOptions[0]?.label ?? 'Select status'} />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
                    </div>
                    <div className="flex justify-center sm:justify-end">
                        <Button
                            onClick={onRefresh}
                            disabled={refreshDisabled}
                            variant="outline"
                            size="default"
                            className="flex h-10 w-full items-center space-x-2 sm:w-auto"
                        >
                            <RefreshCw className={cn('h-4 w-4', isRefreshing ? 'animate-spin' : undefined)} />
                            <span>Refresh</span>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
