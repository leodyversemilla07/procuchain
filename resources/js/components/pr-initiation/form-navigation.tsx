import React from 'react';
import { ArrowLeft, ArrowRight, Save, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

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
  const allComplete = formCompletion.details && formCompletion.prDocument && formCompletion.supporting;

  return (
    <div className="flex justify-between items-center gap-4">
      <div>
        {!isFirstStep && (
          <Button
            type="button"
            variant="outline"
            onClick={handlePrevStep}
            className="group"
            disabled={processing}
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Previous Step
          </Button>
        )}
      </div>

      <div className="flex items-center gap-4">
        {isLastStep ? (
          <Button
            type="submit" // Changed from "button" to "submit" to properly trigger form submission
            variant="default"
            className={`${allComplete
              ? 'bg-primary hover:bg-primary/90'
              : 'bg-muted hover:bg-muted/90'
              } text-white px-6`}
            disabled={processing || !allComplete}
          >
            {processing ? (
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            ) : (
              <Save className="mr-2 h-4 w-4" />
            )}
            Submit Request
          </Button>
        ) : (
          <Button
            type="button"
            variant="default"
            onClick={handleNextStep}
            className="px-6"
            disabled={processing}
          >
            Next Step
            <ArrowRight className="ml-2 h-4 w-4" />
          </Button>
        )}
      </div>
    </div>
  );
}
