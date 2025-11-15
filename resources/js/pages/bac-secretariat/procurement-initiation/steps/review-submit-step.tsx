import React, { useMemo } from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    CheckCircle2,
    FileText,
    DollarSign,
    Building2,
    MapPin,
    Upload,
    Edit,
    AlertCircle,
} from 'lucide-react';
import type { UseFormData, CategoryOption, ProcurementModeOption } from '../types';
import { FUNDING_SOURCES, MUNICIPAL_OFFICES } from '@/types/constants';

interface ReviewSubmitStepProps {
    data: UseFormData;
    categories: CategoryOption[];
    procurementModes: ProcurementModeOption[];
    onEditStep: (stepId: number) => void;
}

export function ReviewSubmitStep({
    data,
    categories,
    procurementModes,
    onEditStep,
}: ReviewSubmitStepProps) {
    const selectedCategory = categories.find((cat) => cat.value === data.category);
    const selectedMode = procurementModes.find((mode) => mode.value === data.procurement_mode);
    const selectedFunding = FUNDING_SOURCES.find((source) => source.value === data.funding_source);
    const selectedOffice = MUNICIPAL_OFFICES.find((office) => office.value === data.office);

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <Card className="border-2 border-primary/20 bg-primary/5">
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                        <div className="rounded-lg bg-primary/10 p-3">
                            <CheckCircle2 className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-base font-semibold sm:text-lg">Review & Submit</h3>
                            <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                Review all information before creating your procurement.
                                Documents will be uploaded progressively in the next step.
                            </p>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Basic Information Summary */}
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader className="p-4 sm:p-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <FileText className="h-4 w-4 text-primary sm:h-5 sm:w-5" />
                                <h3 className="text-sm font-semibold sm:text-base">Basic Information</h3>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onEditStep(1)}
                                className="gap-2"
                            >
                                <Edit className="h-4 w-4" />
                                Edit
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3 p-4 sm:p-6">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">PR Number</p>
                                <p className="mt-1 font-medium">{data.pr_number}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    PPMP Reference
                                </p>
                                <p className="mt-1 font-medium">{data.ppmp_reference}</p>
                            </div>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Title</p>
                            <p className="mt-1 font-medium">{data.title}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Description</p>
                            <p className="mt-1 text-sm">{data.description}</p>
                        </div>
                    </CardContent>
                </Card>

                {/* Classification & Budget Summary */}
                <Card>
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <DollarSign className="h-4 w-4 text-primary sm:h-5 sm:w-5" />
                            <h3 className="text-sm font-semibold sm:text-base">Classification & Budget</h3>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => onEditStep(2)}
                            className="gap-2"
                        >
                            <Edit className="h-4 w-4" />
                            Edit
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-3 p-4 sm:p-6">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Category</p>
                            <p className="mt-1 font-medium">
                                {selectedCategory?.label || data.category}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">
                                Procurement Mode
                            </p>
                            <p className="mt-1 font-medium">{selectedMode?.label || data.procurement_mode}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">ABC Amount</p>
                            <p className="mt-1 font-medium">
                                ₱
                                {parseFloat(data.abc_amount || '0').toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                })}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">
                                Funding Source
                            </p>
                            <p className="mt-1 font-medium">
                                {selectedFunding?.label || data.funding_source}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>

            {/* Office & Purpose and Delivery Details - Grid */}
            <div className="grid gap-6 lg:grid-cols-2">
                {/* Office & Purpose Summary */}
                <Card>
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <Building2 className="h-4 w-4 text-primary sm:h-5 sm:w-5" />
                            <h3 className="text-sm font-semibold sm:text-base">Office & Purpose</h3>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => onEditStep(3)}
                            className="gap-2"
                        >
                            <Edit className="h-4 w-4" />
                            Edit
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-3 p-4 sm:p-6">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Office</p>
                            <p className="mt-1 font-medium">
                                {selectedOffice?.label || data.office}
                            </p>
                        </div>
                        {data.end_user && (
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    End User
                                </p>
                                <p className="mt-1 font-medium">{data.end_user}</p>
                            </div>
                        )}
                    </div>
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">Purpose</p>
                        <p className="mt-1 text-sm">{data.purpose}</p>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">Prepared By</p>
                        <p className="mt-1 font-medium">{data.prepared_by}</p>
                    </div>
                </CardContent>
            </Card>

                {/* Delivery Details Summary */}
                <Card>
                    <CardHeader className="p-4 sm:p-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <MapPin className="h-4 w-4 text-primary sm:h-5 sm:w-5" />
                                <h3 className="text-sm font-semibold sm:text-base">Delivery Details</h3>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onEditStep(4)}
                                className="gap-2"
                            >
                                <Edit className="h-4 w-4" />
                                Edit
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3 p-4 sm:p-6">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Location</p>
                                <p className="mt-1 font-medium">{data.delivery_location}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Date</p>
                                <p className="mt-1 font-medium">
                                    {data.delivery_date
                                        ? new Date(data.delivery_date).toLocaleDateString('en-PH', {
                                              year: 'numeric',
                                              month: 'long',
                                              day: 'numeric',
                                          })
                                        : 'Not set'}
                                </p>
                            </div>
                        </div>
                        {data.delivery_term_days && (
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Delivery Term
                                </p>
                                <p className="mt-1 font-medium">{data.delivery_term_days} days</p>
                            </div>
                        )}
                </CardContent>
            </Card>
            </div>

            {/* Next Steps Info */}
            <Card className="border-2 border-blue-500/20 bg-blue-50/50 dark:bg-blue-950/20">
                <CardContent className="p-4 sm:p-6">
                    <div className="flex gap-3">
                        <div className="mt-1 rounded-lg bg-blue-500/10 p-2">
                            <Upload className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div className="flex-1 space-y-2">
                            <h3 className="font-semibold text-blue-900 dark:text-blue-100">Next: Progressive Document Upload</h3>
                            <p className="text-sm text-blue-700 dark:text-blue-300">
                                After creating this procurement, you'll be redirected to upload required documents progressively. 
                                You can upload them one at a time and save your progress.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
