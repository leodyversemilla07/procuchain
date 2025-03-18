import { Head } from '@inertiajs/react';
import {
    FileTextIcon,
    FileIcon,
    CalendarIcon,
    CheckCircleIcon,
    AlertCircleIcon,
    ClockIcon,
    PlusIcon,
    FileEditIcon,
    ShareIcon,
    DownloadIcon,
    CheckIcon,
    LayersIcon,
    BarChart4Icon,
    ExternalLinkIcon,
    Table2Icon,
    FileUpIcon, // Import for file upload icon
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';

// Layouts
import AppLayout from '@/layouts/app-layout';

// Components - UI
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Skeleton } from "@/components/ui/skeleton";

// Data Table Components
import { DataTable } from "@/components/ui/data-table";
import { DataTableColumnHeader } from "@/components/ui/data-table";
import { DataTableCheckbox } from "@/components/ui/data-table";

import { PreProcurementModal } from '@/components/pre-procurement/pre-procurement-modal';

// Types
import { type BreadcrumbItem } from '@/types';
import { ColumnDef } from "@tanstack/react-table";
import { ProcurementListItem, PhaseIdentifier, ProcurementState } from '@/types/blockchain';
import { toast } from "sonner";


// Define the props interface
interface ShowProps {
    procurements: ProcurementListItem[];
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/bac-secretariat/dashboard',
    },
    {
        title: 'Procurement List',
        href: '#',
    },
];

// Define view types
type ViewType = 'table' | 'kanban';

export default function ProcuremenstList({ procurements: initialProcurements, error: initialError }: ShowProps) {
    const [procurements, setProcurements] = useState<ProcurementListItem[]>(initialProcurements || []);
    const [selectedRows, setSelectedRows] = useState<ProcurementListItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [viewType, setViewType] = useState<ViewType>('table');
    const [error, setError] = useState<string | undefined>(initialError);

    const [modalOpen, setModalOpen] = useState(false);

    useEffect(() => {
        setViewType('table');

        if (initialError) {
            console.error("Backend error:", initialError);
            setError(initialError);
        }
    }, [initialError]);

    // Add this useEffect to update procurements state when initialProcurements changes
    useEffect(() => {
        setProcurements(initialProcurements || []);
    }, [initialProcurements]);

    // Add this function to your component
    const exportToCSV = (selectedProcurements: ProcurementListItem[]) => {
        try {
            setLoading(true); // Use setLoading to indicate export operation started

            const headers = [
                'ID',
                'Title',
                'Phase',
                'State',
                'Documents',
                'Last Updated',
                'Timestamp'
            ];

            // Map data to arrays of values
            const data = selectedProcurements.map(proc => [
                proc.id,
                proc.title,
                proc.phase_identifier,
                proc.current_state,
                // Fix: Use correct property names from the ProcurementListItem type
                proc.document_count || 0, // Default to 0 if undefined
                proc.last_updated || 'N/A', // Default to 'N/A' if undefined
                proc.timestamp || new Date().toISOString() // Default to current time if undefined
            ]);

            // Create CSV content
            const csvContent = [
                headers.join(','),
                ...data.map(row => row.map(value => {
                    // Handle values with commas or quotes by wrapping in quotes
                    if (value === null || value === undefined) return '';
                    const stringValue = String(value);
                    return stringValue.includes(',') || stringValue.includes('"')
                        ? `"${stringValue.replace(/"/g, '""')}"`
                        : stringValue;
                }).join(','))
            ].join('\n');

            // Create a Blob with the CSV data
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });

            // Create a temporary URL for the blob
            const url = URL.createObjectURL(blob);

            // Create a link element
            const link = document.createElement('a');

            // Set link properties
            const fileName = `procurements-export-${new Date().toISOString().slice(0, 10)}.csv`;
            link.setAttribute('href', url);
            link.setAttribute('download', fileName);
            link.style.visibility = 'hidden';

            // Add to document, click and remove
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Clean up by revoking the object URL
            URL.revokeObjectURL(url);

            // Show success message with toast
            toast.success(`Successfully exported ${selectedProcurements.length} procurements to CSV`, {
                description: `File: ${fileName}`,
                duration: 5000,
                icon: <DownloadIcon className="h-4 w-4" />,
            });

        } catch (e) {
            console.error("Failed to export CSV:", e);
            toast.error("Failed to export data to CSV", {
                description: "Please try again later",
                duration: 5000,
                icon: <AlertCircleIcon className="h-4 w-4" />,
            });
            // Use setProcurements to restore original state in case of error
            setProcurements([...procurements]);
        } finally {
            setLoading(false); // Use setLoading to indicate operation completed
        }
    };

    const bulkActions = [
        {
            label: "Export to CSV",
            action: () => {
                if (selectedRows.length === 0) { // Directly use selectedRows here
                    alert("Please select at least one procurement to export.");
                    return;
                }
                exportToCSV(selectedRows);
            },
            icon: <DownloadIcon className="h-4 w-4" />
        },
    ];

    // State badge styling function with more refined styling based on ProcurementState
    const getStateBadgeStyle = (state: ProcurementState): string => {
        switch (state) {
            case 'PR Submitted':
                return 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900';
            case 'Pre-Procurement Completed':
                return 'bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 dark:bg-green-950/40 dark:text-green-300 dark:border-green-900';
            case 'Bid Invitation Published':
                return 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900';
            case 'Bids Opened':
                return 'bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900';
            case 'Bids Evaluated':
                return 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900';
            case 'Post-Qualification Verified':
                return 'bg-violet-50 text-violet-700 border border-violet-200 hover:bg-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-900';
            case 'Resolution Recorded':
                return 'bg-teal-50 text-teal-700 border border-teal-200 hover:bg-teal-100 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-900';
            case 'Awarded':
                return 'bg-pink-50 text-pink-700 border border-pink-200 hover:bg-pink-100 dark:bg-pink-950/40 dark:text-pink-300 dark:border-pink-900';
            case 'Performance Bond Recorded':
                return 'bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-900';
            case 'Contract And PO Recorded':
                return 'bg-lime-50 text-lime-700 border border-lime-200 hover:bg-lime-100 dark:bg-lime-950/40 dark:text-lime-300 dark:border-lime-900';
            case 'NTP Recorded':
                return 'bg-cyan-50 text-cyan-700 border border-cyan-200 hover:bg-cyan-100 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-900';
            case 'Monitoring':
                return 'bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
            default:
                return 'bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-gray-950 dark:text-gray-300 dark:border-gray-800';
        }
    };

    // Phase badge styling function with more refined styling based on PhaseIdentifier
    const getPhaseBadgeStyle = (phase: PhaseIdentifier): string => {
        switch (phase) {
            case 'PR Initiation':
                return 'bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800';
            case 'Pre-Procurement':
                return 'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800';
            case 'Bid Invitation':
                return 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800';
            case 'Bid Opening':
                return 'bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800';
            case 'Bid Evaluation':
                return 'bg-cyan-50 text-cyan-700 border border-cyan-200 dark:bg-cyan-900/30 dark:text-cyan-300 dark:border-cyan-800';
            case 'Post-Qualification':
                return 'bg-teal-50 text-teal-700 border border-teal-200 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-800';
            case 'BAC Resolution':
                return 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800';
            case 'Notice Of Award':
                return 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800';
            case 'Performance Bond':
                return 'bg-lime-50 text-lime-700 border border-lime-200 dark:bg-lime-900/30 dark:text-lime-300 dark:border-lime-800';
            case 'Contract And PO':
                return 'bg-yellow-50 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800';
            case 'Notice To Proceeed':
                return 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800';
            case 'Monitoring':
                return 'bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800';
            default:
                return 'bg-gray-50 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
        }
    };

    // Define table columns with checkbox column and improved styling
    const columns: ColumnDef<ProcurementListItem>[] = [
        {
            id: "select",
            header: ({ table }) => (
                <DataTableCheckbox
                    checked={
                        table.getIsAllPageRowsSelected() ||
                        (table.getIsSomePageRowsSelected() && "indeterminate")
                    }
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    title="Select all"
                />
            ),
            cell: ({ row }) => (
                <DataTableCheckbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    title="Select row"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: "id",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="ID" />
            ),
            cell: ({ row }) => (
                <div className="font-medium text-blue-600 dark:text-blue-400">
                    <Link href={`procurements-list/${row.getValue("id")}`} className="hover:underline">
                        {row.getValue("id")}
                    </Link>
                </div>
            ),
        },
        {
            accessorKey: "title",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Title" />
            ),
            cell: ({ row }) => {
                const title = row.getValue("title") as string;
                const id = row.original.id;

                return (
                    <div className="max-w-[200px] truncate font-medium">
                        <Link href={`procurements-list/${id}`} className="hover:text-blue-600 hover:underline">
                            {title}
                        </Link>
                    </div>
                );
            },
        },
        {
            accessorKey: "phase_identifier", // Changed from "phase"
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Phase" />
            ),
            cell: ({ row }) => {
                const phase = row.getValue("phase_identifier") as PhaseIdentifier; // Use correct type

                return (
                    <Badge variant="outline" className={getPhaseBadgeStyle(phase)}>
                        {phase}
                    </Badge>
                );
            },
            filterFn: (row, id, value) => {
                return value.includes(row.getValue(id));
            },
        },
        {
            accessorKey: "current_state", // Changed from "state"
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="State" />
            ),
            cell: ({ row }) => {
                const state = row.getValue("current_state") as ProcurementState; // Use correct type

                return (
                    <Badge variant="outline" className={getStateBadgeStyle(state)}>
                        {state === 'PR Submitted' && <ClockIcon className="h-3 w-3 mr-1" />}
                        {state === 'Pre-Procurement Completed' && <CheckIcon className="h-3 w-3 mr-1" />}
                        {state === 'Pre-Procurement Skipped' && <CheckIcon className="h-3 w-3 mr-1" />}
                        {state === 'Bid Invitation Published' && <ShareIcon className="h-3 w-3 mr-1" />}
                        {state === 'Bids Opened' && <FileIcon className="h-3 w-3 mr-1" />}
                        {state === 'Bids Evaluated' && <BarChart4Icon className="h-3 w-3 mr-1" />}
                        {state === 'Post-Qualification Verified' && <CheckCircleIcon className="h-3 w-3 mr-1" />}
                        {state === 'Resolution Recorded' && <FileEditIcon className="h-3 w-3 mr-1" />}
                        {state === 'Awarded' && <CheckIcon className="h-3 w-3 mr-1" />}
                        {state === 'Performance Bond Recorded' && <FileTextIcon className="h-3 w-3 mr-1" />}
                        {state === 'Contract And PO Recorded' && <FileTextIcon className="h-3 w-3 mr-1" />}
                        {state === 'NTP Recorded' && <FileTextIcon className="h-3 w-3 mr-1" />}
                        {state === 'Monitoring' && <AlertCircleIcon className="h-3 w-3 mr-1" />}
                        {state}
                    </Badge>
                );
            },
            filterFn: (row, id, value) => {
                return value.includes(row.getValue(id));
            },
        },
        {
            accessorKey: "document_count",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Documents" />
            ),
            cell: ({ row }) => {
                const docCount = row.getValue("document_count") as number;

                return (
                    <div className="flex items-center gap-1">
                        <FileIcon className="h-3 w-3 text-blue-500 dark:text-blue-400" />
                        <span className="font-medium">{docCount}</span>
                    </div>
                );
            },
        },
        {
            accessorKey: "last_updated",
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Last Updated" />
            ),
            cell: ({ row }) => {
                const date = row.getValue("last_updated") as string;

                return (
                    <div className="flex items-center gap-1">
                        <CalendarIcon className="h-3 w-3 text-gray-500 dark:text-gray-400" />
                        <span className="text-sm">{date}</span>
                    </div>
                );
            },
        },
        {
            id: "actions",
            cell: ({ row }) => {
                const procurement = row.original;
                const id = procurement.id;
                const currentState = procurement.current_state;
                const phase = procurement.phase_identifier;

                return (
                    <div className="flex justify-end space-x-1">
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-8 w-8 p-0 text-blue-600 dark:text-blue-400"
                                        asChild
                                    >
                                        <Link href={`procurements-list/${id}`}>
                                            <FileTextIcon className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>View Details</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        {/* Show upload button for Pre-Procurement phase when conference was held but documents not uploaded */}
                        {currentState === 'PR Initiation' && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-green-600 dark:text-green-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/pre-procurement-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Pre-Procurement Documents</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {(phase === 'PR Initiation' && currentState === 'PR Submitted') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            onClick={() => setModalOpen(true)}
                                            size="sm"
                                            className="h-8 w-8 p-0 text-amber-600 dark:text-amber-400"
                                        >
                                            <FileUpIcon className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Record Pre-Procurement Conference Decision</TooltipContent>
                                </Tooltip>
                                {/* Move the PreProcurementModal outside of the TooltipTrigger */}
                                <PreProcurementModal
                                    open={modalOpen}
                                    onOpenChange={setModalOpen}
                                    procurementId={row.original.id}
                                    procurementTitle={row.original.title}
                                    onComplete={() => {
                                        // Refresh the procurement data or handle completion
                                        window.location.reload();
                                    }}
                                />
                            </TooltipProvider>
                        )}

                        {(phase === 'Pre-Procurement' && currentState === 'Pre-Procurement Conference Held') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-green-600 dark:text-green-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/pre-procurement-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Pre-Procurement Documents</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {/* Show upload button for Bid Invitation phase */}
                        {(phase === 'Bid Invitation' && currentState === 'Pre-Procurement Skipped' ||
                            currentState === 'Pre-Procurement Completed') && (
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-8 w-8 p-0 text-amber-600 dark:text-amber-400"
                                                asChild
                                            >
                                                <Link href={`/bac-secretariat/bid-invitation-upload/${id}`}>
                                                    <FileUpIcon className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Upload Bid Invitation Documents</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            )}

                        {(phase === 'Bid Opening' && currentState === 'Bid Invitation Published') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-blue-600 dark:text-blue-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/bid-submission-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Bid Submission Documents</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {/* Show upload button for Bid Evaluation phase */}
                        {(phase === 'Bid Evaluation' && currentState === 'Bids Opened') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-indigo-600 dark:text-indigo-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/bid-evaluation-upload/${id}`}>
                                                <BarChart4Icon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Bid Evaluation Documents</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {/* Show upload button for Post-Qualification phase */}
                        {(phase === 'Post-Qualification' && currentState === 'Bids Evaluated') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-purple-600 dark:text-purple-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/post-qualification-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Post-Qualification Documents</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {(phase === 'BAC Resolution' && currentState === 'Post-Qualification Verified') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-emerald-600 dark:text-emerald-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/bac-resolution-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload BAC Resolution Documents</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {(phase === 'Notice Of Award' && currentState === 'Resolution Recorded') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-green-600 dark:text-green-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/noa-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Notice of Award</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {(phase === 'Performance Bond' && currentState === 'Awarded') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-orange-600 dark:text-orange-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/performance-bond-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Performance Bond</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {(phase === 'Contract And PO' && currentState === 'Performance Bond Recorded') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-lime-600 dark:text-lime-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/contract-po-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Contract & PO</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {(phase === 'Notice To Proceed' && currentState === 'Contract And PO Recorded') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-cyan-600 dark:text-cyan-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/ntp-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Notice to Proceed</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        {(phase === 'Monitoring' && currentState === 'NTP Recorded') && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 w-8 p-0 text-gray-600 dark:text-gray-400"
                                            asChild
                                        >
                                            <Link href={`/bac-secretariat/monitoring-upload/${id}`}>
                                                <FileUpIcon className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Upload Monitoring Documents</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}
                    </div>
                );
            },
        },
    ];

    // KanbanCard component for displaying procurement in kanban view
    const KanbanCard = ({ procurement }: { procurement: ProcurementListItem }) => {
        return (
            <Card className="mb-2 cursor-pointer hover:border-blue-600 dark:hover:border-blue-500 transition-all duration-200 shadow-sm border-sidebar-border/70 dark:border-sidebar-border">
                <CardContent className="p-3">
                    <div className="space-y-2">
                        <div className="flex items-center justify-between gap-1">
                            <div className="max-w-[80px] flex-shrink-0">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Badge variant="outline" className="bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800 text-xs w-full">
                                                <span className="truncate inline-block max-w-full">{procurement.id}</span>
                                            </Badge>
                                        </TooltipTrigger>
                                        <TooltipContent side="top">{procurement.id}</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                            <div className="flex-shrink-0 max-w-[130px]">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Badge variant="outline" className={`${getStateBadgeStyle(procurement.current_state as ProcurementState)} text-xs flex items-center w-full`}>
                                                {procurement.current_state === 'PR Submitted' && <ClockIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Pre-Procurement Completed' && <CheckIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Pre-Procurement Skipped' && <CheckIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Bid Invitation Published' && <ShareIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Bids Opened' && <FileIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Bids Evaluated' && <BarChart4Icon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Post-Qualification Verified' && <CheckCircleIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Resolution Recorded' && <FileEditIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Awarded' && <CheckIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Performance Bond Recorded' && <FileTextIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Contract And PO Recorded' && <FileTextIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'NTP Recorded' && <FileTextIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                {procurement.current_state === 'Monitoring' && <AlertCircleIcon className="h-3 w-3 mr-1 flex-shrink-0" />}
                                                <span className="truncate inline-block overflow-hidden">{procurement.current_state}</span>
                                            </Badge>
                                        </TooltipTrigger>
                                        <TooltipContent side="top">{procurement.current_state}</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
                        <Link href={`/procurement/${procurement.id}`} className="block">
                            <h3 className="font-medium text-sm truncate hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400">{procurement.title}</h3>
                        </Link>
                        <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <div className="flex items-center gap-1">
                                <FileIcon className="h-3 w-3 text-blue-500 dark:text-blue-400" />
                                <span>{procurement.document_count} docs</span>
                            </div>
                            <div className="flex items-center gap-1">
                                <CalendarIcon className="h-3 w-3 text-gray-500 dark:text-gray-400" />
                                <span>{procurement.last_updated}</span>
                            </div>
                        </div>
                    </div>
                    {/* Add action buttons at the bottom */}
                    <div className="flex justify-end space-x-1 mt-2">
                        <Link href={`procurements-list/${procurement.id}`}>
                            <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                                <FileTextIcon className="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                            </Button>
                        </Link>

                        {procurement.current_state === 'Pre-Procurement Conference Held' && (
                            <Link href={`/bac-secretariat/pre-procurement-upload/${procurement.id}`}>
                                <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                                    <FileUpIcon className="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
                                </Button>
                            </Link>
                        )}

                        {(procurement.phase_identifier === 'Bid Invitation' &&
                            (procurement.current_state === 'Pre-Procurement Skipped' ||
                                procurement.current_state === 'Pre-Procurement Completed')) && (
                                <Link href={`/bac-secretariat/bid-invitation-upload/${procurement.id}`}>
                                    <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                                        <FileUpIcon className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                    </Button>
                                </Link>
                            )}

                        {(procurement.phase_identifier === 'Bid Opening' &&
                            procurement.current_state === 'Bid Invitation Published') && (
                                <Link href={`/bac-secretariat/bid-submission-upload/${procurement.id}`}>
                                    <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                                        <FileUpIcon className="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                    </Button>
                                </Link>
                            )}

                        {(procurement.phase_identifier === 'Bid Evaluation' &&
                            procurement.current_state === 'Bids Opened') && (
                                <Link href={`/bac-secretariat/bid-evaluation-upload/${procurement.id}`}>
                                    <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                                        <BarChart4Icon className="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400" />
                                    </Button>
                                </Link>
                            )}
                    </div>
                </CardContent>
            </Card>
        );
    };

    // KanbanBoard component for kanban view
    const KanbanBoard = ({ procurements }: { procurements: ProcurementListItem[] }) => {
        // Group procurements by phase_identifier
        const phases = [...new Set(procurements.map(proc => proc.phase_identifier))].filter(Boolean) as PhaseIdentifier[];

        const procurementsByPhase: Record<string, ProcurementListItem[]> = {};

        phases.forEach(phase => {
            procurementsByPhase[phase] = procurements.filter(proc => proc.phase_identifier === phase);
        });

        return (
            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 overflow-x-auto">
                {phases.map(phase => (
                    <div key={phase} className="border-sidebar-border/70 dark:border-sidebar-border rounded-lg p-3 border min-w-[240px]">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="font-medium text-sm flex items-center gap-2 dark:text-gray-200">
                                <Badge variant="outline" className={`${getPhaseBadgeStyle(phase)} whitespace-nowrap`}>
                                    {phase}
                                </Badge>
                                <span className="ml-1 bg-gray-200 text-gray-700 rounded-full px-2 py-0.5 text-xs dark:bg-gray-700 dark:text-gray-300">
                                    {procurementsByPhase[phase].length}
                                </span>
                            </h3>
                        </div>
                        <div className="space-y-2">
                            {procurementsByPhase[phase].map(procurement => (
                                <KanbanCard key={procurement.id} procurement={procurement} />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader className="pb-4 border-b dark:border-sidebar-border">
                        <div className="flex flex-col space-y-3 sm:space-y-0 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-50 flex items-center">
                                    Procurement List
                                    <Badge variant="outline" className="ml-3 bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800">
                                        {loading ? <span className="animate-pulse">...</span> : procurements.length}
                                    </Badge>
                                </CardTitle>
                                <CardDescription className="mt-1.5 text-sm text-gray-500 dark:text-gray-400 max-w-2xl">
                                    Track and manage all procurement activities with blockchain verification. All transactions are immutable and transparent.
                                </CardDescription>
                            </div>
                            <div className="flex flex-col sm:flex-row gap-3">
                                <div className="flex items-center space-x-2">
                                    <Tabs
                                        value={viewType}
                                        onValueChange={(value) => setViewType(value as ViewType)}
                                        className="hidden sm:flex"
                                    >
                                        <TabsList className="h-9 bg-gray-100 dark:bg-gray-800/60 p-1">
                                            <TabsTrigger
                                                value="table"
                                                className="text-xs px-3 data-[state=active]:bg-primary data-[state=active]:text-white dark:data-[state=active]:bg-primary/90 dark:data-[state=active]:text-white/95"
                                            >
                                                <Table2Icon className="h-3.5 w-3.5 mr-1.5" />
                                                Table
                                            </TabsTrigger>
                                            <TabsTrigger
                                                value="kanban"
                                                className="text-xs px-3 data-[state=active]:bg-primary data-[state=active]:text-white dark:data-[state=active]:bg-primary/90 dark:data-[state=active]:text-white/95"
                                            >
                                                <LayersIcon className="h-3.5 w-3.5 mr-1.5" />
                                                Kanban
                                            </TabsTrigger>
                                        </TabsList>
                                    </Tabs>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        className="bg-primary hover:bg-primary/90 text-sm font-medium shadow-sm transition-colors dark:bg-primary/90 dark:hover:bg-primary/80 dark:text-white/95"
                                        asChild
                                    >
                                        <Link href="/bac-secretariat/procurement/pr-initiation" className="flex items-center justify-center">
                                            <PlusIcon className="h-3.5 w-3.5 mr-1.5" />
                                            New Procurement
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <div className="mt-4 flex items-center text-xs text-gray-500 dark:text-gray-400">
                            <ExternalLinkIcon className="h-3.5 w-3.5 mr-1.5" />
                            <span>All procurements are verified on the blockchain network and cannot be tampered with</span>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="icon" className="h-5 w-5 ml-1 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><path d="M12 17h.01" /></svg>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent className="max-w-xs">
                                        <p>Blockchain verification ensures all procurement records are tamper-proof and transparent to authorized parties.</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </CardHeader>

                    <CardContent className="dark:border-t dark:border-sidebar-border">
                        {loading ? (
                            <div className="space-y-4 mt-0">
                                <div className="flex justify-between">
                                    <Skeleton className="h-10 w-[250px] dark:bg-gray-800" />
                                    <Skeleton className="h-10 w-[120px] dark:bg-gray-800" />
                                </div>
                                <Skeleton className="h-[400px] w-full dark:bg-gray-800" />
                                <Skeleton className="h-10 w-full dark:bg-gray-800" />
                            </div>
                        ) : error ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <div className="rounded-full bg-red-50 p-3 dark:bg-red-900/20 mb-4">
                                    <AlertCircleIcon className="h-6 w-6 text-red-600 dark:text-red-400" />
                                </div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Error Loading Procurements</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mb-6">
                                    {error}
                                </p>
                                <Button
                                    className="bg-primary hover:bg-primary/90 text-sm font-medium shadow-sm transition-colors dark:bg-primary/90 dark:hover:bg-primary/80 dark:text-white/95"
                                    onClick={() => window.location.reload()}
                                >
                                    Retry
                                </Button>
                            </div>
                        ) : procurements.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <div className="rounded-full bg-blue-50 p-3 dark:bg-blue-900/20 mb-4">
                                    <FileTextIcon className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No procurement records found</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mb-6">
                                    There are no procurement records in the blockchain yet. Start by creating your first procurement to begin tracking it on the blockchain.
                                </p>
                                <Button
                                    className="bg-primary hover:bg-primary/90 text-sm font-medium shadow-sm transition-colors dark:bg-primary/90 dark:hover:bg-primary/80 dark:text-white/95"
                                    asChild
                                >
                                    <Link href="/bac-secretariat/procurement/pr-initiation" className="flex items-center">
                                        <PlusIcon className="h-4 w-4 mr-1.5" />
                                        Create First Procurement
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <Tabs value={viewType} className="w-full">
                                <TabsContent value="table" className="mt-0">
                                    <DataTable
                                        columns={columns}
                                        data={procurements}
                                        searchColumn="title"
                                        searchPlaceholder="Search procurements..."
                                        onRowSelectionChange={setSelectedRows}
                                        bulkActions={bulkActions}
                                    />
                                </TabsContent>
                                <TabsContent value="kanban" className="mt-0">
                                    <KanbanBoard
                                        procurements={procurements}
                                    />
                                </TabsContent>
                            </Tabs>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout >
    );
}

