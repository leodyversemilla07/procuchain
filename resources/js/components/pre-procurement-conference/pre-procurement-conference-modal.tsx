import React from 'react';
import { useForm, router } from '@inertiajs/react';
import { toast } from "sonner";
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { LoaderCircle } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface PreProcurementModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    procurementId: string;
    procurementTitle: string;
    onComplete?: (skipToStage?: string, conferenceHeld?: boolean) => void;
}

interface PageProps {
    success?: boolean;
    nextStage?: string;
    errors?: Record<string, string>;
}

export function PreProcurementModal({
    open,
    onOpenChange,
    procurementId,
    procurementTitle,
    onComplete
}: PreProcurementModalProps) {
    const form = useForm({
        procurement_id: procurementId,
        procurement_title: procurementTitle,
        conference_held: undefined as boolean | undefined,
    });

    const handleSuccess = (response: { props: PageProps }) => {
        onOpenChange(false);

        const message = form.data.conference_held
            ? "You will now proceed to upload pre-procurement conference documents."
            : "The pre-procurement conference stage has been skipped.";

        toast.success("Decision submitted successfully!", { description: message });

        if (onComplete && response?.props?.success) {
            onComplete(
                response.props.nextStage,
                form.data.conference_held
            );
        }

        if (!form.data.conference_held) {
            router.visit('/bac-secretariat/procurements-list');
        }

        form.reset();
    };

    const handleError = (errors: Record<string, string>) => {
        toast.error("Failed to submit decision", {
            description: Object.values(errors)[0] || "Please try again or contact support if the problem persists."
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
            onError: handleError
        });
    };

    const handleConferenceSelection = (value: string) => {
        form.setData({
            ...form.data,
            conference_held: value === 'true'
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
                className="w-[90%] sm:max-w-[500px] md:max-w-[600px] p-4 sm:p-6 max-h-[90vh] overflow-y-auto"
                onOpenAutoFocus={(e) => e.preventDefault()}
            >
                <DialogHeader className="space-y-2 sm:space-y-3">
                    <DialogTitle className="text-xl sm:text-2xl font-semibold tracking-tight">
                        Pre-Procurement Conference Decision
                    </DialogTitle>
                    <DialogDescription className="text-sm sm:text-base leading-relaxed">
                        Please indicate whether a pre-procurement conference was held for this procurement:
                    </DialogDescription>
                    <div className="mt-2">
                        <span className="block font-medium text-gray-700 dark:text-gray-300 text-sm sm:text-base">
                            Title: {procurementTitle}
                        </span>
                        <span className="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                            ID: {procurementId}
                        </span>
                    </div>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="mt-4 sm:mt-6">
                    <div className="space-y-4 sm:space-y-6">
                        <div className="space-y-3 sm:space-y-4">
                            <Label className="text-sm sm:text-base font-medium">
                                Was a pre-procurement conference held?
                            </Label>
                            <RadioGroup
                                value={form.data.conference_held === undefined ? undefined : form.data.conference_held.toString()}
                                onValueChange={handleConferenceSelection}
                                className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 pt-2"
                                aria-label="Pre-procurement conference status"
                            >
                                <Label htmlFor="conference-yes" className="w-full m-0">
                                    <div className="flex items-center space-x-3 rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors min-h-[48px]">
                                        <RadioGroupItem value="true" id="conference-yes" />
                                        <span className="cursor-pointer">Yes</span>
                                    </div>
                                </Label>
                                <Label htmlFor="conference-no" className="w-full m-0">
                                    <div className="flex items-center space-x-3 rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors min-h-[48px]">
                                        <RadioGroupItem value="false" id="conference-no" />
                                        <span className="cursor-pointer">No</span>
                                    </div>
                                </Label>
                            </RadioGroup>
                            {form.errors.conference_held && (
                                <p className="text-red-500 text-sm mt-2" id="conference-error" aria-live="polite">
                                    {form.errors.conference_held}
                                </p>
                            )}
                        </div>

                        {form.data.conference_held !== undefined && (
                            <div className={`p-3 sm:p-4 rounded-lg text-sm sm:text-base ${form.data.conference_held
                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300'
                                }`}>
                                {form.data.conference_held ? (
                                    <p>You'll be directed to the procurement list to upload the pre-procurement conference documents.</p>
                                ) : (
                                    <p>This will skip the pre-procurement conference stage and proceed to Bidding Documents Publication.</p>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter className="mt-6 sm:mt-8 flex-col sm:flex-row sm:justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="w-full sm:w-auto min-h-[44px] text-sm sm:text-base"
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full sm:w-auto min-h-[44px] text-sm sm:text-base"
                        >
                            {form.processing ? (
                                <span className="flex items-center gap-2">
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                    Processing...
                                </span>
                            ) : "Submit Decision"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
