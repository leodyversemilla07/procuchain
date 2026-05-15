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
 This will deactivate{' '}
 <span className="text-foreground font-semibold">
 {userCount} user{userCount !== 1 ? 's' : ''}
 </span>
 . These accounts can be restored by an administrator.
 </AlertDialogDescription>
 </AlertDialogHeader>

 <div className="space-y-3">
 <div className="bg-destructive/10 border-destructive/20 rounded-lg border p-4">
 <p className="text-destructive mb-2 text-sm font-medium">This will:</p>
 <ul className="text-muted-foreground space-y-1 text-sm">
 <li>• Deactivate the selected user accounts and remove access</li>
 <li>• Revoke all permissions and roles for these users</li>
 <li>• Record each deletion on the blockchain (immutable audit trail)</li>
 </ul>
 </div>

 {/* List of users to be deleted */}
 <div className="bg-muted/50 max-h-40 overflow-y-auto rounded-lg border p-3">
 <p className="mb-2 text-sm font-medium">Users to be deleted:</p>
 <div className="space-y-1">
 {selectedUsers.map((user) => (
 <div key={user.id} className="text-sm">
 {user.name} ({user.email})
 </div>
 ))}
 </div>
 </div>

 <div className="bg-emerald-50 border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-900 rounded-lg border p-4">
 <div className="flex items-start gap-3">
 <Shield className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
 <div>
 <p className="text-sm font-medium text-emerald-800 dark:text-emerald-300">On-chain • Recoverable</p>
 <p className="text-muted-foreground mt-0.5 text-xs">
 Deletion events are recorded on the blockchain. Data remains replicated across all network nodes and can be restored by an authorized administrator.
 </p>
 </div>
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
