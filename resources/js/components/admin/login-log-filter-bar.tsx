import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { CalendarIcon, ChevronDown, Download, Filter, Search, X } from 'lucide-react';
import type { DateRange } from 'react-day-picker';

interface LoginLogFilterBarProps {
    searchTerm: string;
    onSearchTermChange: (v: string) => void;
    showAdvancedFilters: boolean;
    onToggleAdvanced: () => void;
    hasActiveFilters: boolean;
    onClearAll: () => void;
    selectedCategory: 'all' | 'recent' | 'suspicious';
    onCategoryChange: (v: 'all' | 'recent' | 'suspicious') => void;
    selectedStatus: string;
    onStatusChange: (v: string) => void;
    selectedRole: string;
    onRoleChange: (v: string) => void;
    selectedDeviceType: string;
    onDeviceTypeChange: (v: string) => void;
    selectedBrowser: string;
    onBrowserChange: (v: string) => void;
    dateRange: DateRange | undefined;
    onDateRangeChange: (r: DateRange | undefined) => void;
    onDateRangePreset: (preset: string) => void;
    uniqueRoles: string[];
    uniqueBrowsers: string[];
    uniqueDeviceTypes: string[];
    selectedLogsCount: number;
    onClearSelection: () => void;
    onExportSelected: () => void;
    isExporting: boolean;
}

export default function LoginLogFilterBar({
    searchTerm,
    onSearchTermChange,
    showAdvancedFilters,
    onToggleAdvanced,
    hasActiveFilters,
    onClearAll,
    selectedCategory,
    onCategoryChange,
    selectedStatus,
    onStatusChange,
    selectedRole,
    onRoleChange,
    selectedDeviceType,
    onDeviceTypeChange,
    selectedBrowser,
    onBrowserChange,
    dateRange,
    onDateRangeChange,
    onDateRangePreset,
    uniqueRoles,
    uniqueBrowsers,
    uniqueDeviceTypes,
    selectedLogsCount,
    onClearSelection,
    onExportSelected,
    isExporting,
}: LoginLogFilterBarProps) {
    const categoryLabels = { all: 'All Categories', recent: 'Recent', suspicious: 'Suspicious' };
    const statusLabels: Record<string, string> = { all: 'All Statuses', success: 'Success', failed: 'Failed' };

    return (
        <Card>
            <CardContent className="space-y-4 p-6">
                {/* Search Bar and Main Actions */}
                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            placeholder="Search by name, email, IP, location..."
                            value={searchTerm}
                            onChange={(e) => onSearchTermChange(e.target.value)}
                            className="pl-10"
                        />
                        {searchTerm && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => onSearchTermChange('')}
                                className="absolute top-1/2 right-2 h-6 w-6 -translate-y-1/2 p-0"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={onToggleAdvanced} size="sm" className="whitespace-nowrap">
                            <Filter className="mr-2 h-4 w-4" />
                            Filters
                            <ChevronDown className={`ml-2 h-4 w-4 transition-transform ${showAdvancedFilters ? 'rotate-180' : ''}`} />
                        </Button>
                        {hasActiveFilters && (
                            <Button variant="outline" onClick={onClearAll} size="sm">
                                <X className="mr-2 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

                {/* Advanced Filters */}
                {showAdvancedFilters && (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Select value={selectedCategory} onValueChange={(v) => onCategoryChange(v as 'all' | 'recent' | 'suspicious')}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Category">{() => categoryLabels[selectedCategory]}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All Categories</SelectItem>
                                    <SelectItem value="recent">Recent</SelectItem>
                                    <SelectItem value="suspicious">Suspicious</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select value={selectedStatus} onValueChange={(v) => v && onStatusChange(v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Status">{() => statusLabels[selectedStatus]}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    <SelectItem value="success">Success</SelectItem>
                                    <SelectItem value="failed">Failed</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select value={selectedRole} onValueChange={(v) => v && onRoleChange(v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Role">
                                    {() => (selectedRole === 'all' ? 'All Roles' : selectedRole.replace('_', ' ').toUpperCase())}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All Roles</SelectItem>
                                    {uniqueRoles.map((r) => (
                                        <SelectItem key={r} value={r}>
                                            {r.replace('_', ' ').toUpperCase()}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select value={selectedDeviceType} onValueChange={(v) => v && onDeviceTypeChange(v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Device">
                                    {() =>
                                        selectedDeviceType === 'all'
                                            ? 'All Devices'
                                            : selectedDeviceType.charAt(0).toUpperCase() + selectedDeviceType.slice(1)
                                    }
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All Devices</SelectItem>
                                    {uniqueDeviceTypes.map((d) => (
                                        <SelectItem key={d} value={d}>
                                            {d.charAt(0).toUpperCase() + d.slice(1)}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Select value={selectedBrowser} onValueChange={(v) => v && onBrowserChange(v)}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Browser">
                                    {() => (selectedBrowser === 'all' ? 'All Browsers' : selectedBrowser)}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All Browsers</SelectItem>
                                    {uniqueBrowsers.map((b) => (
                                        <SelectItem key={b} value={b}>
                                            {b}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Popover>
                            <PopoverTrigger
                                render={
                                    <Button
                                        variant="outline"
                                        className={cn('justify-start text-left font-normal', !dateRange && 'text-muted-foreground')}
                                    />
                                }
                            >
                                <CalendarIcon className="mr-2 h-4 w-4" />
                                {dateRange?.from ? (
                                    dateRange.to ? (
                                        <>
                                            <span className="hidden sm:inline">
                                                {format(dateRange.from, 'MMM dd')} - {format(dateRange.to, 'MMM dd')}
                                            </span>
                                            <span className="sm:hidden">
                                                {format(dateRange.from, 'M/d')} - {format(dateRange.to, 'M/d')}
                                            </span>
                                        </>
                                    ) : (
                                        <>
                                            <span className="hidden sm:inline">{format(dateRange.from, 'MMM dd, y')}</span>
                                            <span className="sm:hidden">{format(dateRange.from, 'M/d/yy')}</span>
                                        </>
                                    )
                                ) : (
                                    <span>Date Range</span>
                                )}
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                                <div className="border-b p-3">
                                    <div className="flex flex-wrap gap-2">
                                        <Button variant="ghost" size="sm" onClick={() => onDateRangePreset('today')}>
                                            Today
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => onDateRangePreset('last7days')}>
                                            Last 7 Days
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => onDateRangePreset('last30days')}>
                                            Last 30 Days
                                        </Button>
                                    </div>
                                </div>
                                <Calendar
                                    autoFocus
                                    mode="range"
                                    defaultMonth={dateRange?.from}
                                    selected={dateRange}
                                    onSelect={onDateRangeChange}
                                    numberOfMonths={2}
                                />
                            </PopoverContent>
                        </Popover>
                    </div>
                )}

                {/* Active Filters Display */}
                {hasActiveFilters && (
                    <div className="flex flex-wrap gap-2">
                        {selectedCategory !== 'all' && (
                            <Badge variant="secondary" className="gap-1">
                                {selectedCategory.charAt(0).toUpperCase() + selectedCategory.slice(1)}
                                <X className="h-3 w-3 cursor-pointer" onClick={() => onCategoryChange('all')} />
                            </Badge>
                        )}
                        {searchTerm && (
                            <Badge variant="secondary" className="gap-1">
                                "{searchTerm}"<X className="h-3 w-3 cursor-pointer" onClick={() => onSearchTermChange('')} />
                            </Badge>
                        )}
                        {selectedRole !== 'all' && (
                            <Badge variant="secondary" className="gap-1">
                                {selectedRole.replace('_', ' ').toUpperCase()}
                                <X className="h-3 w-3 cursor-pointer" onClick={() => onRoleChange('all')} />
                            </Badge>
                        )}
                        {selectedStatus !== 'all' && (
                            <Badge variant="secondary" className="gap-1">
                                {selectedStatus.charAt(0).toUpperCase() + selectedStatus.slice(1)}
                                <X className="h-3 w-3 cursor-pointer" onClick={() => onStatusChange('all')} />
                            </Badge>
                        )}
                        {selectedDeviceType !== 'all' && (
                            <Badge variant="secondary" className="gap-1">
                                {selectedDeviceType.charAt(0).toUpperCase() + selectedDeviceType.slice(1)}
                                <X className="h-3 w-3 cursor-pointer" onClick={() => onDeviceTypeChange('all')} />
                            </Badge>
                        )}
                        {selectedBrowser !== 'all' && (
                            <Badge variant="secondary" className="gap-1">
                                {selectedBrowser}
                                <X className="h-3 w-3 cursor-pointer" onClick={() => onBrowserChange('all')} />
                            </Badge>
                        )}
                        {(dateRange?.from || dateRange?.to) && (
                            <Badge variant="secondary" className="gap-1">
                                {dateRange.from?.toLocaleDateString()} - {dateRange.to?.toLocaleDateString()}
                                <X className="h-3 w-3 cursor-pointer" onClick={() => onDateRangeChange(undefined)} />
                            </Badge>
                        )}
                    </div>
                )}

                {/* Bulk Actions Bar */}
                {selectedLogsCount > 0 && (
                    <div className="bg-muted/50 flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <Badge variant="default">{selectedLogsCount} selected</Badge>
                            <Button variant="ghost" size="sm" onClick={onClearSelection}>
                                Clear selection
                            </Button>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={onExportSelected} disabled={isExporting}>
                                <Download className="mr-2 h-4 w-4" />
                                Export Selected
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
