import { initiate } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';
import { index as procurementListIndex } from '@/actions/App/Http/Controllers/ProcurementListController';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import React, { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

import { type BreadcrumbItem } from '@/types';
import { UserRole } from '@/types/enums';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';

import AppLayout from '@/layouts/app-layout';

import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';

import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import { useBlockchainJob } from '@/hooks/use-blockchain-job';
import { Spinner } from '@/components/ui/spinner';
import { AlertCircle, Building2, CheckCircle2, DollarSign, FileText, Save, Trash2, Upload } from 'lucide-react';

import { FUNDING_SOURCES, MUNICIPAL_OFFICES } from '@/types/constants';

// Draft storage key
const DRAFT_STORAGE_KEY = 'procurement_initiation_draft';

// Common procurement descriptions for LGU
const PROCUREMENT_DESCRIPTIONS = [
    { value: 'Office Supplies and Materials', label: 'Office Supplies and Materials' },
    { value: 'Computer Equipment and Accessories', label: 'Computer Equipment and Accessories' },
    { value: 'Furniture and Fixtures', label: 'Furniture and Fixtures' },
    { value: 'Medical Supplies and Equipment', label: 'Medical Supplies and Equipment' },
    { value: 'Agricultural Supplies and Equipment', label: 'Agricultural Supplies and Equipment' },
    { value: 'Construction Materials', label: 'Construction Materials' },
    { value: 'Vehicle Parts and Accessories', label: 'Vehicle Parts and Accessories' },
    { value: 'Fuel, Oil, and Lubricants', label: 'Fuel, Oil, and Lubricants' },
    { value: 'Janitorial Supplies', label: 'Janitorial Supplies' },
    { value: 'Electrical Supplies', label: 'Electrical Supplies' },
    { value: 'Plumbing Supplies', label: 'Plumbing Supplies' },
    { value: 'Food and Catering Services', label: 'Food and Catering Services' },
    { value: 'Printing and Publication Services', label: 'Printing and Publication Services' },
    { value: 'Security Services', label: 'Security Services' },
    { value: 'Janitorial Services', label: 'Janitorial Services' },
    { value: 'Repair and Maintenance Services', label: 'Repair and Maintenance Services' },
    { value: 'Consulting Services', label: 'Consulting Services' },
    { value: 'Construction of Building/Structure', label: 'Construction of Building/Structure' },
    { value: 'Road Construction/Rehabilitation', label: 'Road Construction/Rehabilitation' },
    { value: 'Drainage/Flood Control', label: 'Drainage/Flood Control' },
    { value: 'Water System Installation/Repair', label: 'Water System Installation/Repair' },
    { value: 'Other', label: 'Other (Please specify)' },
] as const;

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

interface NegotiatedProcurementTypeOption {
    value: string;
    label: string;
}

type UseFormData = {
    // Basic Information - REQUIRED per RA 12009 (NGPA)
    pr_number: string;
    app_reference: string;
    title: string;
    description: string;
    other_description: string;

    // Financial Information (ABC = Approved Budget for Contract)
    abc_amount: string;
    funding_source: string;
    other_funding_source: string;

    // Classification
    category: string;
    procurement_mode: string;
    negotiated_procurement_type: string;

    // Municipal Office Information
    office: string;
    end_user: string;
    other_end_user: string;

    // Prepared By
    prepared_by: string;
};

interface DraftData extends UseFormData {
    savedAt: string;
}

interface HeaderProps {
    categories?: CategoryOption[];
    procurementModes?: ProcurementModeOption[];
    negotiatedProcurementTypes?: NegotiatedProcurementTypeOption[];
}

export default function ProcurementInitiationForm({ categories = [], procurementModes = [], negotiatedProcurementTypes = [] }: HeaderProps) {
    const { auth } = usePage<{ auth: { user: { name: string; email: string } } }>().props;
    const { submitAndPoll } = useBlockchainJob();

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [{ title: 'Procurement Initiation', href: '#' }]);

    // Draft state
    const [hasDraft, setHasDraft] = useState(false);
    const [draftSavedAt, setDraftSavedAt] = useState<string | null>(null);
    const [isSavingDraft, setIsSavingDraft] = useState(false);
    const [showDraftBanner, setShowDraftBanner] = useState(false);

    const { data, setData, processing, errors, clearErrors, reset } = useForm<UseFormData>({
        // Basic Information
        pr_number: `PR-${new Date().getFullYear()}-000-0000`,
        app_reference: '',
        title: '',
        description: '',
        other_description: '',

        // Financial Information
        abc_amount: '',
        funding_source: '',
        other_funding_source: '',

        // Classification
        category: '',
        procurement_mode: '',
        negotiated_procurement_type: '',

        // Municipal Office Information
        office: '',
        end_user: '',
        other_end_user: '',

        // Prepared By
        prepared_by: auth.user.name,
    });

    // Draft management functions
    const loadDraft = useCallback((): DraftData | null => {
        try {
            const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
            if (saved) {
                return JSON.parse(saved) as DraftData;
            }
        } catch (e) {
            console.error('Failed to load draft:', e);
        }
        return null;
    }, []);

    const saveDraft = useCallback(() => {
        setIsSavingDraft(true);
        try {
            const draftData: DraftData = {
                ...data,
                savedAt: new Date().toISOString(),
            };
            localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draftData));
            setHasDraft(true);
            setDraftSavedAt(draftData.savedAt);
            toast.success('Draft saved', {
                description: 'Your progress has been saved locally.',
            });
        } catch (e) {
            console.error('Failed to save draft:', e);
            toast.error('Failed to save draft', {
                description: 'Could not save your progress.',
            });
        } finally {
            setIsSavingDraft(false);
        }
    }, [data]);

    const clearDraft = useCallback(() => {
        try {
            localStorage.removeItem(DRAFT_STORAGE_KEY);
            setHasDraft(false);
            setDraftSavedAt(null);
            setShowDraftBanner(false);
            toast.info('Draft cleared', {
                description: 'Your saved draft has been removed.',
            });
        } catch (e) {
            console.error('Failed to clear draft:', e);
        }
    }, []);

    const restoreDraft = useCallback(
        (draft: DraftData) => {
            // Restore all fields from draft
            Object.keys(draft).forEach((key) => {
                if (key !== 'savedAt' && key in data) {
                    setData(key as keyof UseFormData, draft[key as keyof UseFormData]);
                }
            });
            setDraftSavedAt(draft.savedAt);
            setShowDraftBanner(false);
            toast.success('Draft restored', {
                description: 'Your previous progress has been loaded.',
            });
        },
        [data, setData],
    );

    const discardDraft = useCallback(() => {
        clearDraft();
        // Reset form to initial state
        reset();
        setData('prepared_by', auth.user.name);
    }, [clearDraft, reset, setData, auth.user.name]);

    // Check for existing draft on mount
    useEffect(() => {
        const draft = loadDraft();
        if (draft) {
            setHasDraft(true);
            setDraftSavedAt(draft.savedAt);
            setShowDraftBanner(true);
        }
    }, [loadDraft]);

    // Auto-save draft when data changes (debounced)
    useEffect(() => {
        // Only auto-save if there's meaningful data
        const hasContent = data.title.trim() !== '' || data.app_reference.trim() !== '' || data.abc_amount.trim() !== '';

        if (!hasContent) return;

        const timeoutId = setTimeout(() => {
            try {
                const draftData: DraftData = {
                    ...data,
                    savedAt: new Date().toISOString(),
                };
                localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draftData));
                setHasDraft(true);
                setDraftSavedAt(draftData.savedAt);
            } catch (e) {
                console.error('Auto-save failed:', e);
            }
        }, 2000); // Auto-save after 2 seconds of inactivity

        return () => clearTimeout(timeoutId);
    }, [data]);

    const hasError = useCallback(
        (field: string) => {
            return Object.keys(errors).some((error) => error === field || error.startsWith(`${field}.`));
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
    const prSequence1 = prParts[2] ?? '';
    const prSequence2 = prParts[3] ?? '';

    const handlePrPartChange = useCallback(
        (part: 'prefix' | 'year' | 'seq1' | 'seq2', value: string): void => {
            // Only allow digits for sequence parts
            if ((part === 'seq1' || part === 'seq2') && value !== '' && !/^\d*$/.test(value)) {
                return;
            }

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

    // Find selected mode for displaying requirements
    const selectedMode = procurementModes.find((mode) => mode.value === data.procurement_mode);

    // Form validation
    const isFormValid = useCallback((): boolean => {
        const prNumberRegex = /^PR-\d{4}-\d{3}-\d{4}$/;
        const isNegotiatedProcurement = data.procurement_mode === 'negotiated_procurement';

        return !!(
            data.pr_number &&
            data.pr_number.trim() !== '' &&
            prNumberRegex.test(data.pr_number) &&
            data.app_reference &&
            data.app_reference.trim() !== '' &&
            data.title &&
            data.title.trim() !== '' &&
            data.description &&
            data.description.trim() !== '' &&
            // Validate other_description when description is "Other"
            (data.description !== 'Other' || (data.other_description && data.other_description.trim() !== '')) &&
            data.category &&
            data.category.trim() !== '' &&
            data.procurement_mode &&
            data.procurement_mode.trim() !== '' &&
            // Require negotiated_procurement_type when negotiated_procurement is selected
            (!isNegotiatedProcurement || (data.negotiated_procurement_type && data.negotiated_procurement_type.trim() !== '')) &&
            data.abc_amount &&
            parseFloat(data.abc_amount) > 0 &&
            data.funding_source &&
            data.funding_source.trim() !== '' &&
            // Validate other_funding_source when funding_source is "Other Sources"
            (data.funding_source !== 'Other Sources' || (data.other_funding_source && data.other_funding_source.trim() !== '')) &&
            data.office &&
            data.office.trim() !== '' &&
            // Validate other_end_user when end_user is "Other"
            (!data.end_user || data.end_user !== 'Other' || (data.other_end_user && data.other_end_user.trim() !== '')) &&
            data.prepared_by &&
            data.prepared_by.trim() !== ''
        );
    }, [data]);

    const handleCreateProcurement = useCallback(async () => {
        if (!isFormValid()) {
            toast.error('Please complete all required fields', {
                description: 'Fill in all required fields before submitting.',
            });
            return;
        }

        const submissionToast = toast.loading('Creating Procurement...');

        const formData = new FormData();
        formData.append('pr_number', data.pr_number);
        formData.append('app_reference', data.app_reference);
        formData.append('title', data.title);
        formData.append('description', data.description);
        formData.append('other_description', data.other_description);
        formData.append('abc_amount', data.abc_amount);
        formData.append('funding_source', data.funding_source);
        formData.append('other_funding_source', data.other_funding_source);
        formData.append('category', data.category);
        formData.append('procurement_mode', data.procurement_mode);
        formData.append('negotiated_procurement_type', data.negotiated_procurement_type || '');
        formData.append('office', data.office);
        formData.append('end_user', data.end_user);
        formData.append('other_end_user', data.other_end_user);
        formData.append('prepared_by', data.prepared_by);

        try {
            await submitAndPoll(initiate().url, formData);

            localStorage.removeItem(DRAFT_STORAGE_KEY);
            setHasDraft(false);

            toast.success('Procurement created successfully!', {
                id: submissionToast,
                description: 'Redirecting to procurement list. You can upload documents from there.',
            });

            setTimeout(() => {
                router.visit(procurementListIndex['/bac-secretariat/procurements-list'].url(), {
                    preserveState: false,
                    replace: true,
                });
            }, 1500);
        } catch (err) {
            toast.error('Failed to submit', {
                id: submissionToast,
                description: err instanceof Error ? err.message : 'Unknown error',
            });
        }
    }, [data, isFormValid, submitAndPoll]);

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

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Draft Recovery Banner */}
                {showDraftBanner && (
                    <Card className="border-amber-500/50 bg-amber-50/50 dark:bg-amber-950/20">
                        <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                                <div>
                                    <p className="font-medium text-amber-800 dark:text-amber-200">You have an unsaved draft</p>
                                    <p className="text-sm text-amber-700 dark:text-amber-300">
                                        Last saved: {draftSavedAt ? new Date(draftSavedAt).toLocaleString() : 'Unknown'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={discardDraft}
                                    className="border-amber-500/50 text-amber-700 hover:bg-amber-100 dark:text-amber-300 dark:hover:bg-amber-900/30"
                                >
                                    <Trash2 className="mr-1.5 h-4 w-4" />
                                    Discard
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => {
                                        const draft = loadDraft();
                                        if (draft) restoreDraft(draft);
                                    }}
                                    className="bg-amber-600 text-white hover:bg-amber-700"
                                >
                                    <Save className="mr-1.5 h-4 w-4" />
                                    Restore Draft
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Page Header */}
                <HeroCard
                    icon={FileText}
                    title="Procurement Initiation"
                    description={
                        <>
                            Create a new procurement request with all required information per RA 12009 (NGPA).
                            <span className="hidden sm:inline"> Documents will be uploaded progressively after creation.</span>
                        </>
                    }
                    actions={
                        hasDraft && !showDraftBanner && draftSavedAt && (
                            <Badge variant="secondary" className="gap-1.5 text-xs">
                                <Save className="h-3 w-3" />
                                Draft saved {new Date(draftSavedAt).toLocaleTimeString()}
                            </Badge>
                        )
                    }
                />

                <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
                    {/* Section 1: Basic Information */}
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                        <CardHeader className="space-y-1 pb-2 sm:pb-4">
                            <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                <FileText className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                Basic Information
                            </CardTitle>
                            <CardDescription className="text-muted-foreground text-sm">
                                Required procurement details per RA 12009 (NGPA)
                            </CardDescription>
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
                                            handleFieldChange('description', value);
                                            // Clear other_description when switching away from Other
                                            if (value !== 'Other') {
                                                handleFieldChange('other_description', '');
                                            }
                                        }}
                                    >
                                        <SelectTrigger className={hasError('description') ? 'border-destructive ring-destructive/30' : ''}>
                                            <SelectValue placeholder="Select description" />
                                        </SelectTrigger>
                                        <SelectContent className="max-h-60 overflow-y-auto">
                                            {PROCUREMENT_DESCRIPTIONS.map((desc) => (
                                                <SelectItem key={desc.value} value={desc.value}>
                                                    {desc.label}
                                                </SelectItem>
                                            ))}
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

                    {/* Section 2: Classification & Budget */}
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                        <CardHeader className="space-y-1 pb-2 sm:pb-4">
                            <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                <DollarSign className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                Classification & Budget
                            </CardTitle>
                            <CardDescription className="text-muted-foreground text-sm">Procurement type and approved contract budget</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 p-4 sm:space-y-6 sm:p-6">
                            {/* Category and Funding Source - Grid */}
                            <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                                {/* Category */}
                                <Field>
                                    <FieldLabel>
                                        Category
                                        <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <RadioGroup
                                        value={data.category}
                                        onValueChange={(value) => handleFieldChange('category', value)}
                                        className="mt-2 grid gap-2"
                                    >
                                        {categories.map((category) => (
                                            <div
                                                key={category.value}
                                                className={`flex items-center space-x-3 rounded-lg border p-3 transition-colors ${
                                                    data.category === category.value
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-input hover:bg-muted/50'
                                                } ${hasError('category') ? 'border-destructive' : ''}`}
                                            >
                                                <RadioGroupItem value={category.value} id={`category-${category.value}`} />
                                                <Label htmlFor={`category-${category.value}`} className="flex-1 cursor-pointer font-medium">
                                                    {category.label}
                                                </Label>
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
                                            // Clear other_funding_source when switching away from Other Sources
                                            if (value !== 'Other Sources') {
                                                handleFieldChange('other_funding_source', '');
                                            }
                                        }}
                                        className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2"
                                    >
                                        {FUNDING_SOURCES.map((source) => (
                                            <div key={source.value} className="flex items-center space-x-2">
                                                <RadioGroupItem
                                                    value={source.value}
                                                    id={`funding-${source.value}`}
                                                    className={hasError('funding_source') ? 'border-destructive' : ''}
                                                />
                                                <Label htmlFor={`funding-${source.value}`} className="cursor-pointer text-sm font-normal">
                                                    {source.label}
                                                </Label>
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
                                            // Clear negotiated procurement type when switching away from negotiated procurement
                                            if (value !== 'negotiated_procurement') {
                                                handleFieldChange('negotiated_procurement_type', '');
                                            }
                                        }}
                                        className="mt-2 grid gap-2"
                                    >
                                        {procurementModes.map((mode) => (
                                            <div
                                                key={mode.value}
                                                className={`flex items-center space-x-3 rounded-lg border p-3 transition-colors ${
                                                    data.procurement_mode === mode.value
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-input hover:bg-muted/50'
                                                } ${hasError('procurement_mode') ? 'border-destructive' : ''}`}
                                            >
                                                <RadioGroupItem value={mode.value} id={`mode-${mode.value}`} />
                                                <Label htmlFor={`mode-${mode.value}`} className="flex-1 cursor-pointer">
                                                    <span className="font-medium">{mode.label}</span>
                                                    {mode.threshold && (
                                                        <span className="text-muted-foreground ml-2 text-xs">
                                                            (≤ ₱{mode.threshold.toLocaleString()})
                                                        </span>
                                                    )}
                                                </Label>
                                            </div>
                                        ))}
                                    </RadioGroup>
                                    {hasError('procurement_mode') && <FieldError>{errors.procurement_mode}</FieldError>}

                                    {/* Negotiated Procurement Sub-types */}
                                    {data.procurement_mode === 'negotiated_procurement' && (
                                        <div className="border-primary/30 mt-4 ml-6 space-y-3 border-l-2 pl-4">
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
                                                        className={`flex items-center space-x-3 rounded-lg border p-2.5 transition-colors ${
                                                            data.negotiated_procurement_type === type.value
                                                                ? 'border-primary bg-primary/5'
                                                                : 'border-input hover:bg-muted/50'
                                                        } ${hasError('negotiated_procurement_type') ? 'border-destructive' : ''}`}
                                                    >
                                                        <RadioGroupItem value={type.value} id={`neg-type-${type.value}`} />
                                                        <Label
                                                            htmlFor={`neg-type-${type.value}`}
                                                            className="flex-1 cursor-pointer text-sm font-medium"
                                                        >
                                                            {type.label}
                                                        </Label>
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
                                                // Check if user is typing a decimal (ends with . or has incomplete decimal)
                                                const hasTrailingDecimal = data.abc_amount.endsWith('.');
                                                const decimalParts = data.abc_amount.split('.');
                                                const decimalDigits = decimalParts[1] || '';

                                                // Format the integer part with commas
                                                const integerPart = decimalParts[0];
                                                const formattedInteger = integerPart ? parseInt(integerPart, 10).toLocaleString('en-PH') : '';

                                                // Rebuild with decimal part preserved exactly as typed
                                                if (hasTrailingDecimal) {
                                                    return formattedInteger + '.';
                                                } else if (decimalDigits) {
                                                    return formattedInteger + '.' + decimalDigits;
                                                }
                                                return formattedInteger;
                                            })()}
                                            onChange={(e) => {
                                                // Remove commas to get raw number
                                                const rawValue = e.target.value.replace(/,/g, '');
                                                // Only allow valid number input (digits and one decimal point, max 2 decimal places)
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
                            </CardFooter>
                        )}
                    </Card>

                    {/* Section 3: Office & Purpose */}
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                        <CardHeader className="space-y-1 pb-2 sm:pb-4">
                            <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                <Building2 className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                Office & Purpose
                            </CardTitle>
                            <CardDescription className="text-muted-foreground text-sm">
                                Requesting office and procurement justification
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 p-4 sm:space-y-6 sm:p-6">
                            {/* Office, End User, and Prepared By - Grid */}
                            <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
                                {/* Office */}
                                <Field>
                                    <FieldLabel htmlFor="office">
                                        Office
                                        <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>Select the office requesting this procurement</FieldDescription>
                                    <Select value={data.office} onValueChange={(value) => handleFieldChange('office', value)}>
                                        <SelectTrigger className={hasError('office') ? 'border-destructive ring-destructive/30' : ''}>
                                            <SelectValue placeholder="Select office" />
                                        </SelectTrigger>
                                        <SelectContent className="max-h-60 overflow-y-auto">
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
                                    <FieldDescription>If different from the office, specify the actual end user</FieldDescription>
                                    <Select
                                        value={data.end_user}
                                        onValueChange={(value) => {
                                            handleFieldChange('end_user', value);
                                            // Clear other_end_user when switching away from Other
                                            if (value !== 'Other') {
                                                handleFieldChange('other_end_user', '');
                                            }
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Same as Office" />
                                        </SelectTrigger>
                                        <SelectContent className="max-h-60 overflow-y-auto">
                                            {MUNICIPAL_OFFICES.map((office) => (
                                                <SelectItem key={office.value} value={office.value}>
                                                    {office.label}
                                                </SelectItem>
                                            ))}
                                            <SelectItem value="Other">Other (Please specify)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {data.end_user === 'Other' && (
                                        <div className="mt-3">
                                            <Input
                                                id="other_end_user"
                                                name="other_end_user"
                                                type="text"
                                                value={data.other_end_user}
                                                onChange={(e) => handleFieldChange('other_end_user', e.target.value)}
                                                className={hasError('other_end_user') ? 'border-destructive ring-destructive/30' : ''}
                                                placeholder="Please specify the end user"
                                            />
                                            {hasError('other_end_user') && <FieldError>{errors.other_end_user}</FieldError>}
                                        </div>
                                    )}
                                </Field>

                                {/* Prepared By */}
                                <Field>
                                    <FieldLabel htmlFor="prepared_by">
                                        Prepared By
                                        <span className="text-destructive ml-1 text-xs">*</span>
                                    </FieldLabel>
                                    <FieldDescription>Name of the person preparing this request</FieldDescription>
                                    <Input
                                        id="prepared_by"
                                        name="prepared_by"
                                        value={data.prepared_by}
                                        onChange={(e) => handleFieldChange('prepared_by', e.target.value)}
                                        className={hasError('prepared_by') ? 'border-destructive ring-destructive/30' : ''}
                                        placeholder="Full Name"
                                    />
                                    {hasError('prepared_by') && <FieldError>{errors.prepared_by}</FieldError>}
                                </Field>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Next Steps Info */}
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border bg-muted/30 shadow-md">
                        <CardHeader className="space-y-1 pb-2 sm:pb-4">
                            <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                <Upload className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                Next: Progressive Document Upload
                            </CardTitle>
                            <CardDescription className="text-muted-foreground text-sm">
                                After creating this procurement, you'll be redirected to upload required documents progressively.
                                <span className="hidden sm:inline"> You can upload them one at a time and save your progress.</span>
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    {/* Submit Button */}
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                        <CardContent className="p-4 sm:p-6">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                <div className="flex items-center gap-2">
                                    <p className="text-muted-foreground text-sm">
                                        All fields marked with <span className="text-destructive">*</span> are required
                                    </p>
                                    {hasDraft && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={clearDraft}
                                            className="text-muted-foreground hover:text-destructive h-auto p-1 text-xs"
                                        >
                                            <Trash2 className="mr-1 h-3 w-3" />
                                            Clear Draft
                                        </Button>
                                    )}
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={saveDraft}
                                        disabled={isSavingDraft || processing}
                                        className="flex h-10 w-full items-center gap-2 text-sm sm:h-11 sm:w-auto"
                                    >
                                        {isSavingDraft ? (
                                            <>
                                                <Spinner className="h-4 w-4" />
                                                Saving...
                                            </>
                                        ) : (
                                            <>
                                                <Save className="h-4 w-4" />
                                                Save Draft
                                            </>
                                        )}
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={processing || !isFormValid()}
                                        className="flex h-10 w-full items-center gap-2 text-sm sm:h-11 sm:w-auto sm:min-w-[200px] sm:text-base"
                                    >
                                        {processing ? (
                                            <>
                                                <Spinner className="h-4 w-4" />
                                                Creating...
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle2 className="h-4 w-4" />
                                                Create Procurement
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
