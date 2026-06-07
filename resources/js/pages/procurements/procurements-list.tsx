import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { Activity, Archive, Clock, FileText, Plus } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Can } from '@/components/auth/can';
import { HeroCard } from '@/components/hero-card';
import { PreBidConferenceDialog } from '@/components/pre-bid-conference-dialog';
import { PreProcurementDialog } from '@/components/pre-procurement-conference-dialog';
import { createColumns } from '@/components/procurements-list/columns';
import { ProcurementsDataTable } from '@/components/procurements-list/data-table';
import { type ProcurementFilterOption } from '@/components/procurements-list/procurement-filters-toolbar';
import { StatsGrid, type StatsGridItem } from '@/components/stats-grid';
import { SupplementalBidBulletinDialog } from '@/components/supplemental-bid-bulletin-dialog';
import { Button } from '@/components/ui/button';
import { useProcurementList } from '@/hooks/use-procurement-list';
import AppLayout from '@/layouts/app-layout';
import { index as adminProcurementsIndex } from '@/routes/admin/procurements';
import { index as bacChairmanProcurementsIndex } from '@/routes/bac-chairman/procurements';
import procurement from '@/routes/bac-secretariat/procurement';
import { index as bacSecretariatProcurementsIndex } from '@/routes/bac-secretariat/procurements';
import { index as hopeProcurementsIndex } from '@/routes/hope/procurements';
import { type ProcurementListItem, type SharedData, Status } from '@/types';
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
    stageOptions?: Record<string, string>;
    is_archived?: boolean;
}

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

// Transform enum options to filter options
const transformEnumOptions = (options: Record<string, string> | undefined): ProcurementFilterOption[] => {
    if (!options) return [];
    return [{ label: 'All', value: 'all' }, ...Object.entries(options).map(([value, label]) => ({ label, value }))];
};

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

export default function ProcurementsList({
    procurements: initialProcurements,
    pagination,
    error: initialError,
    stageOptions,
    is_archived: isPollArchived,
}: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.role || auth?.user?.role || 'guest';
    const breadcrumbs = getProcurementListBreadcrumbs(userRole);

    // Initialize search and stage from URL params to avoid infinite reload loop
    const initialSearchValue = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('search') || '' : '';
    const initialStageFilter = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('stage') || 'all' : 'all';

    const [searchValue, setSearchValue] = useState(initialSearchValue);
    const [stageFilter, setStageFilter] = useState(initialStageFilter);
    const [lastRefreshed, setLastRefreshed] = useState<Date>(new Date());
    const [pageIndex, setPageIndex] = useState<number>((pagination?.page ?? 1) - 1);
    const [pageSize, setPageSize] = useState<number>(pagination?.per_page ?? 10);
    const [isPolling, setIsPolling] = useState(false);

    const getProcurementsListUrl = useCallback(
        (options?: Parameters<typeof adminProcurementsIndex.url>[0]) => {
            switch (userRole) {
                case 'admin': {
                    return adminProcurementsIndex.url(options);
                }
                case 'bac_chairman': {
                    return bacChairmanProcurementsIndex.url(options);
                }
                case 'hope': {
                    return hopeProcurementsIndex.url(options);
                }
                case 'bac_secretariat':
                default: {
                    return bacSecretariatProcurementsIndex.url(options);
                }
            }
        },
        [userRole],
    );

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

    const handleFilterChange = useCallback(
        (filterType: 'search' | 'stage', value: string) => {
            router.get(
                getProcurementsListUrl({
                    mergeQuery: {
                        [filterType]: value && value !== 'all' ? value : null,
                        page: null, // Reset to first page
                        per_page: pageSize,
                    },
                }),
                {},
                {
                    replace: true,
                    preserveScroll: true,
                    preserveState: true,
                    only: ['procurements', 'pagination'],
                },
            );
        },
        [getProcurementsListUrl, pageSize],
    );

    const handlePageNavigate = useCallback(
        (nextPageIndex: number) => {
            router.get(
                getProcurementsListUrl({
                    mergeQuery: {
                        page: nextPageIndex + 1,
                        per_page: pageSize,
                    },
                }),
                {},
                {
                    replace: true,
                    preserveScroll: true,
                    preserveState: true,
                    only: ['procurements', 'pagination'],
                    onSuccess: () => setPageIndex(nextPageIndex),
                },
            );
        },
        [getProcurementsListUrl, pageSize],
    );

    const handlePageSizeChange = useCallback(
        (nextPageSize: number) => {
            router.get(
                getProcurementsListUrl({
                    mergeQuery: {
                        page: 1,
                        per_page: nextPageSize,
                    },
                }),
                {},
                {
                    replace: true,
                    preserveScroll: true,
                    preserveState: true,
                    only: ['procurements', 'pagination'],
                    onSuccess: () => {
                        setPageIndex(0);
                        setPageSize(nextPageSize);
                    },
                },
            );
        },
        [getProcurementsListUrl],
    );

    const handleRefresh = useCallback(() => {
        router.reload({
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

            // n - New procurement (BAC Secretariat only)
            if (e.key === 'n' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                if (userRole === 'bac_secretariat' && auth?.can?.manageProcurement) {
                    router.visit(procurement.initiation.index.url());
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
    }, [auth, handleRefresh, userRole]);

    const columns = createColumns({
        onOpenPreProcurementDialog: handleOpenPreProcurementDialog,
        onOpenPreBidDialog: handleOpenPreBidDialog,
        onOpenSupplementalBidBulletinDialog: handleOpenSupplementalBidBulletinDialog,
    });

    // Transform enum options to filter options
    const stageFilterOptions = stageOptions ? transformEnumOptions(stageOptions) : STAGE_FILTER_OPTIONS;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Page Header */}
                <HeroCard
                    icon={FileText}
                    title="Procurement List"
                    description={
                        <div className="flex flex-col gap-1">
                            <p>View and manage {isPollArchived ? 'archived' : 'active'} procurement items across all stages</p>
                            <p className="hidden text-xs opacity-80 sm:block">
                                Shortcuts: <kbd className="bg-muted rounded border px-1.5 py-0.5 font-mono text-[10px]">R</kbd> Refresh
                                {' · '}
                                <kbd className="bg-muted rounded border px-1.5 py-0.5 font-mono text-[10px]">/</kbd> Search
                                {!isPollArchived && userRole === 'bac_secretariat' && auth?.can?.manageProcurement && (
                                    <>
                                        {' · '}
                                        <kbd className="bg-muted rounded border px-1.5 py-0.5 font-mono text-[10px]">N</kbd> New
                                    </>
                                )}
                            </p>
                        </div>
                    }
                    actions={
                        !isPollArchived &&
                        userRole === 'bac_secretariat' && (
                            <Can permission="create procurement">
                                <Button
                                    nativeButton={false}
                                    className="shrink-0"
                                    render={<Link href={procurement.initiation.index.url()} className="flex items-center justify-center gap-2" />}
                                >
                                    <Plus />
                                    <span>New Procurement</span>
                                </Button>
                            </Can>
                        )
                    }
                />

                <ProcurementStatsSummary
                    total={procurements.length}
                    inProgress={getInProgressCount()}
                    completed={getCompletedCount()}
                    documentTotal={getTotalDocuments()}
                    userRole={userRole}
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
                    searchValue={searchValue}
                    onSearchChange={(value) => setSearchValue(value)}
                    stageValue={stageFilter}
                    onStageChange={(value) => {
                        setStageFilter(value);
                        handleFilterChange('stage', value);
                    }}
                    stageOptions={stageFilterOptions}
                    onRefresh={handleRefresh}
                    refreshDisabled={loading}
                    isRefreshing={isPolling}
                    lastRefreshed={lastRefreshed}
                    isArchived={isPollArchived}
                />
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
