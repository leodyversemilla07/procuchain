import { useCallback, useEffect, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { initiate } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';
import { index as procurementListIndex } from '@/actions/App/Http/Controllers/ProcurementListController';
import { useBlockchainJob } from '@/hooks/use-blockchain-job';
import { UserRole } from '@/types/enums';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import type { BreadcrumbItem } from '@/types';
import { FUNDING_SOURCES, MUNICIPAL_OFFICES } from '@/types/constants';

// =============================================================================
// Constants
// =============================================================================

const DRAFT_STORAGE_KEY = 'procurement_initiation_draft';

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

// =============================================================================
// Types
// =============================================================================

export interface CategoryOption {
  value: string;
  label: string;
  description: string;
}

export interface ProcurementModeOption {
  value: string;
  label: string;
  description: string;
  threshold: number | null;
  requires_philgeps: boolean;
  requires_bac_resolution: boolean;
}

export interface NegotiatedProcurementTypeOption {
  value: string;
  label: string;
}

export type UseFormData = {
  pr_number: string;
  app_reference: string;
  title: string;
  description: string;
  other_description: string;
  abc_amount: string;
  funding_source: string;
  other_funding_source: string;
  category: string;
  procurement_mode: string;
  negotiated_procurement_type: string;
  office: string;
  end_user: string;
  other_end_user: string;
  prepared_by: string;
};

interface DraftData extends UseFormData {
  savedAt: string;
}

export interface UseProcurementInitiationOptions {
  categories?: CategoryOption[];
  procurementModes?: ProcurementModeOption[];
  negotiatedProcurementTypes?: NegotiatedProcurementTypeOption[];
}

// =============================================================================
// Hook
// =============================================================================

export function useProcurementInitiation({
  categories = [],
  procurementModes = [],
  negotiatedProcurementTypes = [],
}: UseProcurementInitiationOptions) {
  const { auth } = usePage<{ auth: { user: { name: string; email: string } } }>().props;
  const { submitAndPoll } = useBlockchainJob();

  const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
    { title: 'Procurement Initiation', href: '#' },
  ]);

  // Draft state
  const [hasDraft, setHasDraft] = useState(false);
  const [draftSavedAt, setDraftSavedAt] = useState<string | null>(null);
  const [isSavingDraft, setIsSavingDraft] = useState(false);
  const [showDraftBanner, setShowDraftBanner] = useState(false);

  const { data, setData, processing, errors, clearErrors, reset } = useForm<UseFormData>({
    pr_number: `PR-${new Date().getFullYear()}-000-0000`,
    app_reference: '',
    title: '',
    description: '',
    other_description: '',
    abc_amount: '',
    funding_source: '',
    other_funding_source: '',
    category: '',
    procurement_mode: '',
    negotiated_procurement_type: '',
    office: '',
    end_user: '',
    other_end_user: '',
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
    }, 2000);

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

  const selectedDescriptionLabel =
    PROCUREMENT_DESCRIPTIONS.find((description) => description.value === data.description)?.label ?? 'Select description';
  const selectedOfficeLabel = MUNICIPAL_OFFICES.find((office) => office.value === data.office)?.label ?? 'Select office';
  const selectedEndUserLabel =
    data.end_user === ''
      ? 'Same as Office'
      : data.end_user === 'Other'
        ? 'Other (Please specify)'
        : (MUNICIPAL_OFFICES.find((office) => office.value === data.end_user)?.label ?? 'Same as Office');

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
      (data.description !== 'Other' || (data.other_description && data.other_description.trim() !== '')) &&
      data.category &&
      data.category.trim() !== '' &&
      data.procurement_mode &&
      data.procurement_mode.trim() !== '' &&
      (!isNegotiatedProcurement || (data.negotiated_procurement_type && data.negotiated_procurement_type.trim() !== '')) &&
      data.abc_amount &&
      parseFloat(data.abc_amount) > 0 &&
      data.funding_source &&
      data.funding_source.trim() !== '' &&
      (data.funding_source !== 'Other Sources' || (data.other_funding_source && data.other_funding_source.trim() !== '')) &&
      data.office &&
      data.office.trim() !== '' &&
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

  return {
    // Breadcrumbs
    breadcrumbs,
    // Form state
    data,
    setData,
    processing,
    errors,
    hasError,
    handleFieldChange,
    // Draft state
    hasDraft,
    draftSavedAt,
    isSavingDraft,
    showDraftBanner,
    loadDraft,
    saveDraft,
    clearDraft,
    restoreDraft,
    discardDraft,
    // PR number parts
    prPrefix,
    prYear,
    prSequence1,
    prSequence2,
    handlePrPartChange,
    // Selected labels
    selectedDescriptionLabel,
    selectedOfficeLabel,
    selectedEndUserLabel,
    selectedMode,
    // Validation & submit
    isFormValid,
    onSubmit,
    // Constants for rendering
    PROCUREMENT_DESCRIPTIONS,
    FUNDING_SOURCES,
    MUNICIPAL_OFFICES,
    categories,
    procurementModes,
    negotiatedProcurementTypes,
  };
}
