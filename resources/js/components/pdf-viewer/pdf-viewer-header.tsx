import { DocumentCorrectionSheet } from '@/components/documents/document-correction-sheet';
import { TruncateBadge } from '@/components/truncate-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { PdfDocument, SharedData, UserRole, ViewStats } from '@/types';
import { formatStatus, getStageIcon, getStatusBadgeColor, getStatusIcon } from '@/utils/pdf-viewer/helpers';
import { usePage } from '@inertiajs/react';
import { AlertTriangle, Download, Eye, FileText, Users } from 'lucide-react';
import React, { useState } from 'react';

interface Props {
    document: PdfDocument;
    pdfUrl: string;
    viewStats: ViewStats;
    pdfError: boolean;
}

export default function PdfViewerHeader({ document, pdfUrl, viewStats, pdfError }: Props) {
    const [showCorrectionSheet, setShowCorrectionSheet] = useState(false);
    const { auth } = usePage<SharedData>().props;

    // Check if user can correct documents
    const allowedRoles = [UserRole.ADMIN, UserRole.BAC_CHAIRMAN, UserRole.BAC_SECRETARIAT];
    const userRole = auth?.role || auth?.user?.role;
    const canCorrectDocuments = userRole ? allowedRoles.includes(userRole as UserRole) : false;

    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
            <CardContent className="p-4 sm:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0 flex-1">
                        <h1 className="text-primary truncate text-xl font-bold sm:text-2xl">{document.document_type_display}</h1>
                        <p className="text-muted-foreground mt-1 truncate text-xs sm:text-sm">{document.procurement_title}</p>
                        <div className="mt-2 flex flex-wrap items-center gap-1.5 sm:gap-2">
                            {document.phase_display_name && (
                                <TruncateBadge variant="secondary" className="text-xs font-medium" maxChars={20}>
                                    {document.phase_display_name}
                                </TruncateBadge>
                            )}
                            <TruncateBadge
                                variant="outline"
                                icon={React.createElement(getStageIcon(document.stage), { className: 'h-3 w-3 sm:h-3.5 sm:w-3.5' })}
                                className="flex items-center gap-1 text-xs sm:gap-1.5"
                                maxChars={20}
                            >
                                {document.stage_display}
                            </TruncateBadge>
                            {document.current_status && (
                                <TruncateBadge
                                    variant="outline"
                                    icon={React.createElement(getStatusIcon(document.current_status), { className: 'h-3 w-3 sm:h-3.5 sm:w-3.5' })}
                                    className={cn(
                                        'flex items-center gap-1 px-2 py-1 text-xs sm:gap-1.5 sm:px-3',
                                        getStatusBadgeColor(document.current_status),
                                    )}
                                    maxChars={16}
                                >
                                    {formatStatus(document.current_status)}
                                </TruncateBadge>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="flex items-center gap-1 text-xs">
                            <Eye className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">{viewStats.total_views} views</span>
                            <span className="sm:hidden">{viewStats.total_views}</span>
                        </Badge>
                        <Badge variant="outline" className="flex items-center gap-1 text-xs">
                            <Users className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">{viewStats.unique_viewers} unique</span>
                            <span className="sm:hidden">{viewStats.unique_viewers}</span>
                        </Badge>
                        {pdfError && (
                            <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                                <FileText className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">PDF Blocked</span>
                                <span className="sm:hidden">Blocked</span>
                            </Badge>
                        )}
                        {canCorrectDocuments && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="border-amber-200 text-xs text-muted-foreground hover:bg-muted/50"
                                onClick={() => setShowCorrectionSheet(true)}
                            >
                                <AlertTriangle className="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">Correct Document</span>
                                <span className="sm:hidden">Correct</span>
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-xs"
                            render={<a href={pdfUrl} target="_blank" rel="noopener noreferrer" />}
                        >
                            <Eye className="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                            <span className="hidden sm:inline">Open in Tab</span>
                            <span className="sm:hidden">Open</span>
                        </Button>
                        <Button size="sm" className="text-xs" render={<a href={pdfUrl} download />}>
                            <Download className="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                            Download
                        </Button>
                    </div>
                </div>

                {/* Document Correction Sheet */}
                {canCorrectDocuments && document.hash && (
                    <DocumentCorrectionSheet
                        open={showCorrectionSheet}
                        onOpenChange={setShowCorrectionSheet}
                        documentId={document.blockchain_txid || document.id || document.pr_number}
                        pr_number={document.pr_number}
                        procurementTitle={document.procurement_title}
                        originalDocumentHash={document.hash}
                        originalTxid={document.blockchain_txid}
                    />
                )}
            </CardContent>
        </Card>
    );
}
