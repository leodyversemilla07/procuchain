import React from 'react';
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

import AppLayout from '@/layouts/app-layout';
import { useProcurementInitiation, type CategoryOption, type ProcurementModeOption, type NegotiatedProcurementTypeOption } from '@/hooks/use-procurement-initiation';

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
            hasDraft &&
            !showDraftBanner &&
            draftSavedAt && (
              <Badge variant="secondary" className="gap-1.5 text-xs">
                <Save className="h-3 w-3" />
                Draft saved {new Date(draftSavedAt).toLocaleTimeString()}
              </Badge>
            )
          }
        />

        <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
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
