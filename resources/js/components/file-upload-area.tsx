import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
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
        {label && (
            <FieldLabel htmlFor={inputId} className={labelClassName}>
                {label}
            </FieldLabel>
        )}
        <div
            className={cn(
                'group relative flex min-h-[120px] cursor-pointer flex-col justify-center rounded-lg border-2 border-dashed p-4 transition-all duration-200',
                isDragging && 'border-primary bg-primary/5 scale-[1.01] shadow-sm',
                !isDragging && file && 'border-green-500/50 bg-green-50/50 dark:bg-green-900/10',
                !isDragging && !file && error && 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10',
                !isDragging && !file && !error && 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/30',
            )}
            onDragEnter={onDragEnter}
            onDragLeave={onDragLeave}
            onDragOver={onDragOver}
            onDrop={onDrop}
            onClick={() => document.getElementById(inputId)?.click()}
        >
            {!file ? (
                <Empty className="min-h-0 gap-3 border-0 p-4">
                    <EmptyMedia variant="icon">
                        <FileUp className="h-5 w-5" />
                    </EmptyMedia>
                    <div className="flex flex-col gap-1">
                        <EmptyTitle className="text-sm">Drop file or click to browse</EmptyTitle>
                        <EmptyDescription className="text-xs">PDF only, max 10MB</EmptyDescription>
                    </div>
                    <Input id={inputId} type="file" accept={accept} className="hidden" onChange={onFileChange} required={required} />
                </Empty>
            ) : (
                <div className="flex items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                        <FileText className="h-8 w-8 shrink-0 text-green-600 dark:text-green-500" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">{file.name}</p>
                            <p className="text-muted-foreground text-xs">{(file.size / 1024 / 1024).toFixed(2)} MB</p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive hover:bg-destructive/10 h-8 w-8 shrink-0 p-0"
                        onClick={(e) => {
                            e.stopPropagation();
                            onRemove();
                        }}
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>
            )}
        </div>
        {error && <FieldError>{error}</FieldError>}
    </Field>
);

export default FileUploadArea;
