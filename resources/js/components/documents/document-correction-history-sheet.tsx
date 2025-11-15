import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { corrections as correctionsRoute } from '@/routes/procurements';
import { AlertCircle, FileCheck, FileX, History } from 'lucide-react';
import { useEffect, useState } from 'react';

interface CorrectionRecord {
    txid: string;
    timestamp: string;
    correction_type: string;
    correction_type_display?: string;
    action: 'replace' | 'invalidate';
    reason: string;
    corrected_by: string;
    original_txid?: string;
    original_document_hash?: string;
    document_hash?: string;
    file_name?: string;
    file_key?: string;
    document_type?: string;
    document_type_display?: string;
    corrected_metadata?: {
        file_name?: string;
        hash?: string;
        file_size?: number;
        [key: string]: unknown;
    };
}

interface CorrectionHistorySheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pr_number: string | number;
    documentHash?: string;
}

export function CorrectionHistorySheet({ open, onOpenChange, pr_number, documentHash }: CorrectionHistorySheetProps) {
    const [corrections, setCorrections] = useState<CorrectionRecord[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchCorrections = async () => {
        setLoading(true);
        setError(null);

        try {
            const url = correctionsRoute.url(pr_number);
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Failed to fetch corrections');
            }

            const data = await response.json();

            // Filter by document hash if provided
            let filteredCorrections = data.corrections || [];
            if (documentHash) {
                filteredCorrections = filteredCorrections.filter((c: CorrectionRecord) => c.original_document_hash === documentHash);
            }

            // Sort by timestamp descending (newest first)
            filteredCorrections.sort((a: CorrectionRecord, b: CorrectionRecord) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());

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
    }, [open, pr_number]);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-3xl lg:max-w-4xl">
                <SheetHeader>
                    <SheetTitle className="flex items-center gap-2 text-lg sm:text-xl">
                        <History className="h-5 w-5" />
                        {documentHash ? 'Document Correction History' : 'All Corrections History'}
                    </SheetTitle>
                    <SheetDescription className="text-sm">
                        {documentHash 
                            ? 'Correction timeline for this specific document' 
                            : 'Complete timeline of all corrections across all documents in this procurement'}
                    </SheetDescription>
                </SheetHeader>

                <div className="grid flex-1 auto-rows-min gap-6 px-4 py-6">
                    {/* Loading State */}
                    {loading && (
                        <div className="py-12 text-center">
                            <div className="border-primary inline-block h-8 w-8 animate-spin rounded-full border-b-2"></div>
                            <p className="text-muted-foreground mt-3 text-sm">Loading corrections from blockchain...</p>
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
                        <div className="py-12 text-center">
                            <FileCheck className="text-muted-foreground mx-auto mb-4 h-16 w-16" />
                            <p className="text-muted-foreground text-lg font-medium">No corrections found</p>
                            <p className="text-muted-foreground mt-2 text-sm">
                                {documentHash 
                                    ? 'This document has not been corrected yet' 
                                    : 'No documents in this procurement have been corrected yet'}
                            </p>
                        </div>
                    )}

                    {/* Corrections Timeline */}
                    {!loading && !error && corrections.length > 0 && (
                        <div className="relative">
                            {/* Timeline Line - hidden on mobile */}
                            <div className="bg-border absolute top-0 bottom-0 left-4 hidden w-0.5 sm:block" />

                            {/* Corrections */}
                            <div className="grid gap-6 sm:gap-8">
                                {corrections.map((correction) => (
                                    <div key={correction.txid} className="relative sm:pl-12">
                                        {/* Timeline Dot - hidden on mobile */}
                                        <div className="bg-background border-primary absolute top-2 left-2.5 hidden h-4 w-4 rounded-full border-2 sm:block" />

                                        {/* Correction Card */}
                                        <div className="bg-card rounded-lg border p-4 shadow-sm sm:p-5">
                                            {/* Header */}
                                            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                                <div className="flex items-center gap-3">
                                                    {correction.action === 'replace' ? (
                                                        <FileCheck className="h-5 w-5 shrink-0 text-emerald-600 sm:h-6 sm:w-6" />
                                                    ) : (
                                                        <FileX className="h-5 w-5 shrink-0 text-red-600 sm:h-6 sm:w-6" />
                                                    )}
                                                    <div className="min-w-0 flex-1">
                                                        <h4 className="text-sm font-semibold sm:text-base">
                                                            {correction.action === 'replace' ? 'Document Replaced' : 'Document Invalidated'}
                                                        </h4>
                                                        <p className="text-muted-foreground truncate text-xs sm:text-sm">
                                                            {new Date(correction.timestamp).toLocaleString()}
                                                        </p>
                                                    </div>
                                                </div>
                                                <Badge variant={correction.action === 'replace' ? 'default' : 'secondary'} className="w-fit shrink-0 text-xs">
                                                    {correction.correction_type_display || correction.correction_type}
                                                </Badge>
                                            </div>

                                            {/* Reason */}
                                            <div className="mb-4 grid gap-2">
                                                <p className="text-sm font-semibold">Reason:</p>
                                                <p className="text-muted-foreground bg-muted rounded-lg p-3 text-sm leading-relaxed">{correction.reason}</p>
                                            </div>

                                            {/* Metadata Grid */}
                                            <div className="mb-4 grid gap-3 sm:grid-cols-2 sm:gap-4">
                                                <div className="bg-muted/50 grid gap-1.5 rounded-lg p-3">
                                                    <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">Corrected By</p>
                                                    <p className="text-xs font-medium sm:text-sm">{correction.corrected_by}</p>
                                                </div>
                                                <div className="bg-muted/50 grid gap-1.5 rounded-lg p-3">
                                                    <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">Blockchain TXID</p>
                                                    <p className="font-mono text-xs break-all">{correction.txid.substring(0, 16)}...</p>
                                                </div>
                                            </div>

                                            {/* Original Reference */}
                                            <div className="grid gap-3 border-t pt-4">
                                                <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">References Original</p>
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    {correction.original_txid && (
                                                        <div className="bg-muted/30 grid gap-1 rounded-lg p-2 sm:p-3">
                                                            <span className="text-xs font-medium">TXID:</span>
                                                            <span className="font-mono text-xs break-all">{correction.original_txid.substring(0, 20)}...</span>
                                                        </div>
                                                    )}
                                                    {(correction.original_document_hash || correction.document_hash) && (
                                                        <div className="bg-muted/30 grid gap-1 rounded-lg p-2 sm:p-3">
                                                            <span className="text-xs font-medium">Hash:</span>
                                                            <span className="font-mono text-xs break-all">
                                                                {(correction.original_document_hash || correction.document_hash || '').substring(0, 20)}...
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Corrected File Info (if replacement) */}
                                            {correction.action === 'replace' && correction.corrected_metadata && (
                                                <div className="-mx-4 mt-4 -mb-4 grid gap-3 rounded-b-lg border-t bg-emerald-50 px-4 py-4 dark:bg-emerald-950/20 sm:-mx-5 sm:-mb-5 sm:px-5">
                                                    <p className="text-xs font-semibold text-emerald-900 dark:text-emerald-100 sm:text-sm">
                                                        New Document Information
                                                    </p>
                                                    <div className="grid gap-2 text-xs sm:text-sm">
                                                        {correction.corrected_metadata.file_name && (
                                                            <div className="flex flex-col gap-1 sm:flex-row sm:gap-2">
                                                                <strong className="min-w-[60px]">File:</strong> 
                                                                <span className="break-all">{correction.corrected_metadata.file_name}</span>
                                                            </div>
                                                        )}
                                                        {correction.corrected_metadata.file_size && (
                                                            <div className="flex gap-2">
                                                                <strong className="min-w-[60px]">Size:</strong>
                                                                <span>{(correction.corrected_metadata.file_size / 1024 / 1024).toFixed(2)} MB</span>
                                                            </div>
                                                        )}
                                                        {correction.corrected_metadata.hash && (
                                                            <div className="flex flex-col gap-1 sm:flex-row sm:gap-2">
                                                                <strong className="min-w-[60px]">Hash:</strong>
                                                                <span className="font-mono text-xs break-all">
                                                                    {correction.corrected_metadata.hash.substring(0, 24)}...
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
                        <Alert className="border-primary/20 bg-primary/5">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription className="text-sm">
                                All corrections are permanently recorded on the blockchain alongside the original documents, ensuring complete audit
                                trail transparency.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Refresh Button */}
                    {!loading && (
                        <div className="flex justify-center sm:justify-end">
                            <Button variant="outline" size="sm" onClick={fetchCorrections} className="w-full gap-2 sm:w-auto">
                                <History className="h-4 w-4" />
                                Refresh
                            </Button>
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
