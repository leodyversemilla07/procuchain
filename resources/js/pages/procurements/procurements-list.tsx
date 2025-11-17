import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { Activity, Archive, Clock, FileText, Plus } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Can } from '@/components/auth/can';
import { Card, CardContent } from '@/components/ui/card';
import { PreBidConferenceDialog } from '@/components/pre-bid-conference-dialog';
import { PreProcurementDialog } from '@/components/pre-procurement-conference-dialog';
import { createColumns } from '@/components/procurements-list/columns';
import { ProcurementsDataTable } from '@/components/procurements-list/data-table';
import { ProcurementFiltersToolbar, type ProcurementFilterOption } from '@/components/procurements-list/procurement-filters-toolbar';
import { StatsGrid, type StatsGridItem } from '@/components/stats-grid';
import { SupplementalBidBulletinDialog } from '@/components/supplemental-bid-bulletin-dialog';
import { Button } from '@/components/ui/button';
import { useProcurementList } from '@/hooks/use-procurement-list';
import AppLayout from '@/layouts/app-layout';
import { initiation as procurementInitiation } from '@/routes/bac-secretariat/procurement';
import { ProcurementListItem, SharedData, Status } from '@/types';
import { getProcurementListBreadcrumbs } from '@/utils/breadcrumbs';
import { toast } from 'sonner';

interface PaginationMeta {
    total: number;
    page: number;
    per_page: number;
}

interface ShowProps {
    procurements: ProcurementListItem[];
    pagination?: PaginationMeta;
    error?: string;
}

const STATUS_FILTER_OPTIONS: ProcurementFilterOption[] = [
    { label: 'All Status', value: 'all' },
    { label: 'Submitted', value: 'PROCUREMENT_SUBMITTED' },
    { label: 'Pre-Procurement', value: 'PRE_PROCUREMENT_SCHEDULED' },
    { label: 'Bidding Docs', value: 'BIDDING_DOCUMENTS_PREPARED' },
    { label: 'Pre-Bid Conference', value: 'PRE_BID_CONFERENCE_SCHEDULED' },
    { label: 'Bid Submission', value: 'BID_SUBMISSION_ONGOING' },
    { label: 'Bid Opening', value: 'BID_OPENING_SCHEDULED' },
    { label: 'Bid Evaluation', value: 'BID_EVALUATION_ONGOING' },
    { label: 'Post Qualification', value: 'POST_QUALIFICATION_ONGOING' },
    { label: 'Notice of Award', value: 'NOTICE_OF_AWARD_ISSUED' },
    { label: 'Notice to Proceed', value: 'NOTICE_TO_PROCEED_ISSUED' },
    { label: 'Performance Bond', value: 'PERFORMANCE_BOND_RECEIVED' },
    { label: 'Monitoring', value: 'MONITORING_ONGOING' },
    { label: 'Completed', value: 'COMPLETED' },
];

const STAGE_FILTER_OPTIONS: ProcurementFilterOption[] = [
    { label: 'All Stages', value: 'all' },
    { label: 'Initiation', value: 'PROCUREMENT_INITIATION' },
    { label: 'Pre-Procurement', value: 'PRE_PROCUREMENT_CONFERENCE' },
    { label: 'Bidding Documents', value: 'BIDDING_DOCUMENTS' },
    { label: 'Pre-Bid Conference', value: 'PRE_BID_CONFERENCE' },
    { label: 'Bid Submission', value: 'BID_SUBMISSION' },
    { label: 'Bid Opening', value: 'BID_OPENING' },
    { label: 'Bid Evaluation', value: 'BID_EVALUATION' },
    { label: 'Post Qualification', value: 'POST_QUALIFICATION' },
    { label: 'Notice of Award', value: 'NOTICE_OF_AWARD' },
    { label: 'Notice to Proceed', value: 'NOTICE_TO_PROCEED' },
    { label: 'Performance Bond', value: 'PERFORMANCE_BOND_CONTRACT_AND_PO' },
    { label: 'Monitoring', value: 'MONITORING' },
    { label: 'Completion', value: 'COMPLETION' },
];

interface ProcurementStatsSummaryProps {
    total: number;
    inProgress: number;
    completed: number;
    documentTotal: number;
    userRole?: string | null;
    className?: string;
}

const ProcurementStatsSummary = ({ total, inProgress, completed, documentTotal, userRole, className }: ProcurementStatsSummaryProps) => {
    const items: StatsGridItem[] = [
        {
            id: 'total-procurements',
            label: 'Total',
            value: total,
            icon: Archive,
            iconClassName: 'bg-muted text-muted-foreground',
        },
        {
            id: 'in-progress-procurements',
            label: 'In Progress',
            value: inProgress,
            icon: Activity,
            iconClassName: 'bg-muted text-muted-foreground',
        },
        {
            id: 'completed-procurements',
            label: 'Completed',
            value: completed,
            icon: Clock,
            iconClassName: 'bg-muted text-muted-foreground',
        },
        {
            id: 'documents-count',
            label: 'Documents',
            value: documentTotal,
            icon: FileText,
            iconClassName: 'bg-muted text-muted-foreground',
        },
    ];

    return <StatsGrid items={items} userRole={userRole} className={className} gridClassName="sm:grid-cols-2 lg:grid-cols-4" />;
};

export default function ProcurementsList({ procurements: initialProcurements, pagination, error: initialError }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.roles?.[0] || auth?.user?.role || 'guest';
    const breadcrumbs = getProcurementListBreadcrumbs(userRole);

    const [searchValue, setSearchValue] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [stageFilter, setStageFilter] = useState('all');
    const [lastRefreshed, setLastRefreshed] = useState<Date>(new Date());
    const [pageIndex, setPageIndex] = useState<number>((pagination?.page ?? 1) - 1);
    const [pageSize, setPageSize] = useState<number>(pagination?.per_page ?? 10);
    const [isPolling, setIsPolling] = useState(false);

    const isFirstSearchRun = useRef(true);

    // Polling with background refresh indicator
    usePoll(30000, {
        only: ['procurements'],
        onBefore: () => setIsPolling(true),
        onFinish: () => {
            setIsPolling(false);
            setLastRefreshed(new Date());
        },
    });

    const {
        procurements,
        loading,
        error,
        preProcurementDialogOpen,
        preBidConferenceDialogOpen,
        supplementalBidBulletinDialogOpen,
        selectedProcurement,
        setSelectedRows,
        setPreProcurementDialogOpen,
        setPreBidConferenceDialogOpen,
        setSupplementalBidBulletinDialogOpen,
        handleOpenPreProcurementDialog,
        handleOpenPreBidDialog,
        handleOpenSupplementalBidBulletinDialog,
    } = useProcurementList({ initialProcurements, initialError });

    // Initialize filters from URL params on mount
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const urlSearch = params.get('search') || '';
        const urlStatus = params.get('status') || 'all';
        const urlStage = params.get('stage') || 'all';

        setSearchValue(urlSearch);
        setStatusFilter(urlStatus);
        setStageFilter(urlStage);
    }, []);

    // Keep local pagination state in sync with server meta
    useEffect(() => {
        if (pagination) {
            setPageIndex((pagination.page ?? 1) - 1);
            setPageSize(pagination.per_page ?? 10);
        }
    }, [pagination]);

    const getCompletedCount = () => {
        return procurements.filter((p) => p.current_status === Status.COMPLETED || p.current_status === Status.COMPLETION_DOCUMENTS_UPLOADED).length;
    };

    const getInProgressCount = () => {
        return procurements.filter((p) => {
            const status = p.current_status;
            return status !== Status.COMPLETED && status !== Status.COMPLETION_DOCUMENTS_UPLOADED && status !== Status.PROCUREMENT_SUBMITTED;
        }).length;
    };

    const getTotalDocuments = () => {
        return procurements.reduce((sum, p) => {
            const count = Number(p.document_count) || 0;
            return sum + count;
        }, 0);
    };

    const handleFilterChange = useCallback((filterType: 'search' | 'status' | 'stage', value: string) => {
        const params = new URLSearchParams(window.location.search);

        if (value && value !== 'all') {
            params.set(filterType, value);
        } else {
            params.delete(filterType);
        }

        // Reset to first page when filters change
        params.delete('page');
        params.set('per_page', String(pageSize));

        router.visit(`${window.location.pathname}?${params.toString()}`, {
            only: ['procurements'],
            replace: true,
        });
    }, [pageSize]);

    const handlePageNavigate = useCallback((nextPageIndex: number) => {
        const params = new URLSearchParams(window.location.search);
        params.set('page', String(nextPageIndex + 1));
        params.set('per_page', String(pageSize));

        router.visit(`${window.location.pathname}?${params.toString()}`, {
            only: ['procurements'],
            replace: true,
            onSuccess: () => setPageIndex(nextPageIndex),
        });
    }, [pageSize]);

    const handlePageSizeChange = useCallback((nextPageSize: number) => {
        const params = new URLSearchParams(window.location.search);
        params.set('page', '1');
        params.set('per_page', String(nextPageSize));

        router.visit(`${window.location.pathname}?${params.toString()}`, {
            only: ['procurements'],
            replace: true,
            onSuccess: () => {
                setPageIndex(0);
                setPageSize(nextPageSize);
            },
        });
    }, []);

    const handleRefresh = useCallback(() => {
        router.reload({
            only: ['procurements'],
            onStart: () => {
                setIsPolling(true);
                toast.info('Refreshing procurement data...', {
                    description: 'Getting latest updates from the server',
                });
            },
            onSuccess: (page) => {
                const refreshedProcurements = page.props.procurements as ProcurementListItem[] | undefined;
                setIsPolling(false);
                setLastRefreshed(new Date());
                toast.success('Data refreshed successfully', {
                    description: `Updated ${refreshedProcurements?.length || 0} procurements`,
                });
            },
            onError: (errors) => {
                setIsPolling(false);
                toast.error('Failed to refresh data', {
                    description: Object.values(errors).flat().join(', ') || 'Please try again later',
                });
            },
        });
    }, []);

    useEffect(() => {
        if (isFirstSearchRun.current) {
            isFirstSearchRun.current = false;
            return;
        }

        const timeoutId = setTimeout(() => {
            handleFilterChange('search', searchValue);
        }, 500);

        return () => clearTimeout(timeoutId);
    }, [handleFilterChange, searchValue]);

    // Keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            // Don't trigger shortcuts when typing in inputs
            if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
                // Allow '/' to focus search from anywhere
                if (e.key === '/' && !(e.target as HTMLInputElement).placeholder?.includes('Search')) {
                    e.preventDefault();
                    const searchInput = document.querySelector('input[placeholder="Search procurements..."]') as HTMLInputElement;
                    searchInput?.focus();
                }
                return;
            }

            // r - Refresh
            if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                handleRefresh();
                toast.info('Keyboard shortcut', { description: 'Refreshing data (Press R)' });
            }

            // n - New procurement (if has permission)
            if (e.key === 'n' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                if (auth?.permissions?.includes('create procurement')) {
                    router.visit(procurementInitiation.url());
                }
            }

            // / - Focus search
            if (e.key === '/') {
                e.preventDefault();
                const searchInput = document.querySelector('input[placeholder="Search procurements..."]') as HTMLInputElement;
                searchInput?.focus();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [auth, handleRefresh]);

    const columns = createColumns({
        onOpenPreProcurementDialog: handleOpenPreProcurementDialog,
        onOpenPreBidDialog: handleOpenPreBidDialog,
        onOpenSupplementalBidBulletinDialog: handleOpenSupplementalBidBulletinDialog,
    });

    // No client-side filtering - data is already filtered by the server
    // based on URL parameters (search, status, stage)

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="w-full space-y-4 p-4 md:p-6 lg:p-8">
                <Card>
                    <CardContent className="p-4 sm:p-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3 sm:gap-4">
                                <div className="rounded-lg bg-primary/10 p-2 shrink-0">
                                    <FileText className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <h1 className="text-xl font-bold text-foreground sm:text-2xl">Procurement List</h1>
                                    <div className="mt-1 space-y-1">
                                        <p className="text-sm text-muted-foreground">View and manage procurement items across all stages</p>
                                        <p className="hidden text-xs text-muted-foreground sm:block">
                                            Shortcuts: <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">R</kbd> Refresh
                                            {' · '}
                                            <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">/</kbd> Search
                                            {auth?.permissions?.includes('create procurement') && (
                                                <>
                                                    {' · '}
                                                    <kbd className="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">N</kbd> New
                                                </>
                                            )}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <Can permission="create procurement">
                                <Button asChild className="w-full sm:w-auto shrink-0">
                                    <Link href={procurementInitiation.url()} className="flex items-center justify-center gap-2">
                                        <Plus className="h-4 w-4" />
                                        <span>New Procurement</span>
                                    </Link>
                                </Button>
                            </Can>
                        </div>
                    </CardContent>
                </Card>

                <ProcurementStatsSummary
                    total={procurements.length}
                    inProgress={getInProgressCount()}
                    completed={getCompletedCount()}
                    documentTotal={getTotalDocuments()}
                    userRole={userRole}
                    className="gap-3 sm:gap-4"
                />
                <div className="pb-4">
                    <ProcurementFiltersToolbar
                        searchValue={searchValue}
                        onSearchChange={(value) => setSearchValue(value)}
                        statusValue={statusFilter}
                        statusOptions={STATUS_FILTER_OPTIONS}
                        onStatusChange={(value) => {
                            setStatusFilter(value);
                            handleFilterChange('status', value);
                        }}
                        stageValue={stageFilter}
                        stageOptions={STAGE_FILTER_OPTIONS}
                        onStageChange={(value) => {
                            setStageFilter(value);
                            handleFilterChange('stage', value);
                        }}
                        onRefresh={handleRefresh}
                        refreshDisabled={loading}
                        isRefreshing={isPolling}
                        lastRefreshed={lastRefreshed}
                    />
                    <ProcurementsDataTable
                        columns={columns}
                        data={procurements}
                        loading={loading}
                        error={error || null}
                        userRole={userRole}
                        onRowSelectionChange={setSelectedRows}
                        onOpenPreProcurementDialog={handleOpenPreProcurementDialog}
                        onOpenPreBidDialog={handleOpenPreBidDialog}
                        onOpenSupplementalBidBulletinDialog={handleOpenSupplementalBidBulletinDialog}
                        serverTotal={pagination?.total}
                        pageIndex={pageIndex}
                        pageSize={pageSize}
                        onNavigatePage={handlePageNavigate}
                        onChangePageSize={handlePageSizeChange}
                    />
                </div>
            </div>

            {preProcurementDialogOpen && selectedProcurement && (
                <PreProcurementDialog
                    open={preProcurementDialogOpen}
                    onOpenChange={setPreProcurementDialogOpen}
                    pr_number={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
            {preBidConferenceDialogOpen && selectedProcurement && (
                <PreBidConferenceDialog
                    open={preBidConferenceDialogOpen}
                    onOpenChange={setPreBidConferenceDialogOpen}
                    pr_number={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
            {supplementalBidBulletinDialogOpen && selectedProcurement && (
                <SupplementalBidBulletinDialog
                    open={supplementalBidBulletinDialogOpen}
                    onOpenChange={setSupplementalBidBulletinDialogOpen}
                    pr_number={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
        </AppLayout>
    );
}
