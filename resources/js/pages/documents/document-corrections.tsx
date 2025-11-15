import { DocumentCorrectionAlert } from '@/components/documents/document-correction-alert';
import { DocumentCorrectionSheet } from '@/components/documents/document-correction-sheet';
import { CorrectionHistorySheet } from '@/components/documents/document-correction-history-sheet';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { AlertCircle, FileText, History, Info } from 'lucide-react';
import { useState } from 'react';
import type { SharedData } from '@/types';
import { UserRole } from '@/types';
import { getDocumentCorrectionsBreadcrumbs } from '@/utils/breadcrumbs';

interface ProcurementDocument {
    id: number | string;
    file_name: string;
    document_type: string;
    document_type_display?: string;
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
    id: string;
    title: string;
    reference_number: string;
    status: string;
    status_display?: string;
    stage: string;
    stage_display?: string;
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
    const { auth: pageAuth } = usePage<SharedData>().props;
    const userRole = pageAuth?.user?.role || auth?.user?.roles?.[0] || 'guest';
    const breadcrumbs = getDocumentCorrectionsBreadcrumbs(userRole, procurement.title);
    
    const [selectedDocument, setSelectedDocument] = useState<ProcurementDocument | null>(null);
    const [showCorrectionSheet, setShowCorrectionSheet] = useState(false);
    const [showHistorySheet, setShowHistorySheet] = useState(false);
    const [historyDocumentHash, setHistoryDocumentHash] = useState<string | undefined>(undefined);

    // Check if user can correct documents
    const allowedRoles = [UserRole.ADMIN, UserRole.BAC_CHAIRMAN, UserRole.BAC_SECRETARIAT];
    const canCorrectDocuments = 
        auth.user.roles.some((role) => allowedRoles.includes(role as UserRole)) || 
        (pageAuth?.user?.role && allowedRoles.includes(pageAuth.user.role as UserRole));

    // Handle clicking "Correct Document" on a specific document
    const handleCorrectDocument = (document: ProcurementDocument) => {
        setSelectedDocument(document);
        setShowCorrectionSheet(true);
    };

    // Handle clicking "View History" for a specific document
    const handleViewDocumentHistory = (document: ProcurementDocument) => {
        setHistoryDocumentHash(document.hash);
        setShowHistorySheet(true);
    };

    // Handle clicking "View All Corrections" for the entire procurement
    const handleViewAllCorrections = () => {
        setHistoryDocumentHash(undefined); // Show all corrections, not filtered by document
        setShowHistorySheet(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Correct Documents - ${procurement.title}`} />

            <div className="w-full space-y-6 p-4 md:p-6 lg:p-8">
                {/* Page Header */}
                <div>
                    <h1 className="mb-2 text-2xl font-bold sm:text-3xl">{procurement.title}</h1>
                    <p className="text-muted-foreground text-sm sm:text-base">
                        <span className="block sm:inline">Reference: {procurement.reference_number}</span>
                        <span className="hidden sm:inline"> • </span>
                        <span className="block sm:inline">Status: {procurement.status_display || procurement.status}</span>
                        <span className="hidden sm:inline"> • </span>
                        <span className="block sm:inline">Stage: {procurement.stage_display || procurement.stage}</span>
                    </p>
                </div>

                {/* Info Alert */}
                <Alert className="border-primary/20 bg-primary/5 dark:border-primary/30 dark:bg-primary/10">
                    <Info className="h-4 w-4 text-primary" />
                    <AlertTitle>About Document Corrections</AlertTitle>
                    <AlertDescription>
                        You can correct document mistakes while maintaining blockchain immutability. Both the original and correction records
                        remain permanently on the blockchain for a complete audit trail.
                    </AlertDescription>
                </Alert>

                {/* Documents Section */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-lg sm:text-xl">Procurement Documents</CardTitle>
                                <CardDescription className="text-sm">{procurement.documents.length} document(s) uploaded</CardDescription>
                            </div>
                            <Button variant="outline" onClick={handleViewAllCorrections} className="w-full gap-2 sm:w-auto">
                                <History className="h-4 w-4" />
                                <span className="hidden sm:inline">View All Corrections History</span>
                                <span className="sm:hidden">All Corrections</span>
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
                                    <div key={document.id} className="rounded-lg border p-3 sm:p-4">
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
                                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div className="flex-1 min-w-0">
                                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                                    <FileText className="text-muted-foreground h-5 w-5 shrink-0" />
                                                    <h3 className="truncate font-semibold text-sm sm:text-base">{document.file_name}</h3>
                                                    <Badge variant="outline" className="text-xs">{document.document_type_display || document.document_type}</Badge>
                                                    {document.is_corrected && (
                                                        <Badge variant="secondary" className="gap-1 text-xs">
                                                            <AlertCircle className="h-3 w-3" />
                                                            Corrected
                                                        </Badge>
                                                    )}
                                                </div>

                                                <div className="text-muted-foreground grid gap-2 text-xs sm:grid-cols-2 sm:gap-4 sm:text-sm">
                                                    <div>
                                                        <strong>File Size:</strong> {(document.file_size / 1024 / 1024).toFixed(2)} MB
                                                    </div>
                                                    <div>
                                                        <strong>Uploaded:</strong> {new Date(document.uploaded_at).toLocaleDateString()}
                                                    </div>
                                                    <div className="col-span-full">
                                                        <strong>Hash:</strong>{' '}
                                                        <span className="font-mono text-xs break-all">{document.hash.substring(0, 24)}...</span>
                                                    </div>
                                                    {document.blockchain_txid && (
                                                        <div className="col-span-full">
                                                            <strong>Blockchain TXID:</strong>{' '}
                                                            <span className="font-mono text-xs break-all">{document.blockchain_txid.substring(0, 24)}...</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Action Buttons */}
                                            <div className="flex flex-col gap-2 sm:flex-row lg:ml-4 lg:flex-col xl:flex-row">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleViewDocumentHistory(document)}
                                                    className="w-full gap-2 sm:w-auto"
                                                >
                                                    <History className="h-4 w-4" />
                                                    <span className="hidden sm:inline">Document History</span>
                                                    <span className="sm:hidden">History</span>
                                                </Button>
                                                {canCorrectDocuments && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleCorrectDocument(document)}
                                                        className="w-full gap-2 sm:w-auto"
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
                    <Alert className="border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20">
                        <AlertCircle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        <AlertTitle>Limited Permissions</AlertTitle>
                        <AlertDescription>
                            Only administrators and BAC members can submit document corrections. You can view correction history but cannot
                            submit corrections.
                        </AlertDescription>
                    </Alert>
                )}
            </div>

            {/* Correction Sheet */}
            {selectedDocument && (
                <DocumentCorrectionSheet
                    open={showCorrectionSheet}
                    onOpenChange={setShowCorrectionSheet}
                    documentId={selectedDocument.id}
                    pr_number={procurement.id}
                    procurementTitle={procurement.title}
                    originalDocumentHash={selectedDocument.hash}
                    originalTxid={selectedDocument.blockchain_txid}
                />
            )}

            {/* Correction History Sheet */}
            <CorrectionHistorySheet
                open={showHistorySheet}
                onOpenChange={setShowHistorySheet}
                pr_number={procurement.id}
                documentHash={historyDocumentHash}
            />
        </AppLayout>
    );
}
