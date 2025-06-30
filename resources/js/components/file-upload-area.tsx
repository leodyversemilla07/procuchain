import React from 'react';
import { Button } from '@/components/ui/button';
import { FileText, FileUp, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';

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
}) => (
    <div className="space-y-2">
        <Label className="flex items-center text-base font-medium">
            <FileText className="h-4 w-4 mr-2" />
            {label}
            {required && <span className="text-destructive ml-1">*</span>}
        </Label>
        <div
            className={`relative border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDragging
                ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                : file
                    ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                    : error
                        ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                        : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                } cursor-pointer group`}
            onDragEnter={onDragEnter}
            onDragLeave={onDragLeave}
            onDragOver={onDragOver}
            onDrop={onDrop}
            onClick={() => document.getElementById(inputId)?.click()}
        >
            {!file ? (
                <div className="flex flex-col items-center justify-center text-center">
                    <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                        <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                    </div>
                    <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                        Drag and drop your file here
                    </p>
                    <p className="text-sm text-muted-foreground/70 mb-5">
                        Only PDF files are supported
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="group-hover:bg-primary/5 transition-colors"
                        onClick={e => {
                            e.stopPropagation();
                            document.getElementById(inputId)?.click();
                        }}
                    >
                        Browse Files
                    </Button>
                    <Input
                        id={inputId}
                        type="file"
                        accept={accept}
                        className="hidden"
                        onChange={onFileChange}
                    />
                </div>
            ) : (
                <div className="flex items-center justify-between">
                    <div className="flex items-center">
                        <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <p className="font-medium">{file.name}</p>
                            <p className="text-sm text-muted-foreground">
                                {(file.size / 1024).toFixed(2)} KB • PDF
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="rounded-full transition-colors self-end sm:self-auto flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-destructive/60 focus:ring-offset-2 hover:bg-destructive hover:text-white dark:hover:bg-destructive dark:hover:text-white cursor-pointer"
                        onClick={e => {
                            e.stopPropagation();
                            onRemove();
                        }}
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>
            )}
        </div>
        {error && <InputError message={error} />}
    </div>
);

export default FileUploadArea;
