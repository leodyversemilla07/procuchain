import React from 'react';
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

export default function DeleteUserDialog({
    open,
    onOpenChange,
    user,
    onConfirm,
}: DeleteUserDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="sm:max-w-[500px] p-0 gap-0">
                <AlertDialogHeader className="px-6 py-6 pb-4 bg-gradient-to-r from-destructive/5 dark:from-destructive/10 to-background border-b">
                    <div className="flex items-center space-x-3">
                        <div className="h-12 w-12 rounded-lg bg-destructive/10 dark:bg-destructive/20 flex items-center justify-center">
                            <Trash2 className="h-6 w-6 text-destructive" />
                        </div>
                        <div>
                            <AlertDialogTitle className="text-xl font-semibold text-foreground">
                                Delete User Account
                            </AlertDialogTitle>
                            <AlertDialogDescription className="text-sm text-muted-foreground mt-1">
                                This action cannot be undone and will permanently remove the user
                            </AlertDialogDescription>
                        </div>
                    </div>
                </AlertDialogHeader>

                <div className="px-6 py-6">
                    <div className="space-y-4">
                        <div className="p-4 bg-destructive/5 dark:bg-destructive/10 border border-destructive/20 dark:border-destructive/30 rounded-lg">
                            <div className="flex items-start space-x-3">
                                <div className="h-5 w-5 rounded-full bg-destructive/20 dark:bg-destructive/30 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <div className="h-2 w-2 bg-destructive rounded-full"></div>
                                </div>
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-destructive">
                                        Warning: Permanent Data Loss
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        You are about to permanently delete the user account for{' '}
                                        <span className="font-semibold text-foreground">{user?.name}</span>
                                        {user?.email && (
                                            <>
                                                {' '}(<span className="font-mono text-xs">{user.email}</span>)
                                            </>
                                        )}. This will:
                                    </p>
                                    <ul className="text-sm text-muted-foreground space-y-1 ml-4">
                                        <li className="flex items-center space-x-2">
                                            <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                            <span>Remove all user data from the system</span>
                                        </li>
                                        <li className="flex items-center space-x-2">
                                            <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                            <span>Revoke all access permissions and roles</span>
                                        </li>
                                        <li className="flex items-center space-x-2">
                                            <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                            <span>Clear associated activity logs and history</span>
                                        </li>
                                        <li className="flex items-center space-x-2">
                                            <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                            <span>Cannot be reversed or recovered</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div className="p-3 bg-muted/30 border border-border rounded-lg">
                            <p className="text-xs text-muted-foreground text-center">
                                <strong>Note:</strong> This action is irreversible. Please ensure you have any necessary backups before proceeding.
                            </p>
                        </div>
                    </div>
                </div>

                <AlertDialogFooter className="flex flex-col sm:flex-row gap-2 sm:gap-3 px-6 py-4 border-t bg-muted/30 dark:bg-muted/20">
                    <AlertDialogCancel className="h-11 px-6 order-2 sm:order-1">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={onConfirm}
                        className="h-11 px-6 bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-md order-1 sm:order-2"
                    >
                        <Trash2 className="h-4 w-4 mr-2" />
                        Delete User Permanently
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
