import { FileText, ListChecks, HelpCircle, CheckCircle, CheckSquare } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ValidationDialog } from '@/components/pr-initiation/validation-dialog';
import { useState } from 'react';

interface FormCompletionState {
  details: boolean;
  prDocument: boolean;
  supporting: boolean;
}

interface FormHeaderProps {
  currentStep: number;
  formCompletion: FormCompletionState;
  getFormCompletionPercentage: () => number;
  setShowValidationSummary: (show: boolean) => void;
  showValidationSummary: boolean;
  validationErrors?: Array<{ field: string; message: string }>;
}

export function FormHeader({
  currentStep,
  formCompletion,
  getFormCompletionPercentage,
  setShowValidationSummary,
  showValidationSummary,
  validationErrors = []
}: FormHeaderProps) {
  const handleOpenChange = (open: boolean) => {
    setShowValidationSummary(open);
  };

  return (
    <>
      <div className="flex items-center justify-between">
        <div className="flex items-start gap-4">
          <div className="flex items-center">
            <FileText className="h-6 w-6 text-primary mr-3" />
            <div>
              <h1 className="text-2xl font-bold">New Purchase Request</h1>
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <Badge variant="outline">PR Initiation</Badge>
                <Badge variant="outline">Step {currentStep} of 3</Badge>
              </div>
            </div>
          </div>
        </div>
        
        <div className="flex items-center gap-3">
          <TooltipProvider delayDuration={300}>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="outline"
                  onClick={() => setShowValidationSummary(!showValidationSummary)}
                  className="flex gap-2 items-center"
                >
                  <ListChecks className="h-4 w-4" />
                  <span className="hidden sm:inline">Validate Form</span>
                </Button>
              </TooltipTrigger>
              <TooltipContent side="bottom" className="p-2 max-w-xs">
                <div className="flex items-start gap-2.5">
                  <ListChecks className="h-4 w-4 text-primary mt-0.5 flex-shrink-0" />
                  <div>
                    <p className="font-medium mb-0.5">Validate Form Content</p>
                    <p className="text-sm text-muted-foreground">
                      Check for missing required fields
                    </p>
                  </div>
                </div>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <Badge variant={getFormCompletionPercentage() === 100 ? "default" : "outline"}>
            <span className="flex items-center gap-1.5">
              {getFormCompletionPercentage() === 100 ? (
                <CheckCircle className="h-3.5 w-3.5" />
              ) : (
                <CheckSquare className="h-3.5 w-3.5" />
              )}
              <span>{getFormCompletionPercentage()}% Complete</span>
            </span>
          </Badge>
        </div>
      </div>

      <ValidationDialog
        open={showValidationSummary}
        onOpenChange={handleOpenChange}
        errors={validationErrors}
        formCompletion={formCompletion}
        currentStep={currentStep}  // Pass the currentStep to ValidationDialog
      />
    </>
  );
}