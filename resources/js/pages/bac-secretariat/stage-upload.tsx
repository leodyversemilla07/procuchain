import { HeroCard } from '@/components/hero-card';
import { CompletionDialog } from '@/components/procurement/stage-upload/completion-dialog';
import { DeliveryDetailsPanel } from '@/components/procurement/stage-upload/delivery-details-panel';
import { DocumentUploadList } from '@/components/procurement/stage-upload/document-upload-list';
import { StageCompletionFooter } from '@/components/procurement/stage-upload/stage-completion-footer';
import type { ConfirmDialogState, StageUploadProcurement } from '@/components/procurement/stage-upload/types';
import { UploadConfirmDialog } from '@/components/procurement/stage-upload/upload-confirm-dialog';
import { WorkflowProgressPanel } from '@/components/procurement/stage-upload/workflow-progress-panel';
import { Badge } from '@/components/ui/badge';
import { useBlockchainJob } from '@/hooks/use-blockchain-job';
import { useStageActions } from '@/hooks/use-stage-actions';
import AppLayout from '@/layouts/app-layout';
import { getStageCompletionPercentage, getUploadedRequiredCount, hasUploadedAllRequiredDocuments } from '@/lib/stage-upload';
import { BreadcrumbItem, WorkflowInfo } from '@/types';
import type { DocumentGuide } from '@/types/document-guide';
import { UserRole } from '@/types/enums';
import { buildBreadcrumbs, getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import { Head, router } from '@inertiajs/react';
import { FileCheck2 } from 'lucide-react';
import React, { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface StageUploadProps {
    procurement: StageUploadProcurement;
    workflowInfo?: WorkflowInfo;
    documentGuide?: DocumentGuide;
    uploadedDocuments?: string[];
}

export default function StageUpload({ procurement, workflowInfo, documentGuide, uploadedDocuments = [] }: StageUploadProps) {
    const [files, setFiles] = useState<Record<string, File | null>>({});
    const [dragging, setDragging] = useState<Record<string, boolean>>({});
    const [isUploading, setIsUploading] = useState(false);
    const [isMarkingComplete, setIsMarkingComplete] = useState(false);
    const [isSavingDelivery, setIsSavingDelivery] = useState(false);
    const [nextStageInfo, setNextStageInfo] = useState<{ name: string; url: string } | null>(null);
    const [confirmDialog, setConfirmDialog] = useState<ConfirmDialogState>({
        open: false,
        documentValue: '',
        documentName: '',
    });

    const [completeDialog, setCompleteDialog] = useState(false);
    const { submitAndPoll } = useBlockchainJob();

    // NTP Specific State
    const [deliveryForm, setDeliveryForm] = useState({
        delivery_location: procurement.delivery_location || '',
        delivery_date: procurement.delivery_date || '',
        delivery_term_days: procurement.delivery_term_days?.toString() || '',
    });
    const [deliveryDetailsSaved, setDeliveryDetailsSaved] = useState(
        Boolean(procurement.delivery_location && procurement.delivery_date && procurement.delivery_term_days),
    );

    // Determine stage status
    const stageStatus = useMemo(() => {
        if (!workflowInfo) return { isCompleted: false, isCurrent: false, isFuture: false };
        const stageData = workflowInfo.workflow.stages.find((s) => s.value === procurement.stage_value);
        const isCurrent = procurement.current_stage === procurement.stage_value;
        const isCompleted = stageData?.is_completed || false;
        const isFuture = !isCurrent && !isCompleted;
        return { isCompleted, isCurrent, isFuture };
    }, [workflowInfo, procurement.stage_value, procurement.current_stage]);

    const { isCompleted: isStageCompleted, isFuture: isStageFuture } = stageStatus;

    const phase = useMemo(() => {
        if (!documentGuide) return 'procurement';
        return documentGuide.phase;
    }, [documentGuide]);
    const actions = useStageActions(procurement.stage_value, phase);

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        {
            title: `${documentGuide?.stage_display_name || 'Upload'} - ${procurement?.pr_number}`,
            href: '#',
        },
    ]);

    const handleMarkComplete = async () => {
        setCompleteDialog(false);
        setIsMarkingComplete(true);
        const url = actions.complete({ pr_number: procurement.pr_number, stage: procurement.stage_value as string }).url;
        try {
            const result = await submitAndPoll(url, new FormData());
            toast.success('Stage marked as complete!');
            const blockchain = result.result as { next_stage_name?: string; next_stage_url?: string } | undefined;
            if (blockchain?.next_stage_name) {
                setNextStageInfo({ name: blockchain.next_stage_name, url: blockchain.next_stage_url! });
            }
            router.reload();
        } catch (err) {
            toast.error('Failed to mark stage as complete', { description: err instanceof Error ? err.message : undefined });
        } finally {
            setIsMarkingComplete(false);
        }
    };

    const handleFileChange = (documentValue: string) => (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) {
            setFiles((previous) => ({ ...previous, [documentValue]: file }));
        }
    };

    const handleUploadClick = (documentValue: string, documentName: string) => {
        if (!files[documentValue]) {
            toast.error('No file selected');
            return;
        }
        setConfirmDialog({ open: true, documentValue, documentName });
    };

    const handleConfirmUpload = useCallback(async () => {
        const file = files[confirmDialog.documentValue];
        if (!file) {
            return;
        }

        const uploadToast = toast.loading('Uploading document...');
        setIsUploading(true);
        setConfirmDialog({ open: false, documentValue: confirmDialog.documentValue, documentName: confirmDialog.documentName });

        const formData = new FormData();
        formData.append('document_file', file);
        formData.append('document_type', confirmDialog.documentValue);
        formData.append('description', confirmDialog.documentName);

        try {
            await submitAndPoll(actions.upload({ pr_number: procurement.pr_number, stage: procurement.stage_value as string }).url, formData);
            toast.success('Document uploaded successfully!', { id: uploadToast });
            setFiles((previous) => ({ ...previous, [confirmDialog.documentValue]: null }));
            setConfirmDialog({ open: false, documentValue: '', documentName: '' });
            router.reload({ only: ['uploadedDocuments'] });
        } catch (err) {
            toast.error('Upload failed', { id: uploadToast, description: err instanceof Error ? err.message : 'Error' });
        } finally {
            setIsUploading(false);
        }
    }, [actions, confirmDialog, files, procurement.pr_number, procurement.stage_value, submitAndPoll]);

    const handleSaveDeliveryDetails = useCallback(async () => {
        setIsSavingDelivery(true);
        const formData = new FormData();
        formData.append('delivery_location', deliveryForm.delivery_location);
        formData.append('delivery_date', deliveryForm.delivery_date);
        formData.append('delivery_term_days', deliveryForm.delivery_term_days);
        try {
            await submitAndPoll(actions.deliveryDetails({ pr_number: procurement.pr_number }).url, formData);
            toast.success('Delivery details saved');
            setDeliveryDetailsSaved(true);
        } catch (err) {
            toast.error('Failed to save delivery details', { description: err instanceof Error ? err.message : undefined });
        } finally {
            setIsSavingDelivery(false);
        }
    }, [actions, deliveryForm, procurement.pr_number, submitAndPoll]);

    const handleDeliveryFormChange = useCallback((field: keyof typeof deliveryForm, value: string) => {
        setDeliveryForm((previous) => ({ ...previous, [field]: value }));
    }, []);

    const handleDragStateChange = useCallback((documentValue: string, isDocumentDragging: boolean) => {
        setDragging((previous) => ({ ...previous, [documentValue]: isDocumentDragging }));
    }, []);

    const handleFileDrop = useCallback((documentValue: string, file: File | null) => {
        if (!file) {
            return;
        }

        setFiles((previous) => ({ ...previous, [documentValue]: file }));
    }, []);

    const handleRemoveFile = useCallback((documentValue: string) => {
        setFiles((previous) => ({ ...previous, [documentValue]: null }));
    }, []);

    const uploadedRequiredCount = getUploadedRequiredCount(documentGuide, uploadedDocuments);
    const calculatedPercentage = getStageCompletionPercentage(documentGuide, uploadedRequiredCount);
    const allRequiredUploaded = hasUploadedAllRequiredDocuments(documentGuide, uploadedRequiredCount);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={documentGuide?.stage_display_name || 'Procurement Stage'} />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                {/* HERO CARD: Stage Title and Description */}
                <HeroCard
                    icon={FileCheck2}
                    title={documentGuide?.stage_display_name || 'Procurement Stage'}
                    description={
                        <div className="flex flex-col gap-1">
                            <span className="text-foreground line-clamp-2 font-semibold md:line-clamp-none">{procurement.title}</span>
                            <span>{procurement.pr_number} &bull; Upload and verify documents for this stage</span>
                        </div>
                    }
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="font-mono text-[10px] sm:text-xs">
                                {procurement.pr_number}
                            </Badge>
                        </div>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                    <WorkflowProgressPanel
                        procurement={procurement}
                        workflowInfo={workflowInfo}
                        documentGuide={documentGuide}
                        completionPercentage={calculatedPercentage}
                        uploadedRequiredCount={uploadedRequiredCount}
                    >
                        {procurement.stage_value === 'notice_to_proceed' && (
                            <DeliveryDetailsPanel
                                deliveryForm={deliveryForm}
                                isStageCompleted={isStageCompleted}
                                deliveryDetailsSaved={deliveryDetailsSaved}
                                isStageFuture={isStageFuture}
                                isSavingDelivery={isSavingDelivery}
                                onDeliveryFormChange={handleDeliveryFormChange}
                                onSaveDeliveryDetails={handleSaveDeliveryDetails}
                            />
                        )}
                    </WorkflowProgressPanel>

                    <div className="space-y-6 lg:col-span-2">
                        <DocumentUploadList
                            documentGuide={documentGuide}
                            uploadedDocuments={uploadedDocuments}
                            files={files}
                            dragging={dragging}
                            isStageCompleted={isStageCompleted}
                            isStageFuture={isStageFuture}
                            isUploading={isUploading}
                            onFileChange={handleFileChange}
                            onDragStateChange={handleDragStateChange}
                            onFileDrop={handleFileDrop}
                            onRemoveFile={handleRemoveFile}
                            onUploadClick={handleUploadClick}
                        />

                        <StageCompletionFooter
                            isStageCompleted={isStageCompleted}
                            isStageFuture={isStageFuture}
                            nextStageInfo={nextStageInfo}
                            allRequiredUploaded={allRequiredUploaded}
                            isUploading={isUploading}
                            isMarkingComplete={isMarkingComplete}
                            onMarkComplete={() => setCompleteDialog(true)}
                        />
                    </div>
                </div>
            </div>

            <UploadConfirmDialog
                dialog={confirmDialog}
                isUploading={isUploading}
                onOpenChange={(open) => {
                    if (!isUploading) {
                        setConfirmDialog((previous) => ({ ...previous, open }));
                    }
                }}
                onConfirm={handleConfirmUpload}
            />

            <CompletionDialog
                open={completeDialog}
                isMarkingComplete={isMarkingComplete}
                stageName={documentGuide?.stage_display_name || 'this stage'}
                prNumber={procurement.pr_number}
                onOpenChange={(open) => {
                    if (!isMarkingComplete) {
                        setCompleteDialog(open);
                    }
                }}
                onConfirm={handleMarkComplete}
            />
        </AppLayout>
    );
}
