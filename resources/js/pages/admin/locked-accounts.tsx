import UserDetailsSheet from '@/components/admin/user-details-sheet';
import UserLoginHistorySheet from '@/components/admin/user-login-history-sheet';
import LockedAccountsFilterBar from '@/components/admin/locked-accounts-filter-bar';
import LockedAccountsTable from '@/components/admin/locked-accounts-table';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useLockedAccounts, breadcrumbs } from '@/hooks/use-locked-accounts';
import AppLayout from '@/layouts/app-layout';
import {
  AlertTriangle,
  Clock,
  Download,
  RefreshCw,
  RotateCcw,
  Shield,
  ShieldOff,
  Unlock,
} from 'lucide-react';
import { Head } from '@inertiajs/react';

export default function AdminLockedAccounts() {
  const {
    lockedAccounts,
    flash,
    filteredAccounts,
    paginatedAccounts,
    searchQuery,
    setSearchQuery,
    roleFilter,
    setRoleFilter,
    statusFilter,
    setStatusFilter,
    pageIndex,
    setPageIndex,
    pageSize,
    setPageSize,
    pageCount,
    isLoading,
    isRefreshing,
    autoRefresh,
    setAutoRefresh,
    selectedAccounts,
    isExporting,
    isUnlockDialogOpen,
    setIsUnlockDialogOpen,
    isResetDialogOpen,
    setIsResetDialogOpen,
    isProfileDialogOpen,
    setIsProfileDialogOpen,
    isLoginHistoryDialogOpen,
    setIsLoginHistoryDialogOpen,
    selectedUser,
    setSelectedUser,
    hasPermission,
    handleRefresh,
    exportToCSV,
    toggleSelectAll,
    toggleAccountSelection,
    handleBulkUnlock,
    handleBulkResetAttempts,
    handleUnlockAccount,
    handleResetAttempts,
    confirmUnlockAccount,
    confirmResetAttempts,
  } = useLockedAccounts();

  const canManageUsers = hasPermission('manage users');

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Locked Accounts - Admin" />
      <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
        {/* Header Section */}
        <HeroCard
          icon={Shield}
          title="Locked Accounts"
          description="Manage user accounts that have been locked due to security reasons"
          actions={
            <div className="flex flex-wrap items-center gap-2">
              <Button onClick={handleRefresh} disabled={isRefreshing} variant="outline" className="flex items-center gap-2">
                {isRefreshing ? <Spinner className="size-4" /> : <RefreshCw className="h-4 w-4" />}
                <span className="hidden sm:inline">Refresh</span>
              </Button>
              <Button
                onClick={() => setAutoRefresh(!autoRefresh)}
                variant={autoRefresh ? 'default' : 'outline'}
                className="flex items-center gap-2"
              >
                <Clock className="h-4 w-4" />
                <span className="hidden sm:inline">Auto</span>
              </Button>
              <Button onClick={exportToCSV} disabled={isExporting} variant="outline" className="flex items-center gap-2">
                <Download className={`h-4 w-4 ${isExporting ? 'animate-pulse' : ''}`} />
                <span className="hidden sm:inline">Export</span>
              </Button>
              {selectedAccounts.size > 0 && canManageUsers && (
                <>
                  <Button onClick={handleBulkUnlock} variant="default" className="flex items-center gap-2">
                    <Unlock className="h-4 w-4" />
                    <span className="hidden sm:inline">Unlock ({selectedAccounts.size})</span>
                    <span className="sm:hidden">{selectedAccounts.size}</span>
                  </Button>
                  <Button onClick={handleBulkResetAttempts} variant="outline" className="flex items-center gap-2">
                    <RotateCcw className="h-4 w-4" />
                    <span className="hidden sm:inline">Reset ({selectedAccounts.size})</span>
                  </Button>
                </>
              )}
            </div>
          }
        />

        {/* Search and Filter Section */}
        <LockedAccountsFilterBar
          searchQuery={searchQuery}
          onSearchQueryChange={setSearchQuery}
          roleFilter={roleFilter}
          onRoleFilterChange={setRoleFilter}
          statusFilter={statusFilter}
          onStatusFilterChange={setStatusFilter}
          filteredCount={filteredAccounts.length}
          totalCount={lockedAccounts.length}
          selectedCount={selectedAccounts.size}
        />

        {/* Error Display */}
        {flash.error && (
          <Card className="border-destructive/50 bg-destructive/10">
            <CardContent className="p-4">
              <div className="text-destructive flex items-center gap-2">
                <AlertTriangle className="h-5 w-5" />
                <span>{flash.error}</span>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Statistics Cards */}
        <StatsGrid
          items={[
            {
              label: 'Total Locked Accounts',
              value: filteredAccounts.length,
              icon: ShieldOff,
              iconClassName: 'bg-destructive/10 text-destructive',
            },
            {
              label: 'Currently Locked',
              value: filteredAccounts.filter((user) => user.is_currently_locked).length,
              icon: AlertTriangle,
              iconClassName: 'bg-warning/10 text-warning',
            },
            {
              label: 'Total Failed Attempts',
              value: filteredAccounts.reduce((sum, user) => sum + (user.failed_login_attempts || 0), 0),
              icon: Clock,
              iconClassName: 'bg-muted/50 text-muted-foreground',
            },
          ]}
        />

        {/* Lock Activity Insights */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center justify-between text-base font-medium">
              <span>Lock Activity Insights</span>
              <Shield className="text-muted-foreground h-5 w-5" />
            </CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-2">
              <div className="text-muted-foreground text-sm">High-Risk Accounts</div>
              <div className="flex items-baseline gap-2">
                <span className="text-2xl font-bold">
                  {filteredAccounts.filter((u) => (u.failed_login_attempts || 0) >= 3).length}
                </span>
                <Badge variant="destructive" className="text-xs">3+ attempts</Badge>
              </div>
            </div>
            <div className="space-y-2">
              <div className="text-muted-foreground text-sm">Accounts with 2FA</div>
              <div className="flex items-baseline gap-2">
                <span className="text-2xl font-bold">{filteredAccounts.filter((u) => u.two_factor_enabled).length}</span>
                <Badge variant="outline" className="bg-success/10 text-success border-success/50 text-xs">Protected</Badge>
              </div>
            </div>
            <div className="space-y-2">
              <div className="text-muted-foreground text-sm">Expiring Soon</div>
              <div className="flex items-baseline gap-2">
                <span className="text-2xl font-bold">
                  {filteredAccounts.filter((u) => {
                    if (!u.is_currently_locked || !u.lock_time_remaining) return false;
                    return u.lock_time_remaining.includes('minute') || u.lock_time_remaining.includes('second');
                  }).length}
                </span>
                <Badge variant="outline" className="text-xs">{'<'}1 hour</Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Locked Accounts Table */}
        <LockedAccountsTable
          filteredAccounts={filteredAccounts}
          paginatedAccounts={paginatedAccounts}
          isLoading={isLoading}
          isRefreshing={isRefreshing}
          selectedAccounts={selectedAccounts}
          pageIndex={pageIndex}
          pageSize={pageSize}
          pageCount={pageCount}
          searchQuery={searchQuery}
          roleFilter={roleFilter}
          statusFilter={statusFilter}
          onToggleSelectAll={toggleSelectAll}
          onToggleAccountSelection={toggleAccountSelection}
          onPageChange={setPageIndex}
          onPageSizeChange={setPageSize}
          onUnlockAccount={handleUnlockAccount}
          onResetAttempts={handleResetAttempts}
          onViewProfile={(user) => {
            setSelectedUser(user);
            setIsProfileDialogOpen(true);
          }}
          onViewLoginHistory={(user) => {
            setSelectedUser(user);
            setIsLoginHistoryDialogOpen(true);
          }}
          canManageUsers={canManageUsers}
        />
      </div>

      {/* Unlock Account Dialog */}
      <AlertDialog open={isUnlockDialogOpen} onOpenChange={setIsUnlockDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Unlock Account</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to unlock the account for <strong>{selectedUser?.name}</strong>? This will immediately allow them to
              log in again and reset their failed login attempts.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmUnlockAccount} className="bg-success text-success-foreground hover:bg-success/90">
              Unlock Account
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Reset Attempts Dialog */}
      <AlertDialog open={isResetDialogOpen} onOpenChange={setIsResetDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Reset Failed Login Attempts</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to reset the failed login attempts for <strong>{selectedUser?.name}</strong>? This will set their
              failed login attempts back to 0.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmResetAttempts}>Reset Attempts</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* User Details Sheet */}
      <UserDetailsSheet open={isProfileDialogOpen} onOpenChange={setIsProfileDialogOpen} user={selectedUser} />

      {/* Login History Sheet */}
      <UserLoginHistorySheet
        open={isLoginHistoryDialogOpen}
        onOpenChange={setIsLoginHistoryDialogOpen}
        userId={selectedUser?.id ?? null}
        userName={selectedUser?.name}
      />
    </AppLayout>
  );
}
