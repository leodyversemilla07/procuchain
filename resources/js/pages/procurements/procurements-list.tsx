import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { Activity, Archive, Clock, FileText, Plus } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Can } from '@/components/auth/can';
import { HeroCard } from '@/components/hero-card';
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
import { procurementInitiation } from '@/routes/bac-secretariat/procurement';
import { BreadcrumbItem, ProcurementListItem, SharedData, Status } from '@/types';
import { toast } from 'sonner';

interface ShowProps {
    procurements: ProcurementListItem[];
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

    return <StatsGrid items={items} userRole={userRole} className={className} gridClassName="lg:grid-cols-4" />;
};

export const getBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    switch (role) {
        case 'bac_secretariat':
            return [
                { title: 'Bids and Awards Committee Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'bac_chairman':
            return [
                { title: 'Bids and Awards Committee Chairman Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'hope':
            return [
                { title: 'Head of Procuring Entity Dashboard', href: '/hope/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'admin':
            return [
                { title: 'Admin Dashboard', href: '/admin/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        default:
            return [
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
    }
};

export default function ProcurementsList({ procurements: initialProcurements, error: initialError }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || 'guest';
    const breadcrumbs = getBreadcrumbs(userRole);

    const [searchValue, setSearchValue] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [stageFilter, setStageFilter] = useState('all');

    const isFirstSearchRun = useRef(true);

    usePoll(30000, {
        only: ['procurements'],
        onStart: () => console.log('Polling for procurement updates...'),
        onFinish: () => console.log('Procurement data updated'),
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

        router.visit(`${window.location.pathname}?${params.toString()}`, {
            only: ['procurements'],
            replace: true,
        });
    }, []);

    const handleRefresh = useCallback(() => {
        router.reload({
            only: ['procurements'],
            onStart: () => {
                toast.info('Refreshing procurement data...', {
                    description: 'Getting latest updates from the server',
                });
            },
            onProgress: (progress) => {
                if (progress && progress.percentage) {
                    console.log(`Loading: ${Math.round(progress.percentage)}%`);
                }
            },
            onSuccess: (page) => {
                const refreshedProcurements = page.props.procurements as ProcurementListItem[] | undefined;
                toast.success('Data refreshed successfully', {
                    description: `Updated ${refreshedProcurements?.length || 0} procurements`,
                });
            },
            onError: (errors) => {
                toast.error('Failed to refresh data', {
                    description: Object.values(errors).flat().join(', ') || 'Please try again later',
                });
            },
            onFinish: () => {
                console.log('Refresh operation completed');
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

    const columns = createColumns({
        onOpenPreProcurementDialog: handleOpenPreProcurementDialog,
        onOpenPreBidDialog: handleOpenPreBidDialog,
        onOpenSupplementalBidBulletinDialog: handleOpenSupplementalBidBulletinDialog,
    });

    const filteredProcurements = procurements.filter((proc) => {
        if (!searchValue.trim() && statusFilter === 'all' && stageFilter === 'all') return true;

        const searchLower = searchValue.toLowerCase();
        const matchesSearch =
            !searchValue.trim() ||
            proc.id.toLowerCase().includes(searchLower) ||
            proc.title.toLowerCase().includes(searchLower) ||
            proc.stage.toLowerCase().includes(searchLower) ||
            proc.current_status.toLowerCase().includes(searchLower);

        const matchesStatus = statusFilter === 'all' || proc.current_status === statusFilter;
        const matchesStage = stageFilter === 'all' || proc.stage === stageFilter;

        return matchesSearch && matchesStatus && matchesStage;
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="w-full space-y-4 p-4 md:p-6 lg:p-8">
                <HeroCard
                    icon={FileText}
                    title="Procurement List"
                    description="View and manage procurement items across all stages"
                    actions={
                        <Can permission="create procurement">
                            <Button asChild>
                                <Link href={procurementInitiation.url()} className="flex items-center gap-2">
                                    <Plus className="h-4 w-4" />
                                    <span>New Procurement</span>
                                </Link>
                            </Button>
                        </Can>
                    }
                />

                <ProcurementStatsSummary
                    total={procurements.length}
                    inProgress={getInProgressCount()}
                    completed={getCompletedCount()}
                    documentTotal={getTotalDocuments()}
                    userRole={userRole}
                    className="gap-3 md:gap-4"
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
                        isRefreshing={loading}
                    />
                    <ProcurementsDataTable
                        columns={columns}
                        data={filteredProcurements}
                        loading={loading}
                        error={error || null}
                        userRole={userRole}
                        searchValue={searchValue}
                        onRowSelectionChange={setSelectedRows}
                    />
                </div>
            </div>

            {preProcurementDialogOpen && selectedProcurement && (
                <PreProcurementDialog
                    open={preProcurementDialogOpen}
                    onOpenChange={setPreProcurementDialogOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
            {preBidConferenceDialogOpen && selectedProcurement && (
                <PreBidConferenceDialog
                    open={preBidConferenceDialogOpen}
                    onOpenChange={setPreBidConferenceDialogOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
            {supplementalBidBulletinDialogOpen && selectedProcurement && (
                <SupplementalBidBulletinDialog
                    open={supplementalBidBulletinDialogOpen}
                    onOpenChange={setSupplementalBidBulletinDialogOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => router.reload({ only: ['procurements'] })}
                />
            )}
        </AppLayout>
    );
}
