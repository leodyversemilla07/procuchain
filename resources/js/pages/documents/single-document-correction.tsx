import { DocumentCorrectionSheet } from '@/components/documents/document-correction-sheet';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { getDocumentCorrectionsBreadcrumbs } from '@/utils/breadcrumbs';
import { Head, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, FileText, Info } from 'lucide-react';
import { useState } from 'react';

interface SingleDocumentCorrectionProps {
    document: {
        id: string;
        file_name: string;
        document_type: string;
        document_type_display?: string;
        hash: string;
        file_size: number;
        uploaded_at: string;
        blockchain_txid?: string;
        pr_number: string;
        procurement_title: string;
        stage: string;
        stage_display?: string;
        current_status?: string;
        is_corrected: boolean;
        correction_reason?: string;
        corrected_by?: string;
        corrected_at?: string;
        correction_txid?: string;
    };
}

export default function SingleDocumentCorrection({ document }: SingleDocumentCorrectionProps) {
    const { auth: pageAuth } = usePage<SharedData>().props;
    const userRole = pageAuth?.role || pageAuth?.user?.role || 'guest';
    const breadcrumbs = getDocumentCorrectionsBreadcrumbs(userRole, document.procurement_title);

    const [showCorrectionSheet, setShowCorrectionSheet] = useState(true); // Auto-open the correction sheet

    // Check if user can correct documents
    const allowedRoles = ['admin', 'bac_chairman', 'bac_secretariat'];
    const canCorrectDocuments = allowedRoles.includes(pageAuth?.role || pageAuth?.user?.role || '');

    if (!canCorrectDocuments) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Access Denied - Document Correction" />

                <div className="w-full p-4 md:p-6 lg:p-8">
                    <Alert className="border-destructive/20 bg-destructive/5">
                        <AlertCircle className="text-destructive h-4 w-4" />
                        <AlertTitle>Access Denied</AlertTitle>
                        <AlertDescription>
                            You don't have permission to correct documents. Only administrators and BAC members can submit corrections.
                        </AlertDescription>
                    </Alert>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Correct Document - ${document.file_name}`} />

            <div className="w-full space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <a href={`/documents/corrections/${document.pr_number}`}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to All Documents
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">Correct Single Document</h1>
                        <p className="text-muted-foreground">Submit corrections for this specific document</p>
                    </div>
                </div>

                {/* Info Alert */}
                <Alert className="border-primary/20 bg-primary/5 dark:border-primary/30 dark:bg-primary/10">
                    <Info className="text-primary h-4 w-4" />
                    <AlertTitle>About Document Corrections</AlertTitle>
                    <AlertDescription>
                        You can correct document mistakes while maintaining blockchain immutability. Both the original and correction records remain
                        permanently on the blockchain for a complete audit trail.
                    </AlertDescription>
                </Alert>

                {/* Document Info Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Document Details
                        </CardTitle>
                        <CardDescription>Information about the document you want to correct</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-3">
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">File Name</label>
                                    <p className="font-medium">{document.file_name}</p>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Document Type</label>
                                    <div className="mt-1">
                                        <Badge variant="secondary">{document.document_type_display || document.document_type}</Badge>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">File Size</label>
                                    <p className="font-medium">{(document.file_size / 1024 / 1024).toFixed(2)} MB</p>
                                </div>
                            </div>
                            <div className="space-y-3">
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Procurement</label>
                                    <p className="font-medium">{document.procurement_title}</p>
                                    <p className="text-muted-foreground text-sm">PR: {document.pr_number}</p>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Stage</label>
                                    <div className="mt-1">
                                        <Badge variant="outline">{document.stage_display || document.stage}</Badge>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-sm font-medium">Uploaded</label>
                                    <p className="font-medium">{new Date(document.uploaded_at).toLocaleDateString()}</p>
                                </div>
                            </div>
                        </div>

                        {/* Document Hash */}
                        <div className="mt-4 border-t pt-4">
                            <label className="text-muted-foreground text-sm font-medium">Document Hash</label>
                            <p className="bg-muted mt-1 rounded p-2 font-mono text-sm break-all">{document.hash}</p>
                        </div>

                        {/* Correction Status */}
                        {document.is_corrected && (
                            <div className="mt-4 border-t pt-4">
                                <Alert className="border-amber-200 bg-amber-50/50">
                                    <AlertCircle className="h-4 w-4 text-amber-600" />
                                    <AlertTitle className="text-amber-800">Document Already Corrected</AlertTitle>
                                    <AlertDescription className="text-amber-700">
                                        This document has already been corrected. You can still submit additional corrections if needed.
                                        <br />
                                        <strong>Last corrected:</strong> {new Date(document.corrected_at!).toLocaleDateString()} by{' '}
                                        {document.corrected_by}
                                        <br />
                                        <strong>Reason:</strong> {document.correction_reason}
                                    </AlertDescription>
                                </Alert>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Correction Action */}
                <Card>
                    <CardHeader>
                        <CardTitle>Submit Correction</CardTitle>
                        <CardDescription>Click the button below to open the correction form for this document</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button onClick={() => setShowCorrectionSheet(true)} className="w-full sm:w-auto" size="lg">
                            <AlertCircle className="mr-2 h-5 w-5" />
                            Open Correction Form
                        </Button>
                    </CardContent>
                </Card>
            </div>

            {/* Document Correction Sheet */}
            <DocumentCorrectionSheet
                open={showCorrectionSheet}
                onOpenChange={setShowCorrectionSheet}
                documentId={document.blockchain_txid || document.id}
                pr_number={document.pr_number}
                procurementTitle={document.procurement_title}
                originalDocumentHash={document.hash}
                originalTxid={document.blockchain_txid}
            />
        </AppLayout>
    );
}
