import { Head, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Download } from 'lucide-react';
import { ProcurementListItem } from '@/types/blockchain';
import { SharedData } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { DataTableCheckbox } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table';
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
import { useState, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface ShowProps {
    procurements: ProcurementListItem[];
    error?: string;
}

const useTableColumns = (
    onOpenPreProcurementModal: (procurement: ProcurementListItem) => void,
    onOpenPreBidModal: (procurement: ProcurementListItem) => void,
    onOpenSupplementalBidBulletinModal: (procurement: ProcurementListItem) => void,
): ColumnDef<ProcurementListItem>[] => {
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
                    onOpenPreProcurementModal={onOpenPreProcurementModal}
                    onOpenPreBidModal={onOpenPreBidModal}
                    onOpenSupplementalBidBulletinModal={onOpenSupplementalBidBulletinModal}
                />
            ),
        },
    ];
    return columns;
};

interface ProcurementsContentProps {
    loading: boolean;
    error?: string;
    procurements: ProcurementListItem[];
    viewType: 'table' | 'kanban';
    selectedRows: ProcurementListItem[];
    onSelectedRowsChange: (rows: ProcurementListItem[]) => void;
    columns: ColumnDef<ProcurementListItem>[];
    userRole: string;
    searchValue: string;
    onOpenPreProcurementModal: (procurement: ProcurementListItem) => void;
    onOpenPreBidModal: (procurement: ProcurementListItem) => void;
    onOpenSupplementalBidBulletinModal: (procurement: ProcurementListItem) => void;
}

const ProcurementsContent = ({
    loading,
    error,
    procurements,
    viewType,
    selectedRows,
    onSelectedRowsChange,
    columns,
    userRole,
    searchValue,
    onOpenPreProcurementModal,
    onOpenPreBidModal,
    onOpenSupplementalBidBulletinModal,
}: ProcurementsContentProps) => {
    if (loading) return <LoadingSkeleton />;
    if (error) return <ErrorState error={error} />;
    if (procurements.length === 0) return <EmptyState userRole={userRole} />;

    return (
        <div className="w-full h-full flex flex-col">
            {/* Desktop view - Shows table or kanban based on viewType */}
            <div className="hidden lg:block h-full">
                {viewType === 'table' ? (
                    <div className="overflow-x-auto">
                        <DataTable
                            columns={columns}
                            data={procurements}
                            searchValue={searchValue}
                            onRowSelectionChange={onSelectedRowsChange}
                            initialSorting={[
                                {
                                    id: 'last_updated',
                                    desc: true
                                }
                            ]}
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
                            procurements={procurements}
                            onOpenPreProcurementModal={onOpenPreProcurementModal}
                            onOpenPreBidModal={onOpenPreBidModal}
                            onOpenSupplementalBidBulletinModal={onOpenSupplementalBidBulletinModal}
                        />
                    </div>
                )}
            </div>
            {/* Mobile view - Always shows Kanban */}
            <div className="block lg:hidden h-full overflow-hidden">
                <KanbanBoard
                    procurements={procurements}
                    onOpenPreProcurementModal={onOpenPreProcurementModal}
                    onOpenPreBidModal={onOpenPreBidModal}
                    onOpenSupplementalBidBulletinModal={onOpenSupplementalBidBulletinModal}
                />
            </div>
        </div>
    );
};

export default function ProcurementsList({ procurements: initialProcurements, error: initialError }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || "guest";
    const breadcrumbs = getBreadcrumbs(userRole);
    const [searchValue, setSearchValue] = useState('');
    const [isPageLoaded, setIsPageLoaded] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => {
            setIsPageLoaded(true);
        }, 100);
        return () => clearTimeout(timer);
    }, []);

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

    useEffect(() => {
        const mq: MediaQueryList = window.matchMedia('(max-width:1024px)');
        setViewType(mq.matches ? 'kanban' : 'table');
        const listener = (e: MediaQueryListEvent) => {
            setViewType(e.matches ? 'kanban' : 'table');
        };
        mq.addEventListener('change', listener);
        return () => mq.removeEventListener('change', listener);
    }, [setViewType]);

    const columns = useTableColumns(handleOpenPreProcurementModal, handleOpenPreBidModal, handleOpenSupplementalBidBulletinModal);

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div
                className={cn(
                    "flex h-full flex-1 flex-col gap-2 sm:gap-3 md:gap-4 p-2 sm:p-3 md:p-4 transition-opacity duration-300",
                    isPageLoaded ? "opacity-100" : "opacity-0"
                )}
            >
                <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-sm overflow-hidden
                               rounded-lg sm:rounded-xl transition-all duration-200 hover:shadow-md">
                    <ProcurementListHeader
                        userRole={userRole}
                        viewType={viewType}
                        setViewType={setViewType}
                        procurementsCount={filteredProcurements.length}
                        loading={loading}
                        searchValue={searchValue}
                        onSearchChange={setSearchValue}
                    />
                    <CardContent className="dark:border-t dark:border-sidebar-border p-2 sm:p-3 md:p-4 overflow-hidden">
                        <div className={viewType === 'table' ? 'overflow-x-auto w-full' : ''}>
                            <ProcurementsContent
                                loading={loading}
                                error={error}
                                procurements={filteredProcurements}
                                viewType={viewType}
                                selectedRows={selectedRows}
                                onSelectedRowsChange={setSelectedRows}
                                columns={columns}
                                userRole={userRole}
                                searchValue={searchValue}
                                onOpenPreProcurementModal={handleOpenPreProcurementModal}
                                onOpenPreBidModal={handleOpenPreBidModal}
                                onOpenSupplementalBidBulletinModal={handleOpenSupplementalBidBulletinModal}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

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
