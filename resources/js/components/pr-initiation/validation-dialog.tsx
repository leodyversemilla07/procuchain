import { AlertTriangle, CheckCircle2, XCircle } from "lucide-react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";

interface ValidationError {
    field: string;
    message: string;
}

interface ValidationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    errors: ValidationError[];
    formCompletion: {
        details: boolean;
        prDocument: boolean;
        supporting: boolean;
    };
    currentStep?: number; // Add currentStep as an optional prop
}

export function ValidationDialog({
    open,
    onOpenChange,
    errors,
    formCompletion,
    currentStep = 3, // Default to final step if not provided
}: ValidationDialogProps) {
    const hasErrors = errors.length > 0;
    
    // Adjust form completion based on current step
    // Only consider a step complete if we've actually reached that step
    const adjustedFormCompletion = {
        details: formCompletion.details,
        prDocument: currentStep >= 2 ? formCompletion.prDocument : false,
        supporting: currentStep >= 3 ? formCompletion.supporting : false,
    };
    
    const completionStatus = Object.values(adjustedFormCompletion).filter(Boolean).length;
    const totalSteps = Object.keys(adjustedFormCompletion).length;
    const completionPercentage = Math.round((completionStatus / totalSteps) * 100);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md md:max-w-lg p-0 overflow-hidden border-sidebar-border/70 dark:border-sidebar-border rounded-xl">
                <div className={`h-1.5 w-full ${hasErrors ? "bg-destructive" : "bg-primary"}`} />

                <div className="p-6">
                    <DialogHeader className="pb-4">
                        <div className="flex items-center gap-3">
                            {hasErrors ? (
                                <div className="h-10 w-10 rounded-full bg-destructive/10 flex items-center justify-center">
                                    <AlertTriangle className="h-6 w-6 text-destructive" />
                                </div>
                            ) : (
                                <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                    <CheckCircle2 className="h-6 w-6 text-primary" />
                                </div>
                            )}
                            <DialogTitle className="text-xl">
                                {hasErrors ? "Validation Issues Found" : "Form Validation"}
                            </DialogTitle>
                        </div>
                        <DialogDescription className="pt-2">
                            {hasErrors
                                ? "Please address the following issues before submitting the form."
                                : "Your form looks good! All required information has been provided."}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-5">
                        {/* Form Completion Summary */}
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-lg border bg-muted/20 p-4">
                            <h3 className="font-medium mb-3 text-sm">Form Completion Status</h3>

                            <div className="space-y-4">
                                <div className="flex flex-col gap-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Overall Completion</span>
                                        <span className="text-sm font-medium">{completionPercentage}%</span>
                                    </div>
                                    <Progress value={completionPercentage} className="h-2" />
                                </div>

                                <div className="grid grid-cols-1 gap-3 mt-4">
                                    {Object.entries(adjustedFormCompletion).map(([key, completed]) => {
                                        const label = {
                                            details: "Procurement Details",
                                            prDocument: "PR Document",
                                            supporting: "Supporting Documents"
                                        }[key];

                                        const stepNumber = key === "details" ? 1 : key === "prDocument" ? 2 : 3;
                                        const isCurrentOrPreviousStep = stepNumber <= currentStep;

                                        return (
                                            <div
                                                key={key}
                                                className={`flex items-center justify-between text-sm p-2 rounded-lg ${
                                                    completed && isCurrentOrPreviousStep
                                                        ? "bg-primary/10 border border-primary/20"
                                                        : "bg-muted border border-muted"
                                                }`}
                                            >
                                                <span className="flex items-center gap-2">
                                                    {completed && isCurrentOrPreviousStep ? (
                                                        <CheckCircle2 className="h-4 w-4 text-primary" />
                                                    ) : (
                                                        <XCircle className="h-4 w-4 text-muted-foreground" />
                                                    )}
                                                    {label} {!isCurrentOrPreviousStep && "(Not reached yet)"}
                                                </span>
                                                <span className={`font-medium ${completed && isCurrentOrPreviousStep ? "text-primary" : "text-muted-foreground"}`}>
                                                    {completed && isCurrentOrPreviousStep ? "Complete" : "Incomplete"}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>

                        {/* Validation Errors */}
                        {hasErrors && (
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <h3 className="font-medium text-sm">Validation Issues</h3>
                                    <span className="bg-destructive/10 text-destructive text-xs font-medium px-2 py-0.5 rounded-full">
                                        {errors.length} {errors.length === 1 ? 'issue' : 'issues'}
                                    </span>
                                </div>
                                <ScrollArea className="max-h-64 rounded-lg border">
                                    <div className="divide-y">
                                        {errors.map((error, index) => (
                                            <div key={index} className="p-3.5 flex gap-3">
                                                <AlertTriangle className="h-5 w-5 text-destructive flex-shrink-0 mt-0.5" />
                                                <div>
                                                    <p className="font-medium text-sm">{error.field}</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {error.message}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </ScrollArea>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end mt-6">
                        <Button
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
