import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

interface SupplementalBidDialogProps {
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

export function SupplementalBidBulletinDialog({ open, onOpenChange, procurementId, procurementTitle, onComplete }: SupplementalBidDialogProps) {
    const form = useForm({
        procurement_id: procurementId,
        procurement_title: procurementTitle,
        supplemental_bid_needed: undefined as boolean | undefined,
    });

    const handleSuccess = (response: { props: PageProps }) => {
        onOpenChange(false);

        const message = form.data.supplemental_bid_needed
            ? 'You will now proceed to upload supplemental bid bulletin documents.'
            : 'The supplemental bid bulletin stage has been skipped.';

        toast.success('Decision submitted successfully!', { description: message });

        if (onComplete && response?.props?.success) {
            onComplete(response.props.nextStage, form.data.supplemental_bid_needed);
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

        if (form.data.supplemental_bid_needed === undefined) {
            form.setError('supplemental_bid_needed', 'Please select whether supplemental bid bulletin is needed');
            return;
        }

        form.clearErrors();

        form.post('/bac-secretariat/publish-supplemental-bid-bulletin-decision', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: handleSuccess,
            onError: handleError,
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
                className="max-h-[90vh] w-[90%] overflow-y-auto p-4 sm:max-w-[500px] sm:p-6 md:max-w-[600px]"
                onOpenAutoFocus={(e) => e.preventDefault()}
            >
                <DialogHeader className="space-y-2 sm:space-y-3">
                    <DialogTitle className="text-xl font-semibold tracking-tight sm:text-2xl">Supplemental Bid Bulletin Decision</DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed sm:text-base">
                        Please indicate whether a supplemental bid bulletin is needed for this procurement:
                    </DialogDescription>
                    <div className="mt-2">
                        <span className="block text-sm font-medium text-gray-700 sm:text-base dark:text-gray-300">Title: {procurementTitle}</span>
                        <span className="mt-1 block text-xs text-gray-500 sm:text-sm dark:text-gray-400">ID: {procurementId}</span>
                    </div>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="mt-4 sm:mt-6">
                    <div className="space-y-4 sm:space-y-6">
                        <div className="space-y-3 sm:space-y-4">
                            <Label className="text-sm font-medium sm:text-base">Is a supplemental bid bulletin needed?</Label>
                            <RadioGroup
                                value={form.data.supplemental_bid_needed === undefined ? undefined : form.data.supplemental_bid_needed.toString()}
                                onValueChange={handleSelectionChange}
                                className="grid grid-cols-1 gap-3 pt-2 sm:grid-cols-2 sm:gap-4"
                                aria-label="Supplemental bid bulletin status"
                            >
                                <Label htmlFor="supplemental-yes" className="m-0 w-full">
                                    <div className="flex min-h-[48px] cursor-pointer items-center space-x-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <RadioGroupItem value="true" id="supplemental-yes" />
                                        <span className="cursor-pointer">Yes</span>
                                    </div>
                                </Label>
                                <Label htmlFor="supplemental-no" className="m-0 w-full">
                                    <div className="flex min-h-[48px] cursor-pointer items-center space-x-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <RadioGroupItem value="false" id="supplemental-no" />
                                        <span className="cursor-pointer">No</span>
                                    </div>
                                </Label>
                            </RadioGroup>
                            {form.errors.supplemental_bid_needed && (
                                <p className="mt-2 text-sm text-red-500" id="supplemental-error" aria-live="polite">
                                    {form.errors.supplemental_bid_needed}
                                </p>
                            )}
                        </div>

                        {form.data.supplemental_bid_needed !== undefined && (
                            <div
                                className={`rounded-lg p-3 text-sm sm:p-4 sm:text-base ${
                                    form.data.supplemental_bid_needed
                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300'
                                        : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
                                }`}
                            >
                                {form.data.supplemental_bid_needed ? (
                                    <p>You'll be directed to upload supplemental bid bulletin documents.</p>
                                ) : (
                                    <p>This will skip the supplemental bid bulletin stage and proceed to Bid Opening.</p>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter className="mt-6 flex-col gap-4 sm:mt-8 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="min-h-[44px] w-full text-sm sm:w-auto sm:text-base"
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing} className="min-h-[44px] w-full text-sm sm:w-auto sm:text-base">
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
