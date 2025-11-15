import React from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { FileText, Info } from 'lucide-react';
import type { StepProps } from '../types';

export function BasicInformationStep({ data, setData, errors, clearErrors, hasError }: StepProps) {
    const handleFieldChange = (field: keyof typeof data, value: string): void => {
        clearErrors(field);
        setData(field, value);
    };

    // Split PR number into parts for individual inputs
    const prParts = data.pr_number.split('-');
    const prPrefix = prParts[0] || 'PR';
    const prYear = prParts[1] || new Date().getFullYear().toString();
    const prSequence1 = prParts[2] || '0000';
    const prSequence2 = prParts[3] || '0000';

    const handlePrPartChange = (
        part: 'prefix' | 'year' | 'seq1' | 'seq2',
        value: string,
    ): void => {
        let newPrefix = prPrefix;
        let newYear = prYear;
        let newSeq1 = prSequence1;
        let newSeq2 = prSequence2;

        switch (part) {
            case 'prefix':
                newPrefix = value;
                break;
            case 'year':
                newYear = value;
                break;
            case 'seq1':
                newSeq1 = value;
                break;
            case 'seq2':
                newSeq2 = value;
                break;
        }

        const newPrNumber = `${newPrefix}-${newYear}-${newSeq1}-${newSeq2}`;
        handleFieldChange('pr_number', newPrNumber);
    };

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <Card className="border-2 border-primary/20 bg-primary/5">
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                        <div className="rounded-lg bg-primary/10 p-3">
                            <FileText className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-base font-semibold sm:text-lg">Basic Information</h3>
                            <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                Enter the fundamental details of the procurement as required by RA
                                9184.
                            </p>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Form Fields */}
            <Card>
                <CardContent className="space-y-4 p-4 pt-4 sm:space-y-6 sm:p-6 sm:pt-6">
                    {/* PR Number and PPMP Reference - Grid */}
                    <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                        {/* PR Number */}
                        <Field>
                            <FieldLabel>
                                Purchase Request Number
                                <span className="ml-1 text-xs text-destructive">*</span>
                            </FieldLabel>
                        <FieldDescription>
                            Format: PR-YYYY-####-#### (e.g., PR-2025-0001-0001)
                        </FieldDescription>
                        <div className="mt-2 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                            <Input
                                value={prPrefix}
                                onChange={(e) => handlePrPartChange('prefix', e.target.value)}
                                className={
                                    hasError('pr_number')
                                        ? 'w-14 border-destructive ring-destructive/30 sm:w-16'
                                        : 'w-14 sm:w-16'
                                }
                                maxLength={3}
                                placeholder="PR"
                                disabled
                                readOnly
                            />
                            <span className="text-muted-foreground">-</span>
                            <Input
                                value={prYear}
                                onChange={(e) => handlePrPartChange('year', e.target.value)}
                                className={
                                    hasError('pr_number')
                                        ? 'w-16 border-destructive ring-destructive/30 sm:w-20'
                                        : 'w-16 sm:w-20'
                                }
                                maxLength={4}
                                placeholder="YYYY"
                                disabled
                                readOnly
                            />
                            <span className="text-muted-foreground">-</span>
                            <Input
                                value={prSequence1}
                                onChange={(e) => handlePrPartChange('seq1', e.target.value)}
                                className={
                                    hasError('pr_number')
                                        ? 'w-16 border-destructive ring-destructive/30 sm:w-20'
                                        : 'w-16 sm:w-20'
                                }
                                maxLength={4}
                                placeholder="0000"
                            />
                            <span className="text-muted-foreground">-</span>
                            <Input
                                value={prSequence2}
                                onChange={(e) => handlePrPartChange('seq2', e.target.value)}
                                className={
                                    hasError('pr_number')
                                        ? 'w-16 border-destructive ring-destructive/30 sm:w-20'
                                        : 'w-16 sm:w-20'
                                }
                                maxLength={4}
                                placeholder="0000"
                            />
                        </div>
                        {hasError('pr_number') && <FieldError>{errors.pr_number}</FieldError>}
                    </Field>

                    {/* PPMP Reference */}
                    <Field>
                        <FieldLabel>
                            PPMP Reference
                            <span className="ml-1 text-xs text-destructive">*</span>
                        </FieldLabel>
                        <FieldDescription>
                            Reference number from the Project Procurement Management Plan
                        </FieldDescription>
                        <Input
                            value={data.ppmp_reference}
                            onChange={(e) => handleFieldChange('ppmp_reference', e.target.value)}
                            className={
                                hasError('ppmp_reference')
                                    ? 'border-destructive ring-destructive/30'
                                    : ''
                            }
                            placeholder="e.g., PPMP-2025-001"
                        />
                        {hasError('ppmp_reference') && (
                            <FieldError>{errors.ppmp_reference}</FieldError>
                        )}
                    </Field>
                    </div>

                    {/* Title and Description - Grid */}
                    <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                        {/* Title */}
                        <Field>
                            <FieldLabel>
                                Procurement Title
                                <span className="ml-1 text-xs text-destructive">*</span>
                            </FieldLabel>
                            <FieldDescription>
                                A clear and concise title for this procurement
                            </FieldDescription>
                            <Input
                                value={data.title}
                                onChange={(e) => handleFieldChange('title', e.target.value)}
                                className={
                                    hasError('title') ? 'border-destructive ring-destructive/30' : ''
                                }
                                placeholder="e.g., Procurement of Office Supplies"
                            />
                            {hasError('title') && <FieldError>{errors.title}</FieldError>}
                        </Field>

                        {/* Description */}
                        <Field>
                            <FieldLabel>
                                Description
                                <span className="ml-1 text-xs text-destructive">*</span>
                            </FieldLabel>
                            <FieldDescription>
                                Detailed description of the items/services to be procured
                            </FieldDescription>
                            <Textarea
                                value={data.description}
                                onChange={(e) => handleFieldChange('description', e.target.value)}
                                className={`flex min-h-[120px] w-full rounded-md border bg-background px-3 py-2 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 ${
                                    hasError('description')
                                        ? 'border-destructive ring-destructive/30'
                                        : 'border-input'
                                }`}
                                placeholder="Provide a detailed description of what needs to be procured..."
                            />
                            {hasError('description') && <FieldError>{errors.description}</FieldError>}
                        </Field>
                    </div>
                </CardContent>
            </Card>

            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>RA 9184:</strong> All fields marked with <span className="text-destructive">*</span> are required under the Government Procurement Reform Act.
                </AlertDescription>
            </Alert>
        </div>
    );
}
