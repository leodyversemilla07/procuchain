import FileUploadArea from '@/components/file-upload-area';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import type { DocumentGuide } from '@/types/document-guide';
import { CheckCircle2, Lock } from 'lucide-react';
import type { ChangeEvent } from 'react';

interface DocumentUploadListProps {
    documentGuide?: DocumentGuide;
    uploadedDocuments: string[];
    files: Record<string, File | null>;
    dragging: Record<string, boolean>;
    isStageCompleted: boolean;
    isStageFuture: boolean;
    isUploading: boolean;
    onFileChange: (documentValue: string) => (event: ChangeEvent<HTMLInputElement>) => void;
    onDragStateChange: (documentValue: string, isDragging: boolean) => void;
    onFileDrop: (documentValue: string, file: File | null) => void;
    onRemoveFile: (documentValue: string) => void;
    onUploadClick: (documentValue: string, documentName: string) => void;
}

export function DocumentUploadList({
    documentGuide,
    uploadedDocuments,
    files,
    dragging,
    isStageCompleted,
    isStageFuture,
    isUploading,
    onFileChange,
    onDragStateChange,
    onFileDrop,
    onRemoveFile,
    onUploadClick,
}: DocumentUploadListProps) {
    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border min-h-[400px] shadow-md lg:col-span-2">
            <CardContent className="space-y-8 p-6">
                {isStageFuture ? (
                    <div className="text-muted-foreground flex flex-col items-center justify-center py-20 text-center opacity-30">
                        <Lock size={48} className="mb-4" />
                        <h3 className="text-foreground text-lg font-semibold">Stage is Locked</h3>
                        <p className="mt-1 max-w-xs text-xs italic">Please finish the current stage tasks first.</p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {documentGuide &&
                            [...documentGuide.required_documents, ...documentGuide.optional_documents].map((document) => {
		const isUploaded = Array.isArray(uploadedDocuments) && uploadedDocuments.includes(document.value);
                                const isRequired = documentGuide.required_documents.some(
                                    (requiredDocument) => requiredDocument.value === document.value,
                                );

                                return (
                                    <div
                                        key={document.value}
                                        className="bg-card/50 hover:bg-card relative rounded-2xl border p-5 transition-all hover:shadow-sm"
                                    >
                                        <div className="mb-4 flex items-start justify-between">
                                            <div className="space-y-1 text-left">
                                                <div className="flex items-center gap-2">
                                                    <h4 className="text-base font-semibold">{document.display_name}</h4>
                                                    {isRequired && (
                                                        <Badge variant="destructive" className="h-4 px-1 text-[9px] font-bold uppercase">
                                                            Required
                                                        </Badge>
                                                    )}
                                                </div>
                                                {document.description && (
                                                    <p className="text-muted-foreground text-xs leading-relaxed">{document.description}</p>
                                                )}
                                            </div>
                                            {isUploaded && (
                                                <Badge className="bg-green-500 py-0 text-[10px] hover:bg-green-600">
                                                    <CheckCircle2 className="mr-1 h-3 w-3" /> UPLOADED
                                                </Badge>
                                            )}
                                        </div>

                                        {!isUploaded && !isStageCompleted && (
                                            <div className="flex flex-col items-stretch gap-4 lg:flex-row">
                                                <div className="flex-1">
                                                    <FileUploadArea
                                                        label=""
                                                        file={files[document.value] || null}
                                                        isDragging={dragging[document.value] || false}
                                                        onFileChange={onFileChange(document.value)}
                                                        onDragEnter={(event) => {
                                                            event.preventDefault();
                                                            onDragStateChange(document.value, true);
                                                        }}
                                                        onDragLeave={(event) => {
                                                            event.preventDefault();
                                                            onDragStateChange(document.value, false);
                                                        }}
                                                        onDragOver={(event) => event.preventDefault()}
                                                        onDrop={(event) => {
                                                            event.preventDefault();
                                                            onDragStateChange(document.value, false);
                                                            onFileDrop(document.value, event.dataTransfer.files[0] ?? null);
                                                        }}
                                                        onRemove={() => onRemoveFile(document.value)}
                                                        inputId={`file-${document.value}`}
                                                    />
                                                </div>
                                                <Button
                                                    onClick={() => onUploadClick(document.value, document.display_name)}
                                                    disabled={!files[document.value] || isUploading}
                                                    className="shadow-sm transition-transform active:scale-95 lg:h-auto lg:w-[120px]"
                                                >
                                                    {isUploading ? <Spinner className="h-5 w-5" /> : 'UPLOAD'}
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
