import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { roleFilterLabels, statusFilterLabels } from '@/hooks/use-locked-accounts';
import { Search } from 'lucide-react';

interface LockedAccountsFilterBarProps {
    searchQuery: string;
    onSearchQueryChange: (value: string) => void;
    roleFilter: string;
    onRoleFilterChange: (value: string) => void;
    statusFilter: string;
    onStatusFilterChange: (value: string) => void;
    filteredCount: number;
    totalCount: number;
    selectedCount: number;
}

export default function LockedAccountsFilterBar({
    searchQuery,
    onSearchQueryChange,
    roleFilter,
    onRoleFilterChange,
    statusFilter,
    onStatusFilterChange,
    filteredCount,
    totalCount,
    selectedCount,
}: LockedAccountsFilterBarProps) {
    const hasActiveFilters = searchQuery || roleFilter !== 'all' || statusFilter !== 'all';

    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search />
                        <Input
                            placeholder="Search by name or email..."
                            value={searchQuery}
                            onChange={(e) => onSearchQueryChange(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Select value={roleFilter} onValueChange={(value) => value && onRoleFilterChange(value)}>
                            <SelectTrigger className="w-full sm:w-[180px]">
                                <SelectValue placeholder="Filter by role">{() => roleFilterLabels[roleFilter] ?? 'Filter by role'}</SelectValue>
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
                        <Select value={statusFilter} onValueChange={(value) => value && onStatusFilterChange(value)}>
                            <SelectTrigger className="w-full sm:w-[180px]">
                                <SelectValue placeholder="Filter by status">
                                    {() => statusFilterLabels[statusFilter] ?? 'Filter by status'}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="active">Active Lock</SelectItem>
                                    <SelectItem value="expired">Expired Lock</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                {hasActiveFilters && (
                    <div className="text-muted-foreground mt-3 text-sm">
                        Showing {filteredCount} of {totalCount} account(s)
                        {selectedCount > 0 && ` • ${selectedCount} selected`}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
