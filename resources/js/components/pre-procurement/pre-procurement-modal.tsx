import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
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
    const [conferenceHeld, setConferenceHeld] = useState<boolean | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState<boolean>(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});

        // Validate form inputs
        if (conferenceHeld === null) {
            setErrors({ conferenceHeld: 'Please select whether a conference was held' });
            return;
        }

        setProcessing(true);
        setIsSubmitting(true);

        const formData = new FormData();
        formData.append('procurement_id', procurementId);
        formData.append('procurement_title', procurementTitle);
        formData.append('conference_held', conferenceHeld ? '1' : '0');

        axios.post('/bac-secretariat/publish-pre-procurement-decision', formData)
            .then(response => {
                setProcessing(false);
                setIsSubmitting(false);
                onOpenChange(false);

                // Call onComplete callback when done, passing the next phase information
                if (onComplete && response.data.success) {
                    onComplete(response.data.nextPhase, conferenceHeld);
                }
            })
            .catch(error => {
                setProcessing(false);
                setIsSubmitting(false);
                setErrors(error.response?.data?.errors || { general: 'An error occurred' });
            });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(newOpen) => {
                if (isSubmitting) return;
                if (!newOpen && open && onComplete) onComplete();
                onOpenChange(newOpen);
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
                                value={conferenceHeld === null ? undefined : conferenceHeld.toString()}
                                onValueChange={(value) => setConferenceHeld(value === 'true')}
                                className="grid grid-cols-2 gap-4 pt-2"
                            >
                                <div className="flex items-center space-x-3 rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                    <RadioGroupItem value="true" id="conference-yes" />
                                    <Label htmlFor="conference-yes" className="cursor-pointer">Yes</Label>
                                </div>
                                <div className="flex items-center space-x-3 rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                    <RadioGroupItem value="false" id="conference-no" />
                                    <Label htmlFor="conference-no" className="cursor-pointer">No</Label>
                                </div>
                            </RadioGroup>
                            {errors.conferenceHeld && (
                                <p className="text-red-500 text-sm mt-2">{errors.conferenceHeld}</p>
                            )}
                        </div>

                        {conferenceHeld !== null && (
                            <div className={`p-4 rounded-lg ${conferenceHeld
                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300'
                                }`}>
                                {conferenceHeld ? (
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
                            disabled={processing}
                            className="w-full sm:w-auto min-w-[140px] transition-all"
                        >
                            {processing ? (
                                <span className="flex items-center gap-2">
                                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
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
