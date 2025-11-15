import React from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { DollarSign, Info } from 'lucide-react';
import { FUNDING_SOURCES } from '@/types/constants';
import type { StepProps, CategoryOption, ProcurementModeOption } from '../types';

interface ClassificationBudgetStepProps extends StepProps {
    categories: CategoryOption[];
    procurementModes: ProcurementModeOption[];
}

export function ClassificationBudgetStep({
    data,
    setData,
    errors,
    clearErrors,
    hasError,
    categories,
    procurementModes,
}: ClassificationBudgetStepProps) {
    const handleFieldChange = (field: keyof typeof data, value: string): void => {
        clearErrors(field);
        setData(field, value);
    };

    // Find selected category and mode for displaying descriptions
    const selectedCategory = categories.find((cat) => cat.value === data.category);
    const selectedMode = procurementModes.find((mode) => mode.value === data.procurement_mode);

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <Card className="border-2 border-primary/20 bg-primary/5">
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                        <div className="rounded-lg bg-primary/10 p-3">
                            <DollarSign className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-base font-semibold sm:text-lg">Classification & Budget</h3>
                            <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                Classify the procurement type and specify the approved budget for
                                the contract.
                            </p>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Form Fields */}
            <Card>
                <CardContent className="space-y-4 p-4 pt-4 sm:space-y-6 sm:p-6 sm:pt-6">
                    {/* Category and Procurement Mode - Grid */}
                    <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                        {/* Category */}
                        <Field>
                            <FieldLabel>
                                Category
                                <span className="text-destructive">*</span>
                            </FieldLabel>
                        <FieldDescription>
                            Select the type of procurement (Goods, Services, or Infrastructure)
                        </FieldDescription>
                        <Select
                            value={data.category}
                            onValueChange={(value) => handleFieldChange('category', value)}
                        >
                            <SelectTrigger
                                className={
                                    hasError('category')
                                        ? 'h-auto min-h-10 border-destructive ring-destructive/30'
                                        : 'h-auto min-h-10'
                                }
                            >
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem key={category.value} value={category.value} className="py-3">
                                        <div className="flex flex-col gap-1">
                                            <span className="font-medium">{category.label}</span>
                                            <span className="text-xs text-muted-foreground line-clamp-2">
                                                {category.description}
                                            </span>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {hasError('category') && <FieldError>{errors.category}</FieldError>}
                        {selectedCategory && (
                            <Alert className="mt-2">
                                <Info className="h-4 w-4" />
                                <AlertDescription>
                                    {selectedCategory.description}
                                </AlertDescription>
                            </Alert>
                        )}
                    </Field>

                    {/* Procurement Mode */}
                    <Field>
                        <FieldLabel>
                            Procurement Mode
                            <span className="ml-1 text-xs text-destructive">*</span>
                        </FieldLabel>
                        <FieldDescription>
                            Select the appropriate procurement method per RA 9184
                        </FieldDescription>
                        <Select
                            value={data.procurement_mode}
                            onValueChange={(value) => handleFieldChange('procurement_mode', value)}
                        >
                            <SelectTrigger
                                className={
                                    hasError('procurement_mode')
                                        ? 'h-auto min-h-10 border-destructive ring-destructive/30'
                                        : 'h-auto min-h-10'
                                }
                            >
                                <SelectValue placeholder="Select procurement mode" />
                            </SelectTrigger>
                            <SelectContent>
                                {procurementModes.map((mode) => (
                                    <SelectItem key={mode.value} value={mode.value} className="py-3">
                                        <div className="flex flex-col gap-1">
                                            <span className="font-medium">{mode.label}</span>
                                            <span className="text-xs text-muted-foreground line-clamp-2">
                                                {mode.description}
                                            </span>
                                            {mode.threshold && (
                                                <span className="text-xs text-muted-foreground">
                                                    Threshold: ₱
                                                    {mode.threshold.toLocaleString()}
                                                </span>
                                            )}
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {hasError('procurement_mode') && (
                            <FieldError>{errors.procurement_mode}</FieldError>
                        )}
                        {selectedMode && (
                            <Alert className="mt-2">
                                <Info className="h-4 w-4" />
                                <AlertDescription>
                                    <div className="space-y-2">
                                        <p>{selectedMode.description}</p>
                                        {(selectedMode.requires_philgeps || selectedMode.requires_bac_resolution) && (
                                            <div className="flex flex-wrap gap-2">
                                                {selectedMode.requires_philgeps && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-500 text-amber-700 dark:text-amber-300"
                                                    >
                                                        PhilGEPS Required
                                                    </Badge>
                                                )}
                                                {selectedMode.requires_bac_resolution && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-blue-500 text-blue-700 dark:text-blue-300"
                                                    >
                                                        BAC Resolution Required
                                                    </Badge>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </AlertDescription>
                            </Alert>
                        )}
                    </Field>

                    {/* ABC Amount */}
                    <Field>
                        <FieldLabel>
                            ABC Amount (₱)
                            <span className="ml-1 text-xs text-destructive">*</span>
                        </FieldLabel>
                        <FieldDescription>
                            Approved Budget for the Contract - the maximum amount allocated
                        </FieldDescription>
                        <Input
                            type="number"
                            value={data.abc_amount}
                            onChange={(e) => handleFieldChange('abc_amount', e.target.value)}
                            className={
                                hasError('abc_amount')
                                    ? 'border-destructive ring-destructive/30'
                                    : ''
                            }
                            placeholder="0.00"
                            step="0.01"
                            min="0"
                        />
                        {hasError('abc_amount') && <FieldError>{errors.abc_amount}</FieldError>}
                        {data.abc_amount && parseFloat(data.abc_amount) > 0 && (
                            <div className="mt-2 text-sm text-muted-foreground">
                                Formatted: ₱{parseFloat(data.abc_amount).toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                })}
                            </div>
                        )}
                    </Field>

                    {/* Funding Source */}
                    <Field>
                        <FieldLabel>
                            Funding Source
                            <span className="ml-1 text-xs text-destructive">*</span>
                        </FieldLabel>
                        <FieldDescription>
                            Select the source of funds for this procurement
                        </FieldDescription>
                        <Select
                            value={data.funding_source}
                            onValueChange={(value) => handleFieldChange('funding_source', value)}
                        >
                            <SelectTrigger
                                className={
                                    hasError('funding_source')
                                        ? 'border-destructive ring-destructive/30'
                                        : ''
                                }
                            >
                                <SelectValue placeholder="Select funding source" />
                            </SelectTrigger>
                            <SelectContent>
                                {FUNDING_SOURCES.map((source) => (
                                    <SelectItem key={source.value} value={source.value}>
                                        {source.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                            {hasError('funding_source') && (
                                <FieldError>{errors.funding_source}</FieldError>
                            )}
                        </Field>
                    </div>
                </CardContent>
            </Card>

            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>ABC:</strong> The ABC must be determined based on prevailing market prices and should not exceed the appropriated budget.
                </AlertDescription>
            </Alert>
        </div>
    );
}
