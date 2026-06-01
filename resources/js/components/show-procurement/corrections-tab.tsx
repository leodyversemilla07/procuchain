import { Spinner } from '@/components/ui/spinner';
import { CheckCircle, Clock, Edit, FileText } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { DatePickerInput } from '@/components/date-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';

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

interface ProcurementCorrectionsTabProps {
    prNumber: string;
    hasCorrections?: boolean;
    latestCorrection?: {
        timestamp: string;
        corrected_by: string;
        reason: string;
        changed_fields: string[];
    };
    corrections?: ProcurementCorrection[];
    procurement?: {
        title: string;
        description: string;
        abc_amount: number;
        formatted_abc_amount: string;
        funding_source: string;
        category: string;
        procurement_mode: string;
        office: string;
        end_user: string;
        bac_resolution_number: string;
        bac_resolution_date: string;
        philgeps_reference: string;
        philgeps_posting_date: string;
        approved_by: string;
        approval_date: string;
    };
}

export function ProcurementCorrectionsTab({ prNumber, latestCorrection, corrections = [], procurement }: ProcurementCorrectionsTabProps) {
    const [submitting, setSubmitting] = useState(false);
    const [showCorrectionDialog, setShowCorrectionDialog] = useState(false);
    const [correctionForm, setCorrectionForm] = useState({
        correction_reason: '',
        title: procurement?.title || '',
        description: procurement?.description || '',
        abc_amount: procurement?.abc_amount?.toString() || '',
        funding_source: procurement?.funding_source || '',
        category: procurement?.category || '',
        procurement_mode: procurement?.procurement_mode || '',
        office: procurement?.office || '',
        end_user: procurement?.end_user || '',
        bac_resolution_number: procurement?.bac_resolution_number || '',
        bac_resolution_date: procurement?.bac_resolution_date || '',
        philgeps_reference: procurement?.philgeps_reference || '',
        philgeps_posting_date: procurement?.philgeps_posting_date || '',
        approved_by: procurement?.approved_by || '',
        approval_date: procurement?.approval_date || '',
    });

    // Load corrections history - removed since data comes from props
    // const loadCorrections = useCallback(async () => { ... }, [prNumber, toast]);

    // useEffect(() => {
    //     if (hasCorrections) {
    //         loadCorrections();
    //     }
    // }, [hasCorrections, loadCorrections]);

    // Handle form submission
    const handleSubmitCorrection = async (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        try {
            const formData = new FormData();
            Object.entries(correctionForm).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    formData.append(key, String(value));
                }
            });

            router.post(`/procurements/${prNumber}/corrections`, formData, {
                onSuccess: () => {
                    setShowCorrectionDialog(false);
                    setCorrectionForm({
                        correction_reason: '',
                        title: procurement?.title || '',
                        description: procurement?.description || '',
                        abc_amount: procurement?.abc_amount?.toString() || '',
                        funding_source: procurement?.funding_source || '',
                        category: procurement?.category || '',
                        procurement_mode: procurement?.procurement_mode || '',
                        office: procurement?.office || '',
                        end_user: procurement?.end_user || '',
                        bac_resolution_number: procurement?.bac_resolution_number || '',
                        bac_resolution_date: procurement?.bac_resolution_date || '',
                        philgeps_reference: procurement?.philgeps_reference || '',
                        philgeps_posting_date: procurement?.philgeps_posting_date || '',
                        approved_by: procurement?.approved_by || '',
                        approval_date: procurement?.approval_date || '',
                    });
                    // Reload the page to get updated correction data
                    router.reload();
                },
                onError: (errors) => {
                    toast.error(errors.message || 'Failed to submit correction.');
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            });
        } catch (error) {
            console.error('Failed to submit correction:', error);
            toast.error('Failed to submit correction. Please try again.');
            setSubmitting(false);
        }
    };

    const formatFieldName = (field: string): string => {
        return field
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    return (
        <div className="space-y-6">
            {/* Header with submit button */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <h3 className="text-lg font-semibold">Procurement Corrections</h3>
                    <p className="text-muted-foreground text-sm">View and submit corrections to procurement metadata</p>
                </div>
                <Sheet open={showCorrectionDialog} onOpenChange={setShowCorrectionDialog}>
                    <SheetTrigger render={<Button />}>
                        <Edit className="mr-2 h-4 w-4" />
                        Submit Correction
                    </SheetTrigger>
                    <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-2xl">
                        <SheetHeader>
                            <SheetTitle>Submit Procurement Correction</SheetTitle>
                            <SheetDescription>
                                Correct any inaccuracies in the procurement metadata. All corrections are recorded on the blockchain.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="space-y-4">
                            {/* Correction Reason */}
                            <div className="space-y-2">
                                <Label htmlFor="correction_reason">Reason for Correction *</Label>
                                <Textarea
                                    id="correction_reason"
                                    value={correctionForm.correction_reason}
                                    onChange={(e) => setCorrectionForm((prev) => ({ ...prev, correction_reason: e.target.value }))}
                                    placeholder="Please explain why this correction is needed..."
                                    required
                                    rows={3}
                                />
                            </div>

                            {/* Basic Information */}
                            <div className="space-y-4">
                                <h4 className="text-muted-foreground text-sm font-medium tracking-wide uppercase">Basic Information</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="title">Title</Label>
                                        <Input
                                            id="title"
                                            value={correctionForm.title}
                                            onChange={(e) => setCorrectionForm((prev) => ({ ...prev, title: e.target.value }))}
                                            placeholder="Enter corrected title"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="abc_amount">ABC Amount</Label>
                                        <Input
                                            id="abc_amount"
                                            type="number"
                                            step="0.01"
                                            value={correctionForm.abc_amount}
                                            onChange={(e) => setCorrectionForm((prev) => ({ ...prev, abc_amount: e.target.value }))}
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label htmlFor="description">Description</Label>
                                        <Textarea
                                            id="description"
                                            value={correctionForm.description}
                                            onChange={(e) => setCorrectionForm((prev) => ({ ...prev, description: e.target.value }))}
                                            placeholder="Enter corrected description"
                                            rows={2}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Classification */}
                            <div className="space-y-4">
                                <h4 className="text-muted-foreground text-sm font-medium tracking-wide uppercase">Classification</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="category">Category</Label>
                                        <Select
                                            value={correctionForm.category}
                                            onValueChange={(value) => value && setCorrectionForm((prev) => ({ ...prev, category: value }))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select category" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="goods">Goods</SelectItem>
                                                    <SelectItem value="services">Services</SelectItem>
                                                    <SelectItem value="works">Works</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="procurement_mode">Procurement Mode</Label>
                                        <Select
                                            value={correctionForm.procurement_mode}
                                            onValueChange={(value) => value && setCorrectionForm((prev) => ({ ...prev, procurement_mode: value }))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select procurement mode" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="competitive_bidding">Competitive Bidding</SelectItem>
                                                    <SelectItem value="limited_source_bidding">Limited Source Bidding</SelectItem>
                                                    <SelectItem value="competitive_dialogue">Competitive Dialogue</SelectItem>
                                                    <SelectItem value="unsolicited_offer_with_bid_matching">
                                                        Unsolicited Offer with Bid Matching
                                                    </SelectItem>
                                                    <SelectItem value="direct_contracting">Direct Contracting</SelectItem>
                                                    <SelectItem value="direct_acquisition">Direct Acquisition</SelectItem>
                                                    <SelectItem value="repeat_order">Repeat Order</SelectItem>
                                                    <SelectItem value="small_value_procurement">Small Value Procurement</SelectItem>
                                                    <SelectItem value="negotiated_procurement">Negotiated Procurement</SelectItem>
                                                    <SelectItem value="direct_sales">Direct Sales</SelectItem>
                                                    <SelectItem value="direct_procurement_for_sti">Direct Procurement for STI</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            {/* Office & End User */}
                            <div className="space-y-4">
                                <h4 className="text-muted-foreground text-sm font-medium tracking-wide uppercase">Office & End User</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="office">Office</Label>
                                        <Input
                                            id="office"
                                            value={correctionForm.office}
                                            onChange={(e) => setCorrectionForm((prev) => ({ ...prev, office: e.target.value }))}
                                            placeholder="Enter corrected office"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="end_user">End User</Label>
                                        <Input
                                            id="end_user"
                                            value={correctionForm.end_user}
                                            onChange={(e) => setCorrectionForm((prev) => ({ ...prev, end_user: e.target.value }))}
                                            placeholder="Enter corrected end user"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* BAC Resolution */}
                            <div className="space-y-4">
                                <h4 className="text-muted-foreground text-sm font-medium tracking-wide uppercase">BAC Resolution</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="bac_resolution_number">BAC Resolution Number</Label>
                                        <Input
                                            id="bac_resolution_number"
                                            value={correctionForm.bac_resolution_number}
                                            onChange={(e) => setCorrectionForm((prev) => ({ ...prev, bac_resolution_number: e.target.value }))}
                                            placeholder="Enter BAC resolution number"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <DatePickerInput
                                            id="bac_resolution_date"
                                            value={correctionForm.bac_resolution_date}
                                            onChange={(value) => setCorrectionForm((prev) => ({ ...prev, bac_resolution_date: value }))}
                                            placeholder="Select BAC resolution date"
                                            label="BAC Resolution Date"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* PhilGEPS */}
                            <div className="space-y-4">
                                <h4 className="text-muted-foreground text-sm font-medium tracking-wide uppercase">PhilGEPS</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="philgeps_reference">PhilGEPS Reference</Label>
                                        <Input
                                            id="philgeps_reference"
                                            value={correctionForm.philgeps_reference}
                                            onChange={(e) => setCorrectionForm((prev) => ({ ...prev, philgeps_reference: e.target.value }))}
                                            placeholder="Enter PhilGEPS reference"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <DatePickerInput
                                            id="philgeps_posting_date"
                                            value={correctionForm.philgeps_posting_date}
                                            onChange={(value) => setCorrectionForm((prev) => ({ ...prev, philgeps_posting_date: value }))}
                                            placeholder="Select PhilGEPS posting date"
                                            label="PhilGEPS Posting Date"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Approval */}
                            <div className="space-y-4">
                                <h4 className="text-muted-foreground text-sm font-medium tracking-wide uppercase">Approval</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="approved_by">Approved By</Label>
                                        <Input
                                            id="approved_by"
                                            value={correctionForm.approved_by}
                                            onChange={(e) => setCorrectionForm((prev) => ({ ...prev, approved_by: e.target.value }))}
                                            placeholder="Enter approver name"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <DatePickerInput
                                            id="approval_date"
                                            value={correctionForm.approval_date}
                                            onChange={(value) => setCorrectionForm((prev) => ({ ...prev, approval_date: value }))}
                                            placeholder="Select approval date"
                                            label="Approval Date"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end space-x-2 pt-4">
                                <Button type="button" variant="outline" onClick={() => setShowCorrectionDialog(false)} disabled={submitting}>
                                    Cancel
                                </Button>
                                <Button onClick={handleSubmitCorrection} disabled={submitting}>
                                    {submitting ? (
                                        <>
                                            <Spinner data-icon="inline-start" />
                                            Submitting...
                                        </>
                                    ) : (
                                        <>
                                            <CheckCircle className="mr-2 h-4 w-4" />
                                            Submit Correction
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>

            {/* Latest Correction Summary */}
            {latestCorrection && (
                <div className="rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                    <div className="flex items-start gap-4">
                        <div className="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                            <Clock className="h-5 w-5" />
                        </div>
                        <div className="min-w-0 flex-1 space-y-1">
                            <div className="flex min-w-0 flex-wrap items-center justify-between gap-1">
                                <h4 className="shrink-0 font-semibold text-amber-900">Latest Correction</h4>
                                <span className="text-xs font-medium text-amber-700">
                                    {new Date(latestCorrection.timestamp).toLocaleDateString()}
                                </span>
                            </div>
                            <p className="min-w-0 text-sm break-words text-amber-800">{latestCorrection.reason}</p>
                            <div className="mt-3 flex min-w-0 flex-wrap items-center gap-2 text-xs">
                                <span className="shrink-0 font-medium text-amber-900">Changed:</span>
                                {latestCorrection.changed_fields.map((field, index) => (
                                    <Badge
                                        key={index}
                                        variant="outline"
                                        className="border-amber-300 bg-amber-100/50 text-amber-800 hover:bg-amber-100"
                                    >
                                        {formatFieldName(field)}
                                    </Badge>
                                ))}
                                <span className="ml-auto shrink-0 truncate text-amber-700">by {latestCorrection.corrected_by}</span>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Corrections History */}
            <div className="space-y-4">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h3 className="text-lg font-semibold">History</h3>
                </div>

                {corrections.length > 0 ? (
                    <div className="border-border relative ml-3 space-y-8 border-l pl-8">
                        {corrections.map((correction, index) => (
                            <div key={index} className="min-w-0">
                                {/* Timeline Dot */}
                                <div className="bg-background ring-background absolute top-1.5 -left-[37px] flex h-5 w-5 items-center justify-center rounded-full border ring-4">
                                    <div className="bg-muted-foreground h-2 w-2 rounded-full" />
                                </div>

                                <div className="flex min-w-0 flex-col gap-2">
                                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <span className="min-w-0 truncate text-sm font-semibold">{correction.correction_type_display}</span>
                                            <Badge variant="secondary" className="h-5 shrink-0 px-1.5 text-[10px]">
                                                {correction.corrected_by}
                                            </Badge>
                                        </div>
                                        <time className="text-muted-foreground shrink-0 font-mono text-xs">
                                            {new Date(correction.timestamp).toLocaleString()}
                                        </time>
                                    </div>

                                    <div className="bg-muted/30 min-w-0 rounded-lg border p-3 text-sm">
                                        <div className="text-muted-foreground mb-2 text-xs font-medium tracking-wide uppercase">Reason</div>
                                        <p className="break-words">{correction.reason}</p>

                                        {correction.changed_fields.length > 0 && (
                                            <>
                                                <div className="text-muted-foreground mt-3 mb-2 text-xs font-medium tracking-wide uppercase">
                                                    Changes
                                                </div>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {correction.changed_fields.map((field, fieldIndex) => (
                                                        <Badge key={fieldIndex} variant="outline" className="bg-background text-xs">
                                                            {formatFieldName(field)}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed p-8">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon" className="bg-muted/50 rounded-full p-4">
                                    <FileText className="text-muted-foreground h-8 w-8" />
                                </EmptyMedia>
                                <EmptyTitle className="mt-4">No Corrections Yet</EmptyTitle>
                                <EmptyDescription>
                                    This procurement has not been corrected. Corrections will appear here once submitted.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </div>
                )}
            </div>
        </div>
    );
}
