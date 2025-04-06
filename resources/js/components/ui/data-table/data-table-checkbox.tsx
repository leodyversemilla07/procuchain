import * as React from "react";
import { Checkbox } from "@/components/ui/checkbox";

interface DataTableCheckboxProps {
    checked: boolean | "indeterminate";
    onCheckedChange: (value: boolean) => void;
    disabled?: boolean;
    title?: string;
}

export function DataTableCheckbox({
    checked,
    onCheckedChange,
    disabled = false,
    title
}: DataTableCheckboxProps) {
    return (
        <div className="flex items-center justify-center w-full h-full min-w-[24px]">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => {
                    onCheckedChange(value === true);
                }}
                disabled={disabled}
                aria-label={title || "Select row"}
                className="rounded-sm touch-manipulation
                    data-[state=checked]:bg-blue-600 data-[state=checked]:border-blue-600 
                    data-[state=checked]:dark:bg-blue-500 data-[state=checked]:dark:border-blue-500
                    data-[state=indeterminate]:bg-blue-600 data-[state=indeterminate]:border-blue-600 
                    data-[state=indeterminate]:dark:bg-blue-500 data-[state=indeterminate]:dark:border-blue-500
                    border-gray-300 dark:border-gray-600 
                    text-white dark:text-white
                    focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 dark:focus:ring-blue-600 dark:focus:ring-offset-gray-900
                    disabled:opacity-50 disabled:cursor-not-allowed
                    transition-all duration-200
                    sm:hover:border-blue-500 dark:sm:hover:border-blue-400"
            />
        </div>
    );
}
