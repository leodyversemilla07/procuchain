import { Head, router, useForm, usePage } from '@inertiajs/react';
import React, { useCallback } from 'react';
import { toast } from 'sonner';
import { initiate } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';
import { index as procurementListIndex } from '@/actions/App/Http/Controllers/ProcurementListController';

import { type BreadcrumbItem } from '@/types';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';

import AppLayout from '@/layouts/app-layout';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
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
import { DatePicker } from '@/components/ui/date-picker';

import { FileText, DollarSign, Building2, MapPin, Info, Upload } from 'lucide-react';

import { FUNDING_SOURCES, MUNICIPAL_OFFICES } from '@/types/constants';

// Type Definitions
interface CategoryOption {
    value: string;
    label: string;
    description: string;
}

interface ProcurementModeOption {
    value: string;
    label: string;
    description: string;
    threshold: number | null;
    requires_philgeps: boolean;
    requires_bac_resolution: boolean;
}

type UseFormData = {
    // Basic Information - REQUIRED per RA 9184
    pr_number: string;
    ppmp_reference: string;
    title: string;
    description: string;

    // Financial Information (ABC = Approved Budget for Contract)
    abc_amount: string;
    funding_source: string;

    // Classification
    category: string;
    procurement_mode: string;

    // Municipal Office Information
    office: string;
    end_user: string;

    // Purpose
    purpose: string;

    // Delivery Details
    delivery_location: string;
    delivery_date: Date | undefined;
    delivery_term_days: string;

    // Prepared By
    prepared_by: string;
};

interface HeaderProps {
    formState?: {
        isComplete?: boolean;
        createdAt?: string;
        lastUpdated?: string;
        reference?: string;
    };
    categories?: CategoryOption[];
    procurementModes?: ProcurementModeOption[];
}

export default function ProcurementInitiationForm({
    formState,
    categories = [],
    procurementModes = [],
}: HeaderProps) {
    const { auth } = usePage<{ auth: { user: { name: string; email: string } } }>().props;

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        { title: 'Procurement Initiation', href: '#' },
    ]);

    const { data, setData, processing, errors, clearErrors } = useForm<UseFormData>({
        // Basic Information
        pr_number: `PR-${new Date().getFullYear()}-0000-0000`,
        ppmp_reference: '',
        title: '',
        description: '',

        // Financial Information
        abc_amount: '',
        funding_source: '',

        // Classification
        category: '',
        procurement_mode: '',

        // Municipal Office Information
        office: '',
        end_user: '',

        // Purpose
        purpose: '',

        // Delivery Details
        delivery_location: '',
        delivery_date: undefined,
        delivery_term_days: '',

        // Prepared By
        prepared_by: auth.user.name,
    });

    const hasError = useCallback(
        (field: string) => {
            return Object.keys(errors).some(
                (error) => error === field || error.startsWith(`${field}.`),
            );
        },
        [errors],
    );

    const handleFieldChange = useCallback(
        (field: keyof UseFormData, value: string | Date | undefined): void => {
            clearErrors(field);
            setData(field, value as string & Date & undefined);
        },
        [clearErrors, setData],
    );

    // Split PR number into parts for individual inputs
    const prParts = data.pr_number.split('-');
    const prPrefix = prParts[0] || 'PR';
    const prYear = prParts[1] || new Date().getFullYear().toString();
    const prSequence1 = prParts[2] || '0000';
    const prSequence2 = prParts[3] || '0000';

    const handlePrPartChange = useCallback(
        (part: 'prefix' | 'year' | 'seq1' | 'seq2', value: string): void => {
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
        },
        [prPrefix, prYear, prSequence1, prSequence2, handleFieldChange],
    );

    // Find selected category and mode for displaying descriptions
    const selectedCategory = categories.find((cat) => cat.value === data.category);
    const selectedMode = procurementModes.find((mode) => mode.value === data.procurement_mode);

    // Form validation
    const isFormValid = useCallback((): boolean => {
        const prNumberRegex = /^PR-\d{4}-\d{4}-\d{4}$/;
        return !!(
            data.pr_number &&
            data.pr_number.trim() !== '' &&
            prNumberRegex.test(data.pr_number) &&
            data.ppmp_reference &&
            data.ppmp_reference.trim() !== '' &&
            data.title &&
            data.title.trim() !== '' &&
            data.description &&
            data.description.trim() !== '' &&
            data.category &&
            data.category.trim() !== '' &&
            data.procurement_mode &&
            data.procurement_mode.trim() !== '' &&
            data.abc_amount &&
            parseFloat(data.abc_amount) > 0 &&
            data.funding_source &&
            data.funding_source.trim() !== '' &&
            data.office &&
            data.office.trim() !== '' &&
            data.purpose &&
            data.purpose.trim() !== '' &&
            data.prepared_by &&
            data.prepared_by.trim() !== '' &&
            data.delivery_location &&
            data.delivery_location.trim() !== '' &&
            data.delivery_date
        );
    }, [data]);

    const handleCreateProcurement = useCallback(() => {
        if (!isFormValid()) {
            toast.error('Please complete all required fields', {
                description: 'Fill in all required fields before submitting.',
            });
            return;
        }

        const submissionToast = toast.loading('Creating Procurement...');

        // Prepare data for submission (no documents)
        const submissionData = {
            pr_number: data.pr_number,
            ppmp_reference: data.ppmp_reference,
            title: data.title,
            description: data.description,
            abc_amount: data.abc_amount,
            funding_source: data.funding_source,
            category: data.category,
            procurement_mode: data.procurement_mode,
            office: data.office,
            end_user: data.end_user,
            purpose: data.purpose,
            delivery_location: data.delivery_location,
            delivery_date: data.delivery_date
                ? data.delivery_date.toISOString().split('T')[0]
                : '',
            delivery_term_days: data.delivery_term_days,
            prepared_by: data.prepared_by,
        };

        router.post(initiate().url, submissionData, {
            onSuccess: () => {
                toast.success('Procurement created successfully!', {
                    id: submissionToast,
                    description:
                        'Redirecting to procurement list. You can upload documents from there.',
                });

                // Always redirect to procurement list after creation
                setTimeout(() => {
                    router.visit(procurementListIndex['/bac-secretariat/procurements-list'].url(), {
                        preserveState: false,
                        replace: true,
                    });
                }, 1500);
            },
            onError: (formErrors: Record<string, string>) => {
                toast.error('Failed to submit', {
                    id: submissionToast,
                    description: Object.values(formErrors)[0],
                });
            },
            preserveScroll: true,
        });
    }, [data, isFormValid]);

    const onSubmit = useCallback(
        (e: React.FormEvent) => {
            e.preventDefault();
            handleCreateProcurement();
        },
        [handleCreateProcurement],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Initiate Procurement" />

            <div className="mx-auto max-w-7xl space-y-4 p-3 sm:space-y-6 sm:p-6 lg:p-8">
                {/* Modern Header */}
                <div className="relative overflow-hidden rounded-xl border bg-linear-to-br from-primary/5 via-primary/3 to-background p-4 shadow-sm sm:rounded-2xl sm:p-6 lg:p-8">
                    <div className="relative z-10">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex gap-3 sm:gap-4">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 ring-1 ring-primary/20 sm:h-12 sm:w-12 sm:rounded-xl lg:h-14 lg:w-14">
                                    <FileText className="h-5 w-5 text-primary sm:h-6 sm:w-6 lg:h-7 lg:w-7" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <h1 className="text-xl font-bold tracking-tight sm:text-2xl lg:text-3xl">
                                        New Procurement
                                    </h1>
                                    <p className="mt-1 text-xs text-muted-foreground sm:mt-1.5 sm:max-w-2xl sm:text-sm lg:text-base">
                                        Create a new procurement request with all required information.
                                        <span className="hidden sm:inline"> Documents will be uploaded progressively after creation.</span>
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-1.5 sm:mt-3 sm:gap-2">
                                        <Badge
                                            variant="secondary"
                                            className="rounded-full px-2 py-0.5 text-[10px] font-medium sm:px-3 sm:py-1 sm:text-xs"
                                        >
                                            <span className="mr-1 inline-block h-1 w-1 rounded-full bg-primary sm:mr-1.5 sm:h-1.5 sm:w-1.5"></span>
                                            <span className="hidden xs:inline">Procurement </span>Initiation
                                        </Badge>
                                        {formState?.reference && (
                                            <Badge
                                                variant="outline"
                                                className="rounded-full px-2 py-0.5 text-[10px] font-medium sm:px-3 sm:py-1 sm:text-xs"
                                            >
                                                {formState.reference}
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Decorative background elements - hidden on mobile */}
                    <div className="absolute -right-8 -top-8 hidden h-32 w-32 rounded-full bg-primary/5 blur-3xl sm:block"></div>
                    <div className="absolute -bottom-8 -left-8 hidden h-32 w-32 rounded-full bg-primary/5 blur-3xl sm:block"></div>
                </div>

                <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
                    {/* Section 1: Basic Information */}
                    <Card className="overflow-hidden rounded-lg border-0 shadow-sm ring-1 ring-border/50 sm:rounded-xl">
                        <CardHeader className="border-l-3 border-l-primary bg-primary/2 px-4 py-4 sm:border-l-4 sm:px-6 sm:py-5">
                            <div className="flex items-center gap-2.5 sm:gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 ring-1 ring-primary/20 sm:h-10 sm:w-10">
                                    <FileText className="h-4 w-4 text-primary sm:h-5 sm:w-5" />
                                </div>
                                <div className="min-w-0 space-y-1">
                                    <CardTitle className="text-base font-semibold tracking-tight sm:text-lg">
                                        Basic Information
                                    </CardTitle>
                                    <CardDescription className="hidden text-sm xs:block">
                                        Required procurement details per RA 9184
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 p-4 sm:space-y-6 sm:p-6">
                            {/* PR Number and PPMP Reference - Grid */}
                            <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                                {/* PR Number */}
                                <Field>
                                    <FieldLabel htmlFor="pr_prefix">
                                        Purchase Request Number
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Format: PR-YYYY-####-#### (e.g., PR-2025-0001-0001)
                                    </FieldDescription>
                                    <div className="mt-2 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                        <Input
                                            id="pr_prefix"
                                            name="pr_prefix"
                                            value={prPrefix}
                                            onChange={(e) =>
                                                handlePrPartChange('prefix', e.target.value)
                                            }
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
                                            id="pr_year"
                                            name="pr_year"
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
                                            id="pr_sequence1"
                                            name="pr_sequence1"
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
                                            id="pr_sequence2"
                                            name="pr_sequence2"
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
                                    {hasError('pr_number') && (
                                        <FieldError>{errors.pr_number}</FieldError>
                                    )}
                                </Field>

                                {/* PPMP Reference */}
                                <Field>
                                    <FieldLabel htmlFor="ppmp_reference">
                                        PPMP Reference
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Reference number from the Project Procurement Management Plan
                                    </FieldDescription>
                                    <Input
                                        id="ppmp_reference"
                                        name="ppmp_reference"
                                        value={data.ppmp_reference}
                                        onChange={(e) =>
                                            handleFieldChange('ppmp_reference', e.target.value)
                                        }
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
                                    <FieldLabel htmlFor="title">
                                        Procurement Title
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        A clear and concise title for this procurement
                                    </FieldDescription>
                                    <Input
                                        id="title"
                                        name="title"
                                        value={data.title}
                                        onChange={(e) => handleFieldChange('title', e.target.value)}
                                        className={
                                            hasError('title')
                                                ? 'border-destructive ring-destructive/30'
                                                : ''
                                        }
                                        placeholder="e.g., Procurement of Office Supplies"
                                    />
                                    {hasError('title') && <FieldError>{errors.title}</FieldError>}
                                </Field>

                                {/* Description */}
                                <Field>
                                    <FieldLabel htmlFor="description">
                                        Description
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Detailed description of the items/services to be procured
                                    </FieldDescription>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        value={data.description}
                                        onChange={(e) =>
                                            handleFieldChange('description', e.target.value)
                                        }
                                        className={`flex min-h-[120px] w-full rounded-md border bg-background px-3 py-2 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 ${hasError('description')
                                            ? 'border-destructive ring-destructive/30'
                                            : 'border-input'
                                            }`}
                                        placeholder="Provide a detailed description of what needs to be procured..."
                                    />
                                    {hasError('description') && (
                                        <FieldError>{errors.description}</FieldError>
                                    )}
                                </Field>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Section 2: Classification & Budget */}
                    <Card className="overflow-hidden rounded-lg border-0 shadow-sm ring-1 ring-border/50 sm:rounded-xl">
                        <CardHeader className="border-l-3 border-l-emerald-500 bg-emerald-500/2 px-4 py-4 sm:border-l-4 sm:px-6 sm:py-5">
                            <div className="flex items-center gap-2.5 sm:gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 ring-1 ring-emerald-500/20 sm:h-10 sm:w-10">
                                    <DollarSign className="h-4 w-4 text-emerald-600 dark:text-emerald-500 sm:h-5 sm:w-5" />
                                </div>
                                <div className="min-w-0 space-y-1">
                                    <CardTitle className="text-base font-semibold tracking-tight sm:text-lg">
                                        Classification & Budget
                                    </CardTitle>
                                    <CardDescription className="hidden text-sm xs:block">
                                        Procurement type and approved contract budget
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 p-4 sm:space-y-6 sm:p-6">
                            {/* Category and Procurement Mode - Grid */}
                            <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                                {/* Category */}
                                <Field>
                                    <FieldLabel htmlFor="category">
                                        Category
                                        <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Select the type of procurement (Goods, Services, or
                                        Infrastructure)
                                    </FieldDescription>
                                    <Select
                                        value={data.category}
                                        onValueChange={(value) =>
                                            handleFieldChange('category', value)
                                        }
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
                                                <SelectItem
                                                    key={category.value}
                                                    value={category.value}
                                                    className="py-3"
                                                >
                                                    <div className="flex flex-col gap-1">
                                                        <span className="font-medium">
                                                            {category.label}
                                                        </span>
                                                        <span className="line-clamp-2 text-xs text-muted-foreground">
                                                            {category.description}
                                                        </span>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {hasError('category') && (
                                        <FieldError>{errors.category}</FieldError>
                                    )}
                                </Field>

                                {/* Procurement Mode */}
                                <Field>
                                    <FieldLabel htmlFor="procurement_mode">
                                        Procurement Mode
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Select the appropriate procurement method per RA 9184
                                    </FieldDescription>
                                    <Select
                                        value={data.procurement_mode}
                                        onValueChange={(value) =>
                                            handleFieldChange('procurement_mode', value)
                                        }
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
                                                <SelectItem
                                                    key={mode.value}
                                                    value={mode.value}
                                                    className="py-3"
                                                >
                                                    <div className="flex flex-col gap-1">
                                                        <span className="font-medium">{mode.label}</span>
                                                        <span className="line-clamp-2 text-xs text-muted-foreground">
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
                                </Field>
                            </div>

                            {/* ABC Amount and Funding Source - Grid */}
                            <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                                {/* ABC Amount */}
                                <Field>
                                    <FieldLabel htmlFor="abc_amount">
                                        ABC Amount (₱)
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Approved Budget for the Contract - the maximum amount allocated
                                    </FieldDescription>
                                    <Input
                                        id="abc_amount"
                                        name="abc_amount"
                                        type="number"
                                        value={data.abc_amount}
                                        onChange={(e) =>
                                            handleFieldChange('abc_amount', e.target.value)
                                        }
                                        className={
                                            hasError('abc_amount')
                                                ? 'border-destructive ring-destructive/30'
                                                : ''
                                        }
                                        placeholder="0.00"
                                        step="0.01"
                                        min="0"
                                    />
                                    {hasError('abc_amount') && (
                                        <FieldError>{errors.abc_amount}</FieldError>
                                    )}
                                    {data.abc_amount && parseFloat(data.abc_amount) > 0 && (
                                        <div className="mt-2 text-sm text-muted-foreground">
                                            Formatted: ₱
                                            {parseFloat(data.abc_amount).toLocaleString('en-PH', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            })}
                                        </div>
                                    )}
                                </Field>

                                {/* Funding Source */}
                                <Field>
                                    <FieldLabel htmlFor="funding_source">
                                        Funding Source
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Select the source of funds for this procurement
                                    </FieldDescription>
                                    <Select
                                        value={data.funding_source}
                                        onValueChange={(value) =>
                                            handleFieldChange('funding_source', value)
                                        }
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
                        <CardFooter className="border-t bg-muted/30 px-4 py-3 sm:px-6 sm:py-4">
                            <div className="space-y-3">
                                <div className="flex items-start gap-2">
                                    <Info className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                    <p className="text-xs text-muted-foreground sm:text-sm">
                                        <strong className="font-medium text-foreground">ABC:</strong> The ABC must be determined based on prevailing
                                        market prices and should not exceed the appropriated budget.
                                    </p>
                                </div>
                                {selectedCategory && (
                                    <div className="flex items-start gap-2 border-t pt-3">
                                        <Info className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-500" />
                                        <p className="text-xs text-muted-foreground sm:text-sm">
                                            <strong className="font-medium text-foreground">Category:</strong> {selectedCategory.description}
                                        </p>
                                    </div>
                                )}
                                {selectedMode && (
                                    <div className="space-y-2 border-t pt-3">
                                        <div className="flex items-start gap-2">
                                            <Info className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-500" />
                                            <p className="text-xs text-muted-foreground sm:text-sm">
                                                <strong className="font-medium text-foreground">Mode:</strong> {selectedMode.description}
                                            </p>
                                        </div>
                                        {(selectedMode.requires_philgeps || selectedMode.requires_bac_resolution) && (
                                            <div className="ml-6 flex flex-wrap gap-2">
                                                {selectedMode.requires_philgeps && (
                                                    <Badge variant="outline" className="border-amber-500 text-amber-700 dark:text-amber-300">
                                                        PhilGEPS Required
                                                    </Badge>
                                                )}
                                                {selectedMode.requires_bac_resolution && (
                                                    <Badge variant="outline" className="border-blue-500 text-blue-700 dark:text-blue-300">
                                                        BAC Resolution Required
                                                    </Badge>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        </CardFooter>
                    </Card>

                    {/* Section 3: Office & Purpose */}
                    <Card className="overflow-hidden rounded-lg border-0 shadow-sm ring-1 ring-border/50 sm:rounded-xl">
                        <CardHeader className="border-l-3 border-l-blue-500 bg-blue-500/2 px-4 py-4 sm:border-l-4 sm:px-6 sm:py-5">
                            <div className="flex items-center gap-2.5 sm:gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 ring-1 ring-blue-500/20 sm:h-10 sm:w-10">
                                    <Building2 className="h-4 w-4 text-blue-600 dark:text-blue-500 sm:h-5 sm:w-5" />
                                </div>
                                <div className="min-w-0 space-y-1">
                                    <CardTitle className="text-base font-semibold tracking-tight sm:text-lg">
                                        Office & Purpose
                                    </CardTitle>
                                    <CardDescription className="hidden text-sm xs:block">
                                        Requesting office and procurement justification
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 p-4 sm:space-y-6 sm:p-6">
                            {/* Office and End User - Grid */}
                            <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                                {/* Office */}
                                <Field>
                                    <FieldLabel htmlFor="office">
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
                                                hasError('office')
                                                    ? 'border-destructive ring-destructive/30'
                                                    : ''
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
                                    <FieldLabel htmlFor="end_user">End User (Optional)</FieldLabel>
                                    <FieldDescription>
                                        If different from the office, specify the actual end user
                                    </FieldDescription>
                                    <Input
                                        id="end_user"
                                        name="end_user"
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
                                    <FieldLabel htmlFor="purpose">
                                        Purpose
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Explain the purpose and justification for this procurement
                                    </FieldDescription>
                                    <Textarea
                                        id="purpose"
                                        name="purpose"
                                        value={data.purpose}
                                        onChange={(e) => handleFieldChange('purpose', e.target.value)}
                                        className={`flex min-h-[120px] w-full rounded-md border bg-background px-3 py-2 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 ${hasError('purpose')
                                            ? 'border-destructive ring-destructive/30'
                                            : 'border-input'
                                            }`}
                                        placeholder="Describe the purpose and necessity of this procurement..."
                                    />
                                    {hasError('purpose') && <FieldError>{errors.purpose}</FieldError>}
                                </Field>

                                {/* Prepared By */}
                                <Field>
                                    <FieldLabel htmlFor="prepared_by">
                                        Prepared By
                                        <span className="ml-1 text-xs text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Name of the person preparing this request
                                    </FieldDescription>
                                    <Input
                                        id="prepared_by"
                                        name="prepared_by"
                                        value={data.prepared_by}
                                        onChange={(e) =>
                                            handleFieldChange('prepared_by', e.target.value)
                                        }
                                        className={
                                            hasError('prepared_by')
                                                ? 'border-destructive ring-destructive/30'
                                                : ''
                                        }
                                        placeholder="Full Name"
                                    />
                                    {hasError('prepared_by') && (
                                        <FieldError>{errors.prepared_by}</FieldError>
                                    )}
                                </Field>
                            </div>

                        </CardContent>
                        <CardFooter className="border-t bg-muted/30 px-4 py-3 sm:px-6 sm:py-4">
                            <div className="flex items-start gap-2">
                                <Info className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                <p className="text-xs text-muted-foreground sm:text-sm">
                                    <strong className="font-medium text-foreground">Note:</strong> The purpose statement should clearly justify
                                    why this procurement is necessary for government operations.
                                </p>
                            </div>
                        </CardFooter>
                    </Card>

                    {/* Section 4: Delivery Details */}
                    <Card className="overflow-hidden rounded-lg border-0 shadow-sm ring-1 ring-border/50 sm:rounded-xl">
                        <CardHeader className="border-l-3 border-l-amber-500 bg-amber-500/2 px-4 py-4 sm:border-l-4 sm:px-6 sm:py-5">
                            <div className="flex items-center gap-2.5 sm:gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 ring-1 ring-amber-500/20 sm:h-10 sm:w-10">
                                    <MapPin className="h-4 w-4 text-amber-600 dark:text-amber-500 sm:h-5 sm:w-5" />
                                </div>
                                <div className="min-w-0 space-y-1">
                                    <CardTitle className="text-base font-semibold tracking-tight sm:text-lg">
                                        Delivery Details
                                    </CardTitle>
                                    <CardDescription className="hidden text-sm xs:block">
                                        Delivery location, timeline, and terms
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 p-4 sm:space-y-6 sm:p-6">
                            {/* Delivery Location, Date, and Term Days - Grid */}
                            <div className="grid gap-4 sm:gap-6 lg:grid-cols-3">
                                {/* Delivery Location */}
                                <Field>
                                    <FieldLabel htmlFor="delivery_location">
                                        Delivery Location
                                        <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Where should the goods/services be delivered?
                                    </FieldDescription>
                                    <Input
                                        id="delivery_location"
                                        name="delivery_location"
                                        value={data.delivery_location}
                                        onChange={(e) =>
                                            handleFieldChange('delivery_location', e.target.value)
                                        }
                                        className={
                                            hasError('delivery_location')
                                                ? 'border-destructive ring-destructive/30'
                                                : ''
                                        }
                                        placeholder="e.g., Municipal Hall, Main Office"
                                    />
                                    {hasError('delivery_location') && (
                                        <FieldError>{errors.delivery_location}</FieldError>
                                    )}
                                </Field>

                                {/* Delivery Date */}
                                <Field>
                                    <FieldLabel htmlFor="delivery_date">
                                        Delivery Date
                                        <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        Expected date for delivery or completion
                                    </FieldDescription>
                                    <DatePicker
                                        id="delivery_date"
                                        date={data.delivery_date}
                                        onDateChange={(date: Date | undefined) =>
                                            handleFieldChange('delivery_date', date)
                                        }
                                        minDate={new Date()}
                                        className={
                                            hasError('delivery_date')
                                                ? 'border-destructive ring-destructive/30'
                                                : ''
                                        }
                                    />
                                    {hasError('delivery_date') && (
                                        <FieldError>{errors.delivery_date}</FieldError>
                                    )}
                                </Field>

                                {/* Delivery Term Days */}
                                <Field>
                                    <FieldLabel htmlFor="delivery_term_days">Delivery Term (Days)</FieldLabel>
                                    <FieldDescription>
                                        Number of calendar days for delivery from contract signing
                                        (optional)
                                    </FieldDescription>
                                    <Input
                                        id="delivery_term_days"
                                        name="delivery_term_days"
                                        type="number"
                                        value={data.delivery_term_days}
                                        onChange={(e) =>
                                            handleFieldChange('delivery_term_days', e.target.value)
                                        }
                                        placeholder="e.g., 30"
                                        min="0"
                                    />
                                </Field>
                            </div>

                        </CardContent>
                        <CardFooter className="border-t bg-muted/30 px-4 py-3 sm:px-6 sm:py-4">
                            <div className="flex items-start gap-2">
                                <Info className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                <p className="text-xs text-muted-foreground sm:text-sm">
                                    <strong className="font-medium text-foreground">Timeline:</strong> Ensure the delivery date allows
                                    sufficient time for the procurement process and contractor
                                    preparation.
                                </p>
                            </div>
                        </CardFooter>
                    </Card>

                    {/* Next Steps Info */}
                    <Card className="overflow-hidden rounded-lg border-0 bg-linear-to-br from-blue-50 to-indigo-50 shadow-sm ring-1 ring-blue-200/50 dark:from-blue-950/20 dark:to-indigo-950/20 dark:ring-blue-800/30 sm:rounded-xl">
                        <CardContent className="p-4 sm:p-6">
                            <div className="flex gap-3 sm:gap-4">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 ring-1 ring-blue-500/20 sm:h-12 sm:w-12 sm:rounded-xl">
                                    <Upload className="h-5 w-5 text-blue-600 dark:text-blue-400 sm:h-6 sm:w-6" />
                                </div>
                                <div className="min-w-0 flex-1 space-y-1 sm:space-y-2">
                                    <h3 className="text-sm font-semibold text-blue-900 dark:text-blue-100 sm:text-base">
                                        Next: Progressive Document Upload
                                    </h3>
                                    <p className="text-xs leading-relaxed text-blue-700/90 dark:text-blue-300/90 sm:text-sm">
                                        After creating this procurement, you'll be redirected to upload
                                        required documents progressively. <span className="hidden sm:inline">You can upload them one at a
                                            time and save your progress.</span>
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit Button - Sticky on mobile */}
                    <div className="sticky bottom-0 left-0 right-0 z-20 -mx-3 border-t bg-background/95 px-3 py-3 shadow-lg backdrop-blur-md supports-backdrop-filter:bg-background/80 sm:static sm:mx-0 sm:rounded-none sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:shadow-none sm:backdrop-blur-none">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <p className="hidden text-sm text-muted-foreground lg:block">
                                All fields marked with <span className="text-destructive">*</span> are required
                            </p>
                            <Button
                                type="submit"
                                disabled={processing || !isFormValid()}
                                className="w-full gap-2 shadow-sm sm:w-auto sm:min-w-[180px] lg:min-w-[200px]"
                                size="lg"
                            >
                                {processing ? (
                                    <>
                                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                        <span className="text-sm sm:text-base">Creating...</span>
                                    </>
                                ) : (
                                    <>
                                        <FileText className="h-4 w-4" />
                                        <span className="text-sm sm:text-base">Create Procurement</span>
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
