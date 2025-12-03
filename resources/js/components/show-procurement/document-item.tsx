import { Calendar, Download, Eye, FileText, HardDrive, Hash, Lock, TrendingUp } from 'lucide-react';
import { useCallback, type FC } from 'react';

import CorrectionDetailsSheet from '@/components/documents/correction-details-sheet';
import { Button } from '@/components/ui/button';
import files from '@/routes/files';
import pdf from '@/routes/pdf';
import type { Document } from '@/types';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { shortenHash } from '../../utils/show-procurement/helpers';
import { DocumentMetadataCard } from './document-metadata-card';

interface DocumentItemProps {
    doc: Document;
}

export const DocumentItem: FC<DocumentItemProps> = ({ doc }) => {
    const [showCorrectionDetails, setShowCorrectionDetails] = useState(false);

    const handleCopyHash = useCallback(async () => {
        if (!doc.hash) return;
        try {
            await navigator.clipboard.writeText(doc.hash);
            toast.success('Hash copied to clipboard');
        } catch (error) {
            toast.error('Failed to copy hash: ' + String(error));
        }
    }, [doc.hash]);

    return (
        <li className="group hover:bg-muted/30 border-b p-3 transition-all duration-200 last:border-b-0 sm:p-4">
            <div className="flex flex-col gap-3 sm:gap-4">
                {/* Document Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-2 sm:gap-3">
                        <div className="group-hover:border-primary/30 group-hover:bg-primary/5 rounded-lg border p-1.5 transition-all duration-200 sm:p-2">
                            <FileText className="text-destructive h-5 w-5 sm:h-6 sm:w-6" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <h4
                                className="group-hover:text-primary mb-1.5 text-sm font-medium transition-colors duration-200 sm:mb-2 sm:text-base"
                                title={doc.document_type_formatted || doc.document_type}
                            >
                                {doc.document_type_formatted || doc.document_type || 'Unnamed Document'}
                            </h4>
                            <div className="text-muted-foreground flex flex-wrap gap-2 text-xs sm:gap-4 sm:text-sm">
                                <span className="flex items-center gap-1" aria-label={`File key: ${doc.file_key || 'N/A'}`}>
                                    <Hash className="h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4" aria-hidden="true" />
                                    {/* Mobile: Shortened file key */}
                                    <span className="truncate md:hidden">{shortenHash(doc.file_key, 5, 3)}</span>
                                    {/* Desktop: Full file key */}
                                    <span className="hidden break-all md:inline">{doc.file_key || 'N/A'}</span>
                                </span>
                                {doc.file_size_formatted && (
                                    <span className="flex items-center gap-1" aria-label={`File size: ${doc.file_size_formatted}`}>
                                        <HardDrive className="h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4" aria-hidden="true" />
                                        <span className="truncate">{doc.file_size_formatted}</span>
                                    </span>
                                )}
                                {doc.formatted_date_only && (
                                    <span className="flex items-center gap-1" aria-label={`Upload date: ${doc.formatted_date_only}`}>
                                        <Calendar className="h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4" aria-hidden="true" />
                                        <span className="truncate">{doc.formatted_date_only}</span>
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex w-full gap-2 sm:w-auto sm:shrink-0">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="hover:border-primary hover:bg-primary/5 focus-visible:ring-primary h-8 flex-1 text-xs font-medium shadow-sm transition-all duration-200 hover:shadow focus-visible:ring-2 focus-visible:ring-offset-2 sm:h-9 sm:flex-none sm:text-sm"
                        >
                            <Link
                                href={pdf.viewer.url({ fileKey: doc.file_key })}
                                className="flex items-center"
                                aria-label={`View ${doc.document_type || 'document'} with analytics`}
                            >
                                <TrendingUp className="mr-1.5 h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                <span className="hidden sm:inline">View with Analytics</span>
                                <span className="sm:hidden">Analytics</span>
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="hover:border-primary hover:bg-primary/5 focus-visible:ring-primary h-8 text-xs font-medium shadow-sm transition-all duration-200 hover:shadow focus-visible:ring-2 focus-visible:ring-offset-2 sm:h-9 sm:text-sm"
                        >
                            <a
                                href={files.download.url({ fileKey: doc.file_key })}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center"
                                aria-label={`Quick view ${doc.document_type || 'document'}`}
                            >
                                <Eye className="mr-1.5 h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                <span className="hidden sm:inline">Quick View</span>
                                <span className="sm:hidden">View</span>
                            </a>
                        </Button>

                        {doc.has_corrections && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowCorrectionDetails(true)}
                                className="hover:border-primary hover:bg-primary/5 focus-visible:ring-primary h-8 text-xs font-medium shadow-sm transition-all duration-200 hover:shadow focus-visible:ring-2 focus-visible:ring-offset-2 sm:h-9 sm:text-sm"
                                aria-label="View correction details"
                            >
                                <FileText className="mr-1.5 h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                <span className="hidden sm:inline">View Corrections</span>
                                <span className="sm:hidden">Corrections</span>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Hash Section */}
                <div className="bg-muted/30 group-hover:border-primary/30 rounded-lg border p-2.5 transition-all duration-200 sm:p-3">
                    <div className="flex items-center justify-between gap-2 sm:gap-3">
                        <div className="flex min-w-0 flex-1 items-center gap-1.5 sm:gap-2">
                            <Lock className="text-muted-foreground h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4" aria-hidden="true" />
                            <div className="min-w-0 flex-1">
                                <div className="text-muted-foreground mb-0.5 text-[10px] font-medium sm:mb-1 sm:text-xs">Document Hash</div>
                                {/* Mobile: Shortened hash */}
                                <code className="block truncate font-mono text-xs md:hidden" title={doc.hash} aria-label={`Full hash: ${doc.hash}`}>
                                    {shortenHash(doc.hash)}
                                </code>
                                {/* Desktop: Full hash with word break */}
                                <code
                                    className="hidden font-mono text-xs break-all md:block md:text-sm"
                                    title={doc.hash}
                                    aria-label={`Full hash: ${doc.hash}`}
                                >
                                    {doc.hash || 'N/A'}
                                </code>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleCopyHash}
                            className="hover:bg-primary/10 focus-visible:ring-primary h-7 shrink-0 text-xs transition-all duration-200 focus-visible:ring-2 focus-visible:ring-offset-2 sm:h-8 sm:text-sm"
                            aria-label="Copy hash to clipboard"
                        >
                            <Download className="mr-1 h-3 w-3 sm:mr-1.5 sm:h-3.5 sm:w-3.5" aria-hidden="true" />
                            <span className="hidden sm:inline">Copy</span>
                            <span className="sm:hidden">Copy</span>
                        </Button>
                    </div>
                </div>

                {/* Metadata Section */}
                {doc.stage_metadata && <DocumentMetadataCard metadata={doc.stage_metadata} documentType={doc.stage_metadata.document_type} />}
            </div>

            {/* Correction Details Sheet */}
            {doc.has_corrections && doc.latest_correction && (
                <CorrectionDetailsSheet
                    open={showCorrectionDetails}
                    onOpenChange={setShowCorrectionDetails}
                    correction={{
                        txid: doc.latest_correction.txid,
                        timestamp: doc.latest_correction.timestamp,
                        correction_type: doc.latest_correction.correction_type,
                        action: doc.latest_correction.action as 'replace' | 'invalidate',
                        reason: doc.latest_correction.reason,
                        corrected_by: doc.latest_correction.corrected_by,
                        original_txid: doc.data_txid || '',
                        original_document_hash: doc.hash || '',
                        document_hash: doc.latest_correction.corrected_metadata?.hash || '',
                        file_name: doc.latest_correction.corrected_metadata?.file_name || '',
                        file_key: String(doc.latest_correction.corrected_metadata?.file_key || ''),
                        document_type: doc.document_type || '',
                        document_type_display: doc.document_type_formatted || doc.document_type || '',
                        corrected_metadata: doc.latest_correction.corrected_metadata,
                    }}
                    pr_number={doc.pr_number || ''}
                    documentHash={doc.hash}
                />
            )}
        </li>
    );
};
