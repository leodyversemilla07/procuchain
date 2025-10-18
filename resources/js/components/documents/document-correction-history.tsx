import { History, FileCheck, FileX, AlertCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface CorrectionRecord {
    txid: string;
    timestamp: string;
    correction_type: string;
    action: 'replace' | 'invalidate';
    reason: string;
    corrected_by: string;
    original_txid: string;
    original_document_hash: string;
    corrected_metadata?: {
        file_name?: string;
        hash?: string;
        file_size?: number;
        [key: string]: unknown;
    };
}

interface CorrectionHistoryDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    procurementId: number;
    documentHash?: string;
}

export function CorrectionHistoryDialog({
    open,
    onOpenChange,
    procurementId,
    documentHash,
}: CorrectionHistoryDialogProps) {
    const [corrections, setCorrections] = useState<CorrectionRecord[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchCorrections = async () => {
        setLoading(true);
        setError(null);

        try {
            const url = route('procurements.corrections', procurementId);
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Failed to fetch corrections');
            }

            const data = await response.json();
            
            // Filter by document hash if provided
            let filteredCorrections = data.corrections || [];
            if (documentHash) {
                filteredCorrections = filteredCorrections.filter(
                    (c: CorrectionRecord) => c.original_document_hash === documentHash
                );
            }

            // Sort by timestamp descending (newest first)
            filteredCorrections.sort((a: CorrectionRecord, b: CorrectionRecord) => 
                new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime()
            );

            setCorrections(filteredCorrections);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load correction history');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (open) {
            fetchCorrections();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, procurementId]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-4xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <History className="h-5 w-5" />
                        Correction History
                    </DialogTitle>
                    <DialogDescription>
                        Complete timeline of all corrections for this {documentHash ? 'document' : 'procurement'}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Loading State */}
                    {loading && (
                        <div className="text-center py-8">
                            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                            <p className="text-sm text-muted-foreground mt-2">Loading corrections from blockchain...</p>
                        </div>
                    )}

                    {/* Error State */}
                    {error && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {/* Empty State */}
                    {!loading && !error && corrections.length === 0 && (
                        <div className="text-center py-8">
                            <FileCheck className="h-12 w-12 mx-auto text-muted-foreground mb-3" />
                            <p className="text-muted-foreground">No corrections found</p>
                            <p className="text-sm text-muted-foreground mt-1">
                                All documents are in their original state
                            </p>
                        </div>
                    )}

                    {/* Corrections Timeline */}
                    {!loading && !error && corrections.length > 0 && (
                        <div className="relative">
                            {/* Timeline Line */}
                            <div className="absolute left-4 top-0 bottom-0 w-0.5 bg-border" />

                            {/* Corrections */}
                            <div className="space-y-6">
                                {corrections.map((correction) => (
                                    <div key={correction.txid} className="relative pl-10">
                                        {/* Timeline Dot */}
                                        <div className="absolute left-2.5 top-2 w-3 h-3 rounded-full border-2 bg-background border-primary" />

                                        {/* Correction Card */}
                                        <div className="bg-card border rounded-lg p-4 shadow-sm">
                                            {/* Header */}
                                            <div className="flex items-start justify-between mb-3">
                                                <div className="flex items-center gap-2">
                                                    {correction.action === 'replace' ? (
                                                        <FileCheck className="h-5 w-5 text-emerald-600" />
                                                    ) : (
                                                        <FileX className="h-5 w-5 text-red-600" />
                                                    )}
                                                    <div>
                                                        <h4 className="font-semibold">
                                                            {correction.action === 'replace'
                                                                ? 'Document Replaced'
                                                                : 'Document Invalidated'}
                                                        </h4>
                                                        <p className="text-xs text-muted-foreground">
                                                            {new Date(correction.timestamp).toLocaleString()}
                                                        </p>
                                                    </div>
                                                </div>
                                                <Badge
                                                    variant={correction.action === 'replace' ? 'default' : 'secondary'}
                                                >
                                                    {correction.correction_type}
                                                </Badge>
                                            </div>

                                            {/* Reason */}
                                            <div className="mb-3">
                                                <p className="text-sm font-medium mb-1">Reason:</p>
                                                <p className="text-sm text-muted-foreground bg-muted p-2 rounded">
                                                    {correction.reason}
                                                </p>
                                            </div>

                                            {/* Metadata Grid */}
                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <p className="font-medium text-xs text-muted-foreground mb-1">
                                                        Corrected By
                                                    </p>
                                                    <p className="text-sm">{correction.corrected_by}</p>
                                                </div>
                                                <div>
                                                    <p className="font-medium text-xs text-muted-foreground mb-1">
                                                        Blockchain TXID
                                                    </p>
                                                    <p className="text-xs font-mono break-all">
                                                        {correction.txid.substring(0, 16)}...
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Original Reference */}
                                            <div className="mt-3 pt-3 border-t">
                                                <p className="font-medium text-xs text-muted-foreground mb-2">
                                                    References Original
                                                </p>
                                                <div className="grid grid-cols-2 gap-2 text-xs">
                                                    <div>
                                                        <span className="font-medium">TXID:</span>
                                                        <span className="font-mono ml-1">
                                                            {correction.original_txid.substring(0, 12)}...
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">Hash:</span>
                                                        <span className="font-mono ml-1">
                                                            {correction.original_document_hash.substring(0, 12)}...
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Corrected File Info (if replacement) */}
                                            {correction.action === 'replace' && correction.corrected_metadata && (
                                                <div className="mt-3 pt-3 border-t bg-emerald-50 dark:bg-emerald-950/20 -mx-4 -mb-4 px-4 py-3 rounded-b-lg">
                                                    <p className="font-medium text-xs text-emerald-900 dark:text-emerald-100 mb-2">
                                                        New Document Information
                                                    </p>
                                                    <div className="space-y-1 text-xs">
                                                        {correction.corrected_metadata.file_name && (
                                                            <div>
                                                                <strong>File:</strong> {correction.corrected_metadata.file_name}
                                                            </div>
                                                        )}
                                                        {correction.corrected_metadata.file_size && (
                                                            <div>
                                                                <strong>Size:</strong>{' '}
                                                                {(correction.corrected_metadata.file_size / 1024 / 1024).toFixed(2)} MB
                                                            </div>
                                                        )}
                                                        {correction.corrected_metadata.hash && (
                                                            <div>
                                                                <strong>Hash:</strong>
                                                                <span className="font-mono ml-1">
                                                                    {correction.corrected_metadata.hash.substring(0, 16)}...
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Immutability Notice */}
                    {corrections.length > 0 && (
                        <Alert>
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription className="text-sm">
                                All corrections are permanently recorded on the blockchain alongside the original
                                documents, ensuring complete audit trail transparency.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Refresh Button */}
                    {!loading && (
                        <div className="flex justify-end">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={fetchCorrections}
                                className="gap-2"
                            >
                                <History className="h-4 w-4" />
                                Refresh
                            </Button>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
