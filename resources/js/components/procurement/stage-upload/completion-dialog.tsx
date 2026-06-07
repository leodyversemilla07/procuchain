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
import { Spinner } from '@/components/ui/spinner';

interface CompletionDialogProps {
    open: boolean;
    isMarkingComplete: boolean;
    stageName: string;
    prNumber: string;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
}

export function CompletionDialog({ open, isMarkingComplete, stageName, prNumber, onOpenChange, onConfirm }: CompletionDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="rounded-2xl">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-2xl font-black">
                        {isMarkingComplete ? 'Processing...' : 'Mark Stage as Complete?'}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {isMarkingComplete ? (
                            <span className="flex items-center gap-2">
                                <Spinner className="h-4 w-4" />
                                Recording on blockchain. Please wait...
                            </span>
                        ) : (
                            <>
                                This will finalize <strong>{stageName}</strong> for <strong>{prNumber}</strong> and record it on the blockchain. This action
                                cannot be undone.
                            </>
                        )}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isMarkingComplete} className="rounded-lg">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} disabled={isMarkingComplete} className="rounded-lg px-8">
                        {isMarkingComplete ? (
                            <span className="flex items-center gap-2"><Spinner /> Processing...</span>
                        ) : (
                            'Confirm'
                        )}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
