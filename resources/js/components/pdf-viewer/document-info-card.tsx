import { DocumentCorrectionSheet } from '@/components/documents/document-correction-sheet';
import { TruncateBadge } from '@/components/truncate-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { PdfDocument, SharedData, ViewStats } from '@/types';
import {
    formatFileSize,
    formatStatus,
    formatTimestamp,
    formatUserAddress,
    getStageIcon,
    getStatusBadgeColor,
    getStatusIcon,
} from '@/utils/pdf-viewer/helpers';
import { usePage } from '@inertiajs/react';
import { Activity, AlertTriangle, Building2, CalendarDays, Clock, Eye, FileText, Globe, HardDrive, Hash, Shield, Target, Users } from 'lucide-react';
import React, { useState } from 'react';

interface Props {
    document: PdfDocument;
    fileKey: string;
    viewStats: ViewStats;
}

export default function DocumentInfoCard({ document, fileKey, viewStats }: Props) {
    const [showCorrectionSheet, setShowCorrectionSheet] = useState(false);
    const { auth } = usePage<SharedData>().props;

    // Check if user can correct documents
    const allowedRoles = ['admin', 'bac_chairman', 'bac_secretariat'];
    const canCorrectDocuments = auth?.role ? allowedRoles.includes(auth.role) : allowedRoles.includes(auth?.user?.role ?? '');

    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base sm:text-lg">
                    <FileText />
                    Document Information
                </CardTitle>
                <CardDescription className="text-xs sm:text-sm">Complete details about this document</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="flex flex-col gap-3">
                    <div className="flex items-start justify-between gap-2">
                        <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                            <Hash className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                            Procurement ID:
                        </span>
                        <span className="bg-muted rounded px-2 py-1 font-mono text-xs font-medium sm:text-sm">{document.pr_number}</span>
                    </div>

                    <div className="flex items-start justify-between gap-2">
                        <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                            <Building2 className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                            Procurement Title:
                        </span>
                        <span className="max-w-[140px] truncate text-right text-xs font-medium sm:max-w-[200px] sm:text-sm">
                            {document.procurement_title}
                        </span>
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                            <FileText className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                            Document Type:
                        </span>
                        <TruncateBadge variant="secondary" className="text-xs font-medium" maxChars={18}>
                            {document.document_type_display}
                        </TruncateBadge>
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                            <Target className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                            Current Stage:
                        </span>
                        <TruncateBadge
                            variant="outline"
                            icon={React.createElement(getStageIcon(document.stage), { className: 'h-3 w-3 sm:h-3.5 sm:w-3.5' })}
                            className="flex items-center gap-1 text-xs sm:gap-1.5"
                            maxChars={20}
                        >
                            {document.stage_display}
                        </TruncateBadge>
                    </div>

                    {document.phase && (
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <Target className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                Current Phase:
                            </span>
                            <TruncateBadge variant="secondary" className="text-xs font-medium" maxChars={20}>
                                {document.phase_display_name}
                            </TruncateBadge>
                        </div>
                    )}

                    {document.current_status && (
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <Activity className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                Procurement Status:
                            </span>
                            <Badge
                                variant="outline"
                                className={cn('flex items-center gap-1 text-xs sm:gap-1.5', getStatusBadgeColor(document.current_status))}
                            >
                                {React.createElement(getStatusIcon(document.current_status), { className: 'h-3 w-3 sm:h-3.5 sm:w-3.5' })}
                                {formatStatus(document.current_status)}
                            </Badge>
                        </div>
                    )}

                    {document.status_timestamp && (
                        <div className="flex items-start justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <Clock className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                Status Updated:
                            </span>
                            <div className="text-right">
                                <span className="text-xs font-medium sm:text-sm">{formatTimestamp(document.status_timestamp).date}</span>
                                <p className="text-muted-foreground text-xs">
                                    {formatTimestamp(document.status_timestamp).time} ({formatTimestamp(document.status_timestamp).relative})
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                <div className="border-t pt-3">
                    <h4 className="text-primary mb-3 text-xs font-medium sm:text-sm">File Details</h4>
                    <div className="flex flex-col gap-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <HardDrive className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                File Size:
                            </span>
                            <span className="text-xs font-medium sm:text-sm">
                                {document.file_size && document.file_size > 0 ? formatFileSize(document.file_size) : 'N/A'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <Globe className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                File Key:
                            </span>
                            <Tooltip>
                                <TooltipTrigger
                                    render={
                                        <span className="bg-muted max-w-[100px] cursor-help truncate rounded px-2 py-1 font-mono text-xs sm:max-w-[180px]">
                                            {fileKey}
                                        </span>
                                    }
                                />
                                <TooltipContent className="max-w-md">
                                    <p className="font-mono text-xs break-all">{fileKey}</p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </div>
                </div>

                <div className="border-t pt-3">
                    <h4 className="text-primary mb-3 text-xs font-medium sm:text-sm">Blockchain & Security</h4>
                    <div className="flex flex-col gap-3">
                        <div className="flex items-start justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <Shield className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                Document Hash:
                            </span>
                            <div className="min-w-0 text-right">
                                {document.hash && document.hash.trim() !== '' ? (
                                    <>
                                        <Tooltip>
                                            <TooltipTrigger
                                                render={
                                                    <span className="bg-muted text-muted-foreground block max-w-[120px] cursor-help truncate rounded px-2 py-1 font-mono text-xs sm:max-w-none">
                                                        {formatUserAddress(document.hash)}
                                                    </span>
                                                }
                                            />
                                            <TooltipContent className="max-w-md">
                                                <p className="font-mono text-xs break-all">{document.hash}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                        <p className="text-muted-foreground mt-1 text-xs">Blockchain verified</p>
                                    </>
                                ) : (
                                    <>
                                        <span className="bg-muted text-muted-foreground rounded px-2 py-1 font-mono text-xs">Not available</span>
                                        <p className="text-muted-foreground mt-1 text-xs">No blockchain data</p>
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="flex items-start justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <CalendarDays className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                Created:
                            </span>
                            <div className="text-right">
                                <span className="text-xs font-medium sm:text-sm">{formatTimestamp(document.timestamp).date}</span>
                                <p className="text-muted-foreground text-xs">
                                    {formatTimestamp(document.timestamp).time} • {formatTimestamp(document.timestamp).relative}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="border-t pt-3">
                    <h4 className="text-primary mb-3 text-xs font-medium sm:text-sm">Viewing Statistics</h4>
                    <div className="flex flex-col gap-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <Eye className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                Total Views:
                            </span>
                            <span className="text-primary text-xs font-bold sm:text-sm">{viewStats.total_views}</span>
                        </div>

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <Users className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                Unique Viewers:
                            </span>
                            <span className="text-success text-xs font-bold sm:text-sm">{viewStats.unique_viewers}</span>
                        </div>

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground text-xs sm:text-sm">First Viewed:</span>
                            <span className="text-xs font-medium sm:text-sm">{viewStats.first_viewed || 'Never'}</span>
                        </div>

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground text-xs sm:text-sm">Last Viewed:</span>
                            <span className="text-xs font-medium sm:text-sm">{viewStats.last_viewed || 'Never'}</span>
                        </div>

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs sm:text-sm">
                                <Clock className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                Current Session:
                            </span>
                            <span className="text-warning text-xs font-medium sm:text-sm">Active</span>
                        </div>
                    </div>
                </div>

                {/* Correction Button */}
                {canCorrectDocuments && (
                    <div className="flex flex-col gap-3 border-t pt-4">
                        <div className="grid grid-cols-1 gap-2">
                            <Button
                                variant="outline"
                                className="border-amber-200 text-muted-foreground hover:bg-muted/50"
                                onClick={() => setShowCorrectionSheet(true)}
                            >
                                <AlertTriangle />
                                Quick Correct
                            </Button>
                        </div>
                        <p className="text-muted-foreground text-center text-xs">Submit corrections while maintaining blockchain immutability</p>
                    </div>
                )}
            </CardContent>

            {/* Document Correction Sheet */}
            {canCorrectDocuments && (
                <DocumentCorrectionSheet
                    open={showCorrectionSheet}
                    onOpenChange={setShowCorrectionSheet}
                    documentId={document.blockchain_txid || document.id || ''}
                    pr_number={document.pr_number}
                    procurementTitle={document.procurement_title}
                    originalDocumentHash={document.hash || ''}
                    originalTxid={document.blockchain_txid}
                />
            )}
        </Card>
    );
}
