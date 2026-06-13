import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Shield, Trash2 } from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    blockchain_address?: string;
    email_verified_at?: string;
    remember_token?: string;
    created_at: string;
    updated_at?: string;
}

interface DeleteUserDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: User | null;
    onConfirm: () => void;
}

export default function DeleteUserDialog({ open, onOpenChange, user, onConfirm }: DeleteUserDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="sm:max-w-[500px]">
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete User Account</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will deactivate the user account for <span className="text-foreground font-semibold">{user?.name}</span>
                        {user?.email && <> ({user.email})</>}. The account can be restored by an administrator.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="flex flex-col gap-3">
                    <div className="bg-destructive/10 border-destructive/20 rounded-lg border p-4">
                        <p className="text-destructive mb-2 text-sm font-medium">This will:</p>
                        <ul className="text-muted-foreground flex flex-col gap-1 text-sm">
                            <li>• Deactivate the user account and remove access</li>
                            <li>• Revoke all permissions and roles</li>
                            <li>• Record the deletion on the blockchain (immutable audit trail)</li>
                        </ul>
                    </div>
                    <div className="bg-primary/10 dark:bg-primary/10/20 rounded-lg border border-emerald-200 p-4 dark:border-emerald-900">
                        <div className="flex items-start gap-3">
                            <Shield />
                            <div>
                                <p className="text-primary dark:text-primary text-sm font-medium">On-chain • Recoverable</p>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    The deletion event is recorded on the blockchain. Data remains replicated across all network nodes and can be
                                    restored by an authorized administrator.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                        <Trash2 />
                        Delete User
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
