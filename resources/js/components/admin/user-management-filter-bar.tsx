import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CheckCircle2, Download, Search, Shield, Trash2, UserCheck, X } from 'lucide-react';

const roleFilterLabels: Record<string, string> = {
    all: 'All Roles',
    admin: 'Administrator',
    bac_chairman: 'BAC Chairman',
    bac_secretariat: 'BAC Secretariat',
    hope: 'HOPE',
};

const verificationFilterLabels: Record<string, string> = {
    all: 'All Users',
    verified: 'Verified',
    unverified: 'Unverified',
};

const twoFactorFilterLabels: Record<string, string> = {
    all: 'All Users',
    enabled: '2FA Enabled',
    disabled: '2FA Disabled',
};

interface UserManagementFilterBarProps {
    searchQuery: string;
    onSearchQueryChange: (value: string) => void;
    roleFilter: string;
    onRoleFilterChange: (value: string) => void;
    verificationFilter: string;
    onVerificationFilterChange: (value: string) => void;
    twoFactorFilter: string;
    onTwoFactorFilterChange: (value: string) => void;
    activeQuickFilter: string | null;
    onQuickFilter: (filterType: string) => void;
    isRefreshing: boolean;
    autoRefresh: boolean;
    onToggleAutoRefresh: () => void;
    onRefresh: () => void;
    onCreateUser: () => void;
    selectedCount: number;
    totalCount: number;
    filteredCount: number;
    onExportCSV: () => void;
    onBulkDelete: () => void;
    onClearSelection: () => void;
    hasDeletePermission: boolean;
}

export function UserManagementFilterBar({
    searchQuery,
    onSearchQueryChange,
    roleFilter,
    onRoleFilterChange,
    verificationFilter,
    onVerificationFilterChange,
    twoFactorFilter,
    onTwoFactorFilterChange,
    activeQuickFilter,
    onQuickFilter,
    selectedCount,
    totalCount,
    filteredCount,
    onExportCSV,
    onBulkDelete,
    onClearSelection,
    hasDeletePermission,
}: UserManagementFilterBarProps) {
    return (
        <>
            <Card>
                <CardContent className="p-3 sm:p-4">
                    <div className="flex flex-col gap-3 sm:gap-4">
                        {/* Search and Filter Row */}
                        <div className="flex flex-col gap-3 lg:flex-row lg:gap-4">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2" />
                                <Input
                                    placeholder="Search users..."
                                    value={searchQuery}
                                    onChange={(e) => onSearchQueryChange(e.target.value)}
                                    className="h-10 pl-9 text-sm"
                                />
                            </div>
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:gap-3 lg:flex lg:gap-2">
                                <Select value={roleFilter} onValueChange={(value) => value && onRoleFilterChange(value)}>
                                    <SelectTrigger className="h-10 w-full text-sm md:w-[200px] lg:w-[180px]">
                                        <SelectValue placeholder="Filter by role">
                                            {() => roleFilterLabels[roleFilter] ?? 'Filter by role'}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">All Roles</SelectItem>
                                            <SelectItem value="admin">Administrator</SelectItem>
                                            <SelectItem value="bac_chairman">BAC Chairman</SelectItem>
                                            <SelectItem value="bac_secretariat">BAC Secretariat</SelectItem>
                                            <SelectItem value="hope">HOPE</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Select value={verificationFilter} onValueChange={(value) => value && onVerificationFilterChange(value)}>
                                    <SelectTrigger className="h-10 w-full text-sm md:w-[200px] lg:w-[180px]">
                                        <SelectValue placeholder="Email status">
                                            {() => verificationFilterLabels[verificationFilter] ?? 'Email status'}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">All Users</SelectItem>
                                            <SelectItem value="verified">Verified</SelectItem>
                                            <SelectItem value="unverified">Unverified</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Select value={twoFactorFilter} onValueChange={(value) => value && onTwoFactorFilterChange(value)}>
                                    <SelectTrigger className="h-10 w-full text-sm md:w-[200px] lg:w-[180px]">
                                        <SelectValue placeholder="2FA status">
                                            {() => twoFactorFilterLabels[twoFactorFilter] ?? '2FA status'}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">All Users</SelectItem>
                                            <SelectItem value="enabled">2FA Enabled</SelectItem>
                                            <SelectItem value="disabled">2FA Disabled</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Quick Filter Chips */}
                        <div className="scrollbar-hide -mx-3 flex items-center gap-2 overflow-x-auto px-3 pb-2 sm:mx-0 sm:gap-3 sm:px-0 sm:pb-0">
                            <span className="text-muted-foreground shrink-0 text-xs font-medium sm:text-sm">Quick filters:</span>
                            <div className="flex gap-2">
                                <Button
                                    variant={activeQuickFilter === 'verified' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => onQuickFilter('verified')}
                                    className="h-8 shrink-0 gap-1 px-3 text-xs whitespace-nowrap"
                                >
                                    <CheckCircle2 />
                                    <span>Verified</span>
                                </Button>
                                <Button
                                    variant={activeQuickFilter === '2fa' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => onQuickFilter('2fa')}
                                    className="h-8 shrink-0 gap-1 px-3 text-xs whitespace-nowrap"
                                >
                                    <Shield />
                                    <span>2FA</span>
                                </Button>
                                <Button
                                    variant={activeQuickFilter === 'admin' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => onQuickFilter('admin')}
                                    className="h-8 shrink-0 gap-1 px-3 text-xs whitespace-nowrap"
                                >
                                    <UserCheck />
                                    <span>Admin</span>
                                </Button>
                                <Button
                                    variant={activeQuickFilter === 'unverified' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => onQuickFilter('unverified')}
                                    className="h-8 shrink-0 gap-1 px-3 text-xs whitespace-nowrap"
                                >
                                    <X />
                                    <span>Unverified</span>
                                </Button>
                            </div>
                        </div>

                        {/* Filter Info */}
                        {(searchQuery || roleFilter !== 'all' || verificationFilter !== 'all' || twoFactorFilter !== 'all') && (
                            <div className="text-muted-foreground text-xs sm:text-sm">
                                Showing {filteredCount} of {totalCount} user(s)
                                {selectedCount > 0 && ` • ${selectedCount} selected`}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Bulk Actions Bar */}
            {selectedCount > 0 && (
                <div className="bg-accent/50 dark:bg-accent/20 border-accent dark:border-accent/40 flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-4">
                    <div className="flex items-center gap-2">
                        <span className="text-accent-foreground dark:text-accent-foreground text-xs font-medium sm:text-sm">
                            {selectedCount} selected
                        </span>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={onExportCSV}
                            className="border-primary/20 dark:border-primary/30 text-primary dark:text-primary hover:bg-primary/10 dark:hover:bg-primary/20 h-9 w-full justify-center text-xs sm:h-8 sm:w-auto"
                        >
                            <Download className="mr-1.5 h-3.5 w-3.5" />
                            <span>Export CSV</span>
                        </Button>
                        {hasDeletePermission && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={onBulkDelete}
                                className="h-9 w-full justify-center text-xs sm:h-8 sm:w-auto"
                            >
                                <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                                <span>Delete</span>
                            </Button>
                        )}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onClearSelection}
                            className="text-muted-foreground hover:bg-muted hover:text-muted-foreground h-9 w-full justify-center text-xs sm:h-8 sm:w-auto"
                        >
                            <span>Clear</span>
                        </Button>
                    </div>
                </div>
            )}
        </>
    );
}
