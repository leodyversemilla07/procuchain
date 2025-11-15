import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { correct } from '@/routes/documents';
import { router } from '@inertiajs/react';
import { AlertTriangle, FileText, Upload } from 'lucide-react';
import { useState } from 'react';

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
    const [correctionType, setCorrectionType] = useState<'replace' | 'invalidate'>('replace');
    const [correctionReason, setCorrectionReason] = useState('');
    const [correctedFile, setCorrectedFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const formData = new FormData();
        formData.append('correction_reason', correctionReason);
        formData.append('correction_type', correctionType);
        formData.append('pr_number', pr_number.toString());
        formData.append('procurement_title', procurementTitle);
        formData.append('original_document_hash', originalDocumentHash);
        if (originalTxid) {
            formData.append('original_txid', originalTxid);
        }

        if (correctionType === 'replace' && correctedFile) {
            formData.append('corrected_file', correctedFile);
        }

        router.post(correct.url(typeof documentId === 'string' ? parseInt(documentId) : documentId), formData, {
            preserveScroll: true,
            onSuccess: () => {
                setCorrectionReason('');
                setCorrectedFile(null);
                onOpenChange(false);
            },
            onError: (responseErrors) => {
                setErrors(responseErrors);
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setCorrectedFile(e.target.files[0]);
        }
    };

    const handleCancel = () => {
        setCorrectionReason('');
        setCorrectedFile(null);
        setErrors({});
        onOpenChange(false);
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

                <form onSubmit={handleSubmit} className="grid flex-1 auto-rows-min gap-6 px-4 py-6">
                    {/* Correction Type */}
                    <div className="grid gap-3">
                        <Label className="text-base font-semibold">Correction Type</Label>
                        <RadioGroup
                            value={correctionType}
                            onValueChange={(value) => {
                                setCorrectionType(value as 'replace' | 'invalidate');
                            }}
                            className="grid gap-3"
                        >
                            <div className="hover:bg-accent flex items-start gap-3 rounded-lg border p-3 transition-colors sm:items-center sm:p-4">
                                <RadioGroupItem value="replace" id="replace" className="mt-1 sm:mt-0" />
                                <Label htmlFor="replace" className="flex-1 cursor-pointer">
                                    <div className="text-sm font-semibold sm:text-base">Replace Document</div>
                                    <div className="text-muted-foreground text-xs sm:text-sm">Upload a corrected version of the document</div>
                                </Label>
                            </div>
                            <div className="hover:bg-accent flex items-start gap-3 rounded-lg border p-3 transition-colors sm:items-center sm:p-4">
                                <RadioGroupItem value="invalidate" id="invalidate" className="mt-1 sm:mt-0" />
                                <Label htmlFor="invalidate" className="flex-1 cursor-pointer">
                                    <div className="text-sm font-semibold sm:text-base">Invalidate Document</div>
                                    <div className="text-muted-foreground text-xs sm:text-sm">Mark the document as invalid without replacement</div>
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* Reason for Correction */}
                    <div className="grid gap-3">
                        <Label htmlFor="correction_reason" className="text-base font-semibold">
                            Reason for Correction <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="correction_reason"
                            placeholder="Explain why this document needs to be corrected..."
                            value={correctionReason}
                            onChange={(e) => setCorrectionReason(e.target.value)}
                            rows={4}
                            className={errors.correction_reason ? 'border-destructive' : ''}
                        />
                        {errors.correction_reason && <p className="text-destructive text-sm">{errors.correction_reason}</p>}
                    </div>

                    {/* File Upload (only for replacement) */}
                    {correctionType === 'replace' && (
                        <div className="grid gap-3">
                            <Label htmlFor="corrected_file" className="text-base font-semibold">
                                Corrected Document <span className="text-destructive">*</span>
                            </Label>
                            <div className="hover:bg-accent rounded-lg border-2 border-dashed p-6 text-center transition-colors sm:p-8">
                                <input
                                    type="file"
                                    id="corrected_file"
                                    onChange={handleFileChange}
                                    className="hidden"
                                    accept=".pdf,application/pdf"
                                />
                                <Label htmlFor="corrected_file" className="cursor-pointer">
                                    <Upload className="text-muted-foreground mx-auto mb-3 h-10 w-10 sm:h-12 sm:w-12" />
                                    <p className="text-xs font-medium sm:text-sm">
                                        {correctedFile ? (
                                            <span className="break-all">{correctedFile.name}</span>
                                        ) : (
                                            'Click to upload corrected document'
                                        )}
                                    </p>
                                    <p className="text-muted-foreground mt-2 text-xs">PDF only (max 10MB)</p>
                                </Label>
                            </div>
                            {errors.corrected_file && <p className="text-destructive text-sm">{errors.corrected_file}</p>}
                        </div>
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
                </form>

                <SheetFooter className="flex-col gap-2 sm:flex-row">
                    <Button type="button" variant="outline" onClick={handleCancel} disabled={processing} className="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button 
                        type="submit" 
                        onClick={handleSubmit}
                        disabled={processing || !correctionReason || (correctionType === 'replace' && !correctedFile)}
                        className="w-full sm:w-auto"
                    >
                        {processing ? 'Submitting...' : 'Submit Correction'}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
