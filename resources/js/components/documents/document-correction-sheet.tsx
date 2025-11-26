import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field, FieldContent, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import FileUploadArea from '@/components/file-upload-area';
import { correctDocument } from '@/actions/App/Http/Controllers/DocumentCorrectionController';
import { Form, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, FileText } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

interface DocumentCorrectionSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentId: number | string;
    pr_number: number | string;
    procurementTitle: string;
    originalDocumentHash: string;
    originalTxid?: string;
}

export function DocumentCorrectionSheet({
    open,
    onOpenChange,
    documentId,
    pr_number,
    procurementTitle,
    originalDocumentHash,
    originalTxid,
}: DocumentCorrectionSheetProps) {
    const { props } = usePage();
    const [correctedFile, setCorrectedFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const formRef = useRef<ReturnType<typeof useForm>>(null);

    // Initialize Inertia form with initial values
    const form = useForm({
        correction_reason: '',
        correction_type: 'replace' as 'replace' | 'invalidate',
        pr_number: pr_number.toString(),
        procurement_title: procurementTitle,
        original_document_hash: originalDocumentHash,
        original_txid: originalTxid || '',
        corrected_file: null as File | null,
    });

    // Store form reference to avoid dependency issues
    useEffect(() => {
        formRef.current = form;
    }, [form]);

    // Sync correctedFile state with form data
    useEffect(() => {
        if (formRef.current) {
            formRef.current.setData('corrected_file', correctedFile);
        }
    }, [correctedFile]);

    // Handle flash messages from backend
    const handleFlashMessages = useCallback(() => {
        const flash = props.flash as Record<string, unknown> | undefined;
        if (flash?.success) {
            const message = flash.success as string;
            toast.success(message, {
                description: 'The correction has been submitted to the blockchain.',
                onAutoClose: () => {
                    onOpenChange(false);
                },
            });
            // Also close after a delay as backup
            setTimeout(() => {
                onOpenChange(false);
            }, 3000);
        }
        if (flash?.error) {
            const message = flash.error as string;
            toast.error(message, {
                description: 'Please check the form and try again.',
            });
        }
    }, [props.flash, onOpenChange]);

    useEffect(() => {
        handleFlashMessages();
    }, [handleFlashMessages]);

    const handleSubmit = () => {
        // Submit using Wayfinder object directly
        form.submit(correctDocument(documentId), {
            preserveScroll: true,
            onSuccess: () => {
                // Reset form on success
                form.reset();
                setCorrectedFile(null);
                // Reset the input value
                const fileInput = document.getElementById('corrected_file') as HTMLInputElement;
                if (fileInput) {
                    fileInput.value = '';
                }
                // Don't close the sheet immediately - let the flash message handler show the toast
            },
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setCorrectedFile(e.target.files[0]);
        }
    };

    const handleRemoveFile = () => {
        setCorrectedFile(null);
        // Reset the input value so the same file can be selected again if needed
        const fileInput = document.getElementById('corrected_file') as HTMLInputElement;
        if (fileInput) {
            fileInput.value = '';
        }
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);

        const files = e.dataTransfer.files;
        if (files && files[0]) {
            setCorrectedFile(files[0]);
        }
    };

    const handleCancel = () => {
        form.reset();
        setCorrectedFile(null);
        onOpenChange(false);
        // Reset the input value
        const fileInput = document.getElementById('corrected_file') as HTMLInputElement;
        if (fileInput) {
            fileInput.value = '';
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-xl lg:max-w-2xl">
                <SheetHeader>
                    <SheetTitle className="flex items-center gap-2 text-lg sm:text-xl">
                        <AlertTriangle className="h-5 w-5 text-amber-600" />
                        Correct Document
                    </SheetTitle>
                    <SheetDescription className="text-sm">
                        Submit a correction for this document. The original will remain on the blockchain for audit trail purposes.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    as="div"
                    className="grid flex-1 auto-rows-min gap-6 px-4 py-6"
                >
                    {/* Correction Type */}
                    <Field>
                        <FieldLabel className="text-base font-semibold">Correction Type</FieldLabel>
                        <FieldContent>
                            <RadioGroup
                                value={form.data.correction_type}
                                onValueChange={(value) => {
                                    form.setData('correction_type', value as 'replace' | 'invalidate');
                                }}
                                className="grid gap-3"
                            >
                                <div className="hover:bg-accent flex items-start gap-3 rounded-lg border p-3 transition-colors sm:items-center sm:p-4">
                                    <RadioGroupItem value="replace" id="replace" className="mt-1 sm:mt-0" />
                                    <FieldLabel htmlFor="replace" className="flex-1 cursor-pointer">
                                        <div className="text-sm font-semibold sm:text-base">Replace Document</div>
                                        <FieldDescription className="text-xs sm:text-sm">Upload a corrected version of the document</FieldDescription>
                                    </FieldLabel>
                                </div>
                                <div className="hover:bg-accent flex items-start gap-3 rounded-lg border p-3 transition-colors sm:items-center sm:p-4">
                                    <RadioGroupItem value="invalidate" id="invalidate" className="mt-1 sm:mt-0" />
                                    <FieldLabel htmlFor="invalidate" className="flex-1 cursor-pointer">
                                        <div className="text-sm font-semibold sm:text-base">Invalidate Document</div>
                                        <FieldDescription className="text-xs sm:text-sm">Mark the document as invalid without replacement</FieldDescription>
                                    </FieldLabel>
                                </div>
                            </RadioGroup>
                        </FieldContent>
                    </Field>

                    {/* Reason for Correction */}
                    <Field>
                        <FieldLabel htmlFor="correction_reason" className="text-base font-semibold">
                            Reason for Correction <span className="text-destructive">*</span>
                        </FieldLabel>
                        <FieldContent>
                            <Textarea
                                id="correction_reason"
                                placeholder="Explain why this document needs to be corrected..."
                                value={form.data.correction_reason}
                                onChange={(e) => form.setData('correction_reason', e.target.value)}
                                rows={4}
                                className={form.errors.correction_reason ? 'border-destructive' : ''}
                            />
                            <FieldError>{form.errors.correction_reason}</FieldError>
                        </FieldContent>
                    </Field>

                    {/* File Upload (only for replacement) */}
                    {form.data.correction_type === 'replace' && (
                        <Field>
                            <FieldLabel htmlFor="corrected_file" className="text-base font-semibold">
                                Corrected Document <span className="text-destructive">*</span>
                            </FieldLabel>
                            <FieldContent>
                                <FileUploadArea
                                    label=""
                                    file={correctedFile}
                                    error={form.errors.corrected_file}
                                    isDragging={isDragging}
                                    onFileChange={handleFileChange}
                                    onDragEnter={handleDragEnter}
                                    onDragLeave={handleDragLeave}
                                    onDragOver={handleDragOver}
                                    onDrop={handleDrop}
                                    onRemove={handleRemoveFile}
                                    inputId="corrected_file"
                                    accept=".pdf,application/pdf"
                                    required
                                />
                            </FieldContent>
                        </Field>
                    )}

                    {/* Information Alert */}
                    <Alert className="border-primary/20 bg-primary/5">
                        <FileText className="h-4 w-4" />
                        <AlertDescription className="text-sm">
                            <strong>Blockchain Immutability:</strong> The original document and this correction will both be permanently recorded on
                            the blockchain. This maintains a complete audit trail while allowing you to correct mistakes.
                        </AlertDescription>
                    </Alert>

                    {/* Document Info */}
                    <div className="bg-muted grid gap-2 rounded-lg p-3 text-sm sm:p-4">
                        <div className="font-semibold">Document Information</div>
                        <div className="grid gap-1.5">
                            <div>
                                <strong>Procurement:</strong> <span className="wrap-break-word">{procurementTitle}</span>
                            </div>
                            <div>
                                <strong>Original Hash:</strong>
                                <span className="ml-2 block font-mono text-xs break-all sm:inline">{originalDocumentHash.substring(0, 32)}...</span>
                            </div>
                            {originalTxid && (
                                <div>
                                    <strong>Original TXID:</strong>
                                    <span className="ml-2 block font-mono text-xs break-all sm:inline">{originalTxid.substring(0, 32)}...</span>
                                </div>
                            )}
                        </div>
                    </div>
                </Form>

                <SheetFooter className="flex-col gap-2 sm:flex-row">
                    <Button type="button" variant="outline" onClick={handleCancel} disabled={form.processing} className="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={form.processing || !form.data.correction_reason || (form.data.correction_type === 'replace' && !form.data.corrected_file)}
                        className="w-full sm:w-auto"
                    >
                        {form.processing ? 'Submitting...' : 'Submit Correction'}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
