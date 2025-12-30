import { Calendar, Download, FileText, HardDrive, Hash, MoreVertical, ShieldAlert, TrendingUp } from 'lucide-react';
import { useCallback, type FC } from 'react';

import CorrectionDetailsSheet from '@/components/documents/correction-details-sheet';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
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
        <li className="group relative flex flex-col gap-2 p-3 sm:p-4 rounded-xl border bg-card hover:shadow-sm transition-all duration-200">
            {/* Header Row: Icon, Title/Meta, and Actions */}
            <div className="flex items-center gap-3 sm:gap-4">
                {/* Left Icon */}
                <div className="shrink-0">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <FileText className="h-5 w-5" />
                    </div>
                </div>

                {/* Main Content Row */}
                <div className="flex-1 min-w-0 flex items-center justify-between gap-4">
                    <div className="min-w-0 flex flex-col gap-1 justify-center">
                        <h4 className="font-semibold text-sm sm:text-base leading-tight truncate" title={doc.document_type_formatted || doc.document_type}>
                            {doc.document_type_formatted || doc.document_type || 'Unnamed Document'}
                        </h4>

                        {/* Meta Row */}
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            {doc.file_size_formatted && (
                                <span className="flex items-center gap-1 whitespace-nowrap">
                                    <HardDrive className="h-3 w-3" />
                                    {doc.file_size_formatted}
                                </span>
                            )}
                            {doc.formatted_date_only && (
                                <span className="flex items-center gap-1 whitespace-nowrap">
                                    <Calendar className="h-3 w-3" />
                                    {doc.formatted_date_only}
                                </span>
                            )}

                            {/* Hash with copy (Subtle) */}
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <button
                                            onClick={handleCopyHash}
                                            className="flex items-center gap-1 hover:text-foreground transition-colors cursor-pointer whitespace-nowrap"
                                        >
                                            <Hash className="h-3 w-3" />
                                            <span className="font-mono">{shortenHash(doc.hash || '', 6, 4)}</span>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="font-mono text-xs">{doc.hash}</p>
                                        <p className="text-[10px] text-muted-foreground text-center mt-1">Click to copy</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>

                    {/* Desktop Actions */}
                    <div className="hidden sm:flex items-center gap-2 shrink-0">
                        {doc.has_corrections && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setShowCorrectionDetails(true)}
                                className="h-8 text-amber-600 hover:text-amber-700 hover:bg-amber-50"
                            >
                                <ShieldAlert className="mr-1.5 h-3.5 w-3.5" />
                                Corrections
                            </Button>
                        )}
                        <Button variant="outline" size="sm" className="h-8" asChild>
                            <Link href={pdf.viewer.url({ fileKey: doc.file_key })}>
                                <TrendingUp className="mr-1.5 h-3.5 w-3.5" />
                                View
                            </Link>
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" className="h-8 w-8">
                                    <MoreVertical className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <a href={files.download.url({ fileKey: doc.file_key })} target="_blank" rel="noopener noreferrer">
                                        <Download className="mr-2 h-4 w-4" />
                                        Download
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={handleCopyHash}>
                                    <Hash className="mr-2 h-4 w-4" />
                                    Copy Hash
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            {/* Metadata Card (Conditional) - Pushed below the header row */}
            {doc.stage_metadata && (
                <div className="mt-1 sm:ml-13">
                    <DocumentMetadataCard metadata={doc.stage_metadata} documentType={doc.stage_metadata.document_type} />
                </div>
            )}

            {/* Mobile Actions (Bottom) */}
            <div className="flex sm:hidden items-center justify-between gap-2 pt-2 border-t mt-1">
                {doc.has_corrections && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setShowCorrectionDetails(true)}
                        className="text-amber-600 h-8 text-xs px-2"
                    >
                        <ShieldAlert className="mr-1.5 h-3.5 w-3.5" />
                        Corrections
                    </Button>
                )}
                <div className="flex gap-2 ml-auto">
                    <Button variant="outline" size="sm" className="h-8 text-xs" asChild>
                        <Link href={pdf.viewer.url({ fileKey: doc.file_key })}>
                            View
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" className="h-8 text-xs" asChild>
                        <a href={files.download.url({ fileKey: doc.file_key })} target="_blank" rel="noopener noreferrer">
                            Download
                        </a>
                    </Button>
                </div>
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
