import { ClipboardList, FileCheck, Files, CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface FormCompletionState {
  details: boolean;
  prDocument: boolean;
  supporting: boolean;
}

interface StepIndicatorProps {
  currentStep: number;
  formCompletion: FormCompletionState;
  showPercentage?: boolean;
}

export function StepIndicator({
  currentStep,
  formCompletion,
  showPercentage = false
}: StepIndicatorProps) {
  // Adjust form completion based on current step
  // Only consider a step complete if we've actually reached that step
  const adjustedFormCompletion = {
    details: formCompletion.details,
    prDocument: currentStep >= 2 ? formCompletion.prDocument : false,
    supporting: currentStep >= 3 ? formCompletion.supporting : false,
  };

  const steps = [
    {
      id: 1,
      name: 'Procurement Details',
      icon: ClipboardList,
      description: 'Enter procurement ID and title',
      isComplete: adjustedFormCompletion.details,
    },
    {
      id: 2,
      name: 'PR Document',
      icon: FileCheck,
      description: 'Upload Purchase Request document',
      isComplete: adjustedFormCompletion.prDocument,
    },
    {
      id: 3,
      name: 'Supporting Documents',
      icon: Files,
      description: 'Attach required supporting files',
      isComplete: adjustedFormCompletion.supporting,
    },
  ];

  // Calculate completion percentage based on adjusted completion
  const completedSteps = Object.values(adjustedFormCompletion).filter(Boolean).length;
  const completionPercentage = Math.round((completedSteps / steps.length) * 100);

  return (
    <div className="w-full mb-6">
      {/* Overall progress indicator (optional) */}
      {showPercentage && (
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm font-medium">
            Completion Progress
          </span>
          <span className="text-sm font-semibold text-primary">
            {completionPercentage}%
          </span>
        </div>
      )}

      <nav aria-label="Progress indicator" className="mx-auto">
        <ol role="list" className="flex justify-between items-center gap-2">
          {steps.map((step, index) => {
            const isActive = step.id === currentStep;
            
            return (
              <li key={step.id} className={cn(
                "flex-1 relative",
                index !== steps.length - 1 ? "after:content-[''] after:absolute after:top-1/2 after:w-full after:h-0.5 after:bg-muted after:-translate-y-1/2 after:left-1/2 after:z-0" : ""
              )}>
                <div className="relative flex flex-col items-center group">
                  <span className="flex items-center justify-center w-10 h-10 rounded-full z-10 bg-background border text-muted-foreground">
                    {step.isComplete ? (
                      <CheckCircle2 className="w-5 h-5 text-primary" />
                    ) : (
                      isActive ? (
                        <span className="w-2.5 h-2.5 bg-primary rounded-full" />
                      ) : (
                        <step.icon className="w-5 h-5" />
                      )
                    )}
                  </span>
                  
                  <div className="mt-2 text-center">
                    <div className="text-xs font-medium">
                      {step.name}
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground hidden md:block">
                      {step.description}
                    </div>
                  </div>
                </div>
              </li>
            );
          })}
        </ol>
      </nav>
    </div>
  );
}
