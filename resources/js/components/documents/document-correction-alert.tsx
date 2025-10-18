import { AlertCircle, History, Info } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface CorrectionData {
    correction_type: string;
    original_txid: string;
    original_document_hash: string;
    reason: string;
    corrected_by: string;
    timestamp: string;
    action: 'replace' | 'invalidate';
    corrected_metadata?: {
        file_name?: string;
        hash?: string;
        file_key?: string;
        file_size?: number;
        mime_type?: string;
    };
}

interface DocumentCorrectionAlertProps {
    isCorrected: boolean;
    correctionReason?: string;
    correctedBy?: string;
    correctedAt?: string;
    correctionData?: CorrectionData;
    onViewHistory?: () => void;
}

export function DocumentCorrectionAlert({
    isCorrected,
    correctionReason,
    correctedBy,
    correctedAt,
    correctionData,
    onViewHistory,
}: DocumentCorrectionAlertProps) {
    const [showDetails, setShowDetails] = useState(false);

    if (!isCorrected) {
        return null;
    }

    const isReplacement = correctionData?.action === 'replace';

    return (
        <>
            <Alert className="mb-4 border-amber-500 bg-amber-50 dark:bg-amber-950/20">
                <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                <AlertTitle className="text-amber-900 dark:text-amber-100">
                    Document Corrected
                    <Badge variant="outline" className="ml-2 text-xs">
                        {isReplacement ? 'Replaced' : 'Invalidated'}
                    </Badge>
                </AlertTitle>
                <AlertDescription className="mt-2 text-amber-800 dark:text-amber-200">
                    <div className="space-y-2">
                        <div>
                            <strong>Reason:</strong> {correctionReason || 'No reason provided'}
                        </div>
                        {correctedBy && (
                            <div className="text-sm">
                                <strong>Corrected by:</strong> {correctedBy}
                            </div>
                        )}
                        {correctedAt && (
                            <div className="text-sm">
                                <strong>Date:</strong> {new Date(correctedAt).toLocaleString()}
                            </div>
                        )}
                        <div className="flex gap-2 mt-3">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setShowDetails(true)}
                                className="gap-2"
                            >
                                <Info className="h-4 w-4" />
                                View Details
                            </Button>
                            {onViewHistory && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={onViewHistory}
                                    className="gap-2"
                                >
                                    <History className="h-4 w-4" />
                                    View Full History
                                </Button>
                            )}
                        </div>
                    </div>
                </AlertDescription>
            </Alert>

            {/* Correction Details Dialog */}
            <AlertDialog open={showDetails} onOpenChange={setShowDetails}>
                <AlertDialogContent className="max-w-2xl">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-amber-600" />
                            Correction Details
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Complete information about this document correction
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <div className="space-y-4">
                        {/* Correction Type */}
                        <div>
                            <h4 className="font-semibold mb-2">Correction Type</h4>
                            <Badge variant={isReplacement ? 'default' : 'secondary'}>
                                {isReplacement ? 'Document Replaced' : 'Document Invalidated'}
                            </Badge>
                        </div>

                        {/* Reason */}
                        <div>
                            <h4 className="font-semibold mb-2">Reason for Correction</h4>
                            <p className="text-sm text-muted-foreground bg-muted p-3 rounded">
                                {correctionReason || 'No reason provided'}
                            </p>
                        </div>

                        {/* Blockchain Info */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <h4 className="font-semibold mb-2 text-sm">Original Transaction</h4>
                                <p className="text-xs font-mono bg-muted p-2 rounded break-all">
                                    {correctionData?.original_txid || 'N/A'}
                                </p>
                            </div>
                            <div>
                                <h4 className="font-semibold mb-2 text-sm">Original Hash</h4>
                                <p className="text-xs font-mono bg-muted p-2 rounded break-all">
                                    {correctionData?.original_document_hash?.substring(0, 16)}...
                                </p>
                            </div>
                        </div>

                        {/* Corrected Metadata (if replacement) */}
                        {isReplacement && correctionData?.corrected_metadata && (
                            <div>
                                <h4 className="font-semibold mb-2">Corrected Information</h4>
                                <div className="bg-emerald-50 dark:bg-emerald-950/20 p-3 rounded space-y-1">
                                    {correctionData.corrected_metadata.file_name && (
                                        <div className="text-sm">
                                            <strong>New File:</strong>{' '}
                                            {correctionData.corrected_metadata.file_name}
                                        </div>
                                    )}
                                    {correctionData.corrected_metadata.file_size && (
                                        <div className="text-sm">
                                            <strong>File Size:</strong>{' '}
                                            {(correctionData.corrected_metadata.file_size / 1024 / 1024).toFixed(2)} MB
                                        </div>
                                    )}
                                    {correctionData.corrected_metadata.hash && (
                                        <div className="text-sm">
                                            <strong>New Hash:</strong>{' '}
                                            <span className="font-mono text-xs">
                                                {correctionData.corrected_metadata.hash.substring(0, 16)}...
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Audit Info */}
                        <div className="border-t pt-4">
                            <h4 className="font-semibold mb-2">Audit Information</h4>
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <strong>Corrected By:</strong>
                                    <p className="text-muted-foreground">{correctedBy || 'Unknown'}</p>
                                </div>
                                <div>
                                    <strong>Correction Date:</strong>
                                    <p className="text-muted-foreground">
                                        {correctedAt ? new Date(correctedAt).toLocaleString() : 'Unknown'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Immutability Notice */}
                        <div className="bg-blue-50 dark:bg-blue-950/20 p-3 rounded border border-blue-200 dark:border-blue-800">
                            <p className="text-sm text-blue-800 dark:text-blue-200">
                                <strong>Note:</strong> Both the original and correction records remain permanently
                                on the blockchain for audit trail purposes. This ensures complete transparency and
                                compliance with immutability requirements.
                            </p>
                        </div>
                    </div>

                    <AlertDialogFooter>
                        <AlertDialogAction onClick={() => setShowDetails(false)}>
                            Close
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
