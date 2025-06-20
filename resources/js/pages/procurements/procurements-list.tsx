import { useState } from 'react';
import { Head, usePage, Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Download, FileText, Activity, Clock, Archive, RefreshCw, Plus } from 'lucide-react';

import { ProcurementListItem, Status } from '@/types/blockchain';
import { SharedData } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { DataTable, DataTableCheckbox, DataTableColumnHeader } from '@/components/ui/data-table';
import { EmptyState } from '@/components/procurements-list/empty-state';
import { ErrorState } from '@/components/procurements-list/error-state';
import { KanbanBoard } from '@/components/procurements-list/kanban-board';
import { LoadingSkeleton } from '@/components/procurements-list/loading-skeleton';
import { PreBidConferenceModal } from '@/components/pre-bid-conference/pre-bid-conference-modal';
import { PreProcurementModal } from '@/components/pre-procurement-conference/pre-procurement-conference-modal';
import { SupplementalBidBulletinModal } from '@/components/supplemental-bid-bulletin/supplemental-bid-bulletin-modal';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import {
    DocumentCountCell,
    IdCell,
    LastUpdatedCell,
    StageCell,
    StatusCell,
    TitleCell,
} from '@/components/procurements-list/table-cells';
import { useProcurementList } from '@/hooks/use-procurement-list';
import { getBreadcrumbs } from '@/lib/procurements-list-utils';
import { exportProcurementsToCSV } from '@/lib/procurement-utils';

interface ShowProps {
    procurements: ProcurementListItem[];
    error?: string;
}

export default function ProcurementsList({ procurements: initialProcurements, error: initialError }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || "guest";
    const breadcrumbs = getBreadcrumbs(userRole);

    // State management
    const [searchValue, setSearchValue] = useState('');

    // Custom hook for procurement list logic
    const {
        procurements,
        selectedRows,
        loading,
        viewType,
        error,
        preProcurementModalOpen,
        preBidConferenceModalOpen,
        supplementalBidBulletinModalOpen,
        selectedProcurement,
        setSelectedRows,
        setViewType,
        setPreProcurementModalOpen,
        setPreBidConferenceModalOpen,
        setSupplementalBidBulletinModalOpen,
        handleOpenPreProcurementModal,
        handleOpenPreBidModal,
        handleOpenSupplementalBidBulletinModal,
    } = useProcurementList({ initialProcurements, initialError });

    // Helper functions for categorizing procurements
    const getCompletedCount = () => {
        return procurements.filter(p => 
            p.current_status === Status.COMPLETED || 
            p.current_status === Status.COMPLETION_DOCUMENTS_UPLOADED
        ).length;
    };

    const getInProgressCount = () => {
        return procurements.filter(p => {
            const status = p.current_status;
            return status !== Status.COMPLETED && 
                   status !== Status.COMPLETION_DOCUMENTS_UPLOADED &&
                   status !== Status.PROCUREMENT_SUBMITTED;
        }).length;
    };

    const getTotalDocuments = () => {
        return procurements.reduce((sum, p) => {
            const count = Number(p.document_count) || 0;
            return sum + count;
        }, 0);
    };

    // Table columns definition
    const columns: ColumnDef<ProcurementListItem>[] = [
        {
            id: 'select',
            header: ({ table }) => (
                <DataTableCheckbox
                    checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && 'indeterminate')}
                    onCheckedChange={value => table.toggleAllPageRowsSelected(!!value)}
                    title="Select all"
                />
            ),
            cell: ({ row }) => (
                <DataTableCheckbox
                    checked={row.getIsSelected()}
                    onCheckedChange={value => row.toggleSelected(!!value)}
                    title="Select row"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: 'id',
            header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
            cell: ({ row }) => <IdCell id={row.getValue('id')} />,
        },
        {
            accessorKey: 'title',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
            cell: ({ row }) => <TitleCell procurement={row.original} />,
        },
        {
            accessorKey: 'stage',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Stage" />,
            cell: ({ row }) => <StageCell stage={row.getValue('stage')} />,
            filterFn: (row, id, value) => value.includes(row.getValue(id)),
        },
        {
            accessorKey: 'current_status',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
            cell: ({ row }) => <StatusCell status={row.getValue('current_status')} />,
            filterFn: (row, id, value) => value.includes(row.getValue(id)),
        },
        {
            accessorKey: 'document_count',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Documents" />,
            cell: ({ row }) => <DocumentCountCell count={row.getValue('document_count')} />,
        },
        {
            accessorKey: 'last_updated',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Last Updated" />,
            cell: ({ row }) => <LastUpdatedCell date={row.getValue('last_updated')} />,
        },
        {
            id: 'actions',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Actions" className="text-right" />,
            cell: ({ row }) => (
                <ActionButtons
                    procurement={row.original}
                    variant="table"
                    onOpenPreProcurementModal={handleOpenPreProcurementModal}
                    onOpenPreBidModal={handleOpenPreBidModal}
                    onOpenSupplementalBidBulletinModal={handleOpenSupplementalBidBulletinModal}
                />
            ),
        },
    ];

    // Filter procurements based on search
    const filteredProcurements = procurements.filter(proc => {
        if (!searchValue.trim()) return true;
        const searchLower = searchValue.toLowerCase();
        return (
            proc.id.toLowerCase().includes(searchLower) ||
            proc.title.toLowerCase().includes(searchLower) ||
            proc.stage.toLowerCase().includes(searchLower) ||
            proc.current_status.toLowerCase().includes(searchLower)
        );
    });

    // Render content based on state
    const renderContent = () => {
        if (loading) return <LoadingSkeleton />;
        if (error) return <ErrorState error={error} />;
        if (procurements.length === 0) return <EmptyState userRole={userRole} />;

        return (
            <div className="w-full h-full flex flex-col">
                {viewType === 'table' ? (
                    <div className="overflow-x-auto">
                        <DataTable
                            columns={columns}
                            data={filteredProcurements}
                            searchValue={searchValue}
                            onRowSelectionChange={setSelectedRows}
                            initialSorting={[{ id: 'last_updated', desc: true }]}
                            bulkActions={selectedRows.length > 0 ? [
                                {
                                    label: 'Export to CSV',
                                    action: () => {
                                        exportProcurementsToCSV(selectedRows);
                                    },
                                    icon: <Download className="h-4 w-4" />,
                                },
                            ] : []}
                        />
                    </div>
                ) : (
                    <div className="h-full overflow-hidden">
                        <KanbanBoard
                            procurements={filteredProcurements}
                            onOpenPreProcurementModal={handleOpenPreProcurementModal}
                            onOpenPreBidModal={handleOpenPreBidModal}
                            onOpenSupplementalBidBulletinModal={handleOpenSupplementalBidBulletinModal}
                        />
                    </div>
                )}
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <div className="border-b pb-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 className="text-2xl md:text-3xl font-bold tracking-tight flex items-center">
                                <FileText className="h-6 w-6 md:h-8 md:w-8 mr-3 text-primary" />
                                Procurement List
                            </h1>
                            <p className="text-muted-foreground mt-2 text-sm md:text-base">
                                View and manage procurement items across all stages
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            {userRole === 'bac_secretariat' && (
                                <Button asChild>
                                    <Link href="/bac-secretariat/procurement/procurement-initiation" className="flex items-center space-x-2">
                                        <Plus className="h-4 w-4" />
                                        <span>New Procurement</span>
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Error Display */}
                {error && (
                    <Card className="border-destructive/50 bg-destructive/10 dark:border-destructive/20 dark:bg-destructive/5">
                        <CardContent className="p-4">
                            <ErrorState error={error} />
                        </CardContent>
                    </Card>
                )}

                {/* Statistics Cards */}
                <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Procurements</CardTitle>
                            <Archive className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{procurements.length}</div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">In Progress</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{getInProgressCount()}</div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Completed</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{getCompletedCount()}</div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Documents</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{getTotalDocuments()}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Procurements Table */}
                <Card>
                    <CardHeader className="pb-6">
                        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div className="space-y-2">
                                <CardTitle className="flex items-center space-x-2">
                                    <FileText className="h-5 w-5" />
                                    <span>Procurement Records</span>
                                </CardTitle>
                                <CardDescription>
                                    All procurement records with their current status and stage information
                                </CardDescription>
                            </div>
                            {/* View Toggle and Refresh */}
                            <div className="flex flex-col sm:flex-row items-end sm:items-center gap-2">
                                <Button
                                    onClick={() => window.location.reload()}
                                    disabled={loading}
                                    variant="outline"
                                    size="sm"
                                    className="flex items-center space-x-2 w-full sm:w-auto"
                                >
                                    <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                    <span>Refresh</span>
                                </Button>
                                <div className="flex items-center bg-muted/50 p-1 rounded-lg w-full sm:w-auto">
                                    <Button
                                        variant={viewType === 'table' ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewType('table')}
                                        className="text-xs px-3 flex-1 sm:flex-none"
                                    >
                                        Table
                                    </Button>
                                    <Button
                                        variant={viewType === 'kanban' ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewType('kanban')}
                                        className="text-xs px-3 flex-1 sm:flex-none"
                                    >
                                        Kanban
                                    </Button>
                                </div>
                            </div>
                        </div>
                        {/* Search Bar */}
                        <div className="mt-4 w-full max-w-full sm:max-w-md">
                            <div className="relative">
                                <input
                                    type="text"
                                    placeholder="Search procurements..."
                                    value={searchValue}
                                    onChange={(e) => setSearchValue(e.target.value)}
                                    className="w-full px-4 py-2 pl-10 text-sm border border-input bg-background text-foreground rounded-lg focus:ring-2 focus:ring-ring focus:border-ring transition-colors"
                                />
                                <div className="absolute left-3 top-1/2 transform -translate-y-1/2">
                                    <svg className="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="px-6 pt-0 pb-6">
                        <div className="w-full">
                            {renderContent()}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Modals */}
            {preProcurementModalOpen && selectedProcurement && (
                <PreProcurementModal
                    open={preProcurementModalOpen}
                    onOpenChange={setPreProcurementModalOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => window.location.reload()}
                />
            )}
            {preBidConferenceModalOpen && selectedProcurement && (
                <PreBidConferenceModal
                    open={preBidConferenceModalOpen}
                    onOpenChange={setPreBidConferenceModalOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => window.location.reload()}
                />
            )}
            {supplementalBidBulletinModalOpen && selectedProcurement && (
                <SupplementalBidBulletinModal
                    open={supplementalBidBulletinModalOpen}
                    onOpenChange={setSupplementalBidBulletinModalOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => window.location.reload()}
                />
            )}
        </AppLayout>
    );
}
