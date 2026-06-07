import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { CheckCircle2, Link2, Mail, Shield, ShieldCheck, User, XCircle } from 'lucide-react';

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

interface UserDetailsSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: ExtendedUser | null;
}

export default function UserDetailsSheet({ open, onOpenChange, user }: UserDetailsSheetProps) {
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

    // Shared content component
    const UserDetailsContent = () => (
        <div className="flex flex-col gap-6 p-4 sm:px-6 sm:py-4">
            {/* Basic Information */}
            <div className="flex flex-col gap-4">
                <h3 className="text-sm font-semibold tracking-wide uppercase">Basic Information</h3>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <label className="text-muted-foreground text-xs font-medium">User ID</label>
                        <p className="font-mono text-sm">#{user.id}</p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <label className="text-muted-foreground text-xs font-medium">Full Name</label>
                        <p className="flex items-center gap-2 text-sm wrap-break-word">
                            <User />
                            <span className="wrap-break-word">{user.name}</span>
                        </p>
                    </div>

                    <div className="flex flex-col gap-2 md:col-span-2">
                        <label className="text-muted-foreground text-xs font-medium">Email Address</label>
                        <p className="flex items-center gap-2 text-sm break-all">
                            <Mail />
                            <span className="break-all">{user.email}</span>
                        </p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <label className="text-muted-foreground text-xs font-medium">Email Verification</label>
                        <div>
                            {user.email_verified_at ? (
                                <Badge variant="default" className="gap-1">
                                    <CheckCircle2 />
                                    Verified
                                </Badge>
                            ) : (
                                <Badge variant="secondary" className="gap-1">
                                    <XCircle />
                                    Not Verified
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <Separator />

            {/* Role & Permissions */}
            <div className="flex flex-col gap-4">
                <h3 className="text-sm font-semibold tracking-wide uppercase">Role & Permissions</h3>
                <div className="flex flex-col gap-2">
                    <label className="text-muted-foreground text-xs font-medium">Assigned Role</label>
                    <div className="flex flex-wrap gap-2">
                        {user.role ? (
                            <Badge variant="default" className="gap-1">
                                <Shield />
                                {getRoleDisplayName(user.role)}
                            </Badge>
                        ) : user.roles && Array.isArray(user.roles) && user.roles.length > 0 ? (
                            user.roles.map((role: { id: number; name: string }) => (
                                <Badge key={role.id} variant="default" className="gap-1">
                                    <Shield />
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
            <div className="flex flex-col gap-4">
                <h3 className="text-sm font-semibold tracking-wide uppercase">Security</h3>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <label className="text-muted-foreground text-xs font-medium">Two-Factor Authentication</label>
                        <div>
                            {user.two_factor_enabled ? (
                                <Badge variant="default" className="gap-1">
                                    <ShieldCheck />
                                    Enabled
                                </Badge>
                            ) : (
                                <Badge variant="secondary" className="gap-1">
                                    <XCircle />
                                    Disabled
                                </Badge>
                            )}
                        </div>
                    </div>

                    {user.two_factor_confirmed_at && (
                        <div className="flex flex-col gap-2">
                            <label className="text-muted-foreground text-xs font-medium">2FA Confirmed At</label>
                            <p className="text-sm wrap-break-word">{formatDateTime(user.two_factor_confirmed_at)}</p>
                        </div>
                    )}

                    {user.two_factor_recovery_codes && (
                        <div className="flex flex-col gap-2">
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
                    <div className="flex flex-col gap-4">
                        <h3 className="text-sm font-semibold tracking-wide uppercase">Blockchain</h3>
                        <div className="flex flex-col gap-2">
                            <label className="text-muted-foreground text-xs font-medium">Blockchain Address</label>
                            <div className="bg-muted flex items-start gap-2 rounded-md p-3">
                                <Link2 />
                                <span className="font-mono text-sm break-all">{user.blockchain_address}</span>
                            </div>
                        </div>
                    </div>
                    <Separator />
                </>
            )}

            {/* Account Dates */}
            <div className="flex flex-col gap-4">
                <h3 className="text-sm font-semibold tracking-wide uppercase">Account Information</h3>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <label className="text-muted-foreground text-xs font-medium">Account Created</label>
                        <p className="text-sm wrap-break-word">
                            {formatDateTime(user.created_at) !== 'N/A' ? (
                                formatDateTime(user.created_at)
                            ) : (
                                <span className="text-muted-foreground italic">Date not available</span>
                            )}
                        </p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <label className="text-muted-foreground text-xs font-medium">Last Updated</label>
                        <p className="text-sm wrap-break-word">
                            {formatDateTime(user.updated_at) !== 'N/A' ? (
                                formatDateTime(user.updated_at)
                            ) : (
                                <span className="text-muted-foreground italic">Date not available</span>
                            )}
                        </p>
                    </div>

                    {user.email_verified_at && (
                        <div className="flex flex-col gap-2">
                            <label className="text-muted-foreground text-xs font-medium">Email Verified At</label>
                            <p className="text-sm wrap-break-word">{formatDateTime(user.email_verified_at)}</p>
                        </div>
                    )}

                    {user.locked_at && (
                        <div className="flex flex-col gap-2">
                            <label className="text-muted-foreground text-xs font-medium">Account Locked</label>
                            <p className="text-sm wrap-break-word">{formatDateTime(user.locked_at)}</p>
                        </div>
                    )}

                    {user.lock_expires_at && (
                        <div className="flex flex-col gap-2">
                            <label className="text-muted-foreground text-xs font-medium">Lock Expires</label>
                            <p className="text-sm wrap-break-word">{formatDateTime(user.lock_expires_at)}</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );

    // Render Sheet consistently across all devices
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-lg">
                <SheetHeader className="shrink-0 border-b p-4 sm:px-6 sm:py-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 dark:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-lg">
                            <User />
                        </div>
                        <div>
                            <SheetTitle className="text-foreground text-xl font-semibold">User Details</SheetTitle>
                            <SheetDescription className="text-muted-foreground mt-1 text-sm">Complete information for {user.name}</SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div className="flex-1 overflow-auto">
                    <UserDetailsContent />
                </div>

                <SheetFooter className="shrink-0 border-t p-4 sm:px-6 sm:py-3">
                    <SheetClose render={<Button variant="outline" />}>Close</SheetClose>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
