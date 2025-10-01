import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    errorClassName?: string;
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
    errorClassName,
}) => (
    <div className="flex flex-col gap-1">
        <Label htmlFor={inputId} className={labelClassName}>
            {label}
            {required ? (
                <span className="text-destructive ml-1 align-super text-xs" aria-label="required">
                    *
                </span>
            ) : null}
        </Label>
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
                <div className="flex flex-col items-center justify-center text-center">
                    <div className="bg-muted group-hover:bg-primary/10 mb-3 rounded-full p-3 transition-colors">
                        <FileUp className="text-muted-foreground group-hover:text-primary h-6 w-6 transition-colors" />
                    </div>
                    <p className="text-muted-foreground group-hover:text-foreground mb-2 font-medium transition-colors">
                        Drag and drop your file here
                    </p>
                    <p className="text-muted-foreground/70 mb-5 text-sm">Only PDF files are supported</p>
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
                    <Input id={inputId} type="file" accept={accept} className="hidden" onChange={onFileChange} />
                </div>
            ) : (
                <div className="flex items-center justify-between">
                    <div className="flex items-center">
                        <div className="bg-primary/10 mr-4 rounded-full p-3">
                            <FileText className="text-primary h-6 w-6" />
                        </div>
                        <div>
                            <p className="font-medium">{file.name}</p>
                            <p className="text-muted-foreground text-sm">{(file.size / 1024).toFixed(2)} KB • PDF</p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="focus:ring-destructive/60 hover:bg-destructive dark:hover:bg-destructive flex-shrink-0 cursor-pointer self-end rounded-full transition-colors hover:text-white focus:ring-2 focus:ring-offset-2 focus:outline-none sm:self-auto dark:hover:text-white"
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
        {error && <InputError message={error} className={errorClassName} />}
    </div>
);

export default FileUploadArea;
