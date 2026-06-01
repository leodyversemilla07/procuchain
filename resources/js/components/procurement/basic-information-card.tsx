import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { UseFormData } from '@/hooks/use-procurement-initiation';
import { FileText } from 'lucide-react';

interface BasicInformationCardProps {
    data: UseFormData;
    errors: Record<string, string>;
    hasError: (field: string) => boolean;
    handleFieldChange: (field: keyof UseFormData, value: string | Date | undefined) => void;
    prPrefix: string;
    prYear: string;
    prSequence1: string;
    prSequence2: string;
    handlePrPartChange: (part: 'prefix' | 'year' | 'seq1' | 'seq2', value: string) => void;
    selectedDescriptionLabel: string;
    PROCUREMENT_DESCRIPTIONS: readonly { value: string; label: string }[];
}

export function BasicInformationCard({
    data,
    errors,
    hasError,
    handleFieldChange,
    prPrefix,
    prYear,
    prSequence1,
    prSequence2,
    handlePrPartChange,
    selectedDescriptionLabel,
    PROCUREMENT_DESCRIPTIONS,
}: BasicInformationCardProps) {
    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
            <CardHeader className="space-y-1 pb-2 sm:pb-4">
                <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                    <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                    Basic Information
                </CardTitle>
                <CardDescription className="text-muted-foreground text-sm">Required procurement details per RA 12009 (NGPA)</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 p-4 sm:space-y-6 sm:p-6">
                {/* PR Number and PPMP Reference - Grid */}
                <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                    {/* PR Number */}
                    <Field>
                        <FieldLabel htmlFor="pr_prefix">
                            Purchase Request Number
                            <span className="text-destructive ml-1 text-xs">*</span>
                        </FieldLabel>
                        <FieldDescription>Format: PR-YYYY-000-0000 (e.g., PR-2025-001-0001)</FieldDescription>
                        <div className="mt-2 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                            <Input
                                id="pr_prefix"
                                name="pr_prefix"
                                value={prPrefix}
                                onChange={(e) => handlePrPartChange('prefix', e.target.value)}
                                className={hasError('pr_number') ? 'border-destructive ring-destructive/30 w-14 sm:w-16' : 'w-14 sm:w-16'}
                                maxLength={3}
                                placeholder="PR"
                                disabled
                                readOnly
                            />
                            <span className="text-muted-foreground">-</span>
                            <Input
                                id="pr_year"
                                name="pr_year"
                                value={prYear}
                                onChange={(e) => handlePrPartChange('year', e.target.value)}
                                className={hasError('pr_number') ? 'border-destructive ring-destructive/30 w-16 sm:w-20' : 'w-16 sm:w-20'}
                                maxLength={4}
                                placeholder="YYYY"
                                disabled
                                readOnly
                            />
                            <span className="text-muted-foreground">-</span>
                            <Input
                                id="pr_sequence1"
                                name="pr_sequence1"
                                value={prSequence1}
                                onChange={(e) => handlePrPartChange('seq1', e.target.value)}
                                className={hasError('pr_number') ? 'border-destructive ring-destructive/30 w-14 sm:w-16' : 'w-14 sm:w-16'}
                                maxLength={3}
                                placeholder="000"
                            />
                            <span className="text-muted-foreground">-</span>
                            <Input
                                id="pr_sequence2"
                                name="pr_sequence2"
                                value={prSequence2}
                                onChange={(e) => handlePrPartChange('seq2', e.target.value)}
                                className={hasError('pr_number') ? 'border-destructive ring-destructive/30 w-16 sm:w-20' : 'w-16 sm:w-20'}
                                maxLength={4}
                                placeholder="0000"
                            />
                        </div>
                        {hasError('pr_number') && <FieldError>{errors.pr_number}</FieldError>}
                    </Field>

                    {/* PPMP/AIP Code Reference */}
                    <Field>
                        <FieldLabel htmlFor="app_reference">
                            AIP Code Reference
                            <span className="text-destructive ml-1 text-xs">*</span>
                        </FieldLabel>
                        <FieldDescription>Reference number from the Annual Investment Plan</FieldDescription>
                        <Input
                            id="app_reference"
                            name="app_reference"
                            value={data.app_reference}
                            onChange={(e) => handleFieldChange('app_reference', e.target.value)}
                            className={hasError('app_reference') ? 'border-destructive ring-destructive/30' : ''}
                            placeholder="e.g., AIP-2025-001"
                        />
                        {hasError('app_reference') && <FieldError>{errors.app_reference}</FieldError>}
                    </Field>
                </div>

                {/* Title and Description - Grid */}
                <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                    {/* Title */}
                    <Field>
                        <FieldLabel htmlFor="title">
                            Procurement Title
                            <span className="text-destructive ml-1 text-xs">*</span>
                        </FieldLabel>
                        <FieldDescription>A clear and concise title for this procurement</FieldDescription>
                        <Input
                            id="title"
                            name="title"
                            value={data.title}
                            onChange={(e) => handleFieldChange('title', e.target.value)}
                            className={hasError('title') ? 'border-destructive ring-destructive/30' : ''}
                            placeholder="e.g., Procurement of Office Supplies"
                        />
                        {hasError('title') && <FieldError>{errors.title}</FieldError>}
                    </Field>

                    {/* Description */}
                    <Field>
                        <FieldLabel htmlFor="description">
                            Description
                            <span className="text-destructive ml-1 text-xs">*</span>
                        </FieldLabel>
                        <FieldDescription>Type of items/services to be procured</FieldDescription>
                        <Select
                            value={data.description}
                            onValueChange={(value) => {
                                if (!value) return;
                                handleFieldChange('description', value);
                                if (value !== 'Other') {
                                    handleFieldChange('other_description', '');
                                }
                            }}
                        >
                            <SelectTrigger className={hasError('description') ? 'border-destructive ring-destructive/30' : ''}>
                                <SelectValue placeholder="Select description">{() => selectedDescriptionLabel}</SelectValue>
                            </SelectTrigger>
                            <SelectContent className="max-h-60 overflow-y-auto">
                                <SelectGroup>
                                    {PROCUREMENT_DESCRIPTIONS.map((desc) => (
                                        <SelectItem key={desc.value} value={desc.value}>
                                            {desc.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        {hasError('description') && <FieldError>{errors.description}</FieldError>}
                        {data.description === 'Other' && (
                            <div className="mt-3">
                                <Input
                                    id="other_description"
                                    name="other_description"
                                    type="text"
                                    value={data.other_description}
                                    onChange={(e) => handleFieldChange('other_description', e.target.value)}
                                    className={hasError('other_description') ? 'border-destructive ring-destructive/30' : ''}
                                    placeholder="Please specify the description"
                                />
                                {hasError('other_description') && <FieldError>{errors.other_description}</FieldError>}
                            </div>
                        )}
                    </Field>
                </div>
            </CardContent>
        </Card>
    );
}
