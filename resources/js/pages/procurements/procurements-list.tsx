import { useState, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Download } from 'lucide-react';

import { ProcurementListItem } from '@/types/blockchain';
import { SharedData } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable, DataTableCheckbox, DataTableColumnHeader } from '@/components/ui/data-table';
import { EmptyState } from '@/components/procurements-list/empty-state';
import { ErrorState } from '@/components/procurements-list/error-state';
import { KanbanBoard } from '@/components/procurements-list/kanban-board';
import { LoadingSkeleton } from '@/components/procurements-list/loading-skeleton';
import { PreBidConferenceModal } from '@/components/pre-bid-conference/pre-bid-conference-modal';
import { PreProcurementModal } from '@/components/pre-procurement-conference/pre-procurement-conference-modal';
import { SupplementalBidBulletinModal } from '@/components/supplemental-bid-bulletin/supplemental-bid-bulletin-modal';
import { ProcurementListHeader } from '@/components/procurements-list/procurement-list-header';
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
import { cn } from '@/lib/utils';

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
    const [isPageLoaded, setIsPageLoaded] = useState(false);

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

    // Page load effect
    useEffect(() => {
        const timer = setTimeout(() => {
            setIsPageLoaded(true);
        }, 100);
        return () => clearTimeout(timer);
    }, []);

    // Responsive view type effect
    useEffect(() => {
        const mq: MediaQueryList = window.matchMedia('(max-width:1024px)');
        setViewType(mq.matches ? 'kanban' : 'table');
        const listener = (e: MediaQueryListEvent) => {
            setViewType(e.matches ? 'kanban' : 'table');
        };
        mq.addEventListener('change', listener);
        return () => mq.removeEventListener('change', listener);
    }, [setViewType]);

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
                <div className="hidden lg:block h-full">
                    {viewType === 'table' ? (
                        <div className="overflow-x-auto">
                            <DataTable
                                columns={columns}
                                data={filteredProcurements}
                                searchValue={searchValue}
                                onRowSelectionChange={setSelectedRows}
                                initialSorting={[{ id: 'last_updated', desc: true }]}
                                bulkActions={[
                                    {
                                        label: 'Export to CSV',
                                        action: () => {
                                            if (selectedRows.length === 0) {
                                                alert('Please select at least one procurement to export.');
                                                return;
                                            }
                                            exportProcurementsToCSV(selectedRows);
                                        },
                                        icon: <Download className="h-4 w-4" />,
                                    },
                                ]}
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
                <div className="block lg:hidden h-full overflow-hidden">
                    <KanbanBoard
                        procurements={filteredProcurements}
                        onOpenPreProcurementModal={handleOpenPreProcurementModal}
                        onOpenPreBidModal={handleOpenPreBidModal}
                        onOpenSupplementalBidBulletinModal={handleOpenSupplementalBidBulletinModal}
                    />
                </div>
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div
                className={cn(
                    "flex h-full flex-1 flex-col space-y-6 p-4 md:p-6 lg:p-8 transition-opacity duration-300",
                    isPageLoaded ? "opacity-100" : "opacity-0"
                )}
            >
                <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-sm overflow-hidden
                               rounded-lg sm:rounded-xl transition-all duration-200 hover:shadow-md
                               w-full max-w-full">
                    <ProcurementListHeader
                        userRole={userRole}
                        viewType={viewType}
                        setViewType={setViewType}
                        procurementsCount={filteredProcurements.length}
                        loading={loading}
                        searchValue={searchValue}
                        onSearchChange={setSearchValue}
                    />
                    <CardContent className="p-3 sm:p-4 md:p-5 overflow-hidden w-full">
                        <div className={viewType === 'table' ? 'overflow-x-auto w-full max-w-full' : 'w-full'}>
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
