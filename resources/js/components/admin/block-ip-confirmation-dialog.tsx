import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { AlertTriangle, Loader2, ShieldBan } from 'lucide-react';
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
            <DialogContent className="gap-0 p-0 sm:max-w-[500px]">
                <DialogHeader className="border-b px-6 py-6 pb-4">
                    <div className="flex items-center space-x-3">
                        <div className="bg-destructive/10 dark:bg-destructive/20 flex h-10 w-10 items-center justify-center rounded-lg">
                            <ShieldBan className="text-destructive h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-foreground text-xl font-semibold">Block IP Address</DialogTitle>
                            <DialogDescription className="text-muted-foreground mt-1 text-sm">
                                This will prevent access from this IP address
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-6 px-6 py-6">
                    {/* Warning Alert */}
                    <div className="border-destructive/50 bg-destructive/5 flex items-start space-x-3 rounded-lg border p-4">
                        <AlertTriangle className="text-destructive mt-0.5 h-5 w-5" />
                        <div className="flex-1">
                            <p className="text-destructive font-medium">Warning</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                You are about to block IP address{' '}
                                <code className="bg-muted rounded px-1.5 py-0.5 font-mono text-sm">{ipAddress}</code>. This action will immediately
                                prevent any access from this IP address.
                            </p>
                        </div>
                    </div>

                    {/* Duration Selection */}
                    <div className="space-y-3">
                        <Label htmlFor="duration">Block Duration</Label>
                        <RadioGroup value={duration} onValueChange={(value) => setDuration(value as 'temporary' | 'permanent')}>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="permanent" id="permanent" />
                                <Label htmlFor="permanent" className="cursor-pointer font-normal">
                                    <span className="font-medium">Permanent</span>
                                    <span className="text-muted-foreground ml-2 text-sm">Block indefinitely until manually unblocked</span>
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="temporary" id="temporary" />
                                <Label htmlFor="temporary" className="cursor-pointer font-normal">
                                    <span className="font-medium">Temporary</span>
                                    <span className="text-muted-foreground ml-2 text-sm">Block for 30 days</span>
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* Reason Input */}
                    <div className="space-y-2">
                        <Label htmlFor="reason">Reason (Optional)</Label>
                        <Textarea
                            id="reason"
                            placeholder="Enter the reason for blocking this IP address..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={3}
                            className="resize-none"
                            disabled={isBlocking}
                        />
                        <p className="text-muted-foreground text-xs">This reason will be logged for administrative purposes.</p>
                    </div>
                </div>

                <DialogFooter className="border-t px-6 py-4">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isBlocking}>
                        Cancel
                    </Button>
                    <Button type="button" variant="destructive" onClick={handleConfirm} disabled={isBlocking}>
                        {isBlocking ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
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
