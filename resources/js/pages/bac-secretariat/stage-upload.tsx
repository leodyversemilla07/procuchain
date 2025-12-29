import { markStageComplete as initMarkStageComplete, uploadSingleDocument as initUploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';
import { markStageComplete as postMarkStageComplete, updateDeliveryDetails, uploadSingleDocument as postUploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/PostProcurementController';
import { markStageComplete as preMarkStageComplete, uploadSingleDocument as preUploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/PreProcurementController';
import { markStageComplete as procMarkStageComplete, uploadSingleDocument as procUploadSingleDocument } from '@/actions/App/Http/Controllers/Procurement/ProcurementController';
import FileUploadArea from '@/components/file-upload-area';
import { HeroCard } from '@/components/hero-card';
import { ModeBadge } from '@/components/procurement/workflow-progress-indicator';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePickerInput } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem, WorkflowInfo } from '@/types';
import type { DocumentGuide } from '@/types/document-guide';
import { UserRole } from '@/types/enums';
import { handleFlashSuccess } from '@/utils/blockchain-toast';
import { buildBreadcrumbs, getProcurementsListBreadcrumb } from '@/utils/breadcrumbs';
import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ArrowRight, CheckCircle2, Clock, FileCheck2, Lock, MapPin, Send } from 'lucide-react';
import React, { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface StageUploadProps {
    procurement: {
        pr_number: string;
        title: string;
        status?: string;
        stage_value?: string;
        current_stage?: string;
        // Delivery details for NTP stage
        delivery_location?: string;
        delivery_date?: string;
        delivery_date_formatted?: string;
        delivery_term_days?: number;
    };
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
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        documentValue: string;
        documentName: string;
    }>({
        open: false,
        documentValue: '',
        documentName: '',
    });

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
        const stageData = workflowInfo.workflow.stages.find(s => s.value === procurement.stage_value);
        const isCurrent = procurement.current_stage === procurement.stage_value;
        const isCompleted = stageData?.is_completed || false;
        const isFuture = !isCurrent && !isCompleted;
        return { isCompleted, isCurrent, isFuture };
    }, [workflowInfo, procurement.stage_value, procurement.current_stage]);

    const { isCompleted: isStageCompleted, isCurrent: isStageCurrent, isFuture: isStageFuture } = stageStatus;

    const phase = useMemo(() => {
        if (!documentGuide) return 'procurement';
        return documentGuide.phase;
    }, [documentGuide]);

    const breadcrumbs: BreadcrumbItem[] = buildBreadcrumbs(UserRole.BAC_SECRETARIAT, [
        getProcurementsListBreadcrumb(UserRole.BAC_SECRETARIAT),
        {
            title: `${documentGuide?.stage_display_name || 'Upload'} - ${procurement?.pr_number}`,
            href: '#',
        },
    ]);

    const getActions = useCallback(() => {
        if (procurement.stage_value === 'procurement_initiation') {
            return { upload: initUploadSingleDocument, complete: initMarkStageComplete };
        }

        switch (phase) {
            case 'pre_procurement': return { upload: preUploadSingleDocument, complete: preMarkStageComplete };
            case 'post_procurement': return { upload: postUploadSingleDocument, complete: postMarkStageComplete };
            default: return { upload: procUploadSingleDocument, complete: procMarkStageComplete };
        }
    }, [phase, procurement.stage_value]);

    const handleMarkComplete = () => {
        setIsMarkingComplete(true);
        const { complete } = getActions();
        router.post(
            complete({ pr_number: procurement.pr_number, stage: procurement.stage_value as any }).url,
            {},
            {
                onSuccess: (page) => {
                    handleFlashSuccess(page as { props: Record<string, unknown> }, 'Stage marked as complete!');
                    const flash = page.props.flash as Record<string, unknown> | undefined;
                    const response = flash?.success as { blockchain?: { next_stage_name?: string; next_stage_url?: string } } | undefined;
                    if (response?.blockchain?.next_stage_name) {
                        setNextStageInfo({ name: response.blockchain.next_stage_name, url: response.blockchain.next_stage_url! });
                    }
                },
                onError: () => toast.error('Failed to mark stage as complete'),
                onFinish: () => setIsMarkingComplete(false),
                preserveScroll: true,
            },
        );
    };

    const handleFileChange = (documentValue: string) => (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) setFiles((prev) => ({ ...prev, [documentValue]: file }));
    };

    const handleUploadClick = (documentValue: string, documentName: string) => {
        if (!files[documentValue]) {
            toast.error('No file selected');
            return;
        }
        setConfirmDialog({ open: true, documentValue, documentName });
    };

    const handleConfirmUpload = useCallback(() => {
        const file = files[confirmDialog.documentValue];
        const { upload } = getActions();
        if (!file) return;

        const uploadToast = toast.loading('Uploading document...');
        setIsUploading(true);

        router.post(
            upload({ pr_number: procurement.pr_number, stage: procurement.stage_value as any }).url,
            { document_file: file, document_type: confirmDialog.documentValue, description: confirmDialog.documentName },
            {
                onSuccess: () => {
                    toast.success('Document uploaded successfully!', { id: uploadToast });
                    setFiles((prev) => ({ ...prev, [confirmDialog.documentValue]: null }));
                    setConfirmDialog({ open: false, documentValue: '', documentName: '' });
                    setIsUploading(false);
                },
                onError: (errors) => {
                    toast.error('Upload failed', { id: uploadToast, description: errors.message || 'Error' });
                    setIsUploading(false);
                },
                preserveScroll: true,
                only: ['uploadedDocuments'],
                forceFormData: true,
            },
        );
    }, [confirmDialog, files, procurement.pr_number, getActions, procurement.stage_value]);

    const handleSaveDeliveryDetails = useCallback(() => {
        setIsSavingDelivery(true);
        router.post(updateDeliveryDetails({ pr_number: procurement.pr_number }).url,
            {
                delivery_location: deliveryForm.delivery_location,
                delivery_date: deliveryForm.delivery_date,
                delivery_term_days: parseInt(deliveryForm.delivery_term_days),
            },
            {
                onSuccess: () => { toast.success('Delivery details saved'); setDeliveryDetailsSaved(true); },
                onFinish: () => setIsSavingDelivery(false),
                preserveScroll: true,
            });
    }, [deliveryForm, procurement.pr_number]);

    const uploadedRequiredCount = documentGuide ? documentGuide.required_documents.filter((doc) => uploadedDocuments.includes(doc.value)).length : 0;
    const calculatedPercentage = documentGuide && documentGuide.counts.required_count > 0 ? Math.round((uploadedRequiredCount / documentGuide.counts.required_count) * 100) : 100;
    const allRequiredUploaded = documentGuide && uploadedRequiredCount === documentGuide.counts.required_count;

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
                            <span className="font-semibold text-foreground line-clamp-2 md:line-clamp-none">
                                {procurement.title}
                            </span>
                            <span>
                                {procurement.pr_number} &bull; Upload and verify documents for this stage
                            </span>
                        </div>
                    }
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="text-[10px] sm:text-xs font-mono">
                                {procurement.pr_number}
                            </Badge>
                            {workflowInfo && <ModeBadge workflowInfo={workflowInfo} />}
                        </div>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
                    {/* LEFT SIDEBAR: Progress */}
                    <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
                        <CardHeader className="pb-4 border-b">
                            <CardTitle className="text-xs font-semibold uppercase tracking-tight text-muted-foreground flex items-center gap-2">
                                <Clock className="h-3.5 w-3.5" />
                                Workflow Progress
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-6 space-y-6">
                            {workflowInfo && (
                                <div className="space-y-6">
                                    {/* Progress Circles */}
                                    <div className="flex flex-wrap gap-2.5">
                                        {workflowInfo.workflow.stages.map((stage, index) => (
                                            <TooltipProvider key={stage.value}>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Link
                                                            href={stage.url || '#'}
                                                            className={`flex h-9 w-9 items-center justify-center rounded-full border-2 transition-all hover:scale-110 ${stage.value === procurement.stage_value
                                                                ? 'bg-primary border-primary text-primary-foreground ring-primary/20 ring-4'
                                                                : stage.is_completed
                                                                    ? 'border-green-500 bg-green-500 text-white shadow-sm'
                                                                    : stage.is_optional
                                                                        ? 'border-muted-foreground/40 border-dashed text-muted-foreground/40'
                                                                        : 'border-muted-foreground/20 text-muted-foreground/20 bg-muted/30'
                                                                }`}
                                                        >
                                                            {stage.is_completed ? <CheckCircle2 className="h-4 w-4" /> : <span className="text-xs font-bold">{index + 1}</span>}
                                                        </Link>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="right" className="flex flex-col gap-1">
                                                        <span className="font-bold">{stage.display_name}</span>
                                                        <span className="text-[10px] opacity-70">Step {index + 1}</span>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        ))}
                                    </div>

                                    {/* Requirements Progress */}
                                    {documentGuide && (
                                        <div className="space-y-3 pt-4 border-t">
                                            <div className="flex items-center justify-between text-xs font-bold">
                                                <span className="text-muted-foreground uppercase tracking-wider">Completion</span>
                                                <span className="text-primary">{calculatedPercentage}%</span>
                                            </div>
                                            <Progress value={calculatedPercentage} className="h-2 rounded-full" />
                                            <p className="text-[10px] text-muted-foreground italic">
                                                {uploadedRequiredCount} of {documentGuide.counts.required_count} required documents uploaded
                                            </p>
                                        </div>
                                    )}

                                    {/* Legend */}
                                    <div className="space-y-2 pt-4 border-t text-[10px]">
                                        <div className="flex items-center gap-2"><div className="h-2 w-2 rounded-full bg-primary" /> <span>Current Stage</span></div>
                                        <div className="flex items-center gap-2"><div className="h-2 w-2 rounded-full bg-green-500" /> <span>Completed</span></div>
                                        <div className="flex items-center gap-2"><div className="h-2 w-2 rounded-full bg-muted/30 border border-muted-foreground/20" /> <span>Pending</span></div>
                                        <div className="flex items-center gap-2"><div className="h-2 w-2 rounded-full border border-dashed border-muted-foreground/50" /> <span>Optional</span></div>
                                    </div>
                                </div>
                            )}

                            {/* NTP Delivery Details (Sidebar) */}
                            {procurement.stage_value === 'notice_to_proceed' && (
                                <div className="space-y-4 pt-6 border-t mt-4">
                                    <h4 className="text-xs font-bold uppercase text-muted-foreground flex items-center gap-2">
                                        <MapPin className="h-3.5 w-3.5" />
                                        Delivery Info
                                    </h4>
                                    <div className="space-y-3">
                                        <div className="space-y-1">
                                            <Label className="text-[10px] opacity-70 uppercase tracking-tighter">Location</Label>
                                            <Input
                                                value={deliveryForm.delivery_location}
                                                onChange={(e) => setDeliveryForm(p => ({ ...p, delivery_location: e.target.value }))}
                                                disabled={isStageCompleted || deliveryDetailsSaved || isStageFuture}
                                                className="h-8 text-xs bg-muted/30"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] opacity-70 uppercase tracking-tighter">Date</Label>
                                            <DatePickerInput
                                                id="delivery_date"
                                                value={deliveryForm.delivery_date}
                                                onChange={(val) => setDeliveryForm(p => ({ ...p, delivery_date: val }))}
                                                disabled={isStageCompleted || deliveryDetailsSaved || isStageFuture}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] opacity-70 uppercase tracking-tighter">Term (Days)</Label>
                                            <Input
                                                type="number"
                                                value={deliveryForm.delivery_term_days}
                                                onChange={(e) => setDeliveryForm(p => ({ ...p, delivery_term_days: e.target.value }))}
                                                disabled={isStageCompleted || deliveryDetailsSaved || isStageFuture}
                                                className="h-8 text-xs bg-muted/30"
                                            />
                                        </div>
                                        {!deliveryDetailsSaved && !isStageCompleted && !isStageFuture && (
                                            <Button onClick={handleSaveDeliveryDetails} disabled={isSavingDelivery} className="w-full text-xs h-8">
                                                {isSavingDelivery ? <Spinner className="h-3 w-3" /> : 'Save Details'}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* MAIN CONTENT: Tasks */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md min-h-[400px]">
                            <CardContent className="p-6 space-y-8">
                                {isStageFuture ? (
                                    <div className="flex flex-col items-center justify-center py-20 text-center text-muted-foreground opacity-30">
                                        <Lock size={48} className="mb-4" />
                                        <h3 className="text-lg font-semibold text-foreground">Stage is Locked</h3>
                                        <p className="max-w-xs mt-1 text-xs italic">
                                            Please finish the current stage tasks first.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-6">
                                        {documentGuide && [...documentGuide.required_documents, ...documentGuide.optional_documents].map((doc) => {
                                            const isUploaded = uploadedDocuments.includes(doc.value);
                                            const isRequired = documentGuide.required_documents.some(r => r.value === doc.value);
                                            return (
                                                <div key={doc.value} className="relative rounded-2xl border p-5 transition-all bg-card/50 hover:bg-card hover:shadow-sm">
                                                    <div className="flex items-start justify-between mb-4">
                                                        <div className="space-y-1 text-left">
                                                            <div className="flex items-center gap-2">
                                                                <h4 className="text-base font-semibold">{doc.display_name}</h4>
                                                                {isRequired && <Badge variant="destructive" className="h-4 text-[9px] px-1 uppercase font-bold">Required</Badge>}
                                                            </div>
                                                            {doc.description && <p className="text-muted-foreground text-xs leading-relaxed">{doc.description}</p>}
                                                        </div>
                                                        {isUploaded && <Badge className="bg-green-500 hover:bg-green-600 text-[10px] py-0"><CheckCircle2 className="h-3 w-3 mr-1" /> UPLOADED</Badge>}
                                                    </div>

                                                    {!isUploaded && !isStageCompleted && (
                                                        <div className="flex flex-col gap-4 lg:flex-row items-stretch">
                                                            <div className="flex-1">
                                                                <FileUploadArea
                                                                    label=""
                                                                    file={files[doc.value] || null}
                                                                    isDragging={dragging[doc.value] || false}
                                                                    onFileChange={handleFileChange(doc.value)}
                                                                    onDragEnter={(e) => { e.preventDefault(); setDragging(p => ({ ...p, [doc.value]: true })); }}
                                                                    onDragLeave={(e) => { e.preventDefault(); setDragging(p => ({ ...p, [doc.value]: false })); }}
                                                                    onDragOver={(e) => e.preventDefault()}
                                                                    onDrop={(e) => { e.preventDefault(); setDragging(p => ({ ...p, [doc.value]: false })); const f = e.dataTransfer.files[0]; if (f) setFiles(p => ({ ...p, [doc.value]: f })); }}
                                                                    onRemove={() => setFiles(p => ({ ...p, [doc.value]: null }))}
                                                                    inputId={`file-${doc.value}`}
                                                                />
                                                            </div>
                                                            <Button
                                                                onClick={() => handleUploadClick(doc.value, doc.display_name)}
                                                                disabled={!files[doc.value] || isUploading}
                                                                className="lg:h-auto lg:w-[120px] shadow-sm active:scale-95 transition-transform"
                                                            >
                                                                {isUploading ? <Spinner className="h-5 w-5" /> : 'UPLOAD'}
                                                            </Button>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>

                            <CardFooter className="p-6 border-t bg-muted/5 rounded-b-xl flex flex-col gap-4">
                                {isStageCompleted ? (
                                    <div className="w-full bg-green-500/10 border border-green-500/20 p-4 rounded-xl flex items-center justify-between">
                                        <div className="flex items-center gap-3 text-green-700">
                                            <div className="bg-green-500 p-1 rounded-full"><CheckCircle2 className="h-4 w-4 text-white" /></div>
                                            <span className="font-bold text-xs uppercase tracking-tight">Stage Complete</span>
                                        </div>
                                        {nextStageInfo && (
                                            <Button asChild variant="outline" className="border-green-500/20 text-green-700 bg-white">
                                                <Link href={nextStageInfo.url}>NEXT: {nextStageInfo.name} <ArrowRight className="ml-2 h-4 w-4" /></Link>
                                            </Button>
                                        )}
                                    </div>
                                ) : isStageFuture ? (
                                    <Button disabled className="w-full h-12 uppercase font-black"><Lock className="mr-2 h-4 w-4" /> Locked</Button>
                                ) : (
                                    <Button
                                        disabled={!allRequiredUploaded || isUploading || isMarkingComplete}
                                        onClick={handleMarkComplete}
                                        className="w-full h-12 text-sm font-bold uppercase tracking-tight shadow-lg transition-all hover:translate-y-[-1px] active:translate-y-[0px]"
                                    >
                                        {isMarkingComplete ? <Spinner className="h-4 w-4 mr-2" /> : <CheckCircle2 className="h-5 w-5 mr-2" />}
                                        Mark as Complete
                                    </Button>
                                )}
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>

            <AlertDialog open={confirmDialog.open} onOpenChange={(open) => !isUploading && setConfirmDialog({ ...confirmDialog, open })}>
                <AlertDialogContent className="rounded-2xl">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-2xl font-black">Confirm Upload</AlertDialogTitle>
                        <AlertDialogDescription>Attach <strong>{confirmDialog.documentName}</strong> to the blockchain?</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isUploading} className="rounded-lg">Wait, go back</AlertDialogCancel>
                        <AlertDialogAction onClick={handleConfirmUpload} disabled={isUploading} className="rounded-lg px-8">Confirm</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
