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
import { Trash2 } from 'lucide-react';

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
                        This action cannot be undone. This will permanently delete the user account for{' '}
                        <span className="text-foreground font-semibold">{user?.name}</span>
                        {user?.email && <> ({user.email})</>}.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="space-y-3">
                    <div className="bg-destructive/10 border-destructive/20 rounded-lg border p-4">
                        <p className="text-destructive mb-2 text-sm font-medium">Warning: This will permanently:</p>
                        <ul className="text-muted-foreground space-y-1 text-sm">
                            <li>• Remove all user data from the system</li>
                            <li>• Revoke all access permissions and roles</li>
                            <li>• Clear associated activity logs and history</li>
                            <li>• Cannot be reversed or recovered</li>
                        </ul>
                    </div>
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete User
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
