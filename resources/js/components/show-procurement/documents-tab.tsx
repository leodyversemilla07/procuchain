import { FileText, Layers } from 'lucide-react';
import { useMemo, type FC } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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
            <Card className="border-dashed shadow-sm">
                <CardContent className="p-8">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon" className="bg-muted/50 p-4 rounded-full">
                                <FileText className="text-muted-foreground h-8 w-8" />
                            </EmptyMedia>
                            <EmptyTitle className="mt-4">No Documents Yet</EmptyTitle>
                            <EmptyDescription>Documents will appear here once they are uploaded to this procurement.</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between px-1">
                <div className="flex items-center gap-2">
                     <Layers className="h-5 w-5 text-muted-foreground" />
                     <h3 className="font-semibold text-lg">Procurement Documents</h3>
                </div>
                <Badge variant="secondary">
                    {totalDocuments} {totalDocuments === 1 ? 'File' : 'Files'}
                </Badge>
            </div>

            <div className="space-y-8">
                {sortedStageKeys.map((stage, stageIndex) => {
                    const isLatestStage = stageIndex === 0;
                    const stageDocuments = documentsByStage[stage];

                    return (
                        <section
                            key={stage}
                            className="relative"
                            aria-labelledby={`stage-${stage.replace(/\s+/g, '-').toLowerCase()}`}
                        >
                            {/* Sticky Header */}
                            <div className="sticky top-0 z-10 bg-background/95 backdrop-blur-sm py-2 mb-2 border-b">
                                <div className="flex items-center gap-3">
                                    <div className={`h-8 w-1 rounded-full ${isLatestStage ? 'bg-primary' : 'bg-muted-foreground/30'}`} />
                                    <h3
                                        id={`stage-${stage.replace(/\s+/g, '-').toLowerCase()}`}
                                        className="flex items-center gap-2 font-semibold"
                                    >
                                        <span className="text-foreground">{stage}</span>
                                        <Badge variant="secondary" className="text-xs h-5 px-1.5 min-w-6 justify-center">
                                            {stageDocuments.length}
                                        </Badge>
                                        {isLatestStage && (
                                            <Badge variant="default" className="text-[10px] h-5 px-1.5">
                                                Current
                                            </Badge>
                                        )}
                                    </h3>
                                </div>
                            </div>
                            
                            {/* Document List */}
                            <ul role="list" className="grid grid-cols-1 gap-3 sm:grid-cols-1 lg:grid-cols-1">
                                {stageDocuments.map((doc, docIndex) => (
                                    <DocumentItem key={`${doc.file_key}-${docIndex}`} doc={doc} />
                                ))}
                            </ul>
                        </section>
                    );
                })}
            </div>
        </div>
    );
};
