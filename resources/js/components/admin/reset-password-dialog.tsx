import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { AlertTriangle, ShieldAlert } from 'lucide-react';
import React, { useState } from 'react';
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
            route('admin.users.reset-password', user.id),
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
                    <div className="flex items-center space-x-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/20">
                            <ShieldAlert className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div>
                            <DialogTitle className="text-foreground">Reset User Password</DialogTitle>
                            <DialogDescription className="text-muted-foreground mt-1 text-sm">
                                Send password reset link to {user.email}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Warning */}
                    <div className="flex items-start space-x-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-900 dark:bg-yellow-900/10">
                        <AlertTriangle className="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-600 dark:text-yellow-400" />
                        <div className="flex-1 space-y-1">
                            <p className="text-sm font-medium text-yellow-800 dark:text-yellow-200">Important</p>
                            <p className="text-xs text-yellow-700 dark:text-yellow-300">
                                This will send a password reset link to the user's email address. They will be able to choose their new
                                password.
                            </p>
                        </div>
                    </div>

                    {/* Reason */}
                    <div className="space-y-2">
                        <Label htmlFor="reset-reason">Reason for Reset *</Label>
                        <Textarea
                            id="reset-reason"
                            placeholder="e.g., User requested password reset, Security concern, Account recovery..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            disabled={isSubmitting}
                            rows={4}
                            className="resize-none"
                        />
                        <p className="text-muted-foreground text-xs">This will be logged for audit purposes</p>
                    </div>

                    {/* User Info */}
                    <div className="bg-muted rounded-lg p-3">
                        <h4 className="mb-2 text-sm font-medium">User Details</h4>
                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Name:</dt>
                                <dd className="font-medium">{user.name}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Email:</dt>
                                <dd className="font-medium">{user.email}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <DialogFooter className="gap-2">
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
