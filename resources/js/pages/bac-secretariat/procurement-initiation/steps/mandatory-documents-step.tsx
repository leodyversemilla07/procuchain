import React, { useCallback, useMemo } from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { FileText, Upload, CheckCircle2, Info } from 'lucide-react';
import FileUploadArea from '@/components/file-upload-area';
import type { DocumentTypeOption } from '../types';

interface MandatoryDocumentsStepProps {
    data: {
        files: (File | null)[];
        document_types: string[];
    };
    setData: <K extends string>(key: K, value: unknown) => void;
    errors: Partial<Record<string, string>>;
    clearErrors: (...fields: never[]) => void;
    hasError: (field: string) => boolean;
    documentTypes: DocumentTypeOption[];
    dragStates: Record<string, boolean>;
    setDragStates: React.Dispatch<React.SetStateAction<Record<string, boolean>>>;
}

export function MandatoryDocumentsStep({
    data,
    setData,
    errors,
    clearErrors,
    hasError,
    documentTypes,
    dragStates,
    setDragStates,
}: MandatoryDocumentsStepProps) {
    const mandatoryDocTypes = useMemo(() => {
        return documentTypes.filter((dt) => dt.is_mandatory);
    }, [documentTypes]);

    const validateFile = useCallback((file: File): boolean => {
        if (file.size > 10 * 1024 * 1024) {
            return false;
        }
        if (file.type && file.type !== 'application/pdf') {
            return false;
        }
        if (!file.type && !file.name.toLowerCase().endsWith('.pdf')) {
            return false;
        }
        return true;
    }, []);

    const getMandatoryFile = useCallback(
        (docTypeValue: string): File | null => {
            const index = data.document_types.indexOf(docTypeValue);
            return index >= 0 ? data.files[index] : null;
        },
        [data.files, data.document_types],
    );

    const handleMandatoryFileChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>, docType: DocumentTypeOption) => {
            clearErrors();
            const newFile = e.target.files?.[0] || null;

            if (newFile && !validateFile(newFile)) {
                e.target.value = '';
                return;
            }

            const updatedFiles = [...data.files];
            const updatedDocTypes = [...data.document_types];

            const existingIndex = updatedDocTypes.indexOf(docType.value);

            if (existingIndex >= 0) {
                updatedFiles[existingIndex] = newFile;
            } else {
                updatedFiles.push(newFile);
                updatedDocTypes.push(docType.value);
            }

            setData('files', updatedFiles);
            setData('document_types', updatedDocTypes);
        },
        [data.files, data.document_types, validateFile, setData, clearErrors],
    );

    const handleDragEnter = useCallback(
        (e: React.DragEvent, docId: string) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => ({ ...prev, [docId]: true }));
        },
        [setDragStates],
    );

    const handleDragLeave = useCallback(
        (e: React.DragEvent, docId: string) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => ({ ...prev, [docId]: false }));
        },
        [setDragStates],
    );

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    }, []);

    const handleMandatoryDrop = useCallback(
        (e: React.DragEvent, docType: DocumentTypeOption) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => ({ ...prev, [`mandatory-${docType.value}`]: false }));

            const file = e.dataTransfer.files[0];
            if (file && validateFile(file)) {
                const updatedFiles = [...data.files];
                const updatedDocTypes = [...data.document_types];

                const existingIndex = updatedDocTypes.indexOf(docType.value);

                if (existingIndex >= 0) {
                    updatedFiles[existingIndex] = file;
                } else {
                    updatedFiles.push(file);
                    updatedDocTypes.push(docType.value);
                }

                setData('files', updatedFiles);
                setData('document_types', updatedDocTypes);
            }
        },
        [data.files, data.document_types, validateFile, setData, setDragStates],
    );

    const handleRemoveMandatoryFile = useCallback(
        (docType: DocumentTypeOption) => {
            const existingIndex = data.document_types.indexOf(docType.value);
            if (existingIndex >= 0) {
                const updatedFiles = [...data.files];
                const updatedDocTypes = [...data.document_types];
                updatedFiles.splice(existingIndex, 1);
                updatedDocTypes.splice(existingIndex, 1);
                setData('files', updatedFiles);
                setData('document_types', updatedDocTypes);
            }
        },
        [data.files, data.document_types, setData],
    );

    const uploadedCount = useMemo(() => {
        return mandatoryDocTypes.filter((docType) => getMandatoryFile(docType.value) !== null)
            .length;
    }, [mandatoryDocTypes, getMandatoryFile]);

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <Card className="border-2 border-primary/20 bg-primary/5">
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                        <div className="rounded-lg bg-primary/10 p-3">
                            <Upload className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                        </div>
                        <div className="flex-1">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <h3 className="text-base font-semibold sm:text-lg">Required Documents</h3>
                                <Badge
                                    variant={
                                        uploadedCount === mandatoryDocTypes.length
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className="w-fit"
                                >
                                    {uploadedCount} / {mandatoryDocTypes.length} Uploaded
                                </Badge>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                Upload all mandatory documents required by RA 9184. All documents must
                                be in PDF format.
                            </p>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Document Upload Areas */}
            <div className="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                {mandatoryDocTypes.map((docType) => {
                    const file = getMandatoryFile(docType.value);
                    const docId = `mandatory-${docType.value}`;
                    const isDragging = dragStates[docId] || false;

                    return (
                        <Card key={docType.value}>
                            <CardContent className="p-4 pt-4 sm:p-6 sm:pt-6">
                                <div className="mb-3 flex items-start gap-3">
                                    <FileText className="mt-0.5 h-5 w-5 text-primary" />
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <h4 className="font-medium">{docType.label}</h4>
                                            <Badge variant="destructive" className="text-xs">
                                                Required
                                            </Badge>
                                            {file && (
                                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {docType.description}
                                        </p>
                                        {docType.requirement_summary && (
                                            <p className="mt-1 text-xs text-muted-foreground italic">
                                                {docType.requirement_summary}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <FileUploadArea
                                    label=""
                                    file={file}
                                    error={
                                        hasError(`files.${data.document_types.indexOf(docType.value)}`)
                                            ? errors[
                                                  `files.${data.document_types.indexOf(docType.value)}`
                                              ]
                                            : undefined
                                    }
                                    isDragging={isDragging}
                                    onFileChange={(e) => handleMandatoryFileChange(e, docType)}
                                    onDragEnter={(e) => handleDragEnter(e, docId)}
                                    onDragLeave={(e) => handleDragLeave(e, docId)}
                                    onDragOver={handleDragOver}
                                    onDrop={(e) => handleMandatoryDrop(e, docType)}
                                    onRemove={() => handleRemoveMandatoryFile(docType)}
                                    inputId={`file-${docType.value}`}
                                    required
                                />
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            {/* Progress Info */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>RA 9184:</strong> All documents must be uploaded before proceeding to the next step. Ensure files are complete and accurate.
                </AlertDescription>
            </Alert>
        </div>
    );
}
