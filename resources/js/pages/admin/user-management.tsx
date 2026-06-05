import { Can } from '@/components/auth/can';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import users from '@/routes/admin/users';
import { Head } from '@inertiajs/react';
import { CheckCircle2, Clock, Plus, RefreshCw, Shield, UserCheck, Users } from 'lucide-react';

import BulkDeleteDialog from '@/components/admin/bulk-delete-dialog';
import CreateUserDialog from '@/components/admin/create-user-dialog';
import DeleteUserDialog from '@/components/admin/delete-user-dialog';
import EditUserDialog from '@/components/admin/edit-user-dialog';
import ResetPasswordDialog from '@/components/admin/reset-password-dialog';
import UserDetailsSheet from '@/components/admin/user-details-sheet';
import UserLoginHistorySheet from '@/components/admin/user-login-history-sheet';
import { HeroCard } from '@/components/hero-card';
import { StatsGrid } from '@/components/stats-grid';
import { dashboard } from '@/routes/admin';

import { UserManagementFilterBar } from '@/components/admin/user-management-filter-bar';
import { UserManagementTable } from '@/components/admin/user-management-table';
import { getRoleDisplayName, useUserManagement } from '@/hooks/use-user-management';

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Users', href: users.index.url() },
];

export default function AdminUserManagement() {
    const {
        filteredUsers,
        roles,
        stats,
        isCreateModalOpen,
        setIsCreateModalOpen,
        isEditModalOpen,
        setIsEditModalOpen,
        isDeleteDialogOpen,
        setIsDeleteDialogOpen,
        isBulkDeleteDialogOpen,
        setIsBulkDeleteDialogOpen,
        isDetailsDialogOpen,
        setIsDetailsDialogOpen,
        isLoginHistoryDialogOpen,
        setIsLoginHistoryDialogOpen,
        isResetPasswordDialogOpen,
        setIsResetPasswordDialogOpen,
        selectedUser,
        userToDelete,
        formData,
        setFormData,
        isLoading,
        isRefreshing,
        autoRefresh,
        toggleAutoRefresh,
        searchQuery,
        setSearchQuery,
        roleFilter,
        setRoleFilter,
        verificationFilter,
        setVerificationFilter,
        twoFactorFilter,
        setTwoFactorFilter,
        activeQuickFilter,
        handleRefresh,
        handleQuickFilter,
        handleCreateUser,
        handleEditUser,
        handleDeleteUser,
        confirmDeleteUser,
        handleBulkDelete,
        confirmBulkDelete,
        openEditModal,
        exportSelectedToCSV,
        viewDetails,
        viewLoginHistory,
        resetPassword,
        table,
        hasPermission,
    } = useUserManagement();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={Users}
                    title="User Management"
                    description="Manage system users and their roles"
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button onClick={handleRefresh} disabled={isRefreshing} variant="outline" size="sm" className="h-9 gap-1 sm:h-8 sm:gap-2">
                                {isRefreshing ? <Spinner className="size-4" /> : <RefreshCw className="h-4 w-4" />}
                                <span className="hidden sm:inline">Refresh</span>
                            </Button>
                            <Button
                                onClick={toggleAutoRefresh}
                                variant={autoRefresh ? 'default' : 'outline'}
                                size="sm"
                                className="h-9 gap-1 sm:h-8 sm:gap-2"
                            >
                                <Clock className="h-4 w-4" />
                                <span className="hidden sm:inline">Auto</span>
                            </Button>
                            <Can permission="create users">
                                <Button onClick={() => setIsCreateModalOpen(true)} size="sm" className="h-9 gap-1 sm:h-8 sm:gap-2">
                                    <Plus className="h-4 w-4" />
                                    <span className="hidden sm:inline">Add User</span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                <StatsGrid
                    items={[
                        { label: 'Total Users', value: stats.total, icon: Users, iconClassName: 'bg-primary/10 text-primary' },
                        {
                            label: `Email Verified (${stats.verifiedPercentage}%)`,
                            value: stats.verified,
                            icon: CheckCircle2,
                            iconClassName: 'bg-primary/10 text-primary',
                        },
                        {
                            label: `2FA Enabled (${stats.twoFactorPercentage}%)`,
                            value: stats.twoFactor,
                            icon: Shield,
                            iconClassName: 'bg-secondary text-secondary-foreground',
                        },
                        { label: 'Administrators', value: stats.admins, icon: UserCheck, iconClassName: 'bg-destructive/10 text-destructive' },
                    ]}
                />

                <div className="flex-1 space-y-3 sm:space-y-4">
                    <UserManagementFilterBar
                        searchQuery={searchQuery}
                        onSearchQueryChange={setSearchQuery}
                        roleFilter={roleFilter}
                        onRoleFilterChange={setRoleFilter}
                        verificationFilter={verificationFilter}
                        onVerificationFilterChange={setVerificationFilter}
                        twoFactorFilter={twoFactorFilter}
                        onTwoFactorFilterChange={setTwoFactorFilter}
                        activeQuickFilter={activeQuickFilter}
                        onQuickFilter={handleQuickFilter}
                        isRefreshing={isRefreshing}
                        autoRefresh={autoRefresh}
                        onToggleAutoRefresh={toggleAutoRefresh}
                        onRefresh={handleRefresh}
                        onCreateUser={() => setIsCreateModalOpen(true)}
                        selectedCount={table.getFilteredSelectedRowModel().rows.length}
                        totalCount={filteredUsers.length}
                        filteredCount={filteredUsers.length}
                        onExportCSV={exportSelectedToCSV}
                        onBulkDelete={handleBulkDelete}
                        onClearSelection={() => table.toggleAllPageRowsSelected(false)}
                        hasDeletePermission={hasPermission('delete users')}
                    />

                    <UserManagementTable
                        table={table}
                        isLoading={isLoading}
                        isRefreshing={isRefreshing}
                        filteredUsers={filteredUsers}
                        totalUsers={filteredUsers.length}
                        searchQuery={searchQuery}
                        roleFilter={roleFilter}
                        verificationFilter={verificationFilter}
                        twoFactorFilter={twoFactorFilter}
                        onEditUser={openEditModal}
                        onDeleteUser={handleDeleteUser}
                        onViewDetails={viewDetails}
                        onViewLoginHistory={viewLoginHistory}
                        onResetPassword={resetPassword}
                        onCreateUser={() => setIsCreateModalOpen(true)}
                    />
                </div>

                <CreateUserDialog
                    open={isCreateModalOpen}
                    onOpenChange={setIsCreateModalOpen}
                    formData={formData}
                    setFormData={setFormData}
                    roles={roles}
                    onSubmit={handleCreateUser}
                    getRoleDisplayName={getRoleDisplayName}
                />
                <EditUserDialog
                    open={isEditModalOpen}
                    onOpenChange={setIsEditModalOpen}
                    formData={formData}
                    setFormData={setFormData}
                    roles={roles}
                    onSubmit={handleEditUser}
                    getRoleDisplayName={getRoleDisplayName}
                />
                <DeleteUserDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen} user={userToDelete} onConfirm={confirmDeleteUser} />
                <BulkDeleteDialog
                    open={isBulkDeleteDialogOpen}
                    onOpenChange={setIsBulkDeleteDialogOpen}
                    selectedUsers={table.getFilteredSelectedRowModel().rows.map((row) => row.original)}
                    onConfirm={confirmBulkDelete}
                />
                <UserDetailsSheet open={isDetailsDialogOpen} onOpenChange={setIsDetailsDialogOpen} user={selectedUser ? { ...selectedUser } : null} />
                <UserLoginHistorySheet
                    open={isLoginHistoryDialogOpen}
                    onOpenChange={setIsLoginHistoryDialogOpen}
                    userId={selectedUser?.id ?? null}
                    userName={selectedUser?.name}
                />
                <ResetPasswordDialog open={isResetPasswordDialogOpen} onOpenChange={setIsResetPasswordDialogOpen} user={selectedUser} />
            </div>
        </AppLayout>
    );
}
