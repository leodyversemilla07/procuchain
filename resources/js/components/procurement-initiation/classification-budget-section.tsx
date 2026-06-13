import { DollarSign } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';

import type { CategoryOption, NegotiatedProcurementTypeOption, ProcurementModeOption, UseFormData } from '@/hooks/use-procurement-initiation';

interface ClassificationBudgetSectionProps {
    data: UseFormData;
    errors: Record<string, string>;
    hasError: (field: string) => boolean;
    handleFieldChange: (field: keyof UseFormData, value: string | Date | undefined) => void;
    selectedMode: ProcurementModeOption | undefined;
    categories: CategoryOption[];
    procurementModes: ProcurementModeOption[];
    negotiatedProcurementTypes: NegotiatedProcurementTypeOption[];
    FUNDING_SOURCES: readonly { value: string; label: string }[];
}

export function ClassificationBudgetSection({
    data,
    errors,
    hasError,
    handleFieldChange,
    selectedMode,
    categories,
    procurementModes,
    negotiatedProcurementTypes,
    FUNDING_SOURCES,
}: ClassificationBudgetSectionProps) {
    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
            <CardHeader className="flex flex-col gap-1 pb-2 sm:pb-4">
                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                    <DollarSign />
                    Classification &amp; Budget
                </CardTitle>
                <CardDescription className="text-muted-foreground text-sm">Procurement type and approved contract budget</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4 gap-6 p-4 sm:flex sm:p-6">
                {/* Category and Funding Source - Grid */}
                <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                    {/* Category */}
                    <Field>
                        <FieldLabel>
                            Category
                            <span className="text-destructive">*</span>
                        </FieldLabel>
                        <RadioGroup value={data.category} onValueChange={(value) => handleFieldChange('category', value)} className="mt-2 grid gap-2">
                            {categories.map((category) => (
                                <div
                                    key={category.value}
                                    className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
                                        data.category === category.value ? 'border-primary bg-primary/5' : 'border-input hover:bg-muted/50'
                                    } ${hasError('category') ? 'border-destructive' : ''}`}
                                >
                                    <RadioGroupItem value={category.value} id={`category-${category.value}`} />
                                    <FieldLabel htmlFor={`category-${category.value}`} className="flex-1 cursor-pointer font-medium">
                                        {category.label}
                                    </FieldLabel>
                                </div>
                            ))}
                        </RadioGroup>
                        {hasError('category') && <FieldError>{errors.category}</FieldError>}
                    </Field>

                    {/* Funding Source */}
                    <Field>
                        <FieldLabel>
                            Funding Source
                            <span className="text-destructive ml-1 text-xs">*</span>
                        </FieldLabel>
                        <RadioGroup
                            value={data.funding_source}
                            onValueChange={(value) => {
                                handleFieldChange('funding_source', value);
                                if (value !== 'Other Sources') {
                                    handleFieldChange('other_funding_source', '');
                                }
                            }}
                            className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2"
                        >
                            {FUNDING_SOURCES.map((source) => (
                                <div key={source.value} className="flex items-center gap-2">
                                    <RadioGroupItem
                                        value={source.value}
                                        id={`funding-${source.value}`}
                                        className={hasError('funding_source') ? 'border-destructive' : ''}
                                    />
                                    <FieldLabel htmlFor={`funding-${source.value}`} className="cursor-pointer text-sm font-normal">
                                        {source.label}
                                    </FieldLabel>
                                </div>
                            ))}
                        </RadioGroup>
                        {hasError('funding_source') && <FieldError>{errors.funding_source}</FieldError>}
                        {data.funding_source === 'Other Sources' && (
                            <div className="mt-3">
                                <Input
                                    id="other_funding_source"
                                    name="other_funding_source"
                                    type="text"
                                    value={data.other_funding_source}
                                    onChange={(e) => handleFieldChange('other_funding_source', e.target.value)}
                                    className={hasError('other_funding_source') ? 'border-destructive ring-destructive/30' : ''}
                                    placeholder="Please specify the funding source"
                                />
                                {hasError('other_funding_source') && <FieldError>{errors.other_funding_source}</FieldError>}
                            </div>
                        )}
                    </Field>
                </div>

                {/* Procurement Mode and ABC Amount - Grid */}
                <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                    {/* Procurement Mode */}
                    <Field>
                        <FieldLabel>
                            Procurement Mode
                            <span className="text-destructive ml-1 text-xs">*</span>
                        </FieldLabel>
                        <RadioGroup
                            value={data.procurement_mode}
                            onValueChange={(value) => {
                                handleFieldChange('procurement_mode', value);
                                if (value !== 'negotiated_procurement') {
                                    handleFieldChange('negotiated_procurement_type', '');
                                }
                            }}
                            className="mt-2 grid gap-2"
                        >
                            {procurementModes.map((mode) => (
                                <div
                                    key={mode.value}
                                    className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
                                        data.procurement_mode === mode.value ? 'border-primary bg-primary/5' : 'border-input hover:bg-muted/50'
                                    } ${hasError('procurement_mode') ? 'border-destructive' : ''}`}
                                >
                                    <RadioGroupItem value={mode.value} id={`mode-${mode.value}`} />
                                    <FieldLabel htmlFor={`mode-${mode.value}`} className="flex-1 cursor-pointer">
                                        <span className="font-medium">{mode.label}</span>
                                        {mode.threshold && (
                                            <span className="text-muted-foreground ml-2 text-xs">(≤ ₱{mode.threshold.toLocaleString()})</span>
                                        )}
                                    </FieldLabel>
                                </div>
                            ))}
                        </RadioGroup>
                        {hasError('procurement_mode') && <FieldError>{errors.procurement_mode}</FieldError>}

                        {/* Negotiated Procurement Sub-types */}
                        {data.procurement_mode === 'negotiated_procurement' && (
                            <div className="border-primary/30 mt-4 ml-6 flex flex-col gap-3 border-l-2 pl-4">
                                <FieldLabel className="text-sm">
                                    Type of Negotiated Procurement
                                    <span className="text-destructive ml-1 text-xs">*</span>
                                </FieldLabel>
                                <FieldDescription className="text-xs">Select the applicable type for this procurement</FieldDescription>
                                <RadioGroup
                                    value={data.negotiated_procurement_type}
                                    onValueChange={(value) => handleFieldChange('negotiated_procurement_type', value)}
                                    className="grid gap-2"
                                >
                                    {negotiatedProcurementTypes.map((type) => (
                                        <div
                                            key={type.value}
                                            className={`flex items-center gap-3 rounded-lg border p-2.5 transition-colors ${
                                                data.negotiated_procurement_type === type.value
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-input hover:bg-muted/50'
                                            } ${hasError('negotiated_procurement_type') ? 'border-destructive' : ''}`}
                                        >
                                            <RadioGroupItem value={type.value} id={`neg-type-${type.value}`} />
                                            <FieldLabel htmlFor={`neg-type-${type.value}`} className="flex-1 cursor-pointer text-sm font-medium">
                                                {type.label}
                                            </FieldLabel>
                                        </div>
                                    ))}
                                </RadioGroup>
                                {hasError('negotiated_procurement_type') && <FieldError>{errors.negotiated_procurement_type}</FieldError>}
                            </div>
                        )}
                    </Field>

                    {/* ABC Amount */}
                    <Field>
                        <FieldLabel htmlFor="abc_amount">
                            ABC Amount (₱)
                            <span className="text-destructive ml-1 text-xs">*</span>
                        </FieldLabel>
                        <FieldDescription>Approved Budget for the Contract - the maximum amount allocated</FieldDescription>
                        <div className="relative">
                            <span className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 -translate-y-1/2">₱</span>
                            <Input
                                id="abc_amount"
                                name="abc_amount"
                                type="text"
                                inputMode="decimal"
                                value={(() => {
                                    if (!data.abc_amount) return '';
                                    const hasTrailingDecimal = data.abc_amount.endsWith('.');
                                    const decimalParts = data.abc_amount.split('.');
                                    const decimalDigits = decimalParts[1] || '';
                                    const integerPart = decimalParts[0];
                                    const formattedInteger = integerPart ? parseInt(integerPart, 10).toLocaleString('en-PH') : '';
                                    if (hasTrailingDecimal) return formattedInteger + '.';
                                    if (decimalDigits) return formattedInteger + '.' + decimalDigits;
                                    return formattedInteger;
                                })()}
                                onChange={(e) => {
                                    const rawValue = e.target.value.replace(/,/g, '');
                                    if (rawValue === '' || /^\d*\.?\d{0,2}$/.test(rawValue)) {
                                        handleFieldChange('abc_amount', rawValue);
                                    }
                                }}
                                className={`pl-7 ${hasError('abc_amount') ? 'border-destructive ring-destructive/30' : ''}`}
                                placeholder="0.00"
                            />
                        </div>
                        {hasError('abc_amount') && <FieldError>{errors.abc_amount}</FieldError>}
                    </Field>
                </div>
            </CardContent>
            {selectedMode && (selectedMode.requires_philgeps || selectedMode.requires_bac_resolution) && (
                <CardFooter className="bg-muted/30 border-t px-4 py-3 sm:px-6 sm:py-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground text-xs font-medium sm:text-sm">Requirements:</span>
                        {selectedMode.requires_philgeps && (
                            <Badge variant="outline" className="text-muted-foreground dark:text-muted-foreground border-amber-500">
                                PhilGEPS Required
                            </Badge>
                        )}
                        {selectedMode.requires_bac_resolution && (
                            <Badge variant="outline" className="text-primary dark:text-primary border-blue-500">
                                BAC Resolution Required
                            </Badge>
                        )}
                    </div>
                </CardFooter>
            )}
        </Card>
    );
}
