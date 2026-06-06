import { cn } from '@/lib/utils';
import { Check, ChevronRight } from 'lucide-react';
import { useState } from 'react';

export interface Step {
    id: number;
    title: string;
    description?: string;
}

export interface StepperProps {
    steps: Step[];
    currentStep: number;
    onStepClick?: (stepId: number) => void;
    className?: string;
}

export function Stepper({ steps, currentStep, onStepClick, className }: StepperProps) {
    const [showAllSteps, setShowAllSteps] = useState(false);
    const currentStepIndex = steps.findIndex((s) => s.id === currentStep);

    // On mobile, only show current step ± 1 by default (unless expanded)
    const visibleMobileSteps = showAllSteps
        ? steps
        : steps.filter((step) => {
              const idx = steps.findIndex((s) => s.id === step.id);
              // Show completed steps up to current, plus current and next
              return idx <= currentStepIndex + 1;
          });

    const hasMoreSteps = !showAllSteps && visibleMobileSteps.length < steps.length;

    return (
        <div className={cn('w-full', className)}>
            {/* Desktop Stepper - Horizontal with scroll */}
            <div className="hidden md:block">
                <div className="overflow-x-auto pb-2">
                    <ol className="flex items-start gap-4 pr-4">
                        {steps.map((step, index) => {
                            const isCompleted = step.id < currentStep;
                            const isCurrent = step.id === currentStep;
                            const isClickable = onStepClick && (isCompleted || isCurrent);

                            return (
                                <li
                                    key={step.id}
                                    className={cn('relative flex w-20 shrink-0 flex-col items-center md:w-24 lg:w-28', {
                                        'cursor-pointer': isClickable,
                                    })}
                                    onClick={() => isClickable && onStepClick(step.id)}
                                >
                                    {/* Connector Line */}
                                    {index !== 0 && (
                                        <div
                                            className={cn(
                                                'absolute top-4 right-1/2 left-0 h-0.5',
                                                isCompleted || isCurrent ? 'bg-primary' : 'bg-muted',
                                            )}
                                        />
                                    )}
                                    {index !== steps.length - 1 && (
                                        <div className={cn('absolute top-4 right-0 left-1/2 h-0.5', isCompleted ? 'bg-primary' : 'bg-muted')} />
                                    )}

                                    {/* Step Circle */}
                                    <div className="relative z-10 flex flex-col items-center">
                                        <div
                                            className={cn(
                                                'flex h-8 w-8 items-center justify-center rounded-full border-2 transition-all duration-200',
                                                {
                                                    'border-primary bg-primary text-primary-foreground': isCurrent || isCompleted,
                                                    'border-muted-foreground/25 bg-background text-muted-foreground': !isCurrent && !isCompleted,
                                                    'hover:border-primary/50': isClickable,
                                                },
                                            )}
                                        >
                                            {isCompleted ? <Check className="h-4 w-4" /> : <span className="text-xs font-semibold">{step.id}</span>}
                                        </div>

                                        {/* Step Label — truncates on narrow viewports */}
                                        <div className="mt-2 w-16 text-center sm:w-20 lg:w-24">
                                            <p
                                                className={cn('truncate text-xs font-medium lg:text-sm', {
                                                    'text-primary': isCurrent,
                                                    'text-foreground': isCompleted,
                                                    'text-muted-foreground': !isCurrent && !isCompleted,
                                                })}
                                                title={step.title}
                                            >
                                                {step.title}
                                            </p>
                                            {step.description && (
                                                <p className="text-muted-foreground mt-0.5 hidden text-xs lg:block">{step.description}</p>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                </div>
            </div>

            {/* Mobile Stepper - Compact Vertical */}
            <div className="md:hidden">
                <div className="space-y-2">
                    {visibleMobileSteps.map((step) => {
                        const isCompleted = step.id < currentStep;
                        const isCurrent = step.id === currentStep;
                        const isClickable = onStepClick && (isCompleted || isCurrent);

                        return (
                            <div
                                key={step.id}
                                className={cn('flex items-center gap-3 rounded-lg border p-3 transition-all duration-200', {
                                    'border-primary bg-primary/5': isCurrent,
                                    'border-sidebar-border bg-background': !isCurrent,
                                    'hover:border-primary/50 cursor-pointer': isClickable,
                                })}
                                onClick={() => isClickable && onStepClick(step.id)}
                            >
                                {/* Step Circle */}
                                <div
                                    className={cn(
                                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-200',
                                        {
                                            'border-primary bg-primary text-primary-foreground': isCurrent || isCompleted,
                                            'border-muted-foreground/25 text-muted-foreground': !isCurrent && !isCompleted,
                                        },
                                    )}
                                >
                                    {isCompleted ? <Check className="h-4 w-4" /> : <span className="text-xs font-semibold">{step.id}</span>}
                                </div>

                                {/* Step Info */}
                                <div className="min-w-0 flex-1">
                                    <p
                                        className={cn('truncate text-sm font-medium', {
                                            'text-primary': isCurrent,
                                            'text-foreground': isCompleted,
                                            'text-muted-foreground': !isCurrent && !isCompleted,
                                        })}
                                        title={step.title}
                                    >
                                        {step.title}
                                    </p>
                                    {step.description && isCurrent && <p className="text-muted-foreground mt-0.5 text-xs">{step.description}</p>}
                                </div>

                                {/* Status indicator */}
                                {isCurrent && (
                                    <span className="bg-primary/10 text-primary shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium">
                                        Current
                                    </span>
                                )}
                                {isCompleted && !isCurrent && <span className="text-muted-foreground shrink-0 text-[10px] font-medium">Done</span>}
                            </div>
                        );
                    })}
                </div>

                {/* Show more / less toggle */}
                {hasMoreSteps && (
                    <button
                        type="button"
                        onClick={() => setShowAllSteps(true)}
                        className="text-muted-foreground hover:text-foreground mt-2 flex w-full items-center justify-center gap-1 rounded-lg border border-dashed py-2 text-xs font-medium transition-colors"
                    >
                        <ChevronRight className="h-3 w-3" />
                        Show {steps.length - visibleMobileSteps.length} more stages
                    </button>
                )}
                {showAllSteps && visibleMobileSteps.length > 3 && (
                    <button
                        type="button"
                        onClick={() => setShowAllSteps(false)}
                        className="text-muted-foreground hover:text-foreground mt-2 flex w-full items-center justify-center gap-1 rounded-lg border border-dashed py-2 text-xs font-medium transition-colors"
                    >
                        Show less
                    </button>
                )}
            </div>
        </div>
    );
}
