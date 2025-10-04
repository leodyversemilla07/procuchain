import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { FileText, FileUp, X } from 'lucide-react';
import React from 'react';

interface FileUploadAreaProps {
    label: string;
    file: File | null;
    error?: string;
    isDragging: boolean;
    onFileChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    onDragEnter: (e: React.DragEvent) => void;
    onDragLeave: (e: React.DragEvent) => void;
    onDragOver: (e: React.DragEvent) => void;
    onDrop: (e: React.DragEvent) => void;
    onRemove: () => void;
    inputId: string;
    accept?: string;
    required?: boolean;
    labelClassName?: string;
}

const FileUploadArea: React.FC<FileUploadAreaProps> = ({
    label,
    file,
    error,
    isDragging,
    onFileChange,
    onDragEnter,
    onDragLeave,
    onDragOver,
    onDrop,
    onRemove,
    inputId,
    accept = 'application/pdf',
    required = false,
    labelClassName,
}) => (
    <Field>
        <FieldLabel htmlFor={inputId} className={labelClassName}>
            {label}
            {required && (
                <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                    *
                </span>
            )}
        </FieldLabel>
        <div
            className={`relative flex min-h-[220px] flex-col justify-center rounded-lg border-2 border-dashed p-6 transition-all duration-200 ${
                isDragging
                    ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                    : file
                      ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                      : error
                        ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                        : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
            } group cursor-pointer`}
            onDragEnter={onDragEnter}
            onDragLeave={onDragLeave}
            onDragOver={onDragOver}
            onDrop={onDrop}
            onClick={() => document.getElementById(inputId)?.click()}
        >
            {!file ? (
                <Empty className="border-0 p-0">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <FileUp />
                        </EmptyMedia>
                        <EmptyTitle>Drag and drop your file here</EmptyTitle>
                        <EmptyDescription>Only PDF files are supported</EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="group-hover:bg-primary/5 transition-colors"
                            onClick={(e) => {
                                e.stopPropagation();
                                document.getElementById(inputId)?.click();
                            }}
                        >
                            Browse Files
                        </Button>
                    </EmptyContent>
                    <Input id={inputId} type="file" accept={accept} className="hidden" onChange={onFileChange} />
                </Empty>
            ) : (
                <Empty className="border-0 p-0">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <FileText />
                        </EmptyMedia>
                        <EmptyTitle>{file.name}</EmptyTitle>
                        <EmptyDescription>{(file.size / 1024 / 1024).toFixed(2)} MB • PDF</EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="hover:bg-destructive/10 hover:text-destructive hover:border-destructive/50 transition-colors"
                            onClick={(e) => {
                                e.stopPropagation();
                                onRemove();
                            }}
                        >
                            <X className="mr-2 h-4 w-4" />
                            Remove File
                        </Button>
                    </EmptyContent>
                </Empty>
            )}
        </div>
        {error && <FieldError>{error}</FieldError>}
    </Field>
);

export default FileUploadArea;
