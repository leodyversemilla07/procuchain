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
        <li className="group bg-card relative flex flex-col gap-2 rounded-xl border p-3 transition-all duration-200 hover:shadow-sm sm:p-4">
            {/* Header Row: Icon, Title/Meta, and Actions */}
            <div className="flex items-center gap-3 sm:gap-4">
                {/* Left Icon */}
                <div className="shrink-0">
                    <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-lg">
                        <FileText className="h-5 w-5" />
                    </div>
                </div>

                {/* Main Content Row */}
                <div className="flex min-w-0 flex-1 items-center justify-between gap-4">
                    <div className="flex min-w-0 flex-col justify-center gap-1">
                        <h4
                            className="truncate text-sm leading-tight font-semibold sm:text-base"
                            title={doc.document_type_formatted || doc.document_type}
                        >
                            {doc.document_type_formatted || doc.document_type || 'Unnamed Document'}
                        </h4>

                        {/* Meta Row */}
                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
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
                                    <TooltipTrigger
                                        render={
                                            <button
                                                onClick={handleCopyHash}
                                                className="hover:text-foreground flex cursor-pointer items-center gap-1 whitespace-nowrap transition-colors"
                                            />
                                        }
                                    >
                                        <Hash className="h-3 w-3" />
                                        <span className="font-mono">{shortenHash(doc.hash || '', 6, 4)}</span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="font-mono text-xs">{doc.hash}</p>
                                        <p className="text-muted-foreground mt-1 text-center text-[10px]">Click to copy</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>

                    {/* Desktop Actions */}
                    <div className="hidden shrink-0 items-center gap-2 sm:flex">
                        {doc.has_corrections && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setShowCorrectionDetails(true)}
                                className="h-8 text-amber-600 hover:bg-amber-50 hover:text-amber-700"
                            >
                                <ShieldAlert className="mr-1.5 h-3.5 w-3.5" />
                                Corrections
                            </Button>
                        )}
                        <Button variant="outline" size="sm" className="h-8" render={<Link href={pdf.viewer.url({ fileKey: doc.file_key })} />}>
                            <TrendingUp className="mr-1.5 h-3.5 w-3.5" />
                            View
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger render={<Button variant="ghost" size="icon" className="h-8 w-8" />}>
                                <MoreVertical className="h-4 w-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    render={<a href={files.download.url({ fileKey: doc.file_key })} target="_blank" rel="noopener noreferrer" />}
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    Download
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
            <div className="mt-1 flex items-center justify-between gap-2 border-t pt-2 sm:hidden">
                {doc.has_corrections && (
                    <Button variant="ghost" size="sm" onClick={() => setShowCorrectionDetails(true)} className="h-8 px-2 text-xs text-amber-600">
                        <ShieldAlert className="mr-1.5 h-3.5 w-3.5" />
                        Corrections
                    </Button>
                )}
                <div className="ml-auto flex gap-2">
                    <Button variant="outline" size="sm" className="h-8 text-xs" render={<Link href={pdf.viewer.url({ fileKey: doc.file_key })} />}>
                        View
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 text-xs"
                        render={<a href={files.download.url({ fileKey: doc.file_key })} target="_blank" rel="noopener noreferrer" />}
                    >
                        Download
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
