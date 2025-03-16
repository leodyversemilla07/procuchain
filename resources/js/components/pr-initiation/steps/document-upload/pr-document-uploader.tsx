import React, { useCallback } from 'react';
import { Upload, FileX, FileCheck, FileText, Info } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { formatBytes, isPdfFile, MAX_FILE_SIZE } from '@/lib/utils';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface PRDocumentUploaderProps {
    documentFile: File | null;
    onChange: (file: File | null) => void;
    errors: Record<string, string>;
    hasError: (field: string) => boolean;
    isDragging: boolean;
    onDragEnter: (e: React.DragEvent) => void;
    onDragLeave: (e: React.DragEvent) => void;
    onDragOver: (e: React.DragEvent) => void;
    onDrop: (e: React.DragEvent) => void;
}

/**
 * Component for handling PR document file uploads with drag-and-drop support
 */
export function PRDocumentUploader({
    documentFile,
    onChange,
    errors,
    hasError,
    isDragging,
    onDragEnter,
    onDragLeave,
    onDragOver,
    onDrop
}: PRDocumentUploaderProps) {
    const handlePRFileChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        try {
            const file: File | undefined = e.target.files?.[0];

            if (!file) {
                onChange(null);
                return;
            }

            if (file.size > MAX_FILE_SIZE) {
                // We let the parent component handle the error state
                // This just prevents setting the file
                return;
            }

            onChange(file);
        } catch (error) {
            console.error("Error handling file:", error);
        }
    }, [onChange]);

    const handleRemoveFile = useCallback(() => {
        onChange(null);
    }, [onChange]);

    const hasUploadedFile = documentFile !== null;

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <div className="flex items-center">
                    <FileText className="h-5 w-5 mr-2 text-primary" />
                    <Label htmlFor="pr_file" className="font-medium">
                        Purchase Request Document
                        <Badge variant="outline" className="ml-2 text-xs">Required</Badge>
                    </Label>
                </div>

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-full">
                                <Info className="h-4 w-4 text-primary" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Upload a PDF file up to 10MB</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            {hasError('pr_file') && (
                <Alert variant="destructive">
                    <AlertDescription>
                        {errors.pr_file}
                    </AlertDescription>
                </Alert>
            )}

            <div className={`relative border-2 border-dashed rounded-lg p-6 transition-all 
            ${isDragging
                    ? 'border-primary bg-primary/5'
                    : hasError('pr_file')
                        ? 'border-destructive bg-destructive/5'
                        : hasUploadedFile && isPdfFile(documentFile)
                            ? 'border-primary/50 bg-primary/5'
                            : hasUploadedFile && !isPdfFile(documentFile)
                                ? 'border-destructive bg-destructive/5'
                                : 'border-muted hover:border-primary/50 hover:bg-primary/5'
                }
          text-center cursor-pointer group`}


                onDragEnter={onDragEnter}
                onDragLeave={onDragLeave}
                onDragOver={onDragOver}
                onDrop={onDrop}
            >
                <Input
                    type="file"
                    id="pr_file"
                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                    onChange={handlePRFileChange}
                    accept="application/pdf,.pdf"
                />

                {hasUploadedFile ? (
                    <FilePreview file={documentFile} onRemove={handleRemoveFile} />
                ) : (
                    <UploadPrompt isDragging={isDragging} />
                )}
            </div>
        </div>
    );
}

interface FilePreviewProps {
    file: File | null;
    onRemove: () => void;
}

/**
 * Displays file preview after upload with status indicator
 */
function FilePreview({ file, onRemove }: FilePreviewProps) {
    const isValidPdf = isPdfFile(file);

    return (
        <div className="space-y-5">
            <div
                className={`mx-auto w-24 h-24 ${isValidPdf
                    ? 'bg-gradient-to-br from-green-100 via-green-50 to-emerald-100 dark:from-green-900/40 dark:via-green-800/30 dark:to-emerald-900/40'
                    : 'bg-gradient-to-br from-orange-100 via-orange-50 to-red-100 dark:from-orange-900/40 dark:via-orange-800/30 dark:to-red-900/40'
                    } rounded-full flex items-center justify-center shadow-lg`}
            >
                {isValidPdf ? (
                    <FileCheck className="h-12 w-12 text-green-600 dark:text-green-400" />
                ) : (
                    <FileX className="h-12 w-12 text-orange-600 dark:text-orange-400" />
                )}
            </div>
            <div>
                <h4 className="text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-100 truncate leading-relaxed">
                    {file?.name}
                </h4>
                <p className="text-base font-normal tracking-wide text-gray-600 dark:text-gray-400 mt-2.5 leading-relaxed">
                    {formatBytes(file?.size || 0)} • {file?.type || 'Unknown type'}
                </p>


                {isValidPdf && (
                    <p className="text-sm font-medium tracking-wide text-green-600 dark:text-green-400 mt-4 bg-green-50 dark:bg-green-900/30 py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <FileCheck className="h-4 w-4 mr-2 flex-shrink-0" />
                        <span>PDF file successfully uploaded</span>
                    </p>
                )}

                {!isValidPdf && (
                    <p className="text-sm font-medium tracking-wide text-orange-600 dark:text-orange-400 mt-4 bg-orange-50 dark:bg-orange-900/30 py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                        <FileX className="h-4 w-4 mr-2 flex-shrink-0" />
                        <span>Only PDF files are accepted</span>
                    </p>
                )}


                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 mt-5 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30 transition-all duration-300 text-sm font-medium px-4 py-2 rounded-lg shadow-sm"
                        onClick={onRemove}
                    >
                        <FileX className="h-4 w-4 mr-2" />
                        <span>Remove File</span>
                    </Button>
                </div>
            </div>
        </div>
    );
}

interface UploadPromptProps {
    isDragging: boolean;
}

/**
 * Displays the drag-and-drop upload prompt when no file is selected
 */
function UploadPrompt({ isDragging }: UploadPromptProps) {
    return (
        <div className="space-y-7">
            <div className="mx-auto w-24 h-24 bg-gradient-to-br from-gray-100 via-blue-50/80 to-gray-100 dark:from-gray-800 dark:via-blue-900/30 dark:to-gray-800 rounded-full flex items-center justify-center shadow-md">
                <Upload className="h-12 w-12 text-gray-500 dark:text-gray-400 group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors duration-300" />
            </div>
            <div>
                <h4 className="text-xl font-medium text-gray-800 dark:text-gray-200 tracking-tight leading-relaxed">
                    {isDragging ? 'Drop the PDF file here' : 'Drag and drop your PR document here'}
                </h4>
                <p className="text-base font-normal text-gray-600 dark:text-gray-400 mt-3 leading-relaxed">
                    Or <span className="text-blue-600 dark:text-blue-400 font-medium underline underline-offset-4 decoration-2 hover:text-blue-700 dark:hover:text-blue-300 cursor-pointer transition-colors duration-200">browse</span> to select a file
                </p>
                <div className="inline-flex items-center mt-6 px-4 py-2.5 bg-gray-100/90 dark:bg-gray-800/90 rounded-lg text-sm font-medium tracking-wide text-gray-600 dark:text-gray-400 border border-gray-200/90 dark:border-gray-700/90 leading-relaxed shadow-sm">
                    <svg viewBox="0 0 24 24" className="w-4.5 h-4.5 mr-2 text-red-500 dark:text-red-400 flex-shrink-0">
                        <path fill="currentColor" d="M12,10.5H13V13.5H16V14.5H13V17.5H12V14.5H9V13.5H12V10.5M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                    </svg>
                    PDF files only (up to 10MB)
                </div>
            </div>
        </div>
    );
}
