import {
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
    Pagination as UIPagination,
} from '@/components/ui/pagination';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import * as React from 'react';

interface PaginationProps {
    pageIndex: number;
    pageSize: number;
    pageCount: number;
    totalItems: number;
    onPageChange: (pageIndex: number) => void;
    onPageSizeChange?: (pageSize: number) => void;
    pageSizeOptions?: number[];
    className?: string;
}

export const Pagination: React.FC<PaginationProps> = ({
    pageIndex,
    pageSize,
    pageCount,
    totalItems,
    onPageChange,
    onPageSizeChange,
    pageSizeOptions = [10, 25, 50, 100, 250],
    className = '',
}) => {
    const pageNumbers = React.useMemo(() => {
        const currentPage = pageIndex + 1;
        const totalPages = pageCount;
        if (totalPages === 0) return [];
        if (totalPages <= 7) {
            return Array.from({ length: totalPages }, (_, i) => i + 1);
        }
        const pages = [1];
        if (currentPage > 3) pages.push(-1); // -1 = ellipsis
        const start = Math.max(2, currentPage - 1);
        const end = Math.min(totalPages - 1, currentPage + 1);
        for (let i = start; i <= end; i++) pages.push(i);
        if (currentPage < totalPages - 2) pages.push(-2); // -2 = ellipsis
        if (totalPages > 1) pages.push(totalPages);
        return pages;
    }, [pageIndex, pageCount]);

    const startEntry = totalItems > 0 ? pageIndex * pageSize + 1 : 0;
    const endEntry = Math.min((pageIndex + 1) * pageSize, totalItems);

    return (
        <UIPagination className={cn('flex flex-col items-center justify-between gap-4 lg:flex-row', className)}>
            <div className="text-muted-foreground w-full text-center text-sm lg:w-auto lg:text-left">
                {totalItems > 0 ? (
                    <>
                        <span className="lg:hidden">
                            <span className="text-foreground font-medium">
                                {startEntry}–{endEntry}
                            </span>{' '}
                            / <span className="text-foreground font-medium">{totalItems}</span>
                        </span>
                        <span className="hidden lg:inline">
                            Showing <span className="text-foreground font-medium">{startEntry}</span> to{' '}
                            <span className="text-foreground font-medium">{endEntry}</span> of{' '}
                            <span className="text-foreground font-medium">{totalItems}</span> entries
                        </span>
                    </>
                ) : (
                    <>No entries</>
                )}
            </div>
            <div className="flex w-full flex-col items-center gap-3 sm:flex-row sm:gap-4 lg:w-auto">
                {onPageSizeChange && (
                    <div className="order-2 flex items-center gap-2 sm:order-1">
                        <span className="text-muted-foreground text-sm whitespace-nowrap">Rows per page</span>
                        <Select value={String(pageSize)} onValueChange={(v) => onPageSizeChange(Number(v))}>
                            <SelectTrigger className="focus:ring-ring focus:border-ring bg-background h-8 w-[70px] rounded border px-2">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {pageSizeOptions.map((size) => (
                                        <SelectItem key={size} value={String(size)}>
                                            {size}
                                        </SelectItem>
                                    ))}
                                    <SelectItem value="9999">All</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                )}
                {totalItems > 0 && (
                    <PaginationContent className="order-1 sm:order-2">
                        <PaginationItem>
                            <PaginationPrevious
                                onClick={() => onPageChange(pageIndex - 1)}
                                aria-disabled={pageIndex === 0}
                                tabIndex={pageIndex === 0 ? -1 : 0}
                                style={{ pointerEvents: pageIndex === 0 ? 'none' : undefined, opacity: pageIndex === 0 ? 0.5 : 1 }}
                            />
                        </PaginationItem>
                        {pageNumbers.map((pageNumber, i) => {
                            if (pageNumber < 0) {
                                return (
                                    <PaginationItem key={`ellipsis-${i}`}>
                                        <PaginationEllipsis />
                                    </PaginationItem>
                                );
                            }
                            const isCurrentPage = pageNumber === pageIndex + 1;
                            return (
                                <PaginationItem key={`page-${pageNumber}`}>
                                    <PaginationLink
                                        isActive={isCurrentPage}
                                        onClick={() => onPageChange(pageNumber - 1)}
                                        aria-current={isCurrentPage ? 'page' : undefined}
                                    >
                                        {pageNumber}
                                    </PaginationLink>
                                </PaginationItem>
                            );
                        })}
                        <PaginationItem>
                            <PaginationNext
                                onClick={() => onPageChange(pageIndex + 1)}
                                aria-disabled={pageIndex >= pageCount - 1}
                                tabIndex={pageIndex >= pageCount - 1 ? -1 : 0}
                                style={{
                                    pointerEvents: pageIndex >= pageCount - 1 ? 'none' : undefined,
                                    opacity: pageIndex >= pageCount - 1 ? 0.5 : 1,
                                }}
                            />
                        </PaginationItem>
                    </PaginationContent>
                )}
            </div>
        </UIPagination>
    );
};
