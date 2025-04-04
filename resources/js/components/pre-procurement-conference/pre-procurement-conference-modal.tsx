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

interface PreProcurementModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    procurementId: string;
    procurementTitle: string;
    onComplete?: (skipToPhase?: string, conferenceHeld?: boolean) => void;
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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (form.data.conference_held === undefined) {
            form.setError('conference_held', 'Please select whether a conference was held');
            return;
        }

        form.post('/bac-secretariat/publish-pre-procurement-conference-decision', {
            onSuccess: (response) => {
                onOpenChange(false);

                const message = form.data.conference_held
                    ? "You will now proceed to upload pre-procurement documents."
                    : "The pre-procurement phase has been skipped.";

                toast.success("Decision submitted successfully!", { description: message });

                if (onComplete && response?.props?.success) {
                    onComplete(
                        response?.props?.nextPhase as string | undefined,
                        form.data.conference_held
                    );
                }

                form.reset();
            }
        });
    };

    const handleConferenceSelection = (value: string) => {
        form.setData('conference_held', value === 'true');
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(newOpen) => {
                if (!form.processing) onOpenChange(newOpen);
            }}
        >
            <DialogContent className="sm:max-w-[500px] p-6">
                <DialogHeader className="space-y-3">
                    <DialogTitle className="text-2xl font-semibold tracking-tight">
                        Pre-Procurement Conference Decision
                    </DialogTitle>
                    <DialogDescription className="text-base leading-relaxed">
                        Please indicate whether a pre-procurement conference was held for this procurement:
                        <span className="block font-medium text-gray-700 dark:text-gray-300 mt-2">
                            {procurementTitle}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="mt-6">
                    <div className="space-y-6">
                        <div className="space-y-4">
                            <Label className="text-base font-medium">
                                Was a pre-procurement conference held?
                            </Label>
                            <RadioGroup
                                value={form.data.conference_held === undefined ? undefined : form.data.conference_held.toString()}
                                onValueChange={handleConferenceSelection}
                                className="grid grid-cols-2 gap-4 pt-2"
                                aria-label="Pre-procurement conference status"
                            >
                                <Label htmlFor="conference-yes" className="w-full m-0">
                                    <div className="flex items-center space-x-3 rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                        <RadioGroupItem value="true" id="conference-yes" />
                                        <span className="cursor-pointer">Yes</span>
                                    </div>
                                </Label>
                                <Label htmlFor="conference-no" className="w-full m-0">
                                    <div className="flex items-center space-x-3 rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
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
                            <div className={`p-4 rounded-lg ${form.data.conference_held
                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300'
                                }`}>
                                {form.data.conference_held ? (
                                    <p>You'll be directed to the procurement list to upload the pre-procurement documents.</p>
                                ) : (
                                    <p>This will skip the pre-procurement phase and proceed to Bid Invitation Publication.</p>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter className="mt-8">
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full sm:w-auto min-w-[140px] transition-all"
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
