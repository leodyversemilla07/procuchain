import React from 'react';
import { ArrowLeft, ArrowRight, Save, Loader2, CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface FormCompletionState {
  details: boolean;
  prDocument: boolean;
  supporting: boolean;
}

interface FormNavigationProps {
  currentStep: number;
  handlePrevStep: (e: React.MouseEvent<HTMLButtonElement>) => void;
  handleNextStep: (e: React.MouseEvent<HTMLButtonElement>) => void;
  processing: boolean;
  formCompletion: FormCompletionState;
}

export function FormNavigation({
  currentStep,
  handlePrevStep,
  handleNextStep,
  processing,
  formCompletion,
}: FormNavigationProps) {
  const isLastStep = currentStep === 3;
  const isFirstStep = currentStep === 1;
  const totalSteps = 3;
  const allComplete = formCompletion.details && formCompletion.prDocument && formCompletion.supporting;

  const stepTitles = ['Request Details', 'PR Document', 'Supporting Files'];

  return (
    <div className="space-y-6">
      {/* Step Indicator */}
      <div className="flex items-center justify-between mb-8 px-1">
        {[1, 2, 3].map((step) => (
          <div key={step} className="flex flex-col items-center">
            <div className="flex items-center">
              <div
                className={cn(
                  "w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium transition-all",
                  step < currentStep
                    ? "bg-green-100 text-green-700 border border-green-300"
                    : step === currentStep
                      ? "bg-primary text-white"
                      : "bg-gray-100 text-gray-500 border border-gray-200"
                )}
              >
                {step < currentStep ? (
                  <CheckCircle2 className="h-5 w-5" />
                ) : (
                  step
                )}
              </div>
              {step < totalSteps && (
                <div
                  className={cn(
                    "h-1 w-16 md:w-24 lg:w-32",
                    step < currentStep ? "bg-primary" : "bg-gray-200"
                  )}
                />
              )}
            </div>
            <span className={cn(
              "mt-2 text-xs md:text-sm",
              step === currentStep ? "font-medium text-primary" : "text-gray-500"
            )}>
              {stepTitles[step - 1]}
            </span>
          </div>
        ))}
      </div>

      {/* Navigation Buttons */}
      <div className="flex justify-between items-center gap-4 pt-4 border-t">
        <div>
          {!isFirstStep && (
            <Button
              type="button"
              variant="outline"
              onClick={handlePrevStep}
              className="group transition-all"
              disabled={processing}
            >
              <ArrowLeft className="mr-2 h-4 w-4 group-hover:-translate-x-1 transition-transform" />
              Previous
            </Button>
          )}
        </div>

        <div className="flex flex-col items-end gap-2">
          {isLastStep && !allComplete && (
            <div className="text-xs text-amber-600 flex items-center">
              <span>Please complete all sections before submitting</span>
            </div>
          )}

          <div className="flex items-center gap-4">
            {isLastStep ? (
              <Button
                type="submit"
                variant="default"
                className={cn(
                  "transition-all min-w-[160px]",
                  allComplete
                    ? "bg-primary hover:bg-primary/90"
                    : "bg-gray-300 text-gray-600"
                )}
                disabled={processing || !allComplete}
              >
                {processing ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Processing...
                  </>
                ) : (
                  <>
                    <Save className="mr-2 h-4 w-4" />
                    Submit Request
                  </>
                )}
              </Button>
            ) : (
              <Button
                type="button"
                variant="default"
                onClick={handleNextStep}
                className="group transition-all min-w-[120px]"
                disabled={processing}
              >
                Next Step
                <ArrowRight className="ml-2 h-4 w-4 group-hover:translate-x-1 transition-transform" />
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
