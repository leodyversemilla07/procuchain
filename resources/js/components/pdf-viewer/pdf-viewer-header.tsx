import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { PdfDocument, ViewStats } from '@/types';
import { formatStatus, getStageIcon, getStatusBadgeColor, getStatusIcon } from '@/utils/pdf-viewer/helpers';
import { Download, Eye, FileText, Users } from 'lucide-react';
import React from 'react';

interface Props {
    document: PdfDocument;
    pdfUrl: string;
    viewStats: ViewStats;
    pdfError: boolean;
}

export default function PdfViewerHeader({ document, pdfUrl, viewStats, pdfError }: Props) {
    return (
        <div className="mb-4 sm:mb-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex-1">
                    <h1 className="text-primary text-xl font-bold sm:text-2xl">{document.document_type_display}</h1>
                    <p className="text-muted-foreground mt-1 text-xs sm:text-sm">{document.procurement_title}</p>
                    <div className="mt-2 flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <Badge variant="outline" className="flex items-center gap-1 text-xs sm:gap-1.5">
                            {React.createElement(getStageIcon(document.stage), { className: 'h-3 w-3 sm:h-3.5 sm:w-3.5' })}
                            <span className="font-medium">{document.stage_display}</span>
                        </Badge>
                        {document.current_status && (
                            <Badge
                                variant="outline"
                                className={cn('flex items-center gap-1 px-2 py-1 text-xs sm:gap-1.5 sm:px-3', getStatusBadgeColor(document.current_status))}
                            >
                                {React.createElement(getStatusIcon(document.current_status), { className: 'h-3 w-3 sm:h-3.5 sm:w-3.5' })}
                                <span className="font-medium">{formatStatus(document.current_status)}</span>
                            </Badge>
                        )}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline" className="flex items-center gap-1 text-xs">
                        <Eye className="h-3 w-3" />
                        <span className="hidden sm:inline">{viewStats.total_views} views</span>
                        <span className="sm:hidden">{viewStats.total_views}</span>
                    </Badge>
                    <Badge variant="outline" className="flex items-center gap-1 text-xs">
                        <Users className="h-3 w-3" />
                        <span className="hidden sm:inline">{viewStats.unique_viewers} unique</span>
                        <span className="sm:hidden">{viewStats.unique_viewers}</span>
                    </Badge>
                    {pdfError && (
                        <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                            <FileText className="h-3 w-3" />
                            <span className="hidden sm:inline">PDF Blocked</span>
                            <span className="sm:hidden">Blocked</span>
                        </Badge>
                    )}
                    <Button variant="outline" size="sm" className="text-xs" asChild>
                        <a href={pdfUrl} target="_blank" rel="noopener noreferrer">
                            <Eye className="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                            <span className="hidden sm:inline">Open in Tab</span>
                            <span className="sm:hidden">Open</span>
                        </a>
                    </Button>
                    <Button size="sm" className="text-xs" asChild>
                        <a href={pdfUrl} download>
                            <Download className="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                            Download
                        </a>
                    </Button>
                </div>
            </div>
        </div>
    );
}
