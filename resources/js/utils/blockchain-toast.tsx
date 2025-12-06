import { toast } from 'sonner';
import { ShieldCheck } from 'lucide-react';

interface BlockchainResponse {
    message: string;
    blockchain?: {
        status_txid?: string;
        event_txid?: string;
        stage?: string;
        next_stage?: string;
        completion_status?: string;
    };
}

/**
 * Show a user-friendly success toast for blockchain operations.
 * Hides technical transaction IDs and shows a simple verification message instead.
 */
export function showBlockchainSuccessToast(response: BlockchainResponse | string): void {
    if (typeof response === 'string') {
        toast.success(response);
        return;
    }

    const { message, blockchain } = response;
    const hasBlockchainVerification = blockchain?.status_txid || blockchain?.event_txid;

    toast.success(message, {
        description: hasBlockchainVerification ? (
            <div className="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
                <ShieldCheck className="h-3.5 w-3.5" />
                <span>Verified and recorded on blockchain</span>
            </div>
        ) : undefined,
    });
}

/**
 * Parse flash success response and show appropriate toast.
 * Use this in onSuccess callbacks for Inertia router calls.
 */
export function handleFlashSuccess(page: { props: Record<string, unknown> }, fallbackMessage?: string): void {
    const flash = page.props.flash as Record<string, unknown> | undefined;
    const response = flash?.success;

    if (typeof response === 'object' && response && 'message' in response) {
        showBlockchainSuccessToast(response as BlockchainResponse);
    } else if (typeof response === 'string') {
        toast.success(response);
    } else if (fallbackMessage) {
        toast.success(fallbackMessage);
    }
}

/**
 * Show a simple success toast for stage completion.
 */
export function showStageCompleteToast(stageName: string, nextStageName?: string): void {
    const message = nextStageName
        ? `${stageName} completed! Proceeding to ${nextStageName}.`
        : `${stageName} marked as complete!`;

    toast.success(message, {
        description: (
            <div className="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
                <ShieldCheck className="h-3.5 w-3.5" />
                <span>Verified and recorded on blockchain</span>
            </div>
        ),
    });
}
