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
            <AlertDialogContent className="gap-0 p-0 sm:max-w-[500px]">
                <AlertDialogHeader className="border-b px-6 py-6 pb-4">
                    <div className="flex items-center space-x-3">
                        <div className="bg-destructive/10 dark:bg-destructive/20 flex h-12 w-12 items-center justify-center rounded-lg">
                            <Trash2 className="text-destructive h-6 w-6" />
                        </div>
                        <div>
                            <AlertDialogTitle className="text-foreground text-xl font-semibold">Delete User Account</AlertDialogTitle>
                            <AlertDialogDescription className="text-muted-foreground mt-1 text-sm">
                                This action cannot be undone and will permanently remove the user
                            </AlertDialogDescription>
                        </div>
                    </div>
                </AlertDialogHeader>

                <div className="px-6 py-6">
                    <div className="space-y-4">
                        <div className="bg-destructive/5 dark:bg-destructive/10 border-destructive/20 dark:border-destructive/30 rounded-lg border p-4">
                            <div className="flex items-start space-x-3">
                                <div className="bg-destructive/20 dark:bg-destructive/30 mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full">
                                    <div className="bg-destructive h-2 w-2 rounded-full"></div>
                                </div>
                                <div className="space-y-2">
                                    <p className="text-destructive text-sm font-medium">Warning: Permanent Data Loss</p>
                                    <p className="text-muted-foreground text-sm">
                                        You are about to permanently delete the user account for{' '}
                                        <span className="text-foreground font-semibold">{user?.name}</span>
                                        {user?.email && (
                                            <>
                                                {' '}
                                                (<span className="font-mono text-xs">{user.email}</span>)
                                            </>
                                        )}
                                        . This will:
                                    </p>
                                    <ul className="text-muted-foreground ml-4 space-y-1 text-sm">
                                        <li className="flex items-center space-x-2">
                                            <div className="bg-muted-foreground h-1 w-1 rounded-full"></div>
                                            <span>Remove all user data from the system</span>
                                        </li>
                                        <li className="flex items-center space-x-2">
                                            <div className="bg-muted-foreground h-1 w-1 rounded-full"></div>
                                            <span>Revoke all access permissions and roles</span>
                                        </li>
                                        <li className="flex items-center space-x-2">
                                            <div className="bg-muted-foreground h-1 w-1 rounded-full"></div>
                                            <span>Clear associated activity logs and history</span>
                                        </li>
                                        <li className="flex items-center space-x-2">
                                            <div className="bg-muted-foreground h-1 w-1 rounded-full"></div>
                                            <span>Cannot be reversed or recovered</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div className="bg-muted/30 border-border rounded-lg border p-3">
                            <p className="text-muted-foreground text-center text-xs">
                                <strong>Note:</strong> This action is irreversible. Please ensure you have any necessary backups before proceeding.
                            </p>
                        </div>
                    </div>
                </div>

                <AlertDialogFooter className="bg-muted/30 dark:bg-muted/20 flex flex-col gap-2 border-t px-6 py-4 sm:flex-row sm:gap-3">
                    <AlertDialogCancel className="order-2 h-11 px-6 sm:order-1">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={onConfirm}
                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90 order-1 h-11 px-6 shadow-md sm:order-2"
                    >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete User Permanently
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
