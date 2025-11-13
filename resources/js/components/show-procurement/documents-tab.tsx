import { FileCheck, FileText } from 'lucide-react';
import { useMemo, type FC } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import type { Document } from '@/types';
import { groupDocumentsByStage, sortStageKeys } from '../../utils/show-procurement/helpers';
import { DocumentItem } from './document-item';

interface DocumentsTabProps {
    documents?: Document[];
}

export const DocumentsTab: FC<DocumentsTabProps> = ({ documents }) => {
    const documentsByStage = useMemo(() => groupDocumentsByStage(documents), [documents]);
    const sortedStageKeys = useMemo(() => sortStageKeys(documentsByStage), [documentsByStage]);
    const totalDocuments = useMemo(() => documents?.length ?? 0, [documents]);

    if (totalDocuments === 0) {
        return (
            <Card className="border shadow-sm transition-shadow duration-200 hover:shadow-md">
                <CardContent className="p-0">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FileText className="text-muted-foreground" />
                            </EmptyMedia>
                            <EmptyTitle>No Documents Yet</EmptyTitle>
                            <EmptyDescription>
                                Documents will appear here once they are uploaded to this procurement.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="border shadow-sm transition-shadow duration-200 hover:shadow-md">
            <CardHeader className="p-4 sm:p-6">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 sm:h-10 sm:w-10">
                            <FileText className="h-4 w-4 text-primary sm:h-5 sm:w-5" aria-hidden="true" />
                        </div>
                        <div className="min-w-0">
                            <CardTitle className="truncate text-base sm:text-lg">Procurement Documents</CardTitle>
                            <CardDescription className="truncate text-xs sm:text-sm">
                                Documents organized by procurement stage
                            </CardDescription>
                        </div>
                    </div>
                    <Badge variant="outline" className="hidden font-medium sm:inline-flex">
                        {totalDocuments} {totalDocuments === 1 ? 'Document' : 'Documents'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div role="list" aria-label="Documents by stage">
                    {sortedStageKeys.map((stage, stageIndex) => {
                        const isLatestStage = stageIndex === 0;
                        const stageDocuments = documentsByStage[stage];

                        return (
                            <section
                                key={stage}
                                className="border-b last:border-b-0"
                                aria-labelledby={`stage-${stage.replace(/\s+/g, '-').toLowerCase()}`}
                            >
                                <div className="sticky top-0 z-10 border-b bg-muted/80 p-3 backdrop-blur-sm sm:p-4">
                                    <div className="flex items-center justify-between gap-2">
                                        <h3
                                            id={`stage-${stage.replace(/\s+/g, '-').toLowerCase()}`}
                                            className="flex flex-wrap items-center gap-1.5 text-sm font-semibold sm:gap-2 sm:text-base"
                                        >
                                            <FileCheck className="h-3.5 w-3.5 shrink-0 text-primary sm:h-4 sm:w-4" aria-hidden="true" />
                                            <span className="truncate">{stage}</span>
                                            <Badge variant="outline" className="ml-0.5 shrink-0 text-[10px] sm:ml-1 sm:text-xs">
                                                {stageDocuments.length}
                                            </Badge>
                                            {isLatestStage && (
                                                <Badge variant="default" className="shrink-0 text-[10px] sm:text-xs">
                                                    Latest Stage
                                                </Badge>
                                            )}
                                        </h3>
                                    </div>
                                </div>
                                <ul role="list">
                                    {stageDocuments.map((doc, docIndex) => (
                                        <DocumentItem key={`${doc.file_key}-${docIndex}`} doc={doc} />
                                    ))}
                                </ul>
                            </section>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
};
