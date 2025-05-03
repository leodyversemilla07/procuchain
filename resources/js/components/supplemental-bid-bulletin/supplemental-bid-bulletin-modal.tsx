import React from 'react';
import { useForm } from '@inertiajs/react';
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

interface SupplementalBidModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    procurementId: string;
    procurementTitle: string;
    onComplete?: (skipToStage?: string, supplementalBidNeeded?: boolean) => void;
}

interface PageProps {
    success?: boolean;
    nextStage?: string;
    errors?: Record<string, string>;
}

export function SupplementalBidBulletinModal({
    open,
    onOpenChange,
    procurementId,
    procurementTitle,
    onComplete
}: SupplementalBidModalProps) {
    const form = useForm({
        procurement_id: procurementId,
        procurement_title: procurementTitle,
        supplemental_bid_needed: undefined as boolean | undefined,
    });

    const handleSuccess = (response: { props: PageProps }) => {
        onOpenChange(false);

        const message = form.data.supplemental_bid_needed
            ? "You will now proceed to upload supplemental bid bulletin documents."
            : "The supplemental bid bulletin stage has been skipped.";

        toast.success("Decision submitted successfully!", { description: message });

        if (onComplete && response?.props?.success) {
            onComplete(
                response.props.nextStage,
                form.data.supplemental_bid_needed
            );
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

        if (form.data.supplemental_bid_needed === undefined) {
            form.setError('supplemental_bid_needed', 'Please select whether supplemental bid bulletin is needed');
            return;
        }

        form.clearErrors();

        form.post('/bac-secretariat/publish-supplemental-bid-bulletin-decision', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: handleSuccess,
            onError: handleError
        });
    };

    const handleSelectionChange = (value: string) => {
        form.setData('supplemental_bid_needed', value === 'true');
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
                        Supplemental Bid Bulletin Decision
                    </DialogTitle>
                    <DialogDescription className="text-sm sm:text-base leading-relaxed">
                        Please indicate whether a supplemental bid bulletin is needed for this procurement:
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
                                Is a supplemental bid bulletin needed?
                            </Label>
                            <RadioGroup
                                value={form.data.supplemental_bid_needed === undefined ? undefined : form.data.supplemental_bid_needed.toString()}
                                onValueChange={handleSelectionChange}
                                className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 pt-2"
                                aria-label="Supplemental bid bulletin status"
                            >
                                <Label htmlFor="supplemental-yes" className="w-full m-0">
                                    <div className="flex items-center space-x-3 rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors min-h-[48px]">
                                        <RadioGroupItem value="true" id="supplemental-yes" />
                                        <span className="cursor-pointer">Yes</span>
                                    </div>
                                </Label>
                                <Label htmlFor="supplemental-no" className="w-full m-0">
                                    <div className="flex items-center space-x-3 rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors min-h-[48px]">
                                        <RadioGroupItem value="false" id="supplemental-no" />
                                        <span className="cursor-pointer">No</span>
                                    </div>
                                </Label>
                            </RadioGroup>
                            {form.errors.supplemental_bid_needed && (
                                <p className="text-red-500 text-sm mt-2" id="supplemental-error" aria-live="polite">
                                    {form.errors.supplemental_bid_needed}
                                </p>
                            )}
                        </div>

                        {form.data.supplemental_bid_needed !== undefined && (
                            <div className={`p-3 sm:p-4 rounded-lg text-sm sm:text-base ${form.data.supplemental_bid_needed
                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300'
                                }`}>
                                {form.data.supplemental_bid_needed ? (
                                    <p>You'll be directed to upload supplemental bid bulletin documents.</p>
                                ) : (
                                    <p>This will skip the supplemental bid bulletin stage and proceed to Bid Opening.</p>
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