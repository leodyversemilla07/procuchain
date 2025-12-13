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

interface BulkDeleteDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    selectedUsers: User[];
    onConfirm: () => void;
}

export default function BulkDeleteDialog({ open, onOpenChange, selectedUsers, onConfirm }: BulkDeleteDialogProps) {
    const userCount = selectedUsers.length;

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="sm:max-w-[500px]">
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Multiple Users</AlertDialogTitle>
                    <AlertDialogDescription>
                        This action cannot be undone. This will permanently delete{' '}
                        <span className="text-foreground font-semibold">
                            {userCount} user{userCount !== 1 ? 's' : ''}
                        </span>
                        .
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="space-y-3">
                    <div className="bg-destructive/10 border-destructive/20 rounded-lg border p-4">
                        <p className="text-destructive text-sm font-medium mb-2">Warning: This will permanently:</p>
                        <ul className="text-muted-foreground space-y-1 text-sm">
                            <li>• Remove all selected user data from the system</li>
                            <li>• Revoke all access permissions and roles for these users</li>
                            <li>• Clear all associated activity logs and history</li>
                            <li>• Cannot be reversed or recovered</li>
                        </ul>
                    </div>

                    {/* List of users to be deleted */}
                    <div className="bg-muted/50 border rounded-lg p-3 max-h-40 overflow-y-auto">
                        <p className="text-sm font-medium mb-2">Users to be deleted:</p>
                        <div className="space-y-1">
                            {selectedUsers.map((user) => (
                                <div key={user.id} className="text-sm">
                                    {user.name} ({user.email})
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete {userCount} User{userCount !== 1 ? 's' : ''}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
