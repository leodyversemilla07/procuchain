import { Head, usePage } from '@inertiajs/react';
import { AlertCircle, Calendar, Edit, File, FileText, User } from 'lucide-react';
import { useState } from 'react';

import { DocumentCorrectionSheet } from '@/components/documents/document-correction-sheet';
import { HeroCard } from '@/components/hero-card';
import { ProcurementCorrectionsTab } from '@/components/show-procurement/corrections-tab';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { SharedData } from '@/types/navigation';
import { getProcurementDetailBreadcrumbs } from '@/utils/breadcrumbs';

interface ProcurementData {
    pr_number: string;
    title: string;
    description: string;
    abc_amount: number;
    formatted_abc_amount: string;
    funding_source: string;
    category: string;
    category_display: string;
    procurement_mode: string;
    procurement_mode_display: string;
    office: string;
    end_user: string;
    bac_resolution_number: string;
    bac_resolution_date: string;
    philgeps_reference: string;
    philgeps_posting_date: string;
    approved_by: string;
    approval_date: string;
    status: string;
    has_corrections: boolean;
    latest_correction?: {
        timestamp: string;
        corrected_by: string;
        reason: string;
        changed_fields: string[];
    };
}

interface ProcurementCorrection {
    pr_number: string;
    timestamp: string;
    reason: string;
    corrected_by: string;
    correction_type: string;
    correction_type_display: string;
    changed_fields: string[];
    metadata: Record<string, unknown>;
}

interface DocumentData {
    id: number;
    pr_number: string;
    file_key: string;
    document_type: string;
    document_type_display: string;
    stage: string;
    stage_display: string;
    file_size: number;
    hash: string;
    timestamp: string;
    blockchain_txid: string;
    uploaded_by: string;
    metadata: Record<string, unknown>;
}

interface ShowProps {
    procurement: ProcurementData;
    corrections: ProcurementCorrection[];
    documents: DocumentData[];
}

export default function ProcurementCorrections({ procurement, corrections, documents }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.role || auth?.user?.role || 'guest';
    const breadcrumbs = getProcurementDetailBreadcrumbs(userRole, procurement?.title);

    const [selectedDocument, setSelectedDocument] = useState<DocumentData | null>(null);
    const [showDocumentCorrectionSheet, setShowDocumentCorrectionSheet] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Corrections - ${procurement.pr_number}`} />

            <div className="w-full space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <HeroCard icon={Edit} title="Procurement Corrections" description={`Manage corrections for ${procurement.pr_number}`} />

                {/* Procurement Overview */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Procurement Overview
                        </CardTitle>
                        <CardDescription>Current procurement information</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-4">
                                <div>
                                    <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">PR Number</label>
                                    <p className="font-mono text-sm font-medium">{procurement.pr_number}</p>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Title</label>
                                    <p className="text-sm">{procurement.title}</p>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">ABC Amount</label>
                                    <p className="text-sm font-semibold text-green-600 dark:text-green-400">{procurement.formatted_abc_amount}</p>
                                </div>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Category</label>
                                    <p className="text-sm">{procurement.category_display}</p>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Procurement Mode</label>
                                    <p className="text-sm">{procurement.procurement_mode_display}</p>
                                </div>
                                <div>
                                    <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Current Status</label>
                                    <Badge variant="outline" className="mt-1">
                                        {procurement.status}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        {procurement.has_corrections && procurement.latest_correction && (
                            <>
                                <Separator />
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/20">
                                    <div className="flex items-start gap-3">
                                        <AlertCircle className="mt-0.5 h-5 w-5 text-amber-600" />
                                        <div className="flex-1">
                                            <h4 className="font-medium text-amber-800 dark:text-amber-200">Latest Correction</h4>
                                            <div className="mt-2 space-y-2 text-sm">
                                                <div className="flex items-center gap-2">
                                                    <User className="text-muted-foreground h-4 w-4" />
                                                    <span>Corrected by {procurement.latest_correction.corrected_by}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="text-muted-foreground h-4 w-4" />
                                                    <span>{new Date(procurement.latest_correction.timestamp).toLocaleString()}</span>
                                                </div>
                                                <div>
                                                    <span className="font-medium">Reason:</span> {procurement.latest_correction.reason}
                                                </div>
                                                <div>
                                                    <span className="font-medium">Changed fields:</span>{' '}
                                                    {Object.keys(procurement.latest_correction.changed_fields).join(', ')}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* Corrections Management */}
                <ProcurementCorrectionsTab
                    prNumber={procurement.pr_number}
                    hasCorrections={procurement.has_corrections}
                    latestCorrection={procurement.latest_correction}
                    corrections={corrections}
                    procurement={{
                        title: procurement.title,
                        description: procurement.description,
                        abc_amount: procurement.abc_amount,
                        formatted_abc_amount: procurement.formatted_abc_amount,
                        funding_source: procurement.funding_source,
                        category: procurement.category,
                        procurement_mode: procurement.procurement_mode,
                        office: procurement.office,
                        end_user: procurement.end_user,
                        bac_resolution_number: procurement.bac_resolution_number,
                        bac_resolution_date: procurement.bac_resolution_date,
                        philgeps_reference: procurement.philgeps_reference,
                        philgeps_posting_date: procurement.philgeps_posting_date,
                        approved_by: procurement.approved_by,
                        approval_date: procurement.approval_date,
                    }}
                />

                {/* Document Corrections */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <File className="h-5 w-5" />
                            Document Corrections
                        </CardTitle>
                        <CardDescription>Correct individual documents for this procurement</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {documents.length > 0 ? (
                            <div className="space-y-4">
                                {documents.map((document) => (
                                    <div key={document.id} className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="flex items-center gap-3">
                                            <FileText className="text-muted-foreground h-5 w-5" />
                                            <div>
                                                <p className="font-medium">{document.document_type_display}</p>
                                                <p className="text-muted-foreground text-sm">
                                                    {document.stage_display} • {(document.file_size / 1024 / 1024).toFixed(2)} MB
                                                </p>
                                                <p className="text-muted-foreground font-mono text-xs">{document.hash.substring(0, 16)}...</p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setSelectedDocument(document);
                                                setShowDocumentCorrectionSheet(true);
                                            }}
                                        >
                                            <Edit className="mr-2 h-4 w-4" />
                                            Correct
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-8 text-center">
                                <FileText className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                <p className="text-muted-foreground">No documents found for this procurement</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Document Correction Sheet */}
            {selectedDocument && (
                <DocumentCorrectionSheet
                    open={showDocumentCorrectionSheet}
                    onOpenChange={setShowDocumentCorrectionSheet}
                    documentId={selectedDocument.id.toString()}
                    pr_number={selectedDocument.pr_number}
                    procurementTitle={procurement.title}
                    originalDocumentHash={selectedDocument.hash}
                    originalTxid={selectedDocument.blockchain_txid}
                />
            )}
        </AppLayout>
    );
}
