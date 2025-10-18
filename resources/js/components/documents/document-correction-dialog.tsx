import { AlertTriangle, FileText, Upload } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface DocumentCorrectionDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentId: number;
    procurementId: number;
    procurementTitle: string;
    originalDocumentHash: string;
    originalTxid?: string;
}

export function DocumentCorrectionDialog({
    open,
    onOpenChange,
    documentId,
    procurementId,
    procurementTitle,
    originalDocumentHash,
    originalTxid,
}: DocumentCorrectionDialogProps) {
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
        formData.append('procurement_id', procurementId.toString());
        formData.append('procurement_title', procurementTitle);
        formData.append('original_document_hash', originalDocumentHash);
        if (originalTxid) {
            formData.append('original_txid', originalTxid);
        }

        if (correctionType === 'replace' && correctedFile) {
            formData.append('corrected_file', correctedFile);
        }

        router.post(route('documents.correct', documentId), formData, {
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
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-amber-600" />
                        Correct Document
                    </DialogTitle>
                    <DialogDescription>
                        Submit a correction for this document. The original will remain on the blockchain for
                        audit trail purposes.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Correction Type */}
                    <div className="space-y-3">
                        <Label>Correction Type</Label>
                        <RadioGroup
                            value={correctionType}
                            onValueChange={(value) => {
                                setCorrectionType(value as 'replace' | 'invalidate');
                            }}
                        >
                            <div className="flex items-center space-x-2 p-3 border rounded-lg hover:bg-accent">
                                <RadioGroupItem value="replace" id="replace" />
                                <Label htmlFor="replace" className="flex-1 cursor-pointer">
                                    <div className="font-semibold">Replace Document</div>
                                    <div className="text-sm text-muted-foreground">
                                        Upload a corrected version of the document
                                    </div>
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2 p-3 border rounded-lg hover:bg-accent">
                                <RadioGroupItem value="invalidate" id="invalidate" />
                                <Label htmlFor="invalidate" className="flex-1 cursor-pointer">
                                    <div className="font-semibold">Invalidate Document</div>
                                    <div className="text-sm text-muted-foreground">
                                        Mark the document as invalid without replacement
                                    </div>
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* Reason for Correction */}
                    <div className="space-y-2">
                        <Label htmlFor="correction_reason">
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
                        {errors.correction_reason && (
                            <p className="text-sm text-destructive">{errors.correction_reason}</p>
                        )}
                    </div>

                    {/* File Upload (only for replacement) */}
                    {correctionType === 'replace' && (
                        <div className="space-y-2">
                            <Label htmlFor="corrected_file">
                                Corrected Document <span className="text-destructive">*</span>
                            </Label>
                            <div className="border-2 border-dashed rounded-lg p-6 text-center hover:bg-accent transition-colors">
                                <input
                                    type="file"
                                    id="corrected_file"
                                    onChange={handleFileChange}
                                    className="hidden"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx"
                                />
                                <Label htmlFor="corrected_file" className="cursor-pointer">
                                    <Upload className="h-10 w-10 mx-auto mb-2 text-muted-foreground" />
                                    <p className="text-sm font-medium">
                                        {correctedFile
                                            ? correctedFile.name
                                            : 'Click to upload corrected document'}
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        PDF, DOC, DOCX, XLS, XLSX (max 10MB)
                                    </p>
                                </Label>
                            </div>
                            {errors.corrected_file && (
                                <p className="text-sm text-destructive">{errors.corrected_file}</p>
                            )}
                        </div>
                    )}

                    {/* Information Alert */}
                    <Alert>
                        <FileText className="h-4 w-4" />
                        <AlertDescription className="text-sm">
                            <strong>Blockchain Immutability:</strong> The original document and this correction
                            will both be permanently recorded on the blockchain. This maintains a complete audit
                            trail while allowing you to correct mistakes.
                        </AlertDescription>
                    </Alert>

                    {/* Document Info */}
                    <div className="bg-muted p-4 rounded-lg space-y-1 text-sm">
                        <div>
                            <strong>Procurement:</strong> {procurementTitle}
                        </div>
                        <div>
                            <strong>Original Hash:</strong>
                            <span className="font-mono ml-2 text-xs">{originalDocumentHash.substring(0, 32)}...</span>
                        </div>
                        {originalTxid && (
                            <div>
                                <strong>Original TXID:</strong>
                                <span className="font-mono ml-2 text-xs">{originalTxid.substring(0, 32)}...</span>
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={handleCancel} disabled={processing}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                !correctionReason ||
                                (correctionType === 'replace' && !correctedFile)
                            }
                        >
                            {processing ? 'Submitting...' : 'Submit Correction'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
