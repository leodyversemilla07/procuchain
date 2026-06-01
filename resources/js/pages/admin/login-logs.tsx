import BlockIpConfirmationDialog from '@/components/admin/block-ip-confirmation-dialog';
import LoginLogDetailsSheet from '@/components/admin/login-log-details-sheet';
import LoginLogFilterBar from '@/components/admin/login-log-filter-bar';
import LoginLogStats from '@/components/admin/login-log-stats';
import LoginLogTable from '@/components/admin/login-log-table';
import { HeroCard } from '@/components/hero-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useLoginLogFilters, type LoginLog, type LoginStatistics } from '@/hooks/use-login-log-filters';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import loginLogs from '@/routes/admin/login-logs';
import { type BreadcrumbItem } from '@/types';
import { Deferred, Head } from '@inertiajs/react';
import { Clock, Download, RefreshCw, Shield } from 'lucide-react';

interface Props {
    recentLogins: LoginLog[];
    statistics: LoginStatistics;
    suspiciousActivities?: LoginLog[];
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Login Logs', href: loginLogs.index.url() },
];

export default function LoginLogs({ recentLogins, statistics, suspiciousActivities, flash }: Props) {
    const {
        searchTerm,
        setSearchTerm,
        debouncedSearchTerm,
        selectedRole,
        setSelectedRole,
        selectedStatus,
        setSelectedStatus,
        selectedDeviceType,
        setSelectedDeviceType,
        selectedBrowser,
        setSelectedBrowser,
        dateRange,
        setDateRange,
        showAdvancedFilters,
        setShowAdvancedFilters,
        selectedCategory,
        setSelectedCategory,
        isLoading,
        isRefreshing,
        autoRefresh,
        setAutoRefresh,
        selectedLogs,
        isExporting,
        combinedPage,
        setCombinedPage,
        pageSize,
        setPageSize,
        combinedFilteredAndSortedLogs,
        paginatedCombinedLogs,
        totalCombinedPages,
        hasActiveFilters,
        getUniqueRoles,
        getUniqueBrowsers,
        getUniqueDeviceTypes,
        selectedLog,
        selectedLogCategory,
        isDetailsDialogOpen,
        setIsDetailsDialogOpen,
        ipToBlock,
        isBlockDialogOpen,
        setIsBlockDialogOpen,
        isBlocking,
        exportToCSV,
        handleRefresh,
        toggleSelectAll,
        toggleLogSelection,
        clearAllFilters,
        setDateRangePreset,
        handleViewDetails,
        handleBlockIpClick,
        handleBlockIpConfirm,
    } = useLoginLogFilters({ recentLogins, suspiciousActivities, flash });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Login Logs - Admin" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Header */}
                <HeroCard
                    icon={Shield}
                    title="Login Logs"
                    description="Monitor user login activities and security events"
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button onClick={handleRefresh} variant="outline" disabled={isRefreshing} size="sm">
                                {isRefreshing ? <Spinner className="size-4" /> : <RefreshCw className="h-4 w-4" />}
                                <span className="hidden sm:ml-2 sm:inline">{isRefreshing ? 'Refreshing...' : 'Refresh'}</span>
                            </Button>
                            <Button onClick={() => setAutoRefresh(!autoRefresh)} variant={autoRefresh ? 'default' : 'outline'} size="sm">
                                {autoRefresh ? (
                                    <>
                                        <Spinner className="size-4" />
                                        <span className="hidden sm:ml-2 sm:inline">Auto-refresh On</span>
                                    </>
                                ) : (
                                    <>
                                        <Clock className="h-4 w-4" />
                                        <span className="hidden sm:ml-2 sm:inline">Enable Auto-refresh</span>
                                    </>
                                )}
                            </Button>
                            <Button
                                onClick={() => exportToCSV()}
                                variant="outline"
                                disabled={isExporting || combinedFilteredAndSortedLogs.length === 0}
                                size="sm"
                            >
                                {isExporting ? (
                                    <>
                                        <Spinner className="size-4" />
                                        <span className="hidden sm:ml-2 sm:inline">Exporting...</span>
                                    </>
                                ) : (
                                    <>
                                        <Download className="h-4 w-4" />
                                        <span className="hidden sm:ml-2 sm:inline">Export CSV</span>
                                    </>
                                )}
                            </Button>
                        </div>
                    }
                />

                {/* Statistics + Trend */}
                <LoginLogStats statistics={statistics} />

                {/* Filter Bar */}
                <LoginLogFilterBar
                    searchTerm={searchTerm}
                    onSearchTermChange={setSearchTerm}
                    showAdvancedFilters={showAdvancedFilters}
                    onToggleAdvanced={() => setShowAdvancedFilters(!showAdvancedFilters)}
                    hasActiveFilters={hasActiveFilters}
                    onClearAll={clearAllFilters}
                    selectedCategory={selectedCategory}
                    onCategoryChange={setSelectedCategory}
                    selectedStatus={selectedStatus}
                    onStatusChange={setSelectedStatus}
                    selectedRole={selectedRole}
                    onRoleChange={setSelectedRole}
                    selectedDeviceType={selectedDeviceType}
                    onDeviceTypeChange={setSelectedDeviceType}
                    selectedBrowser={selectedBrowser}
                    onBrowserChange={setSelectedBrowser}
                    dateRange={dateRange}
                    onDateRangeChange={setDateRange}
                    onDateRangePreset={setDateRangePreset}
                    uniqueRoles={getUniqueRoles}
                    uniqueBrowsers={getUniqueBrowsers}
                    uniqueDeviceTypes={getUniqueDeviceTypes}
                    selectedLogsCount={selectedLogs.size}
                    onClearSelection={() => toggleSelectAll()} // clears when all selected; toggles otherwise
                    onExportSelected={() => exportToCSV()}
                    isExporting={isExporting}
                />

                {/* Login Logs Table (Deferred for suspiciousActivities) */}
                <Deferred
                    data="suspiciousActivities"
                    fallback={
                        <Card>
                            <CardContent className="space-y-4 p-6">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-1">
                                        <Skeleton className="h-6 w-48" />
                                        <Skeleton className="h-4 w-64" />
                                    </div>
                                    <Skeleton className="h-9 w-24" />
                                </div>
                                <div className="space-y-3">
                                    {Array.from({ length: 8 }).map((_, i) => (
                                        <div key={`table-skeleton-${i}`} className="flex items-center gap-4 border-b pb-3">
                                            <Skeleton className="h-4 w-4" />
                                            <Skeleton className="h-6 w-20" />
                                            <div className="flex-1 space-y-2">
                                                <Skeleton className="h-4 w-32" />
                                                <Skeleton className="h-3 w-48" />
                                            </div>
                                            <Skeleton className="h-6 w-24" />
                                            <Skeleton className="h-6 w-16" />
                                            <Skeleton className="h-4 w-28" />
                                            <Skeleton className="h-8 w-8" />
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    }
                >
                    <div className="flex-1">
                        <LoginLogTable
                            logs={paginatedCombinedLogs}
                            isLoading={isLoading}
                            isRefreshing={isRefreshing}
                            selectedLogs={selectedLogs}
                            onToggleSelectAll={toggleSelectAll}
                            onToggleLogSelection={toggleLogSelection}
                            debouncedSearchTerm={debouncedSearchTerm}
                            hasActiveFilters={hasActiveFilters}
                            onViewDetails={(log, cat) => handleViewDetails(log, cat)}
                            onBlockIpClick={handleBlockIpClick}
                            pageIndex={combinedPage - 1}
                            pageSize={pageSize}
                            pageCount={totalCombinedPages}
                            totalItems={combinedFilteredAndSortedLogs.length}
                            onPageChange={(i) => setCombinedPage(i + 1)}
                            onPageSizeChange={(size) => {
                                setPageSize(size);
                                setCombinedPage(1);
                            }}
                        />
                    </div>
                </Deferred>

                {/* Login Log Details Sheet */}
                <LoginLogDetailsSheet
                    open={isDetailsDialogOpen}
                    onOpenChange={setIsDetailsDialogOpen}
                    log={selectedLog}
                    category={selectedLogCategory}
                />

                {/* Block IP Confirmation Dialog */}
                <BlockIpConfirmationDialog
                    open={isBlockDialogOpen}
                    onOpenChange={setIsBlockDialogOpen}
                    ipAddress={ipToBlock || ''}
                    onConfirm={handleBlockIpConfirm}
                    isBlocking={isBlocking}
                />
            </div>
        </AppLayout>
    );
}
