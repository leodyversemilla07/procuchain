import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { AlertTriangle, ShieldBan } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import { useState } from 'react';

interface BlockIpConfirmationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    ipAddress: string;
    onConfirm: (reason: string, duration: 'temporary' | 'permanent') => Promise<void>;
    isBlocking: boolean;
}

export default function BlockIpConfirmationDialog({ open, onOpenChange, ipAddress, onConfirm, isBlocking }: BlockIpConfirmationDialogProps) {
    const [reason, setReason] = useState('');
    const [duration, setDuration] = useState<'temporary' | 'permanent'>('permanent');

    const handleConfirm = async () => {
        await onConfirm(reason || 'Blocked due to suspicious activity', duration);
        // Reset form
        setReason('');
        setDuration('permanent');
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px] [&>button]:hidden">
                <DialogHeader>
                    <DialogTitle>Block IP Address</DialogTitle>
                    <DialogDescription>This will prevent access from this IP address</DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Warning */}
                    <div className="bg-muted/50 rounded-lg p-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="text-destructive mt-0.5 h-5 w-5 shrink-0" />
                            <p className="text-muted-foreground text-sm">
                                • You are about to block IP address{' '}
                                <code className="bg-background rounded px-1.5 py-0.5 font-mono text-sm">{ipAddress}</code>
                                <br />• This action will immediately prevent any access from this IP address
                            </p>
                        </div>
                    </div>

                    {/* Duration Selection */}
                    <Field>
                        <FieldLabel>Block Duration</FieldLabel>
                        <FieldGroup>
                            <RadioGroup value={duration} onValueChange={(value) => setDuration(value as 'temporary' | 'permanent')}>
                                <div className="flex items-center space-x-2">
                                    <RadioGroupItem value="permanent" id="permanent" />
                                    <Label htmlFor="permanent" className="cursor-pointer font-normal">
                                        Permanent <span className="text-muted-foreground text-sm">— Block indefinitely until manually unblocked</span>
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <RadioGroupItem value="temporary" id="temporary" />
                                    <Label htmlFor="temporary" className="cursor-pointer font-normal">
                                        Temporary <span className="text-muted-foreground text-sm">— Block for 30 days</span>
                                    </Label>
                                </div>
                            </RadioGroup>
                        </FieldGroup>
                    </Field>

                    {/* Reason Input */}
                    <Field>
                        <FieldLabel>Reason (Optional)</FieldLabel>
                        <FieldGroup>
                            <Textarea
                                id="reason"
                                placeholder="Enter the reason for blocking this IP address..."
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                rows={3}
                                className="resize-none"
                                disabled={isBlocking}
                            />
                        </FieldGroup>
                        <FieldDescription>This reason will be logged for administrative purposes.</FieldDescription>
                    </Field>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isBlocking}>
                        Cancel
                    </Button>
                    <Button type="button" variant="destructive" onClick={handleConfirm} disabled={isBlocking}>
                        {isBlocking ? (
                            <>
                                <Spinner data-icon="inline-start" />
                                Blocking...
                            </>
                        ) : (
                            <>
                                <ShieldBan className="mr-2 h-4 w-4" />
                                Block IP Address
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
