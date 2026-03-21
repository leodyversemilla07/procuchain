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
import type { ConfirmDialogState } from './types';

interface UploadConfirmDialogProps {
    dialog: ConfirmDialogState;
    isUploading: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
}

export function UploadConfirmDialog({ dialog, isUploading, onOpenChange, onConfirm }: UploadConfirmDialogProps) {
    return (
        <AlertDialog open={dialog.open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="rounded-2xl">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-2xl font-black">Confirm Upload</AlertDialogTitle>
                    <AlertDialogDescription>
                        Attach <strong>{dialog.documentName}</strong> to the blockchain?
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isUploading} className="rounded-lg">
                        Wait, go back
                    </AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} disabled={isUploading} className="rounded-lg px-8">
                        Confirm
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
