import React, { useCallback, useMemo } from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { FileText, Plus, X, CheckCircle2, Info } from 'lucide-react';
import FileUploadArea from '@/components/file-upload-area';
import type { DocumentTypeOption, OptionalDocument } from '../types';

interface OptionalDocumentsStepProps {
    documentTypes: DocumentTypeOption[];
    optionalDocuments: OptionalDocument[];
    setOptionalDocuments: React.Dispatch<React.SetStateAction<OptionalDocument[]>>;
    dragStates: Record<string, boolean>;
    setDragStates: React.Dispatch<React.SetStateAction<Record<string, boolean>>>;
    clearErrors: (...fields: never[]) => void;
}

export function OptionalDocumentsStep({
    documentTypes,
    optionalDocuments,
    setOptionalDocuments,
    dragStates,
    setDragStates,
    clearErrors,
}: OptionalDocumentsStepProps) {
    const optionalDocTypes = useMemo(() => {
        return documentTypes.filter((dt) => !dt.is_mandatory);
    }, [documentTypes]);

    const availableOptionalDocTypes = useMemo(() => {
        const addedOptionalTypes = optionalDocuments.map((doc) => doc.document_type);
        return optionalDocTypes.filter((docType) => !addedOptionalTypes.includes(docType.value));
    }, [optionalDocTypes, optionalDocuments]);

    const uploadedOptionalCount = useMemo(() => {
        return optionalDocuments.filter((doc) => doc.file !== null).length;
    }, [optionalDocuments]);

    const [selectedDocType, setSelectedDocType] = React.useState<string>('');

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

    const addOptionalDocument = useCallback(() => {
        if (!selectedDocType) return;

        const newOptDoc: OptionalDocument = {
            id: `opt-${Date.now()}-${Math.random()}`,
            document_type: selectedDocType,
            file: null,
        };
        setOptionalDocuments((prev) => [...prev, newOptDoc]);
        setSelectedDocType('');
    }, [selectedDocType, setOptionalDocuments]);

    const removeOptionalDocument = useCallback(
        (optionalDocId: string) => {
            setOptionalDocuments((prev) => prev.filter((doc) => doc.id !== optionalDocId));
        },
        [setOptionalDocuments],
    );

    const handleOptionalFileChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>, optionalDocId: string) => {
            clearErrors();
            const newFile = e.target.files?.[0] || null;

            if (newFile && !validateFile(newFile)) {
                e.target.value = '';
                return;
            }

            setOptionalDocuments((prev) =>
                prev.map((doc) => (doc.id === optionalDocId ? { ...doc, file: newFile } : doc)),
            );
        },
        [validateFile, clearErrors, setOptionalDocuments],
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

    const handleOptionalDrop = useCallback(
        (e: React.DragEvent, optionalDocId: string) => {
            e.preventDefault();
            e.stopPropagation();
            setDragStates((prev) => ({ ...prev, [`optional-${optionalDocId}`]: false }));

            const file = e.dataTransfer.files[0];
            if (file && validateFile(file)) {
                setOptionalDocuments((prev) =>
                    prev.map((doc) => (doc.id === optionalDocId ? { ...doc, file } : doc)),
                );
            }
        },
        [validateFile, setDragStates, setOptionalDocuments],
    );

    const handleRemoveOptionalFile = useCallback(
        (optionalDocId: string) => {
            setOptionalDocuments((prev) =>
                prev.map((doc) => (doc.id === optionalDocId ? { ...doc, file: null } : doc)),
            );
        },
        [setOptionalDocuments],
    );

    const getOptionalDocType = useCallback(
        (docTypeValue: string): DocumentTypeOption | undefined => {
            return optionalDocTypes.find((dt) => dt.value === docTypeValue);
        },
        [optionalDocTypes],
    );

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <Card className="border-2 border-primary/20 bg-primary/5">
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                        <div className="rounded-lg bg-primary/10 p-3">
                            <FileText className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                        </div>
                        <div className="flex-1">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <h3 className="text-base font-semibold sm:text-lg">Optional Supporting Documents</h3>
                                <Badge variant="outline" className="w-fit">
                                    {uploadedOptionalCount} / {optionalDocuments.length} Uploaded
                                </Badge>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                Add any additional supporting documents that may strengthen your
                                procurement request.
                            </p>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Add Optional Document & Document Upload Areas */}
            <div className="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                {/* Add Optional Document Selector */}
                {availableOptionalDocTypes.length > 0 && (
                    <Card>
                        <CardContent className="p-4 pt-4 sm:p-6 sm:pt-6">
                            <div className="flex flex-col gap-3">
                                <div className="flex-1">
                                    <Select value={selectedDocType} onValueChange={setSelectedDocType}>
                                        <SelectTrigger className="h-auto min-h-10">
                                            <SelectValue placeholder="Select document type to add" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableOptionalDocTypes.map((docType) => (
                                                <SelectItem key={docType.value} value={docType.value} className="py-3">
                                                    <div className="flex flex-col gap-1">
                                                        <span className="font-medium">
                                                            {docType.label}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground line-clamp-2">
                                                            {docType.description}
                                                        </span>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button
                                    type="button"
                                    onClick={addOptionalDocument}
                                    disabled={!selectedDocType}
                                    className="w-full gap-2"
                                >
                                    <Plus className="h-4 w-4" />
                                    Add Document
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Existing Optional Documents */}
                {optionalDocuments.map((optDoc) => {
                        const docType = getOptionalDocType(optDoc.document_type);
                        if (!docType) return null;

                        const docId = `optional-${optDoc.id}`;
                        const isDragging = dragStates[docId] || false;

                        return (
                            <Card key={optDoc.id}>
                                <CardContent className="p-4 pt-4 sm:p-6 sm:pt-6">
                                    <div className="mb-3 flex items-start justify-between gap-3">
                                        <div className="flex items-start gap-3">
                                            <FileText className="mt-0.5 h-5 w-5 text-primary" />
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <h4 className="font-medium">{docType.label}</h4>
                                                    <Badge variant="outline" className="text-xs">
                                                        Optional
                                                    </Badge>
                                                    {optDoc.file && (
                                                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                    )}
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {docType.description}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeOptionalDocument(optDoc.id)}
                                            className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>

                                    <FileUploadArea
                                        label=""
                                        file={optDoc.file}
                                        isDragging={isDragging}
                                        onFileChange={(e) => handleOptionalFileChange(e, optDoc.id)}
                                        onDragEnter={(e) => handleDragEnter(e, docId)}
                                        onDragLeave={(e) => handleDragLeave(e, docId)}
                                        onDragOver={handleDragOver}
                                        onDrop={(e) => handleOptionalDrop(e, optDoc.id)}
                                        onRemove={() => handleRemoveOptionalFile(optDoc.id)}
                                        inputId={`file-optional-${optDoc.id}`}
                                        required={false}
                                    />
                                </CardContent>
                            </Card>
                        );
                    })}

                {/* Empty State - Show when no documents and selector hidden */}
                {optionalDocuments.length === 0 && availableOptionalDocTypes.length === 0 && (
                    <Card className="md:col-span-2 lg:col-span-3">
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <FileText className="mb-3 h-12 w-12 text-muted-foreground/50" />
                            <p className="text-sm font-medium text-muted-foreground">
                                All optional document types have been added
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Optional:</strong> While not required, additional documents can help expedite the procurement review process.
                </AlertDescription>
            </Alert>
        </div>
    );
}
