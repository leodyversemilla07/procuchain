import { DocumentCorrectionAlert } from '@/components/documents/document-correction-alert';
import { DocumentCorrectionDialog } from '@/components/documents/document-correction-dialog';
import { CorrectionHistoryDialog } from '@/components/documents/document-correction-history';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { AlertCircle, FileText, History } from 'lucide-react';
import { useState } from 'react';

interface ProcurementDocument {
    id: number;
    file_name: string;
    document_type: string;
    hash: string;
    file_size: number;
    uploaded_at: string;
    blockchain_txid?: string;
    is_corrected: boolean;
    correction_reason?: string;
    corrected_by?: string;
    corrected_at?: string;
    correction_txid?: string;
}

interface Procurement {
    id: number;
    title: string;
    reference_number: string;
    status: string;
    stage: string;
    documents: ProcurementDocument[];
}

interface DocumentCorrectionsProps {
    procurement: Procurement;
    auth: {
        user: {
            name: string;
            roles: string[];
        };
    };
}

export default function DocumentCorrections({ procurement, auth }: DocumentCorrectionsProps) {
    const [selectedDocument, setSelectedDocument] = useState<ProcurementDocument | null>(null);
    const [showCorrectionDialog, setShowCorrectionDialog] = useState(false);
    const [showHistoryDialog, setShowHistoryDialog] = useState(false);
    const [historyDocumentHash, setHistoryDocumentHash] = useState<string | undefined>(undefined);

    // Check if user can correct documents
    const canCorrectDocuments = auth.user.roles.some((role) => ['admin', 'bac_chairman', 'bac_secretariat'].includes(role));

    // Handle clicking "Correct Document" on a specific document
    const handleCorrectDocument = (document: ProcurementDocument) => {
        setSelectedDocument(document);
        setShowCorrectionDialog(true);
    };

    // Handle clicking "View History" for a specific document
    const handleViewDocumentHistory = (document: ProcurementDocument) => {
        setHistoryDocumentHash(document.hash);
        setShowHistoryDialog(true);
    };

    // Handle clicking "View All Corrections" for the entire procurement
    const handleViewAllCorrections = () => {
        setHistoryDocumentHash(undefined); // Show all corrections, not filtered by document
        setShowHistoryDialog(true);
    };

    return (
        <AppLayout>
            <Head title={`Correct Documents - ${procurement.title}`} />

            <div className="container mx-auto px-4 py-8">
                {/* Page Header */}
                <div className="mb-8">
                    <h1 className="mb-2 text-3xl font-bold">{procurement.title}</h1>
                    <p className="text-muted-foreground">
                        Reference: {procurement.reference_number} • Status: {procurement.status} • Stage: {procurement.stage}
                    </p>
                </div>

                {/* Info Alert */}
                <Card className="mb-6 border-blue-200 bg-blue-50 dark:bg-blue-950/20">
                    <CardContent className="pt-6">
                        <div className="flex gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                            <div className="text-sm">
                                <p className="mb-1 font-semibold text-blue-900 dark:text-blue-100">About Document Corrections</p>
                                <p className="text-blue-800 dark:text-blue-200">
                                    You can correct document mistakes while maintaining blockchain immutability. Both the original and correction
                                    records remain permanently on the blockchain for a complete audit trail.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Documents Section */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Procurement Documents</CardTitle>
                                <CardDescription>{procurement.documents.length} document(s) uploaded</CardDescription>
                            </div>
                            <Button variant="outline" onClick={handleViewAllCorrections} className="gap-2">
                                <History className="h-4 w-4" />
                                View All Corrections
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {procurement.documents.length === 0 ? (
                            <div className="py-12 text-center">
                                <FileText className="text-muted-foreground mx-auto mb-3 h-12 w-12" />
                                <p className="text-muted-foreground">No documents uploaded yet</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {procurement.documents.map((document) => (
                                    <div key={document.id} className="rounded-lg border p-4">
                                        {/* Correction Alert (if document is corrected) */}
                                        {document.is_corrected && (
                                            <div className="mb-4">
                                                <DocumentCorrectionAlert
                                                    isCorrected={document.is_corrected}
                                                    correctionReason={document.correction_reason}
                                                    correctedBy={document.corrected_by}
                                                    correctedAt={document.corrected_at}
                                                    onViewHistory={() => handleViewDocumentHistory(document)}
                                                />
                                            </div>
                                        )}

                                        {/* Document Info */}
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="mb-2 flex items-center gap-2">
                                                    <FileText className="text-muted-foreground h-5 w-5" />
                                                    <h3 className="font-semibold">{document.file_name}</h3>
                                                    <Badge variant="outline">{document.document_type}</Badge>
                                                    {document.is_corrected && (
                                                        <Badge variant="secondary" className="gap-1">
                                                            <AlertCircle className="h-3 w-3" />
                                                            Corrected
                                                        </Badge>
                                                    )}
                                                </div>

                                                <div className="text-muted-foreground grid grid-cols-2 gap-4 text-sm">
                                                    <div>
                                                        <strong>File Size:</strong> {(document.file_size / 1024 / 1024).toFixed(2)} MB
                                                    </div>
                                                    <div>
                                                        <strong>Uploaded:</strong> {new Date(document.uploaded_at).toLocaleDateString()}
                                                    </div>
                                                    <div className="col-span-2">
                                                        <strong>Hash:</strong>{' '}
                                                        <span className="font-mono text-xs">{document.hash.substring(0, 32)}...</span>
                                                    </div>
                                                    {document.blockchain_txid && (
                                                        <div className="col-span-2">
                                                            <strong>Blockchain TXID:</strong>{' '}
                                                            <span className="font-mono text-xs">{document.blockchain_txid.substring(0, 32)}...</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Action Buttons */}
                                            <div className="ml-4 flex gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleViewDocumentHistory(document)}
                                                    className="gap-2"
                                                >
                                                    <History className="h-4 w-4" />
                                                    History
                                                </Button>
                                                {canCorrectDocuments && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleCorrectDocument(document)}
                                                        className="gap-2"
                                                    >
                                                        <AlertCircle className="h-4 w-4" />
                                                        Correct
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Permission Message for Non-Admin Users */}
                {!canCorrectDocuments && (
                    <Card className="mt-6 border-amber-200 bg-amber-50 dark:bg-amber-950/20">
                        <CardContent className="pt-6">
                            <div className="flex gap-3">
                                <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                                <div className="text-sm">
                                    <p className="mb-1 font-semibold text-amber-900 dark:text-amber-100">Limited Permissions</p>
                                    <p className="text-amber-800 dark:text-amber-200">
                                        Only administrators and BAC members can submit document corrections. You can view correction history but
                                        cannot submit corrections.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Correction Dialog */}
            {selectedDocument && (
                <DocumentCorrectionDialog
                    open={showCorrectionDialog}
                    onOpenChange={setShowCorrectionDialog}
                    documentId={selectedDocument.id}
                    procurementId={procurement.id}
                    procurementTitle={procurement.title}
                    originalDocumentHash={selectedDocument.hash}
                    originalTxid={selectedDocument.blockchain_txid}
                />
            )}

            {/* Correction History Dialog */}
            <CorrectionHistoryDialog
                open={showHistoryDialog}
                onOpenChange={setShowHistoryDialog}
                procurementId={procurement.id}
                documentHash={historyDocumentHash}
            />
        </AppLayout>
    );
}
