import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Input } from '@/components/ui/input';
import {
    PlusIcon,
    Table2Icon,
    LayersIcon,
    HelpCircleIcon,
    SearchIcon,
    XIcon,
    FileTextIcon,
    SlidersHorizontalIcon
} from 'lucide-react';
import React, { useState } from 'react';
import { cn } from '@/lib/utils';

type ViewType = 'table' | 'kanban';

interface ProcurementListHeaderProps {
    userRole: string;
    viewType: ViewType;
    setViewType: (value: ViewType) => void;
    procurementsCount: number;
    loading: boolean;
    searchValue: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    onOpenFilters?: () => void;
}

export const ProcurementListHeader = ({
    userRole,
    viewType,
    setViewType,
    procurementsCount,
    searchValue,
    onSearchChange,
    searchPlaceholder = "Search procurements...",
    onOpenFilters,
}: ProcurementListHeaderProps) => {
    const [isSearchFocused, setIsSearchFocused] = useState(false);

    const handleSearchInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        onSearchChange(e.target.value);
    };

    const clearSearch = () => {
        onSearchChange("");
    };

    return (
        <CardHeader
            className={cn(
                "pb-4 md:pb-5 border-b dark:border-sidebar-border",
                "px-4 sm:px-6 md:px-8"
            )}
        >
            <div className="flex flex-col md:flex-row justify-between items-start gap-2 sm:gap-3 mb-4 md:mb-5">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center flex-wrap gap-2 mb-1">
                        <div className="hidden sm:flex items-center justify-center h-8 w-8 md:h-9 md:w-9 rounded-lg bg-primary/10 text-primary mr-1.5">
                            <FileTextIcon className="h-4 w-4 md:h-5 md:w-5" />
                        </div>
                        <CardTitle className="text-lg sm:text-xl md:text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-50 flex items-center flex-wrap gap-1 sm:gap-2">
                            <span>Procurement List</span>
                            <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800/60">
                                {procurementsCount} {procurementsCount === 1 ? 'item' : 'items'}
                            </Badge>
                        </CardTitle>
                    </div>
                    <CardDescription className="text-sm text-gray-500 dark:text-gray-400">
                        View and manage procurement items
                    </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                    <TooltipProvider delayDuration={300}>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="ghost" size="icon" className="h-5 w-5 ml-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <HelpCircleIcon className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom" className="max-w-xs bg-gray-900 text-gray-100 dark:bg-gray-800">
                                <p className="text-xs">Blockchain verification ensures all procurement records are tamper-proof and transparent.</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>

            <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4">
                <div className="flex items-center gap-2 w-full sm:max-w-md">
                    <div className={cn(
                        "relative w-full flex-grow transition-all duration-200",
                        isSearchFocused && "sm:flex-grow"
                    )}>
                        <div className={cn(
                            "absolute left-2.5 sm:left-3 top-1/2 transform -translate-y-1/2 transition-all duration-200",
                            isSearchFocused ? "text-primary" : "text-gray-500 dark:text-gray-400"
                        )}>
                            <SearchIcon className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                        <Input
                            placeholder={searchPlaceholder}
                            value={searchValue}
                            onChange={handleSearchInputChange}
                            onFocus={() => setIsSearchFocused(true)}
                            onBlur={() => setIsSearchFocused(false)}
                            className={cn(
                                "pl-8 sm:pl-10 pr-8 sm:pr-10 w-full rounded-full shadow-sm transition-all duration-200",
                                "border-sidebar-border/70 dark:border-sidebar-border",
                                "dark:placeholder-gray-400 hover:border-gray-400 dark:hover:border-gray-600",
                                "focus:ring-1 focus:ring-offset-0",
                                "text-sm h-9 sm:h-10",
                                isSearchFocused
                                    ? "border-primary dark:border-primary focus:border-primary dark:focus:border-primary focus:ring-primary/20 dark:focus:ring-primary/30"
                                    : "focus:border-gray-400 dark:focus:border-gray-600"
                            )}
                        />
                        {searchValue && (
                            <button
                                onClick={clearSearch}
                                type="button"
                                aria-label="Clear search"
                                className="absolute right-2.5 sm:right-3 top-1/2 transform -translate-y-1/2
                                    text-gray-400 hover:text-gray-600 dark:text-gray-500
                                    dark:hover:text-gray-300 transition-colors duration-150
                                    h-6 w-6 flex items-center justify-center"
                            >
                                <XIcon className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                            </button>
                        )}
                    </div>
                    {onOpenFilters && (
                        <TooltipProvider delayDuration={300}>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={onOpenFilters}
                                        className="h-9 sm:h-10 w-9 sm:w-10 rounded-full border-sidebar-border/70 dark:border-sidebar-border
                                                hover:bg-gray-100 dark:hover:bg-gray-800 flex-shrink-0"
                                    >
                                        <SlidersHorizontalIcon className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="bottom" className="bg-gray-900 text-gray-100 dark:bg-gray-800">
                                    <p className="text-xs">Filter procurements</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </div>

                <div className="flex items-center gap-2 sm:gap-3 w-full sm:w-auto justify-between sm:justify-end">
                    {/* View toggle buttons - Only shown on desktop */}
                    <div className="hidden lg:flex items-center bg-gray-100 dark:bg-gray-800/60 p-1 rounded-lg flex-shrink-0 
                                border border-gray-200 dark:border-gray-700/80 shadow-sm">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setViewType('table')}
                            className={cn(
                                "text-xs px-2.5 sm:px-3 rounded-md transition-all duration-200",
                                viewType === 'table'
                                    ? "bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 shadow-sm"
                                    : "text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                            )}
                        >
                            <Table2Icon className="h-3.5 w-3.5 mr-1 sm:mr-1.5" />
                            Table
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setViewType('kanban')}
                            className={cn(
                                "text-xs px-2.5 sm:px-3 rounded-md transition-all duration-200",
                                viewType === 'kanban'
                                    ? "bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 shadow-sm"
                                    : "text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                            )}
                        >
                            <LayersIcon className="h-3.5 w-3.5 mr-1 sm:mr-1.5" />
                            Kanban
                        </Button>
                    </div>

                    {userRole === 'bac_secretariat' && (
                        <Button
                            className="bg-primary hover:bg-primary/90 text-xs py-1.5 px-3 font-medium shadow 
                                transition-all duration-200 dark:bg-primary/90 dark:hover:bg-primary/80 
                                dark:text-white/95 h-9 sm:h-auto min-w-0 flex-shrink-0"
                            asChild
                        >
                            <Link href="/bac-secretariat/procurement/procurement-initiation" className="flex items-center justify-center">
                                <PlusIcon className="h-3.5 w-3.5 mr-1 sm:mr-1.5" />
                                <span className="hidden xs:inline">New</span> Procurement
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </CardHeader>
    );
};
