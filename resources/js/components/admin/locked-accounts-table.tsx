import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Pagination } from '@/components/pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  formatDateTime,
  getLockStatusColor,
  getRoleBadgeColor,
  getRoleDisplayName,
} from '@/hooks/use-locked-accounts';
import type { User } from '@/types';
import {
  AlertTriangle,
  Clock,
  Copy,
  ExternalLink,
  History,
  MoreHorizontal,
  QrCode,
  RotateCcw,
  Shield,
  Unlock,
  User as UserIcon,
} from 'lucide-react';
import { toast } from 'sonner';

interface LockedAccountsTableProps {
  filteredAccounts: User[];
  paginatedAccounts: User[];
  isLoading: boolean;
  isRefreshing: boolean;
  selectedAccounts: Set<number>;
  pageIndex: number;
  pageSize: number;
  pageCount: number;
  searchQuery: string;
  roleFilter: string;
  statusFilter: string;
  onToggleSelectAll: () => void;
  onToggleAccountSelection: (id: number) => void;
  onPageChange: (index: number) => void;
  onPageSizeChange: (size: number) => void;
  onUnlockAccount: (user: User) => void;
  onResetAttempts: (user: User) => void;
  onViewProfile: (user: User) => void;
  onViewLoginHistory: (user: User) => void;
  canManageUsers: boolean;
}

export default function LockedAccountsTable({
  filteredAccounts,
  paginatedAccounts,
  isLoading,
  isRefreshing,
  selectedAccounts,
  pageIndex,
  pageSize,
  pageCount,
  searchQuery,
  roleFilter,
  statusFilter,
  onToggleSelectAll,
  onToggleAccountSelection,
  onPageChange,
  onPageSizeChange,
  onUnlockAccount,
  onResetAttempts,
  onViewProfile,
  onViewLoginHistory,
  canManageUsers,
}: LockedAccountsTableProps) {
  const isBusy = isLoading || isRefreshing;

  // Shared dropdown menu items for a user row
  const renderActionMenu = (user: User) => (
    <DropdownMenu>
      <DropdownMenuTrigger render={<Button variant="ghost" className="h-8 w-8 p-0" />}>
        <MoreHorizontal className="h-4 w-4" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <div className="text-muted-foreground px-1.5 py-1 text-xs font-medium">Actions</div>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={() => {
            navigator.clipboard.writeText(user.email);
            toast.success('Email copied to clipboard');
          }}
        >
          <Copy className="mr-2 h-4 w-4" />
          Copy Email
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => onViewProfile(user)}>
          <ExternalLink className="mr-2 h-4 w-4" />
          View Profile
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => onViewLoginHistory(user)}>
          <History className="mr-2 h-4 w-4" />
          Login History
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        {user.is_currently_locked && canManageUsers && (
          <DropdownMenuItem onClick={() => onUnlockAccount(user)} className="text-success">
            <Unlock className="mr-2 h-4 w-4" />
            Unlock Account
          </DropdownMenuItem>
        )}
        {canManageUsers && (
          <DropdownMenuItem onClick={() => onResetAttempts(user)}>
            <RotateCcw className="mr-2 h-4 w-4" />
            Reset Attempts
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );

  // Shared 2FA badge
  const render2FABadge = (enabled: boolean, compact = false) => {
    if (enabled) {
      return (
        <Badge
          className={`bg-success/10 dark:bg-success/20 text-success dark:text-success-foreground border-success/50 dark:border-success/30 border px-2 py-1 text-xs ${compact ? 'dark:bg-success/20 dark:text-success-foreground' : ''}`}
        >
          <QrCode className="mr-1 h-3 w-3" />
          Enabled
        </Badge>
      );
    }
    return (
      <Badge
        className={`bg-muted dark:bg-muted/50 text-muted-foreground dark:text-muted-foreground/80 border-muted/50 dark:border-muted/30 border px-2 py-1 text-xs ${compact ? '' : ''}`}
      >
        Disabled
      </Badge>
    );
  };

  // Shared lock time remaining
  const renderTimeRemaining = (user: User) =>
    user.is_currently_locked ? (
      <div className="text-warning flex items-center space-x-1">
        <Clock className="h-3 w-3" />
        <span>{user.lock_time_remaining ?? '—'}</span>
      </div>
    ) : (
      <span className="text-muted-foreground">Expired</span>
    );

  // Shared failed attempts display
  const renderFailedAttempts = (attempts: number | null | undefined) => (
    <div className="flex items-center space-x-2">
      <span className="text-sm font-medium">{attempts || 0}</span>
      {(attempts || 0) >= 3 && <AlertTriangle className="text-destructive h-4 w-4" />}
    </div>
  );

  if (filteredAccounts.length === 0) {
    return (
      <Card>
        <CardContent className="flex justify-center px-6 py-12">
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon">
                <Shield className="h-8 w-8" />
              </EmptyMedia>
            </EmptyHeader>
            <EmptyTitle>No Locked Accounts Found</EmptyTitle>
            <EmptyDescription>
              {searchQuery || roleFilter !== 'all' || statusFilter !== 'all'
                ? 'Try adjusting your search or filter criteria.'
                : 'There are currently no locked user accounts in the system.'}
            </EmptyDescription>
          </Empty>
        </CardContent>
      </Card>
    );
  }

  return (
    <>
      {/* Desktop Table */}
      <Card className="hidden md:block">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-12">
                  <Checkbox
                    checked={selectedAccounts.size === paginatedAccounts.length && paginatedAccounts.length > 0}
                    onCheckedChange={onToggleSelectAll}
                  />
                </TableHead>
                <TableHead>User</TableHead>
                <TableHead>Role</TableHead>
                <TableHead className="hidden lg:table-cell">2FA Status</TableHead>
                <TableHead>Lock Status</TableHead>
                <TableHead>Failed Attempts</TableHead>
                <TableHead className="hidden xl:table-cell">Locked At</TableHead>
                <TableHead className="hidden xl:table-cell">Expires At</TableHead>
                <TableHead className="hidden lg:table-cell">Time Remaining</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isBusy
                ? Array.from({ length: 5 }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell><Skeleton className="h-4 w-4" /></TableCell>
                      <TableCell>
                        <div className="flex items-center space-x-3">
                          <Skeleton className="h-8 w-8 rounded-full" />
                          <div className="space-y-2">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-3 w-48" />
                          </div>
                        </div>
                      </TableCell>
                      <TableCell><Skeleton className="h-5 w-20" /></TableCell>
                      <TableCell className="hidden lg:table-cell"><Skeleton className="h-5 w-16" /></TableCell>
                      <TableCell><Skeleton className="h-5 w-24" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-8" /></TableCell>
                      <TableCell className="hidden xl:table-cell"><Skeleton className="h-4 w-32" /></TableCell>
                      <TableCell className="hidden xl:table-cell"><Skeleton className="h-4 w-32" /></TableCell>
                      <TableCell className="hidden lg:table-cell"><Skeleton className="h-4 w-24" /></TableCell>
                      <TableCell><Skeleton className="h-8 w-8 rounded" /></TableCell>
                    </TableRow>
                  ))
                : paginatedAccounts.map((user) => (
                    <TableRow key={user.id}>
                      <TableCell>
                        <Checkbox
                          checked={selectedAccounts.has(user.id)}
                          onCheckedChange={() => onToggleAccountSelection(user.id)}
                        />
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center space-x-3">
                          <div className="bg-muted flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                            <UserIcon className="text-muted-foreground h-4 w-4" />
                          </div>
                          <div className="min-w-0">
                            <div className="truncate font-medium">{user.name}</div>
                            <div className="text-muted-foreground truncate text-sm">{user.email}</div>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge className={getRoleBadgeColor(user.role)}>{getRoleDisplayName(user.role)}</Badge>
                      </TableCell>
                      <TableCell className="hidden lg:table-cell">
                        <div className="flex items-center space-x-1">{render2FABadge(!!user.two_factor_enabled)}</div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className={getLockStatusColor(user)}>
                          {user.is_currently_locked ? 'Active Lock' : 'Expired Lock'}
                        </Badge>
                      </TableCell>
                      <TableCell>{renderFailedAttempts(user.failed_login_attempts)}</TableCell>
                      <TableCell className="hidden xl:table-cell">
                        <div className="text-sm">{formatDateTime(user.locked_at)}</div>
                      </TableCell>
                      <TableCell className="hidden xl:table-cell">
                        <div className="text-sm">{formatDateTime(user.lock_expires_at)}</div>
                      </TableCell>
                      <TableCell className="hidden lg:table-cell">
                        <div className="text-sm">{renderTimeRemaining(user)}</div>
                      </TableCell>
                      <TableCell className="text-right">{renderActionMenu(user)}</TableCell>
                    </TableRow>
                  ))}
            </TableBody>
          </Table>
        </CardContent>
        <CardFooter className="justify-end border-t px-6 py-5">
          <Pagination
            pageIndex={pageIndex}
            pageSize={pageSize}
            pageCount={pageCount}
            totalItems={filteredAccounts.length}
            onPageChange={onPageChange}
            onPageSizeChange={(size) => {
              onPageSizeChange(size);
              onPageChange(0);
            }}
          />
        </CardFooter>
      </Card>

      {/* Mobile Card View */}
      <div className="md:hidden">
        <div className="space-y-4">
          {isBusy
            ? Array.from({ length: 3 }).map((_, index) => (
                <Card key={index}>
                  <CardContent className="p-4">
                    <div className="space-y-4">
                      <div className="flex items-start justify-between">
                        <div className="flex items-center space-x-3">
                          <Skeleton className="h-10 w-10 rounded-full" />
                          <div className="space-y-2">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-3 w-48" />
                          </div>
                        </div>
                        <Skeleton className="h-8 w-8" />
                      </div>
                      <div className="space-y-2">
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-2/3" />
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))
            : paginatedAccounts.map((user) => (
                <Card key={user.id}>
                  <CardContent className="p-4">
                    <div className="space-y-4">
                      {/* Header */}
                      <div className="flex items-start justify-between">
                        <div className="flex items-center space-x-3">
                          <Checkbox
                            checked={selectedAccounts.has(user.id)}
                            onCheckedChange={() => onToggleAccountSelection(user.id)}
                          />
                          <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-full">
                            <UserIcon className="text-muted-foreground h-5 w-5" />
                          </div>
                          <div>
                            <div className="font-medium">{user.name}</div>
                            <div className="text-muted-foreground text-sm">{user.email}</div>
                          </div>
                        </div>
                        {renderActionMenu(user)}
                      </div>

                      {/* Details */}
                      <div className="space-y-2 text-sm">
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">Role</span>
                          <Badge className={getRoleBadgeColor(user.role)}>{getRoleDisplayName(user.role)}</Badge>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">2FA Status</span>
                          {render2FABadge(!!user.two_factor_enabled, true)}
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">Lock Status</span>
                          <Badge variant="outline" className={getLockStatusColor(user)}>
                            {user.is_currently_locked ? 'Active Lock' : 'Expired Lock'}
                          </Badge>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">Failed Attempts</span>
                          {renderFailedAttempts(user.failed_login_attempts)}
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">Locked At</span>
                          <span>{formatDateTime(user.locked_at)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">Expires At</span>
                          <span>{formatDateTime(user.lock_expires_at)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">Time Remaining</span>
                          {renderTimeRemaining(user)}
                        </div>
                        {user.locked_reason && (
                          <div className="bg-muted/50 mt-2 rounded-md p-2">
                            <div className="text-muted-foreground mb-1 text-xs font-medium">Lock Reason</div>
                            <div className="text-sm">{user.locked_reason}</div>
                          </div>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
        </div>
        <div className="mt-4 flex justify-center">
          <Pagination
            pageIndex={pageIndex}
            pageSize={pageSize}
            pageCount={pageCount}
            totalItems={filteredAccounts.length}
            onPageChange={onPageChange}
            onPageSizeChange={(size) => {
              onPageSizeChange(size);
              onPageChange(0);
            }}
          />
        </div>
      </div>
    </>
  );
}
