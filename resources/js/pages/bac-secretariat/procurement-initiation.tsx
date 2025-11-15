import { Head, router, useForm, usePage } from '@inertiajs/react';
import React, { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { initiate } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';

import { type BreadcrumbItem } from '@/types';
import { buildBreadcrumbs } from '@/utils/breadcrumbs';
import { UserRole } from '@/types/enums';

import AppLayout from '@/layouts/app-layout';

import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Stepper, type Step } from '@/components/ui/stepper';

import { FileText, ArrowLeft, ArrowRight } from 'lucide-react';

import {
    BasicInformationStep,
    ClassificationBudgetStep,
    OfficePurposeStep,
    ReviewSubmitStep,
} from './procurement-initiation/steps';

import type {
    UseFormData,
    CategoryOption,
    ProcurementModeOption,
} from './procurement-initiation/types';

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

    // Multi-step state
    const [currentStep, setCurrentStep] = useState<number>(1);

    // Define steps (documents will be uploaded progressively after procurement creation)
    const steps: Step[] = [
        { id: 1, title: 'Basic Info', description: 'PR details' },
        { id: 2, title: 'Budget', description: 'Classification & ABC' },
        { id: 3, title: 'Office', description: 'Office & purpose' },
        { id: 4, title: 'Review', description: 'Final check' },
    ];

    const { data, setData, processing, errors, reset, clearErrors } = useForm<UseFormData>({
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

        // Documents (empty arrays - not used but required by type)
        files: [],
        document_types: [],
        document_descriptions: [],
    });


    const hasError = useCallback(
        (field: string) => {
            return Object.keys(errors).some(
                (error) => error === field || error.startsWith(`${field}.`),
            );
        },
        [errors],
    );

    // Step validation functions
    const validateStep1 = useCallback((): boolean => {
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
            data.description.trim() !== ''
        );
    }, [data.pr_number, data.ppmp_reference, data.title, data.description]);

    const validateStep2 = useCallback((): boolean => {
        return !!(
            data.category &&
            data.category.trim() !== '' &&
            data.procurement_mode &&
            data.procurement_mode.trim() !== '' &&
            data.abc_amount &&
            parseFloat(data.abc_amount) > 0 &&
            data.funding_source &&
            data.funding_source.trim() !== ''
        );
    }, [data.category, data.procurement_mode, data.abc_amount, data.funding_source]);

    const validateStep3 = useCallback((): boolean => {
        return !!(
            data.office &&
            data.office.trim() !== '' &&
            data.purpose &&
            data.purpose.trim() !== '' &&
            data.prepared_by &&
            data.prepared_by.trim() !== ''
        );
    }, [data.office, data.purpose, data.prepared_by]);

    const validateStep4 = useCallback((): boolean => {
        // Review step - validate all previous steps
        return validateStep1() && validateStep2() && validateStep3();
    }, [validateStep1, validateStep2, validateStep3]);

    const validateCurrentStep = useCallback((): boolean => {
        switch (currentStep) {
            case 1:
                return validateStep1();
            case 2:
                return validateStep2();
            case 3:
                return validateStep3();
            case 4:
                return validateStep4(); // Review step
            default:
                return false;
        }
    }, [
        currentStep,
        validateStep1,
        validateStep2,
        validateStep3,
        validateStep4,
    ]);

    const handleNext = useCallback((e?: React.MouseEvent) => {
        // Explicitly prevent any form submission
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (!validateCurrentStep()) {
            toast.error('Please complete all required fields', {
                description: 'Fill in all required fields before proceeding to the next step.',
            });
            return;
        }
        setCurrentStep((prev) => Math.min(prev + 1, steps.length));
    }, [validateCurrentStep, steps.length]);

    const handlePrevious = useCallback(() => {
        setCurrentStep((prev) => Math.max(prev - 1, 1));
    }, []);

    const handleStepClick = useCallback(
        (stepId: number) => {
            // Allow navigation only to completed steps or current step
            if (stepId <= currentStep) {
                setCurrentStep(stepId);
            }
        },
        [currentStep],
    );

    const onSubmit = useCallback(
        (e: React.FormEvent) => {
            e.preventDefault();

            // Only allow submission on the final step (Review step)
            if (currentStep !== steps.length) {
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
                delivery_date: data.delivery_date ? data.delivery_date.toISOString().split('T')[0] : '',
                delivery_term_days: data.delivery_term_days,
                prepared_by: data.prepared_by,
            };

            router.post(initiate().url, submissionData, {
                onSuccess: () => {
                    const createdPrNumber = data.pr_number;
                    toast.success('Procurement created successfully!', {
                        id: submissionToast,
                        description: 'Redirecting to document upload...',
                    });
                    reset();
                    
                    // Redirect to PPMP stage for progressive document uploads
                    setTimeout(() => {
                        router.visit(`/bac-secretariat/pre-procurement/${createdPrNumber}/ppmp`, {
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
        },
        [currentStep, steps.length, reset, data],
    );

    const renderCurrentStep = () => {
        const commonProps = {
            data,
            setData,
            errors,
            clearErrors,
            hasError,
        };

        switch (currentStep) {
            case 1:
                return <BasicInformationStep {...commonProps} />;
            case 2:
                return (
                    <ClassificationBudgetStep
                        {...commonProps}
                        categories={categories}
                        procurementModes={procurementModes}
                    />
                );
            case 3:
                return <OfficePurposeStep {...commonProps} />;
            case 4:
                return (
                    <ReviewSubmitStep
                        data={data}
                        categories={categories}
                        procurementModes={procurementModes}
                        onEditStep={setCurrentStep}
                    />
                );
            default:
                return null;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Initiate Procurement" />

            <div
                className="w-full space-y-4 p-3 sm:space-y-6 sm:p-4 md:p-6 lg:p-8"
                role="main"
                aria-labelledby="page-title"
            >
                {/* Header Section */}
                <HeroCard
                    icon={FileText}
                    title="New Procurement"
                    description="Create a new procurement with basic information. You'll upload documents progressively in the next step."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className="rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary transition-colors duration-200 hover:bg-primary/20 md:px-3 md:py-1.5 md:text-sm">
                                Procurement Initiation
                            </Badge>
                            {formState?.reference && (
                                <Badge className="rounded-md bg-chart-1/10 px-2 py-1 text-xs text-chart-1 dark:bg-chart-1/20 dark:text-chart-1 md:px-3 md:py-1.5 md:text-sm">
                                    {formState.reference}
                                </Badge>
                            )}
                        </div>
                    }
                />

                {/* Stepper */}
                <Stepper steps={steps} currentStep={currentStep} onStepClick={handleStepClick} />

                <form 
                    onSubmit={onSubmit} 
                    className="space-y-4 sm:space-y-6"
                    onKeyDown={(e) => {
                        // Prevent Enter key from submitting form unless on the final review step
                        if (e.key === 'Enter' && currentStep !== steps.length) {
                            // Only prevent if not inside a textarea
                            const target = e.target as HTMLElement;
                            if (target.tagName !== 'TEXTAREA') {
                                e.preventDefault();
                            }
                        }
                    }}
                >
                    {/* Step Content */}
                    <div className="min-h-[60vh] sm:min-h-0">
                        {renderCurrentStep()}
                    </div>

                    {/* Navigation Buttons */}
                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handlePrevious}
                            disabled={currentStep === 1}
                            className="w-full gap-2 sm:w-auto"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            <span className="hidden sm:inline">Previous</span>
                            <span className="sm:hidden">Back</span>
                        </Button>

                        <div className="flex w-full gap-3 sm:w-auto">
                            {currentStep < steps.length ? (
                                <Button
                                    type="button"
                                    onClick={handleNext}
                                    disabled={!validateCurrentStep()}
                                    className="w-full gap-2"
                                >
                                    <span className="hidden sm:inline">Next Step</span>
                                    <span className="sm:hidden">Continue</span>
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    disabled={processing || !validateStep4()}
                                    className="w-full gap-2"
                                >
                                    {processing ? (
                                        <>
                                            <span className="hidden sm:inline">Creating...</span>
                                            <span className="sm:hidden">Creating...</span>
                                        </>
                                    ) : (
                                        <>
                                            <span className="hidden sm:inline">Create & Upload Documents</span>
                                            <span className="sm:hidden">Create</span>
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
