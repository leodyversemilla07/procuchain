import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { CheckCircle2, Link2, Mail, Shield, ShieldCheck, User, XCircle } from 'lucide-react';
import React from 'react';

// Flexible user interface that works with different User types
interface ExtendedUser {
    id: number;
    name: string;
    email: string;
    role: string;
    blockchain_address?: string | null;
    avatar?: string;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string | null;
    two_factor_enabled?: boolean;
    two_factor_confirmed_at?: string | null;
    two_factor_recovery_codes?: string | null;
    backup_codes?: string[];
    backup_codes_generated_at?: string | null;
    roles?: Array<{ id: number; name: string }>;
    // Account locking fields
    account_locked?: boolean;
    locked_at?: string | null;
    lock_expires_at?: string | null;
    failed_login_attempts?: number;
    last_failed_login_at?: string | null;
    locked_reason?: string | null;
    is_currently_locked?: boolean;
    lock_time_remaining?: string | null;
    [key: string]: unknown;
}

interface UserDetailsDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: ExtendedUser | null;
}

export default function UserDetailsDialog({ open, onOpenChange, user }: UserDetailsDialogProps) {
    if (!user) return null;

    const formatDateTime = (dateString?: string | null | unknown) => {
        // Handle null or undefined
        if (dateString === null || dateString === undefined) return 'N/A';
        
        // Convert to string if needed
        const dateStr = String(dateString);
        
        // Check if it's empty or invalid
        if (!dateStr || dateStr === 'null' || dateStr === 'undefined' || dateStr.trim() === '') return 'N/A';
        
        try {
            const date = new Date(dateStr);
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
            if (typeof user.two_factor_recovery_codes !== 'string') return 0;
            const codes = JSON.parse(user.two_factor_recovery_codes);
            return Array.isArray(codes) ? codes.length : 0;
        } catch {
            return 0;
        }
    };

    const getRoleDisplayName = (role: string) => {
        switch (role) {
            case 'bac_secretariat':
                return 'BAC Secretariat';
            case 'bac_chairman':
                return 'BAC Chairman';
            case 'hope':
                return 'HOPE';
            case 'admin':
                return 'Administrator';
            default:
                return role.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-2xl gap-0 p-0 flex flex-col">
                <DialogHeader className="border-b px-6 py-4 shrink-0">
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

                <div className="flex-1 overflow-auto">
                    <div className="space-y-6 px-6 py-4">
                        {/* Basic Information */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide">Basic Information</h3>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">User ID</label>
                                    <p className="font-mono text-sm">#{user.id}</p>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Full Name</label>
                                    <p className="flex items-center gap-2 text-sm break-words">
                                        <User className="h-4 w-4 shrink-0" />
                                        <span className="break-words">{user.name}</span>
                                    </p>
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <label className="text-muted-foreground text-xs font-medium">Email Address</label>
                                    <p className="flex items-center gap-2 text-sm break-all">
                                        <Mail className="h-4 w-4 shrink-0" />
                                        <span className="break-all">{user.email}</span>
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
                                <label className="text-muted-foreground text-xs font-medium">Assigned Role</label>
                                <div className="flex flex-wrap gap-2">
                                    {user.role ? (
                                        <Badge variant="default" className="gap-1">
                                            <Shield className="h-3 w-3" />
                                            {getRoleDisplayName(user.role)}
                                        </Badge>
                                    ) : user.roles && Array.isArray(user.roles) && user.roles.length > 0 ? (
                                        user.roles.map((role: { id: number; name: string }) => (
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

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                        <p className="text-sm break-words">{formatDateTime(user.two_factor_confirmed_at)}</p>
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
                                        <div className="bg-muted flex items-start gap-2 rounded-md p-3">
                                            <Link2 className="h-4 w-4 shrink-0 mt-0.5" />
                                            <span className="font-mono text-sm break-all">{user.blockchain_address}</span>
                                        </div>
                                    </div>
                                </div>
                                <Separator />
                            </>
                        )}

                        {/* Account Dates */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold uppercase tracking-wide">Account Information</h3>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Account Created</label>
                                    <p className="text-sm break-words">
                                        {formatDateTime(user.created_at) !== 'N/A' 
                                            ? formatDateTime(user.created_at) 
                                            : <span className="text-muted-foreground italic">Date not available</span>
                                        }
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-muted-foreground text-xs font-medium">Last Updated</label>
                                    <p className="text-sm break-words">
                                        {formatDateTime(user.updated_at) !== 'N/A' 
                                            ? formatDateTime(user.updated_at) 
                                            : <span className="text-muted-foreground italic">Date not available</span>
                                        }
                                    </p>
                                </div>

                                {user.email_verified_at && (
                                    <div className="space-y-2">
                                        <label className="text-muted-foreground text-xs font-medium">Email Verified At</label>
                                        <p className="text-sm break-words">{formatDateTime(user.email_verified_at)}</p>
                                    </div>
                                )}

                                {user.locked_at && (
                                    <div className="space-y-2">
                                        <label className="text-muted-foreground text-xs font-medium">Account Locked</label>
                                        <p className="text-sm break-words">{formatDateTime(user.locked_at)}</p>
                                    </div>
                                )}

                                {user.lock_expires_at && (
                                    <div className="space-y-2">
                                        <label className="text-muted-foreground text-xs font-medium">Lock Expires</label>
                                        <p className="text-sm break-words">{formatDateTime(user.lock_expires_at)}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end border-t px-6 py-3 shrink-0">
                    <Button onClick={() => onOpenChange(false)} variant="outline">
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
