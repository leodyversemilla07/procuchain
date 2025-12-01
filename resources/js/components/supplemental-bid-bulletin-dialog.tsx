import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

interface SupplementalBidDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pr_number: string;
    procurementTitle: string;
    onComplete?: (skipToStage?: string, supplementalBidNeeded?: boolean) => void;
}

interface PageProps {
    success?: boolean;
    nextStage?: string;
    errors?: Record<string, string>;
}

export function SupplementalBidBulletinDialog({ open, onOpenChange, pr_number, procurementTitle, onComplete }: SupplementalBidDialogProps) {
    const form = useForm({
        pr_number: pr_number,
        procurement_title: procurementTitle,
        supplemental_bid_needed: undefined as boolean | undefined,
    });

    const handleSuccess = () => {
        onOpenChange(false);

        const message = form.data.supplemental_bid_needed
            ? 'You will now proceed to upload supplemental bid bulletin documents.'
            : 'The supplemental bid bulletin stage has been skipped.';

        toast.success('Decision submitted successfully!', { description: message });

        if (onComplete) {
            onComplete(undefined, form.data.supplemental_bid_needed);
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
        form.setData({
            ...form.data,
            supplemental_bid_needed: value === 'true',
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
                className="max-h-[90vh] w-[90%] overflow-y-auto sm:max-w-[500px] md:max-w-[600px]"
                onOpenAutoFocus={(e) => e.preventDefault()}
            >
                <DialogHeader className="space-y-3">
                    <DialogTitle className="text-xl font-semibold tracking-tight sm:text-2xl">Supplemental Bid Bulletin Decision</DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed sm:text-base">
                        Please indicate whether a supplemental bid bulletin is needed for this procurement.
                    </DialogDescription>
                    <div className="bg-muted/50 rounded-lg border p-3 sm:p-4">
                        <p className="text-foreground text-sm font-medium sm:text-base">
                            <span className="text-muted-foreground">Title:</span> {procurementTitle}
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs sm:text-sm">
                            <span className="font-medium">ID:</span> {pr_number}
                        </p>
                    </div>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="mt-6 space-y-6">
                    <div className="space-y-4">
                        <Label className="text-sm font-medium sm:text-base">
                            Is a supplemental bid bulletin needed? <span className="text-destructive">*</span>
                        </Label>
                        <RadioGroup
                            value={form.data.supplemental_bid_needed === undefined ? undefined : form.data.supplemental_bid_needed.toString()}
                            onValueChange={handleSelectionChange}
                            className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                            aria-label="Supplemental bid bulletin status"
                        >
                            <Label
                                htmlFor="supplemental-yes"
                                className={`hover:border-primary/50 hover:bg-accent/50 m-0 flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border-2 p-4 transition-all ${
                                    form.data.supplemental_bid_needed === true
                                        ? 'border-primary bg-primary/5 ring-primary/20 ring-2'
                                        : 'border-border'
                                }`}
                            >
                                <RadioGroupItem value="true" id="supplemental-yes" />
                                <div className="flex flex-1 items-center justify-between">
                                    <span className="font-medium">Yes, Bulletin Needed</span>
                                    {form.data.supplemental_bid_needed === true && <CheckCircle2 className="text-primary h-4 w-4" />}
                                </div>
                            </Label>
                            <Label
                                htmlFor="supplemental-no"
                                className={`hover:border-primary/50 hover:bg-accent/50 m-0 flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border-2 p-4 transition-all ${
                                    form.data.supplemental_bid_needed === false
                                        ? 'border-primary bg-primary/5 ring-primary/20 ring-2'
                                        : 'border-border'
                                }`}
                            >
                                <RadioGroupItem value="false" id="supplemental-no" />
                                <div className="flex flex-1 items-center justify-between">
                                    <span className="font-medium">No, Skip Bulletin</span>
                                    {form.data.supplemental_bid_needed === false && <AlertCircle className="h-4 w-4 text-amber-500" />}
                                </div>
                            </Label>
                        </RadioGroup>
                        {form.errors.supplemental_bid_needed && (
                            <div className="border-destructive/50 bg-destructive/10 flex items-start gap-2 rounded-lg border p-3">
                                <AlertCircle className="text-destructive mt-0.5 h-4 w-4 shrink-0" />
                                <p className="text-destructive text-sm" id="supplemental-error" aria-live="polite">
                                    {form.errors.supplemental_bid_needed}
                                </p>
                            </div>
                        )}
                    </div>

                    {form.data.supplemental_bid_needed !== undefined && (
                        <div
                            className={`flex items-start gap-3 rounded-lg border p-4 ${
                                form.data.supplemental_bid_needed
                                    ? 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30'
                                    : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30'
                            }`}
                        >
                            {form.data.supplemental_bid_needed ? (
                                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                            ) : (
                                <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                            )}
                            <div className="flex-1">
                                <p className="text-foreground text-sm font-medium sm:text-base">
                                    {form.data.supplemental_bid_needed ? 'Next Step: Upload Bulletin' : 'Next Step: Skip to Bid Opening'}
                                </p>
                                <p
                                    className={`mt-1 text-sm ${
                                        form.data.supplemental_bid_needed ? 'text-blue-700 dark:text-blue-300' : 'text-amber-700 dark:text-amber-300'
                                    }`}
                                >
                                    {form.data.supplemental_bid_needed
                                        ? "You'll be directed to upload supplemental bid bulletin documents."
                                        : 'This will skip the supplemental bid bulletin stage and proceed to Bid Opening.'}
                                </p>
                            </div>
                        </div>
                    )}

                    <DialogFooter className="gap-3 sm:gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="min-h-11 w-full sm:w-auto"
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || form.data.supplemental_bid_needed === undefined}
                            className="min-h-11 w-full sm:w-auto"
                        >
                            {form.processing ? (
                                <span className="flex items-center gap-2">
                                    <Spinner />
                                    Processing...
                                </span>
                            ) : (
                                <span className="flex items-center gap-2">
                                    <CheckCircle2 className="h-4 w-4" />
                                    Submit Decision
                                </span>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
