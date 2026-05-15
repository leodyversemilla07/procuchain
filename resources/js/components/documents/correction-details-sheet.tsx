import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { getXsrfToken } from '@/lib/csrf';
import procurements from '@/routes/procurements';
import { AlertCircle, Calendar, FileCheck, FileText, FileX, Hash, History, User } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

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

interface CorrectionDetailsSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    correction?: CorrectionRecord; // For single correction details
    pr_number?: string | number; // For history mode
    documentHash?: string; // For history mode
}

export default function CorrectionDetailsSheet({ open, onOpenChange, correction, pr_number, documentHash }: CorrectionDetailsSheetProps) {
    const [corrections, setCorrections] = useState<CorrectionRecord[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchCorrections = useCallback(async () => {
        if (!pr_number) {
            setLoading(false);
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const url = procurements.corrections.history.url(pr_number);

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch corrections');
            }

            let filteredCorrections = data.corrections || [];

            if (documentHash) {
                filteredCorrections = filteredCorrections.filter((c: CorrectionRecord) => c.original_document_hash === documentHash);
            }

            // If we have a specific correction provided, ensure it's included in the timeline
            if (correction && documentHash) {
                const correctionExists = filteredCorrections.some((c: CorrectionRecord) => c.txid === correction.txid);
                if (!correctionExists && correction.original_document_hash === documentHash) {
                    filteredCorrections.unshift(correction);
                }
            }

            filteredCorrections.sort((a: CorrectionRecord, b: CorrectionRecord) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());

            setCorrections(filteredCorrections);
            setLoading(false);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load correction history');
            setLoading(false);
        }
    }, [pr_number, documentHash, correction]);

    useEffect(() => {
        if (open && pr_number) {
            fetchCorrections();
        }
    }, [open, pr_number, documentHash, fetchCorrections]);

    // Combined mode - show both details and history in one sheet
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full overflow-x-hidden overflow-y-auto sm:max-w-4xl">
                <SheetHeader>
                    <SheetTitle className="flex items-center gap-2 text-lg sm:text-xl">
                        <History className="h-5 w-5" />
                        {correction ? 'Correction Details & History' : 'Correction History'}
                    </SheetTitle>
                    <SheetDescription className="text-sm">
                        {correction
                            ? 'Complete correction information and timeline for this document'
                            : 'Complete timeline of all corrections across all documents in this procurement'}
                    </SheetDescription>
                </SheetHeader>

                <div className="grid flex-1 auto-rows-min gap-6 px-4 py-6">
                    {/* Show correction details if provided */}
                    {correction && (
                        <div className="border-b pb-6">
                            <CorrectionDetails correction={correction} />
                        </div>
                    )}

                    {/* Show history section */}
                    <div className="grid gap-4">
                        <h3 className="text-lg font-semibold">{correction ? 'Correction Timeline' : 'All Corrections'}</h3>

                        {loading && (
                            <div className="py-12 text-center">
                                <Spinner className="text-primary mx-auto size-8" />
                                <p className="text-muted-foreground mt-3 text-sm">Loading corrections from blockchain...</p>
                            </div>
                        )}

                        {error && (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}

                        {!loading && !error && corrections.length === 0 && (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <FileCheck />
                                    </EmptyMedia>
                                    <EmptyTitle>No corrections found</EmptyTitle>
                                    <EmptyDescription>
                                        {correction
                                            ? 'No additional corrections found for this document'
                                            : 'No documents in this procurement have been corrected yet'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        )}

                        {!loading && !error && corrections.length > 0 && <CorrectionTimeline corrections={corrections} />}

                        {corrections.length > 0 && (
                            <Alert className="border-primary/20 bg-primary/5">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription className="text-sm">
                                    All corrections are permanently recorded on the blockchain alongside the original documents, ensuring complete
                                    audit trail transparency.
                                </AlertDescription>
                            </Alert>
                        )}

                        {!loading && (
                            <div className="flex justify-center sm:justify-end">
                                <Button variant="outline" size="sm" onClick={fetchCorrections} className="w-full gap-2 sm:w-auto">
                                    <History className="h-4 w-4" />
                                    Refresh
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

// Shared component for displaying correction details
function CorrectionDetails({ correction }: { correction: CorrectionRecord }) {
    // Format correction type for display
    const formatCorrectionType = (type: string, displayType?: string) => {
        if (displayType) return displayType;
        // Convert snake_case to Title Case
        return type
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
    };

    return (
        <div className="grid flex-1 auto-rows-min gap-6 px-4 py-6">
            {/* Correction Type */}
            <div className="grid gap-3">
                <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Correction Type</h3>
                <div className="flex items-center gap-3">
                    {correction.action === 'replace' ? (
                        <FileCheck className="h-5 w-5 text-emerald-600" />
                    ) : (
                        <FileX className="h-5 w-5 text-red-600" />
                    )}
                    <Badge variant={correction.action === 'replace' ? 'default' : 'secondary'}>
                        {formatCorrectionType(correction.correction_type, correction.correction_type_display)}
                    </Badge>
                </div>
            </div>

            {/* Reason */}
            <div className="grid gap-3">
                <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Reason for Correction</h3>
                <div className="bg-muted/50 rounded-lg p-4">
                    <p className="text-sm leading-relaxed">{correction.reason}</p>
                </div>
            </div>

            {/* Original Information */}
            <div className="grid gap-4">
                <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Original Information</h3>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-1.5 rounded-lg border p-3">
                        <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                            <FileText className="h-3 w-3" />
                            Original Transaction
                        </div>
                        <p className="font-mono text-sm break-all">{correction.original_txid || 'N/A'}</p>
                    </div>
                    <div className="grid gap-1.5 rounded-lg border p-3">
                        <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                            <Hash className="h-3 w-3" />
                            Original Hash
                        </div>
                        <p className="font-mono text-sm break-all">{correction.original_document_hash || correction.document_hash || 'N/A'}</p>
                    </div>
                </div>
            </div>

            {/* Corrected Information */}
            {correction.action === 'replace' && correction.corrected_metadata && (
                <div className="grid gap-4">
                    <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Corrected Information</h3>
                    <div className="rounded-lg border bg-emerald-50 p-4 dark:bg-emerald-950/20">
                        <div className="grid gap-3">
                            {correction.corrected_metadata.file_name && (
                                <div className="flex items-center gap-2">
                                    <FileText className="h-4 w-4 text-emerald-600" />
                                    <span className="text-sm font-medium">New File:</span>
                                    <span className="text-sm">{correction.corrected_metadata.file_name}</span>
                                </div>
                            )}
                            {correction.corrected_metadata.file_size && (
                                <div className="flex items-center gap-2">
                                    <FileText className="h-4 w-4 text-emerald-600" />
                                    <span className="text-sm font-medium">File Size:</span>
                                    <span className="text-sm">{(correction.corrected_metadata.file_size / 1024 / 1024).toFixed(2)} MB</span>
                                </div>
                            )}
                            {correction.corrected_metadata.hash && (
                                <div className="flex items-center gap-2">
                                    <Hash className="h-4 w-4 text-emerald-600" />
                                    <span className="text-sm font-medium">New Hash:</span>
                                    <span className="font-mono text-sm break-all">{correction.corrected_metadata.hash}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Audit Information */}
            <div className="grid gap-4">
                <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Audit Information</h3>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-1.5 rounded-lg border p-3">
                        <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                            <User className="h-3 w-3" />
                            Corrected By
                        </div>
                        <p className="text-sm font-medium">{correction.corrected_by}</p>
                    </div>
                    <div className="grid gap-1.5 rounded-lg border p-3">
                        <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                            <Calendar className="h-3 w-3" />
                            Correction Date
                        </div>
                        <p className="text-sm">{new Date(correction.timestamp).toLocaleString()}</p>
                    </div>
                </div>
            </div>

            {/* Blockchain TXID */}
            <div className="grid gap-3">
                <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Blockchain Transaction</h3>
                <div className="grid gap-1.5 rounded-lg border p-3">
                    <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                        <Hash className="h-3 w-3" />
                        Transaction ID
                    </div>
                    <p className="font-mono text-sm break-all">{correction.txid}</p>
                </div>
            </div>

            {/* Immutability Note */}
            <Alert className="border-primary/20 bg-primary/5">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription className="text-sm">
                    Note: Both the original and correction records remain permanently on the blockchain for audit trail purposes. This ensures
                    complete transparency and compliance with immutability requirements.
                </AlertDescription>
            </Alert>
        </div>
    );
}

// Shared component for correction timeline
function CorrectionTimeline({ corrections }: { corrections: CorrectionRecord[] }) {
    // Format correction type for display
    const formatCorrectionType = (type: string, displayType?: string) => {
        if (displayType) return displayType;
        // Convert snake_case to Title Case
        return type
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
    };

    return (
        <div className="relative">
            <div className="bg-border absolute top-0 bottom-0 left-4 hidden w-0.5 sm:block" />

            <div className="grid gap-6 sm:gap-8">
                {corrections.map((correction) => (
                    <div key={correction.txid} className="relative sm:pl-12">
                        <div className="bg-background border-primary absolute top-2 left-2.5 hidden h-4 w-4 rounded-full border-2 sm:block" />

                        <div className="bg-card rounded-lg border p-4 shadow-sm sm:p-5">
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
                                    {formatCorrectionType(correction.correction_type, correction.correction_type_display)}
                                </Badge>
                            </div>

                            <div className="mb-4 grid gap-2">
                                <p className="text-sm font-semibold">Reason:</p>
                                <p className="text-muted-foreground bg-muted rounded-lg p-3 text-sm leading-relaxed">{correction.reason}</p>
                            </div>

                            <div className="mb-4 grid gap-3 sm:grid-cols-2 sm:gap-4">
                                <div className="bg-muted/50 grid gap-1.5 rounded-lg p-3">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Corrected By</p>
                                    <p className="text-xs font-medium sm:text-sm">{correction.corrected_by}</p>
                                </div>
                                <div className="bg-muted/50 grid gap-1.5 rounded-lg p-3">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Blockchain TXID</p>
                                    <p className="font-mono text-xs break-all">{correction.txid}</p>
                                </div>
                            </div>

                            <div className="grid gap-3 border-t pt-4">
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">References Original</p>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {correction.original_txid && (
                                        <div className="bg-muted/30 grid gap-1 rounded-lg p-2 sm:p-3">
                                            <span className="text-xs font-medium">TXID:</span>
                                            <span className="font-mono text-xs break-all">{correction.original_txid}</span>
                                        </div>
                                    )}
                                    {(correction.original_document_hash || correction.document_hash) && (
                                        <div className="bg-muted/30 grid gap-1 rounded-lg p-2 sm:p-3">
                                            <span className="text-xs font-medium">Hash:</span>
                                            <span className="font-mono text-xs break-all">
                                                {correction.original_document_hash || correction.document_hash || ''}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {correction.action === 'replace' && correction.corrected_metadata && (
                                <div className="-mx-4 mt-4 -mb-4 grid gap-3 rounded-b-lg border-t bg-emerald-50 px-4 py-4 sm:-mx-5 sm:-mb-5 sm:px-5 dark:bg-emerald-950/20">
                                    <p className="text-xs font-semibold text-emerald-900 sm:text-sm dark:text-emerald-100">
                                        New Document Information
                                    </p>
                                    <div className="grid gap-2 text-xs sm:text-sm">
                                        {correction.corrected_metadata.file_name && (
                                            <div className="flex flex-col gap-1 sm:flex-row sm:gap-2">
                                                <strong className="shrink-0">File:</strong>
                                                <span className="wrap-break-word">{correction.corrected_metadata.file_name}</span>
                                            </div>
                                        )}
                                        {correction.corrected_metadata.file_size && (
                                            <div className="flex gap-2">
                                                <strong className="shrink-0">Size:</strong>
                                                <span>{(correction.corrected_metadata.file_size / 1024 / 1024).toFixed(2)} MB</span>
                                            </div>
                                        )}
                                        {correction.corrected_metadata.hash && (
                                            <div className="flex flex-col gap-1 sm:flex-row sm:gap-2">
                                                <strong className="shrink-0">Hash:</strong>
                                                <span className="font-mono text-xs break-all">{correction.corrected_metadata.hash}</span>
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
    );
}
