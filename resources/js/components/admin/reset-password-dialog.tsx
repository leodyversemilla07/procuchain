import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';
import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface ResetPasswordDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: { id: number; name: string; email: string } | null;
}

export default function ResetPasswordDialog({ open, onOpenChange, user }: ResetPasswordDialogProps) {
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleReset = () => {
        if (!user) return;

        if (!reason.trim()) {
            toast.error('Please provide a reason for the password reset');
            return;
        }

        setIsSubmitting(true);

        router.post(
            admin.users.resetPassword.url(user.id),
            {
                reason: reason.trim(),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Password reset email sent to ${user.email}`);
                    setReason('');
                    onOpenChange(false);
                },
                onError: (errors) => {
                    console.error('Reset password error:', errors);
                    toast.error(errors.message || 'Failed to send password reset email');
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleClose = () => {
        if (!isSubmitting) {
            setReason('');
            onOpenChange(false);
        }
    };

    if (!user) return null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reset User Password</DialogTitle>
                    <DialogDescription>Send password reset link to {user.name} ({user.email})</DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Warning */}
                    <div className="flex items-start gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-900 dark:bg-yellow-900/10">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-yellow-600 dark:text-yellow-400" />
                        <div className="flex-1">
                            <p className="text-sm text-yellow-800 dark:text-yellow-200">
                                This will send a password reset link to the user's email address. They will be able to choose their new password.
                            </p>
                        </div>
                    </div>

                    {/* Reason */}
                    <Field>
                        <FieldLabel htmlFor="reset-reason">
                            Reason for Reset
                            <span className="text-destructive ml-1">*</span>
                        </FieldLabel>
                        <Textarea
                            id="reset-reason"
                            placeholder="e.g., User requested password reset, Security concern, Account recovery..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            disabled={isSubmitting}
                            rows={4}
                            className="resize-none"
                        />
                        <FieldDescription>This will be logged for audit purposes</FieldDescription>
                    </Field>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
                        Cancel
                    </Button>
                    <Button onClick={handleReset} disabled={isSubmitting || !reason.trim()} variant="default">
                        {isSubmitting ? 'Sending...' : 'Send Reset Link'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
