import { Table } from "@tanstack/react-table";
import { Button } from "@/components/ui/button";
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronsLeftIcon,
    ChevronsRightIcon,
} from "lucide-react";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useMemo } from "react";

interface DataTablePaginationProps<TData> {
    table: Table<TData>;
}

export function DataTablePagination<TData>({
    table,
}: DataTablePaginationProps<TData>) {
    // Generate pagination numbers with ellipsis for large datasets
    const pageNumbers = useMemo(() => {
        const currentPage = table.getState().pagination.pageIndex + 1;
        const totalPages = table.getPageCount();

        if (totalPages === 0) {
            return [];
        }

        // If 7 or fewer pages, show all
        if (totalPages <= 7) {
            return Array.from({ length: totalPages }, (_, i) => i + 1);
        }

        // Always show first, last, and pages around current
        const pages = [1];

        // Start of middle range
        if (currentPage > 3) {
            pages.push(-1); // -1 represents ellipsis
        }

        // Middle range
        const start = Math.max(2, currentPage - 1);
        const end = Math.min(totalPages - 1, currentPage + 1);

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        // End of range
        if (currentPage < totalPages - 2) {
            pages.push(-2); // -2 represents end ellipsis
        }

        // Last page
        if (totalPages > 1) {
            pages.push(totalPages);
        }

        return pages;
    }, [table.getState().pagination.pageIndex, table.getPageCount()]);

    const currentEntries = table.getFilteredRowModel().rows.length;
    const pageIndex = table.getState().pagination.pageIndex;
    const pageSize = table.getState().pagination.pageSize;

    const startEntry = currentEntries > 0 ? pageIndex * pageSize + 1 : 0;
    const endEntry = Math.min((pageIndex + 1) * pageSize, currentEntries);

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 border-sidebar-border/70 dark:border-sidebar-border p-3 rounded-md border shadow-sm">
            <div className="text-sm text-gray-600 dark:text-gray-300 order-2 sm:order-1">
                {currentEntries > 0 ? (
                    <>
                        Showing <span className="font-medium">{startEntry}</span> to <span className="font-medium">{endEntry}</span> of <span className="font-medium">{currentEntries}</span> entries
                    </>
                ) : (
                    <>No entries to show</>
                )}
            </div>

            <div className="flex items-center space-x-2 order-1 sm:order-2">
                {/* Rows per page selector */}
                <div className="hidden sm:flex items-center space-x-2 mr-4">
                    <span className="text-sm text-gray-600 whitespace-nowrap dark:text-gray-300">Rows per page</span>
                    <Select
                        value={`${pageSize}`}
                        onValueChange={(value) => {
                            table.setPageSize(Number(value));
                        }}
                    >
                        <SelectTrigger className="h-8 w-[70px] border-sidebar-border/70 dark:border-sidebar-border focus:ring-primary focus:border-primary dark:focus:ring-primary dark:focus:border-primary">
                            <SelectValue placeholder={pageSize} />
                        </SelectTrigger>
                        <SelectContent className="border-sidebar-border/70 dark:border-sidebar-border shadow-lg">
                            {[10, 15, 20, 25, 30, 35, 40, 45, 50].map((size) => (
                                <SelectItem
                                    key={size}
                                    value={`${size}`}
                                    className="dark:text-gray-200 dark:hover:bg-gray-700 hover:bg-gray-100"
                                >
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Pagination controls */}
                {currentEntries > 0 && (
                    <div className="flex items-center">
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8 mr-1 border-sidebar-border/70 dark:border-sidebar-border hover:bg-muted/50 dark:hover:bg-muted/20 focus:ring-primary dark:focus:ring-primary"
                            onClick={() => table.setPageIndex(0)}
                            disabled={!table.getCanPreviousPage()}
                            title="First page"
                        >
                            <ChevronsLeftIcon className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8 border-sidebar-border/70 dark:border-sidebar-border hover:bg-muted/50 dark:hover:bg-muted/20 focus:ring-primary dark:focus:ring-primary"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                            title="Previous page"
                        >
                            <ChevronLeftIcon className="h-4 w-4" />
                        </Button>

                        <div className="hidden sm:flex mx-2 items-center">
                            {pageNumbers.map((pageNumber, i) => {
                                if (pageNumber < 0) {
                                    // Render ellipsis
                                    return (
                                        <span key={`ellipsis-${i}`} className="px-2 text-gray-400 dark:text-gray-500">
                                            …
                                        </span>
                                    );
                                }

                                const isCurrentPage = pageNumber === pageIndex + 1;

                                return (
                                    <Button
                                        key={`page-${pageNumber}`}
                                        variant={isCurrentPage ? "default" : "outline"}
                                        size="sm"
                                        className={`h-8 w-8 mx-0.5 ${isCurrentPage
                                            ? "bg-primary hover:bg-primary/90 text-primary-foreground"
                                            : "border-sidebar-border/70 dark:border-sidebar-border hover:bg-muted/50 dark:hover:bg-muted/20"
                                            }`}
                                        onClick={() => table.setPageIndex(pageNumber - 1)}
                                    >
                                        {pageNumber}
                                    </Button>
                                );
                            })}
                        </div>

                        {/* Mobile page indicator */}
                        <span className="sm:hidden mx-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Page {pageIndex + 1} of {table.getPageCount() || 1}
                        </span>

                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8 border-sidebar-border/70 dark:border-sidebar-border hover:bg-muted/50 dark:hover:bg-muted/20 focus:ring-primary dark:focus:ring-primary"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                            title="Next page"
                        >
                            <ChevronRightIcon className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8 ml-1 border-sidebar-border/70 dark:border-sidebar-border hover:bg-muted/50 dark:hover:bg-muted/20 focus:ring-primary dark:focus:ring-primary"
                            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
                            disabled={!table.getCanNextPage()}
                            title="Last page"
                        >
                            <ChevronsRightIcon className="h-4 w-4" />
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
