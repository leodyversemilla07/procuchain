import * as React from 'react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

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
    return (
        <div className={cn('w-full', className)}>
            {/* Desktop Stepper - Horizontal */}
            <div className="hidden md:block">
                <ol className="flex items-start justify-between">
                    {steps.map((step, index) => {
                        const isCompleted = step.id < currentStep;
                        const isCurrent = step.id === currentStep;
                        const isClickable = onStepClick && (isCompleted || isCurrent);

                        return (
                            <li
                                key={step.id}
                                className={cn('relative flex flex-1 flex-col items-center', {
                                    'cursor-pointer': isClickable,
                                })}
                                onClick={() => isClickable && onStepClick(step.id)}
                            >
                                {/* Connector Line */}
                                {index !== 0 && (
                                    <div
                                        className={cn(
                                            'absolute left-0 right-1/2 top-5 h-0.5 -translate-y-1/2',
                                            isCompleted || isCurrent
                                                ? 'bg-primary'
                                                : 'bg-muted',
                                        )}
                                    />
                                )}
                                {index !== steps.length - 1 && (
                                    <div
                                        className={cn(
                                            'absolute left-1/2 right-0 top-5 h-0.5 -translate-y-1/2',
                                            isCompleted ? 'bg-primary' : 'bg-muted',
                                        )}
                                    />
                                )}

                                {/* Step Circle */}
                                <div className="relative z-10 flex flex-col items-center">
                                    <div
                                        className={cn(
                                            'flex h-10 w-10 items-center justify-center rounded-full border-2 bg-background transition-all duration-200',
                                            {
                                                'border-primary bg-primary text-primary-foreground':
                                                    isCurrent || isCompleted,
                                                'border-muted-foreground/25 text-muted-foreground':
                                                    !isCurrent && !isCompleted,
                                                'hover:border-primary/50': isClickable,
                                            },
                                        )}
                                    >
                                        {isCompleted ? (
                                            <Check className="h-5 w-5" />
                                        ) : (
                                            <span className="text-sm font-semibold">{step.id}</span>
                                        )}
                                    </div>

                                    {/* Step Label */}
                                    <div className="mt-3 text-center">
                                        <p
                                            className={cn('text-sm font-medium', {
                                                'text-primary': isCurrent,
                                                'text-foreground': isCompleted,
                                                'text-muted-foreground': !isCurrent && !isCompleted,
                                            })}
                                        >
                                            {step.title}
                                        </p>
                                        {step.description && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {step.description}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </div>

            {/* Mobile Stepper - Vertical Compact */}
            <div className="md:hidden">
                <div className="space-y-3">
                    {steps.map((step) => {
                        const isCompleted = step.id < currentStep;
                        const isCurrent = step.id === currentStep;
                        const isClickable = onStepClick && (isCompleted || isCurrent);

                        return (
                            <div
                                key={step.id}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg border p-3 transition-all duration-200',
                                    {
                                        'border-primary bg-primary/5': isCurrent,
                                        'border-sidebar-border bg-background': !isCurrent,
                                        'cursor-pointer hover:border-primary/50': isClickable,
                                    },
                                )}
                                onClick={() => isClickable && onStepClick(step.id)}
                            >
                                {/* Step Circle */}
                                <div
                                    className={cn(
                                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-200',
                                        {
                                            'border-primary bg-primary text-primary-foreground':
                                                isCurrent || isCompleted,
                                            'border-muted-foreground/25 text-muted-foreground':
                                                !isCurrent && !isCompleted,
                                        },
                                    )}
                                >
                                    {isCompleted ? (
                                        <Check className="h-4 w-4" />
                                    ) : (
                                        <span className="text-xs font-semibold">{step.id}</span>
                                    )}
                                </div>

                                {/* Step Info */}
                                <div className="flex-1">
                                    <p
                                        className={cn('text-sm font-medium', {
                                            'text-primary': isCurrent,
                                            'text-foreground': isCompleted,
                                            'text-muted-foreground': !isCurrent && !isCompleted,
                                        })}
                                    >
                                        {step.title}
                                    </p>
                                    {step.description && isCurrent && (
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {step.description}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
