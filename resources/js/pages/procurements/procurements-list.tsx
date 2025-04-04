import { Head, Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { ColumnDef } from '@tanstack/react-table';
import { ProcurementListItem } from '@/types/blockchain';
import { SharedData } from '@/types';
import { toast } from 'sonner';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table';
import { DataTableCheckbox } from '@/components/ui/data-table';
import { PreProcurementModal } from '@/components/pre-procurement/pre-procurement-modal';
import { MarkCompleteDialog } from '@/components/procurement/mark-complete-dialog';
import AppLayout from '@/layouts/app-layout';
import { ActionButtons } from '@/components/procurements-list/action-buttons';
import { ProcurementListHeader } from '@/components/procurements-list/procurement-list-header';
import { LoadingSkeleton } from '@/components/procurements-list/loading-skeleton';
import { ErrorState } from '@/components/procurements-list/error-state';
import { EmptyState } from '@/components/procurements-list/empty-state';
import { KanbanBoard } from '@/components/procurements-list/kanban-board';
import { getBreadcrumbs, getStatusBadgeStyle, getStageBadgeStyle } from '@/lib/procurements-list-utils';
import { CalendarIcon, DownloadIcon, FileIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

interface ShowProps {
    procurements: ProcurementListItem[];
    error?: string;
}

type ViewType = 'table' | 'kanban';

export default function ProcurementsList({ procurements: initialProcurements, error: initialError }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const user = auth?.user;
    const userRole = user?.role || "guest";
    const breadcrumbs = getBreadcrumbs(userRole);

    const [procurements, setProcurements] = useState<ProcurementListItem[]>(initialProcurements || []);
    const [selectedRows, setSelectedRows] = useState<ProcurementListItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [viewType, setViewType] = useState<ViewType>('table');
    const [error, setError] = useState<string | undefined>(initialError);
    const [modalOpen, setModalOpen] = useState(false);
    const [markCompleteDialogOpen, setMarkCompleteDialogOpen] = useState(false);
    const [selectedProcurementId, setSelectedProcurementId] = useState<string>('');
    const [selectedProcurementTitle, setSelectedProcurementTitle] = useState<string>('');

    useEffect(() => {
        setViewType('table');
        if (initialError) {
            console.error('Backend error:', initialError);
            setError(initialError);
        }
    }, [initialError]);

    useEffect(() => {
        setProcurements(initialProcurements || []);
    }, [initialProcurements]);

    const exportToCSV = (selectedProcurements: ProcurementListItem[]) => {
        try {
            setLoading(true);
            const headers = ['ID', 'Title', 'Phase', 'State', 'Documents', 'Last Updated', 'Timestamp'];
            const data = selectedProcurements.map(proc => [
                proc.id,
                proc.title,
                proc.stage,
                proc.current_status,
                proc.document_count || 0,
                proc.last_updated || 'N/A',
                proc.timestamp || new Date().toISOString(),
            ]);
            const csvContent = [
                headers.join(','),
                ...data.map(row =>
                    row.map(value => {
                        if (value === null || value === undefined) return '';
                        const stringValue = String(value);
                        return stringValue.includes(',') || stringValue.includes('"')
                            ? `"${stringValue.replace(/"/g, '""')}"`
                            : stringValue;
                    }).join(',')
                ),
            ].join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const fileName = `procurements-export-${new Date().toISOString().slice(0, 10)}.csv`;
            link.setAttribute('href', url);
            link.setAttribute('download', fileName);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            toast.success(`Successfully exported ${selectedProcurements.length} procurements to CSV`, {
                description: `File: ${fileName}`,
                duration: 5000,
            });
        } catch (e) {
            console.error('Failed to export CSV:', e);
            toast.error('Failed to export data to CSV', {
                description: 'Please try again later',
                duration: 5000,
            });
            setProcurements([...procurements]);
        } finally {
            setLoading(false);
        }
    };

    const bulkActions = [
        {
            label: 'Export to CSV',
            action: () => {
                if (selectedRows.length === 0) {
                    alert('Please select at least one procurement to export.');
                    return;
                }
                exportToCSV(selectedRows);
            },
            icon: <DownloadIcon className="h-4 w-4" />,
        },
    ];

    const handleOpenPreProcurementModal = (procurement: ProcurementListItem) => {
        setSelectedProcurementId(procurement.id);
        setSelectedProcurementTitle(procurement.title);
        setModalOpen(true);
    };

    const handleOpenMarkCompleteDialog = (procurement: ProcurementListItem) => {
        setSelectedProcurementId(procurement.id);
        setSelectedProcurementTitle(procurement.title);
        setMarkCompleteDialogOpen(true);
    };

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
            cell: ({ row }) => (
                <div className="font-medium text-blue-600 dark:text-blue-400">
                    <Link href={`procurements-list/${row.getValue('id')}`} className="hover:underline">
                        {row.getValue('id')}
                    </Link>
                </div>
            ),
        },
        {
            accessorKey: 'title',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
            cell: ({ row }) => (
                <div className="max-w-[200px] truncate font-medium">
                    <Link href={`procurements-list/${row.original.id}`} className="hover:text-blue-600 hover:underline">
                        {row.getValue('title')}
                    </Link>
                </div>
            ),
        },
        {
            accessorKey: 'stage',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Stage" />,
            cell: ({ row }) => (
                <Badge variant="outline" className={getStageBadgeStyle(row.getValue('stage'))}>
                    {row.getValue('stage')}
                </Badge>
            ),
            filterFn: (row, id, value) => value.includes(row.getValue(id)),
        },
        {
            accessorKey: 'current_status',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
            cell: ({ row }) => (
                <Badge variant="outline" className={getStatusBadgeStyle(row.getValue('current_status'))}>
                    {row.getValue('current_status')}
                </Badge>
            ),
            filterFn: (row, id, value) => value.includes(row.getValue(id)),
        },
        {
            accessorKey: 'document_count',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Documents" />,
            cell: ({ row }) => (
                <div className="flex items-center gap-1">
                    <FileIcon className="h-3 w-3 text-blue-500 dark:text-blue-400" />
                    <span className="font-medium">{row.getValue('document_count')}</span>
                </div>
            ),
        },
        {
            accessorKey: 'last_updated',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Last Updated" />,
            cell: ({ row }) => (
                <div className="flex items-center gap-1">
                    <CalendarIcon className="h-3 w-3 text-gray-500 dark:text-gray-400" />
                    <span className="text-sm">{row.getValue('last_updated')}</span>
                </div>
            ),
        },
        {
            id: 'actions',
            header: ({ column }) => <DataTableColumnHeader column={column} title="Actions" className="text-right" />,
            cell: ({ row }) => (
                <ActionButtons
                    procurement={row.original}
                    variant="table"
                    onOpenPreProcurementModal={handleOpenPreProcurementModal}
                    onOpenMarkCompleteDialog={handleOpenMarkCompleteDialog}
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <ProcurementListHeader
                        userRole={userRole}
                        viewType={viewType}
                        setViewType={setViewType}
                        procurementsCount={procurements.length}
                        loading={loading}
                    />
                    <CardContent className="dark:border-t dark:border-sidebar-border">
                        {loading ? (
                            <LoadingSkeleton />
                        ) : error ? (
                            <ErrorState error={error} />
                        ) : procurements.length === 0 ? (
                            <EmptyState userRole={userRole} />
                        ) : viewType === 'table' ? (
                            <DataTable
                                columns={columns}
                                data={procurements}
                                searchColumn="title"
                                searchPlaceholder="Search procurements..."
                                onRowSelectionChange={setSelectedRows}
                                bulkActions={bulkActions}
                            />
                        ) : (
                            <KanbanBoard
                                procurements={procurements}
                                onOpenPreProcurementModal={handleOpenPreProcurementModal}
                                onOpenMarkCompleteDialog={handleOpenMarkCompleteDialog}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
            <PreProcurementModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                procurementId={selectedProcurementId}
                procurementTitle={selectedProcurementTitle}
                onComplete={() => window.location.reload()}
            />
            <MarkCompleteDialog
                open={markCompleteDialogOpen}
                onOpenChange={setMarkCompleteDialogOpen}
                procurementId={selectedProcurementId}
                procurementTitle={selectedProcurementTitle}
                onComplete={() => window.location.reload()}
            />
        </AppLayout>
    );
}