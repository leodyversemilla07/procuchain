import React from 'react';
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
    <div className="w-full max-w-4xl mx-auto mb-8 px-6">
      {showPercentage && (
        <div className="flex items-center justify-between mb-8 bg-muted/20 p-5 rounded-xl shadow-sm backdrop-blur-sm">
          <span className="text-sm font-semibold flex items-center gap-2.5">
            <CheckCircle2 className="w-5 h-5 text-primary/90" />
            Completion Progress
          </span>
          <div className="flex items-center gap-4">
            <div className="w-48 h-2.5 bg-muted rounded-full overflow-hidden">
              <div
                className="h-full bg-primary transition-all duration-700 ease-out"
                style={{ width: `${completionPercentage}%` }}
              />
            </div>
            <span className="text-sm font-bold text-primary min-w-[3ch] tabular-nums">
              {completionPercentage}%
            </span>
          </div>
        </div>
      )}

      <nav aria-label="Progress indicator" className="mx-auto">
        <div className="flex justify-between items-center">
          {steps.map((step, index) => (
            <React.Fragment key={step.id}>
              <div className="flex flex-col items-center group relative">
                <div className={cn(
                  "flex items-center justify-center w-12 h-12 rounded-full border-2 transition-all duration-300",
                  "transform hover:scale-105 hover:shadow-lg",
                  currentStep >= step.id
                    ? "bg-primary border-primary/90 shadow-primary/20 shadow-lg"
                    : "bg-background border-muted hover:border-primary/40 hover:bg-primary/5"
                )}>
                  {step.isComplete ? (
                    <CheckCircle2 className="w-6 h-6 text-white animate-appearance-in" />
                  ) : (
                    <step.icon className={cn(
                      "w-6 h-6",
                      currentStep >= step.id ? "text-white" : "text-muted-foreground"
                    )} />
                  )}
                </div>
                <div className="mt-3 text-center">
                  <div className={cn(
                    "text-sm font-semibold tracking-tight transition-colors duration-300",
                    currentStep >= step.id ? "text-primary" : "text-muted-foreground group-hover:text-primary/70"
                  )}>
                    {step.name}
                  </div>
                  <div className="mt-1.5 text-xs text-muted-foreground/80 hidden md:block max-w-[140px]">
                    {step.description}
                  </div>
                </div>
              </div>

              {index < steps.length - 1 && (
                <div className="w-full mx-4">
                  <div className={cn(
                    "h-0.5 transition-all duration-500 ease-out",
                    currentStep > step.id ? "bg-primary shadow-sm" : "bg-muted"
                  )}></div>
                </div>
              )}
            </React.Fragment>
          ))}
        </div>
      </nav>
    </div>
  );
}
