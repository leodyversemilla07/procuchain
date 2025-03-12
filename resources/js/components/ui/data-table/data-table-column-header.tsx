import { ArrowDownIcon, ArrowUpIcon, ArrowUpDown, EyeOffIcon } from "lucide-react";
import { Column } from "@tanstack/react-table";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

interface DataTableColumnHeaderProps<TData, TValue>
    extends React.HTMLAttributes<HTMLDivElement> {
    column: Column<TData, TValue>;
    title: string;
}

export function DataTableColumnHeader<TData, TValue>({
    column,
    title,
    className,
}: DataTableColumnHeaderProps<TData, TValue>) {
    if (!column.getCanSort()) {
        return <div className={cn("font-semibold text-xs text-gray-700 dark:text-gray-200", className)}>{title}</div>;
    }

    return (
        <div className={cn("flex items-center space-x-2", className)}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-3 h-8 font-semibold text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 data-[state=open]:bg-gray-100 dark:data-[state=open]:bg-gray-700"
                    >
                        <span>{title}</span>
                        {column.getIsSorted() === "desc" ? (
                            <ArrowDownIcon className="ml-2 h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                        ) : column.getIsSorted() === "asc" ? (
                            <ArrowUpIcon className="ml-2 h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                        ) : (
                            <ArrowUpDown className="ml-2 h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="min-w-[150px] p-1.5 bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 shadow-md">
                    <DropdownMenuItem 
                        onClick={() => column.toggleSorting(false)}
                        className={cn(
                            "flex items-center cursor-pointer rounded px-2.5 py-1.5 text-gray-700 dark:text-gray-200", 
                            column.getIsSorted() === "asc" 
                                ? "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200" 
                                : "hover:bg-gray-100 dark:hover:bg-gray-700"
                        )}
                    >
                        <ArrowUpIcon className="mr-2 h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                        <span>Sort Ascending</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem 
                        onClick={() => column.toggleSorting(true)}
                        className={cn(
                            "flex items-center cursor-pointer rounded px-2.5 py-1.5 text-gray-700 dark:text-gray-200", 
                            column.getIsSorted() === "desc" 
                                ? "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200" 
                                : "hover:bg-gray-100 dark:hover:bg-gray-700"
                        )}
                    >
                        <ArrowDownIcon className="mr-2 h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                        <span>Sort Descending</span>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator className="my-1 h-px bg-gray-200 dark:bg-gray-700" />
                    <DropdownMenuItem 
                        onClick={() => column.toggleVisibility(false)}
                        className="flex items-center cursor-pointer rounded px-2.5 py-1.5 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <EyeOffIcon className="mr-2 h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                        <span>Hide Column</span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
