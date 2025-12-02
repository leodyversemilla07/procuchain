import { useState } from 'react';
import { CheckCircle, FileSearch, Loader2, XCircle } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { VerificationStatus } from './verification-status';

interface VerificationDialogProps {
    prNumber: string;
    trigger?: React.ReactNode;
    onVerificationComplete?: (result: VerificationResult) => void;
}

interface VerificationResult {
    success: boolean;
    pr_number: string;
    verification_types: string[];
    results: {
        integrity?: unknown[];
        completeness?: unknown;
        cross_reference?: unknown;
        compliance?: unknown;
    };
    verified_at: string;
    verified_by: number;
}

const verificationTypes = [
    { id: 'integrity', label: 'Hash Integrity', description: 'Verify document hashes match blockchain records' },
    { id: 'completeness', label: 'Completeness', description: 'Check if all required documents are uploaded' },
    { id: 'cross_reference', label: 'Cross-Reference', description: 'Validate PR numbers and data consistency' },
    { id: 'compliance', label: 'RA 9184 Compliance', description: 'Check regulatory compliance requirements' },
];

export function VerificationDialog({ prNumber, trigger, onVerificationComplete }: VerificationDialogProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [isVerifying, setIsVerifying] = useState(false);
    const [selectedTypes, setSelectedTypes] = useState<string[]>(['integrity', 'completeness', 'cross_reference', 'compliance']);
    const [result, setResult] = useState<VerificationResult | null>(null);

    const handleToggleType = (typeId: string) => {
        setSelectedTypes((prev) =>
            prev.includes(typeId)
                ? prev.filter((t) => t !== typeId)
                : [...prev, typeId]
        );
    };

    const handleSelectAll = () => {
        if (selectedTypes.length === verificationTypes.length) {
            setSelectedTypes([]);
        } else {
            setSelectedTypes(verificationTypes.map((t) => t.id));
        }
    };

    const handleVerify = async () => {
        if (selectedTypes.length === 0) {
            toast.error('Please select at least one verification type');
            return;
        }

        setIsVerifying(true);
        setResult(null);

        try {
            const response = await fetch(`/procurement/${prNumber}/verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    verification_types: selectedTypes,
                }),
            });

            if (!response.ok) {
                throw new Error('Verification request failed');
            }

            const data: VerificationResult = await response.json();
            setResult(data);

            if (data.success) {
                toast.success('Verification completed successfully');
            } else {
                toast.warning('Verification completed with issues');
            }

            onVerificationComplete?.(data);
        } catch (error) {
            console.error('Verification error:', error);
            toast.error('Failed to complete verification');
        } finally {
            setIsVerifying(false);
        }
    };

    const getOverallStatus = (): 'verified' | 'failed' | 'pending' => {
        if (!result) return 'pending';

        // Check integrity results
        const integrityResults = result.results.integrity as Array<{ verification: { is_valid: boolean } }> | undefined;
        const integrityValid = !integrityResults || integrityResults.every((r) => r.verification?.is_valid);

        // Check other results
        const completenessValid = !result.results.completeness || (result.results.completeness as { is_complete?: boolean })?.is_complete;
        const crossRefValid = !result.results.cross_reference || (result.results.cross_reference as { is_consistent?: boolean })?.is_consistent;
        const complianceValid = !result.results.compliance || (result.results.compliance as { is_compliant?: boolean })?.is_compliant;

        if (integrityValid && completenessValid && crossRefValid && complianceValid) {
            return 'verified';
        }

        return 'failed';
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                {trigger || (
                    <Button variant="outline" size="sm">
                        <FileSearch className="mr-2 h-4 w-4" />
                        Verify Documents
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileSearch className="h-5 w-5" />
                        Document Verification
                    </DialogTitle>
                    <DialogDescription>
                        Verify documents for PR: <strong>{prNumber}</strong>
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Verification Type Selection */}
                    {!result && (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-sm font-medium">Verification Types</Label>
                                <Button variant="ghost" size="sm" onClick={handleSelectAll}>
                                    {selectedTypes.length === verificationTypes.length ? 'Deselect All' : 'Select All'}
                                </Button>
                            </div>

                            <div className="space-y-2">
                                {verificationTypes.map((type) => (
                                    <div
                                        key={type.id}
                                        className="flex items-start space-x-3 rounded-lg border p-3 hover:bg-muted/50 transition-colors"
                                    >
                                        <Checkbox
                                            id={type.id}
                                            checked={selectedTypes.includes(type.id)}
                                            onCheckedChange={() => handleToggleType(type.id)}
                                            disabled={isVerifying}
                                        />
                                        <div className="space-y-0.5">
                                            <Label htmlFor={type.id} className="text-sm font-medium cursor-pointer">
                                                {type.label}
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                {type.description}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Verification Results */}
                    {result && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-center py-4">
                                <VerificationStatus status={getOverallStatus()} size="lg" />
                            </div>

                            <div className="space-y-2">
                                {result.results.integrity ? (
                                    <ResultItem
                                        label="Integrity"
                                        isValid={(result.results.integrity as Array<{ verification: { is_valid: boolean } }>).every(
                                            (r) => r.verification?.is_valid
                                        )}
                                        count={(result.results.integrity as unknown[]).length}
                                    />
                                ) : null}

                                {result.results.completeness ? (
                                    <ResultItem
                                        label="Completeness"
                                        isValid={(result.results.completeness as { is_complete: boolean }).is_complete}
                                        percentage={(result.results.completeness as { completion_percentage: number }).completion_percentage}
                                    />
                                ) : null}

                                {result.results.cross_reference ? (
                                    <ResultItem
                                        label="Cross-Reference"
                                        isValid={(result.results.cross_reference as { is_consistent: boolean }).is_consistent}
                                    />
                                ) : null}

                                {result.results.compliance ? (
                                    <ResultItem
                                        label="RA 9184 Compliance"
                                        isValid={(result.results.compliance as { is_compliant: boolean }).is_compliant}
                                    />
                                ) : null}
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    {!result ? (
                        <>
                            <Button variant="outline" onClick={() => setIsOpen(false)} disabled={isVerifying}>
                                Cancel
                            </Button>
                            <Button onClick={handleVerify} disabled={isVerifying || selectedTypes.length === 0}>
                                {isVerifying ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Verifying...
                                    </>
                                ) : (
                                    <>
                                        <FileSearch className="mr-2 h-4 w-4" />
                                        Start Verification
                                    </>
                                )}
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button variant="outline" onClick={() => { setResult(null); }}>
                                Verify Again
                            </Button>
                            <Button onClick={() => setIsOpen(false)}>
                                Close
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

interface ResultItemProps {
    label: string;
    isValid: boolean;
    count?: number;
    percentage?: number;
}

function ResultItem({ label, isValid, count, percentage }: ResultItemProps) {
    return (
        <div className="flex items-center justify-between rounded-lg border p-3">
            <div className="flex items-center gap-2">
                {isValid ? (
                    <CheckCircle className="h-4 w-4 text-green-600" />
                ) : (
                    <XCircle className="h-4 w-4 text-red-600" />
                )}
                <span className="text-sm font-medium">{label}</span>
            </div>
            <div className="text-sm text-muted-foreground">
                {percentage !== undefined && <span>{percentage.toFixed(1)}%</span>}
                {count !== undefined && <span>{count} documents</span>}
                {percentage === undefined && count === undefined && (
                    <span>{isValid ? 'Passed' : 'Failed'}</span>
                )}
            </div>
        </div>
    );
}

export default VerificationDialog;
