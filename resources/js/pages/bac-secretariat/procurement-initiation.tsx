import { Head } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, FileText, Save, Trash2, Upload } from 'lucide-react';

import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

import { BasicInfoSection } from '@/components/procurement-initiation/basic-info-section';
import { ClassificationBudgetSection } from '@/components/procurement-initiation/classification-budget-section';
import { OfficePurposeSection } from '@/components/procurement-initiation/office-purpose-section';

import {
    useProcurementInitiation,
    type CategoryOption,
    type NegotiatedProcurementTypeOption,
    type ProcurementModeOption,
} from '@/hooks/use-procurement-initiation';
import AppLayout from '@/layouts/app-layout';

interface ProcurementInitiationFormProps {
    categories?: CategoryOption[];
    procurementModes?: ProcurementModeOption[];
    negotiatedProcurementTypes?: NegotiatedProcurementTypeOption[];
}

export default function ProcurementInitiationForm({
    categories = [],
    procurementModes = [],
    negotiatedProcurementTypes = [],
}: ProcurementInitiationFormProps) {
    const {
        breadcrumbs,
        data,
        processing,
        errors,
        hasError,
        handleFieldChange,
        hasDraft,
        draftSavedAt,
        isSavingDraft,
        showDraftBanner,
        loadDraft,
        saveDraft,
        clearDraft,
        restoreDraft,
        discardDraft,
        prPrefix,
        prYear,
        prSequence1,
        prSequence2,
        handlePrPartChange,
        selectedDescriptionLabel,
        selectedOfficeLabel,
        selectedEndUserLabel,
        selectedMode,
        isFormValid,
        onSubmit,
        PROCUREMENT_DESCRIPTIONS,
        FUNDING_SOURCES,
        MUNICIPAL_OFFICES,
        categories: hookCategories,
        procurementModes: hookProcurementModes,
        negotiatedProcurementTypes: hookNegotiatedTypes,
    } = useProcurementInitiation({ categories, procurementModes, negotiatedProcurementTypes });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Initiate Procurement" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* Draft Recovery Banner */}
                {showDraftBanner && (
                    <Card className="border-amber-500/50 bg-muted/50/50 dark:bg-muted/50/20">
                        <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <AlertCircle />
                                <div>
                                    <p className="font-medium text-muted-foreground dark:text-muted-foreground">You have an unsaved draft</p>
                                    <p className="text-sm text-muted-foreground dark:text-muted-foreground">
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
                                    className="border-amber-500/50 text-muted-foreground hover:bg-muted dark:text-muted-foreground dark:hover:bg-muted/30"
                                >
                                    <Trash2 />
                                    Discard
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => {
                                        const draft = loadDraft();
                                        if (draft) restoreDraft(draft);
                                    }}
                                    className="bg-muted-foreground text-white hover:bg-muted-foreground/90"
                                >
                                    <Save />
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
                        hasDraft &&
                        !showDraftBanner &&
                        draftSavedAt && (
                            <Badge variant="secondary" className="gap-1.5 text-xs">
                                <Save />
                                Draft saved {new Date(draftSavedAt).toLocaleTimeString()}
                            </Badge>
                        )
                    }
                />

                <form onSubmit={onSubmit} className="flex flex-col gap-4 sm:gap-6">
                    {/* Section 1: Basic Information */}
                    <BasicInfoSection
                        data={data}
                        errors={errors}
                        hasError={hasError}
                        handleFieldChange={handleFieldChange}
                        prPrefix={prPrefix}
                        prYear={prYear}
                        prSequence1={prSequence1}
                        prSequence2={prSequence2}
                        handlePrPartChange={handlePrPartChange}
                        selectedDescriptionLabel={selectedDescriptionLabel}
                        PROCUREMENT_DESCRIPTIONS={PROCUREMENT_DESCRIPTIONS}
                    />

                    {/* Section 2: Classification & Budget */}
                    <ClassificationBudgetSection
                        data={data}
                        errors={errors}
                        hasError={hasError}
                        handleFieldChange={handleFieldChange}
                        selectedMode={selectedMode}
                        categories={hookCategories}
                        procurementModes={hookProcurementModes}
                        negotiatedProcurementTypes={hookNegotiatedTypes}
                        FUNDING_SOURCES={FUNDING_SOURCES}
                    />

                    {/* Section 3: Office & Purpose */}
                    <OfficePurposeSection
                        data={data}
                        errors={errors}
                        hasError={hasError}
                        handleFieldChange={handleFieldChange}
                        selectedOfficeLabel={selectedOfficeLabel}
                        selectedEndUserLabel={selectedEndUserLabel}
                        MUNICIPAL_OFFICES={MUNICIPAL_OFFICES}
                    />

                    {/* Next Steps Info */}
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border bg-muted/30 shadow-md">
                        <CardHeader className="flex flex-col gap-1 pb-2 sm:pb-4">
                            <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                                <Upload className="text-primary" />
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
                                            <Trash2 data-icon="inline-start" />
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
                                                <Spinner />
                                                Saving...
                                            </>
                                        ) : (
                                            <>
                                                <Save />
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
                                                <Spinner />
                                                Creating...
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle2 />
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
