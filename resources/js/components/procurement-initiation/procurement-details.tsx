import React from 'react';
import { Info } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface ProcurementDetailsProps {
    data: {
        procurement_id: string;
        procurement_title: string;
    };
    errors: Record<string, string>;
    hasError: (field: string) => boolean;
    handleFieldChange: (field: string, value: string) => void;
    clearErrors: () => void;
}

export function ProcurementDetails({
    data,
    errors,
    hasError,
    handleFieldChange,
    clearErrors
}: ProcurementDetailsProps) {
    return (
        <div className="space-y-6 sm:space-y-8 animate-fadeIn">
            <div className="flex flex-col sm:flex-row sm:items-center gap-2 mb-4 sm:mb-6">
                <h2 className="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    Procurement Details
                </h2>
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Info className="h-4 w-4 text-muted-foreground cursor-help" />
                        </TooltipTrigger>
                        <TooltipContent>
                            <p className="text-xs max-w-xs">Enter the basic information for this procurement, including a unique ID and descriptive title</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-8">
                <Card className="p-4 sm:p-6 border-sidebar-border/70 dark:border-sidebar-border shadow-sm transition-all duration-200 hover:shadow-md overflow-hidden relative">
                    <div className="absolute top-0 left-0 w-1.5 h-full bg-primary/60"></div>
                    <div className="space-y-4 sm:space-y-5">
                        <div>
                            <div className="flex flex-col sm:flex-row sm:items-baseline sm:justify-between mb-1 sm:mb-2">
                                <Label
                                    htmlFor="procurement_id"
                                    className="text-sm sm:text-base font-medium text-gray-700 dark:text-gray-300 mb-0.5 sm:mb-0"
                                >
                                    Procurement ID
                                </Label>
                                <span className="text-[0.65rem] sm:text-[0.7rem] text-muted-foreground">Required</span>
                            </div>

                            <Input
                                id="procurement_id"
                                type="text"
                                value={data.procurement_id}
                                onChange={(e) => handleFieldChange('procurement_id', e.target.value)}
                                onFocus={clearErrors}
                                placeholder="Enter a unique ID for this procurement"
                                className={`transition-all duration-200 ${hasError('procurement_id')
                                    ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30'
                                    : 'border-gray-200 dark:border-gray-700 focus:border-primary'}`}
                                aria-invalid={hasError('procurement_id')}
                            />

                            {hasError('procurement_id') && (
                                <p className="mt-1.5 sm:mt-2 text-xs sm:text-sm text-red-600 dark:text-red-400">
                                    {errors.procurement_id}
                                </p>
                            )}

                            <p className="mt-1.5 sm:mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                The procurement ID is a unique identifier for this procurement process.
                            </p>
                        </div>

                        <div className="p-3 sm:p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-900/30">
                            <p className="text-xs sm:text-sm text-blue-700 dark:text-blue-300">
                                <span className="font-medium">Tip:</span> The procurement ID should follow your organization's naming convention, for example: PROC-2025-001
                            </p>
                        </div>
                    </div>
                </Card>

                <Card className="p-4 sm:p-6 border-sidebar-border/70 dark:border-sidebar-border shadow-sm transition-all duration-200 hover:shadow-md overflow-hidden relative">
                    <div className="absolute top-0 left-0 w-1.5 h-full bg-primary/60"></div>
                    <div className="space-y-4 sm:space-y-5">
                        <div>
                            <div className="flex flex-col sm:flex-row sm:items-baseline sm:justify-between mb-1 sm:mb-2">
                                <Label
                                    htmlFor="procurement_title"
                                    className="text-sm sm:text-base font-medium text-gray-700 dark:text-gray-300 mb-0.5 sm:mb-0"
                                >
                                    Procurement Title
                                </Label>
                                <span className="text-[0.65rem] sm:text-[0.7rem] text-muted-foreground">Required</span>
                            </div>

                            <Input
                                id="procurement_title"
                                type="text"
                                value={data.procurement_title}
                                onChange={(e) => handleFieldChange('procurement_title', e.target.value)}
                                onFocus={clearErrors}
                                placeholder="Enter a descriptive title for this procurement"
                                className={`transition-all duration-200 ${hasError('procurement_title')
                                    ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30'
                                    : 'border-gray-200 dark:border-gray-700 focus:border-primary'}`}
                                aria-invalid={hasError('procurement_title')}
                            />

                            {hasError('procurement_title') && (
                                <p className="mt-1.5 sm:mt-2 text-xs sm:text-sm text-red-600 dark:text-red-400">
                                    {errors.procurement_title}
                                </p>
                            )}

                            <p className="mt-1.5 sm:mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                The procurement title should clearly describe what is being procured.
                            </p>
                        </div>

                        <div className="p-3 sm:p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-900/30">
                            <p className="text-xs sm:text-sm text-blue-700 dark:text-blue-300">
                                <span className="font-medium">Example:</span> "Supply and Delivery of Office Equipment for the Municipal Hall"
                            </p>
                        </div>
                    </div>
                </Card>
            </div>

            <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
                <p className="text-xs sm:text-sm text-muted-foreground">
                    Please ensure all information is accurate before proceeding to the next step. This information will be stored in the blockchain and cannot be easily modified once submitted.
                </p>
            </div>
        </div>
    );
}
