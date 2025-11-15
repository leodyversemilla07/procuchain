import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { index as procurementsListIndex } from '@/routes/bac-secretariat/procurements';

interface PreProcurementDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pr_number: string;
    procurementTitle: string;
    onComplete?: (skipToStage?: string, conferenceHeld?: boolean) => void;
}

interface PageProps {
    success?: boolean;
    nextStage?: string;
    errors?: Record<string, string>;
}

export function PreProcurementDialog({ open, onOpenChange, pr_number, procurementTitle, onComplete }: PreProcurementDialogProps) {
    const form = useForm({
        pr_number: pr_number,
        procurement_title: procurementTitle,
        conference_held: undefined as boolean | undefined,
    });

    const handleSuccess = (response: { props: PageProps }) => {
        onOpenChange(false);

        const message = form.data.conference_held
            ? 'You will now proceed to upload pre-procurement conference documents.'
            : 'The pre-procurement conference stage has been skipped.';

        toast.success('Decision submitted successfully!', { description: message });

        if (onComplete && response?.props?.success) {
            onComplete(response.props.nextStage, form.data.conference_held);
        }

        if (!form.data.conference_held) {
            router.visit(procurementsListIndex.url());
        }

        form.reset();
    };

    const handleError = (errors: Record<string, string>) => {
        toast.error('Failed to submit decision', {
            description: Object.values(errors)[0] || 'Please try again or contact support if the problem persists.',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (form.data.conference_held === undefined) {
            form.setError('conference_held', 'Please select whether a conference was held');
            return;
        }

        form.clearErrors();

        form.post('/bac-secretariat/publish-pre-procurement-conference-decision', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: handleSuccess,
            onError: handleError,
        });
    };

    const handleConferenceSelection = (value: string) => {
        form.setData({
            ...form.data,
            conference_held: value === 'true',
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(newOpen) => {
                if (!form.processing) onOpenChange(newOpen);
            }}
        >
            <DialogContent
                className="max-h-[90vh] w-[90%] overflow-y-auto p-4 sm:max-w-[500px] sm:p-6 md:max-w-[600px]"
                onOpenAutoFocus={(e) => e.preventDefault()}
            >
                <DialogHeader className="space-y-2 sm:space-y-3">
                    <DialogTitle className="text-xl font-semibold tracking-tight sm:text-2xl">Pre-Procurement Conference Decision</DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed sm:text-base">
                        Please indicate whether a pre-procurement conference was held for this procurement:
                    </DialogDescription>
                    <div className="mt-2">
                        <span className="block text-sm font-medium text-gray-700 sm:text-base dark:text-gray-300">Title: {procurementTitle}</span>
                        <span className="mt-1 block text-xs text-gray-500 sm:text-sm dark:text-gray-400">ID: {pr_number}</span>
                    </div>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="mt-4 sm:mt-6">
                    <div className="space-y-4 sm:space-y-6">
                        <div className="space-y-3 sm:space-y-4">
                            <Label className="text-sm font-medium sm:text-base">Was a pre-procurement conference held?</Label>
                            <RadioGroup
                                value={form.data.conference_held === undefined ? undefined : form.data.conference_held.toString()}
                                onValueChange={handleConferenceSelection}
                                className="grid grid-cols-1 gap-3 pt-2 sm:grid-cols-2 sm:gap-4"
                                aria-label="Pre-procurement conference status"
                            >
                                <Label htmlFor="conference-yes" className="m-0 w-full">
                                    <div className="flex min-h-12 cursor-pointer items-center space-x-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <RadioGroupItem value="true" id="conference-yes" />
                                        <span className="cursor-pointer">Yes</span>
                                    </div>
                                </Label>
                                <Label htmlFor="conference-no" className="m-0 w-full">
                                    <div className="flex min-h-12 cursor-pointer items-center space-x-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <RadioGroupItem value="false" id="conference-no" />
                                        <span className="cursor-pointer">No</span>
                                    </div>
                                </Label>
                            </RadioGroup>
                            {form.errors.conference_held && (
                                <p className="mt-2 text-sm text-red-500" id="conference-error" aria-live="polite">
                                    {form.errors.conference_held}
                                </p>
                            )}
                        </div>

                        {form.data.conference_held !== undefined && (
                            <div
                                className={`rounded-lg p-3 text-sm sm:p-4 sm:text-base ${
                                    form.data.conference_held
                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300'
                                        : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
                                }`}
                            >
                                {form.data.conference_held ? (
                                    <p>You'll be directed to the procurement list to upload the pre-procurement conference documents.</p>
                                ) : (
                                    <p>This will skip the pre-procurement conference stage and proceed to Bidding Documents Publication.</p>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter className="mt-6 flex-col gap-4 sm:mt-8 sm:flex-row sm:justify-end">
                        <Button
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                            className="min-h-11 w-full text-sm sm:w-auto sm:text-base"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing} className="min-h-11 w-full text-sm sm:w-auto sm:text-base">
                            {form.processing ? (
                                <span className="flex items-center gap-2">
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                    Processing...
                                </span>
                            ) : (
                                'Submit Decision'
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
