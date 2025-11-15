import React from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Building2, Info } from 'lucide-react';
import { MUNICIPAL_OFFICES } from '@/types/constants';
import type { StepProps } from '../types';

export function OfficePurposeStep({ data, setData, errors, clearErrors, hasError }: StepProps) {
    const handleFieldChange = (field: keyof typeof data, value: string): void => {
        clearErrors(field);
        setData(field, value);
    };

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <Card className="border-2 border-primary/20 bg-primary/5">
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                        <div className="rounded-lg bg-primary/10 p-3">
                            <Building2 className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-base font-semibold sm:text-lg">Office & Purpose</h3>
                            <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                Specify the requesting office and the purpose of this procurement.
                            </p>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Form Fields */}
            <Card>
                <CardContent className="space-y-4 p-4 pt-4 sm:space-y-6 sm:p-6 sm:pt-6">
                    {/* Office and End User - Grid */}
                    <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                        {/* Office */}
                        <Field>
                            <FieldLabel>
                                Office
                                <span className="text-destructive">*</span>
                            </FieldLabel>
                        <FieldDescription>
                            Select the office requesting this procurement
                        </FieldDescription>
                        <Select
                            value={data.office}
                            onValueChange={(value) => handleFieldChange('office', value)}
                        >
                            <SelectTrigger
                                className={
                                    hasError('office') ? 'border-destructive ring-destructive/30' : ''
                                }
                            >
                                <SelectValue placeholder="Select office" />
                            </SelectTrigger>
                            <SelectContent>
                                {MUNICIPAL_OFFICES.map((office) => (
                                    <SelectItem key={office.value} value={office.value}>
                                        {office.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                            {hasError('office') && <FieldError>{errors.office}</FieldError>}
                        </Field>

                        {/* End User */}
                        <Field>
                            <FieldLabel>End User (Optional)</FieldLabel>
                        <FieldDescription>
                            If different from the office, specify the actual end user
                        </FieldDescription>
                        <Input
                            value={data.end_user}
                            onChange={(e) => handleFieldChange('end_user', e.target.value)}
                            placeholder="e.g., Accounting Department"
                        />
                    </Field>
                    </div>

                    {/* Purpose and Prepared By - Grid */}
                    <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                        {/* Purpose */}
                        <Field>
                            <FieldLabel>
                                Purpose
                                <span className="ml-1 text-xs text-destructive">*</span>
                            </FieldLabel>
                            <FieldDescription>
                                Explain the purpose and justification for this procurement
                            </FieldDescription>
                            <Textarea
                                value={data.purpose}
                                onChange={(e) => handleFieldChange('purpose', e.target.value)}
                                className={`flex min-h-[120px] w-full rounded-md border bg-background px-3 py-2 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 ${
                                    hasError('purpose')
                                        ? 'border-destructive ring-destructive/30'
                                        : 'border-input'
                                }`}
                                placeholder="Describe the purpose and necessity of this procurement..."
                            />
                            {hasError('purpose') && <FieldError>{errors.purpose}</FieldError>}
                        </Field>

                        {/* Prepared By */}
                        <Field>
                            <FieldLabel>
                                Prepared By
                                <span className="ml-1 text-xs text-destructive">*</span>
                            </FieldLabel>
                            <FieldDescription>Name of the person preparing this request</FieldDescription>
                            <Input
                                value={data.prepared_by}
                                onChange={(e) => handleFieldChange('prepared_by', e.target.value)}
                                className={
                                    hasError('prepared_by')
                                        ? 'border-destructive ring-destructive/30'
                                        : ''
                                }
                                placeholder="Full Name"
                            />
                            {hasError('prepared_by') && <FieldError>{errors.prepared_by}</FieldError>}
                        </Field>
                    </div>
                </CardContent>
            </Card>

            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Note:</strong> The purpose statement should clearly justify why this procurement is necessary for government operations.
                </AlertDescription>
            </Alert>
        </div>
    );
}
