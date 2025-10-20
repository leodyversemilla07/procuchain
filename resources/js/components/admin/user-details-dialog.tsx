import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { CheckCircle2, Link2, Mail, Shield, ShieldCheck, User, XCircle } from 'lucide-react';
import React from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    blockchain_address?: string | null;
    two_factor_enabled?: boolean;
    two_factor_confirmed_at?: string | null;
    two_factor_recovery_codes?: string | null;
    profile_photo_url?: string | null;
    created_at: string;
    updated_at?: string | null;
    roles?: Array<{ id: number; name: string }>;
}

interface UserDetailsDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: User | null;
}

export default function UserDetailsDialog({ open, onOpenChange, user }: UserDetailsDialogProps) {
    if (!user) return null;

    const formatDateTime = (dateString?: string | null) => {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'N/A';
            return date.toLocaleString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return 'N/A';
        }
    };

    const getRecoveryCodesCount = () => {
        if (!user.two_factor_recovery_codes) return 0;
        try {
            const codes = JSON.parse(user.two_factor_recovery_codes);
            return Array.isArray(codes) ? codes.length : 0;
        } catch {
            return 0;
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl gap-0 p-0">
                <DialogHeader className="border-b px-6 py-6 pb-4">
                    <div className="flex items-center space-x-3">
                        <div className="bg-primary/10 dark:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-lg">
                            <User className="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-foreground text-xl font-semibold">User Details</DialogTitle>
                            <DialogDescription className="text-muted-foreground mt-1 text-sm">
                                Complete information for {user.name}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <ScrollArea className="max-h-[600px]">
                    <div className="space-y-6 px-6 py-4">
                        {/* Basic Information */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide">Basic Information</h3>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">User ID</label>
                                    <p className="font-mono text-sm">#{user.id}</p>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Full Name</label>
                                    <p className="flex items-center gap-2 text-sm">
                                        <User className="h-4 w-4" />
                                        {user.name}
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Email Address</label>
                                    <p className="flex items-center gap-2 text-sm">
                                        <Mail className="h-4 w-4" />
                                        {user.email}
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Email Verification</label>
                                    <div>
                                        {user.email_verified_at ? (
                                            <Badge variant="default" className="gap-1">
                                                <CheckCircle2 className="h-3 w-3" />
                                                Verified
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary" className="gap-1">
                                                <XCircle className="h-3 w-3" />
                                                Not Verified
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Role & Permissions */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide">Role & Permissions</h3>
                            <div className="space-y-2">
                                <label className="text-muted-foreground text-xs font-medium">Assigned Roles</label>
                                <div className="flex flex-wrap gap-2">
                                    {user.roles && user.roles.length > 0 ? (
                                        user.roles.map((role) => (
                                            <Badge key={role.id} variant="default" className="gap-1">
                                                <Shield className="h-3 w-3" />
                                                {role.name}
                                            </Badge>
                                        ))
                                    ) : (
                                        <Badge variant="secondary">No roles assigned</Badge>
                                    )}
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Security */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide">Security</h3>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Two-Factor Authentication</label>
                                    <div>
                                        {user.two_factor_enabled ? (
                                            <Badge variant="default" className="gap-1">
                                                <ShieldCheck className="h-3 w-3" />
                                                Enabled
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary" className="gap-1">
                                                <XCircle className="h-3 w-3" />
                                                Disabled
                                            </Badge>
                                        )}
                                    </div>
                                </div>

                                {user.two_factor_confirmed_at && (
                                    <div className="space-y-2">
                                        <label className="text-muted-foreground text-xs font-medium">2FA Confirmed At</label>
                                        <p className="text-sm">{formatDateTime(user.two_factor_confirmed_at)}</p>
                                    </div>
                                )}

                                {user.two_factor_recovery_codes && (
                                    <div className="space-y-2">
                                        <label className="text-muted-foreground text-xs font-medium">Recovery Codes</label>
                                        <p className="text-sm">{getRecoveryCodesCount()} codes available</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        <Separator />

                        {/* Blockchain */}
                        {user.blockchain_address && (
                            <>
                                <div className="space-y-4">
                                    <h3 className="text-sm font-semibold uppercase tracking-wide">Blockchain</h3>
                                    <div className="space-y-2">
                                        <label className="text-muted-foreground text-xs font-medium">Blockchain Address</label>
                                        <p className="bg-muted flex items-center gap-2 rounded-md p-3 font-mono text-sm">
                                            <Link2 className="h-4 w-4" />
                                            <span className="break-all">{user.blockchain_address}</span>
                                        </p>
                                    </div>
                                </div>
                                <Separator />
                            </>
                        )}

                        {/* Account Dates */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide">Account Information</h3>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Account Created</label>
                                    <p className="text-sm">{formatDateTime(user.created_at)}</p>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Last Updated</label>
                                    <p className="text-sm">{formatDateTime(user.updated_at)}</p>
                                </div>

                                {user.email_verified_at && (
                                    <div className="space-y-2">
                                        <label className="text-muted-foreground text-xs font-medium">Email Verified At</label>
                                        <p className="text-sm">{formatDateTime(user.email_verified_at)}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </ScrollArea>

                <div className="flex justify-end border-t px-6 py-4">
                    <Button onClick={() => onOpenChange(false)} variant="outline">
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
