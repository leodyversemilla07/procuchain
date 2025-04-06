import { Head, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ProcurementListItem } from '@/types/blockchain';
import { SharedData } from '@/types';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table';
import { DataTableCheckbox } from '@/components/ui/data-table';
import { PreProcurementModal } from '@/components/pre-procurement-conference/pre-procurement-conference-modal';
import { PreBidConferenceModal } from '@/components/pre-bid-conference/pre-bid-conference-modal';
import { MarkCompleteDialog } from '@/components/procurement/mark-complete-dialog';
import AppLayout from '@/layouts/app-layout';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import { ProcurementListHeader } from '@/components/procurements-list/procurement-list-header';
import { LoadingSkeleton } from '@/components/procurements-list/loading-skeleton';
import { ErrorState } from '@/components/procurements-list/error-state';
import { EmptyState } from '@/components/procurements-list/empty-state';
import { KanbanBoard } from '@/components/procurements-list/kanban-board';
import { getBreadcrumbs } from '@/lib/procurements-list-utils';
import { exportProcurementsToCSV } from '@/lib/procurement-utils';
import { useProcurementList } from '@/hooks/use-procurement-list';
import { Card, CardContent } from '@/components/ui/card';
import { Download } from 'lucide-react';
import {
    IdCell,
    TitleCell,
    StageCell,
    StatusCell,
    DocumentCountCell,
    LastUpdatedCell,
} from '@/components/procurements-list/table-cells';

interface ShowProps {
    procurements: ProcurementListItem[];
    error?: string;
}

const useTableColumns = (
    onOpenPreProcurementModal: (procurement: ProcurementListItem) => void,
    onOpenPreBidModal: (procurement: ProcurementListItem) => void,
    onOpenMarkCompleteDialog: (procurement: ProcurementListItem) => void
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
                    onOpenMarkCompleteDialog={onOpenMarkCompleteDialog}
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
    onOpenPreProcurementModal: (procurement: ProcurementListItem) => void;
    onOpenPreBidModal: (procurement: ProcurementListItem) => void;
    onOpenMarkCompleteDialog: (procurement: ProcurementListItem) => void;
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
    onOpenPreProcurementModal,
    onOpenPreBidModal,
    onOpenMarkCompleteDialog,
}: ProcurementsContentProps) => {
    if (loading) return <LoadingSkeleton />;
    if (error) return <ErrorState error={error} />;
    if (procurements.length === 0) return <EmptyState userRole={userRole} />;
    
    return viewType === 'table' ? (
        <DataTable
            columns={columns}
            data={procurements}
            searchColumn="title"
            searchPlaceholder="Search procurements..."
            onRowSelectionChange={onSelectedRowsChange}
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
    ) : (
        <KanbanBoard
            procurements={procurements}
            onOpenPreProcurementModal={onOpenPreProcurementModal}
            onOpenPreBidModal={onOpenPreBidModal}
            onOpenMarkCompleteDialog={onOpenMarkCompleteDialog}
        />
    );
};

export default function ProcurementsList({ procurements: initialProcurements, error: initialError }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || "guest";
    const breadcrumbs = getBreadcrumbs(userRole);

    const {
        procurements,
        selectedRows,
        loading,
        viewType,
        error,
        modalOpen,
        preBidModalOpen,
        markCompleteDialogOpen,
        selectedProcurement,
        setSelectedRows,
        setViewType,
        setModalOpen,
        setPreBidModalOpen,
        setMarkCompleteDialogOpen,
        handleOpenPreProcurementModal,
        handleOpenPreBidModal,
        handleOpenMarkCompleteDialog,
    } = useProcurementList({ initialProcurements, initialError });

    const columns = useTableColumns(handleOpenPreProcurementModal, handleOpenPreBidModal, handleOpenMarkCompleteDialog);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="flex h-full flex-1 flex-col gap-4 p-2 sm:p-4">
                <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-sm overflow-hidden">
                    <ProcurementListHeader
                        userRole={userRole}
                        viewType={viewType}
                        setViewType={setViewType}
                        procurementsCount={procurements.length}
                        loading={loading}
                    />
                    <CardContent className="dark:border-t dark:border-sidebar-border p-0 sm:p-4">
                        <div className="overflow-x-auto">
                            <ProcurementsContent
                                loading={loading}
                                error={error}
                                procurements={procurements}
                                viewType={viewType}
                                selectedRows={selectedRows}
                                onSelectedRowsChange={setSelectedRows}
                                columns={columns}
                                userRole={userRole}
                                onOpenPreProcurementModal={handleOpenPreProcurementModal}
                                onOpenPreBidModal={handleOpenPreBidModal}
                                onOpenMarkCompleteDialog={handleOpenMarkCompleteDialog}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
            <PreProcurementModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                procurementId={selectedProcurement.id}
                procurementTitle={selectedProcurement.title}
                onComplete={() => window.location.reload()}
            />
            <PreBidConferenceModal
                open={preBidModalOpen}
                onOpenChange={setPreBidModalOpen}
                procurementId={selectedProcurement.id}
                procurementTitle={selectedProcurement.title}
                onComplete={() => window.location.reload()}
            />
            <MarkCompleteDialog
                open={markCompleteDialogOpen}
                onOpenChange={setMarkCompleteDialogOpen}
                procurementId={selectedProcurement.id}
                procurementTitle={selectedProcurement.title}
                onComplete={() => window.location.reload()}
            />
        </AppLayout>
    );
}