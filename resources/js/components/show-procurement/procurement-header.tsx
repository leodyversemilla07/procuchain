import { Calendar, Clock, FileCheck, Hash, ArrowRight } from 'lucide-react';
import { router } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { STAGE_ORDER } from '@/types/constants';

import { calculateProgress } from '../../utils/show-procurement/helpers';

interface ProcurementStatus {
    stage: string;
    stage_formatted: string;
    stage_description?: string;
    stage_order: number;
    current_status: string;
    status_formatted?: string;
    timestamp: string;
    formatted_date: string;
    formatted_date_only: string;
    pr_number?: string;
    procurement_title?: string;
    user_address?: string;
    progress: number;
    total_stages: number;
}

interface ProcurementHeaderProps {
    title: string;
    pr_number: string;
    status: ProcurementStatus;
    userRole?: string;
}

export function ProcurementHeader({ title, pr_number, status, userRole }: ProcurementHeaderProps) {
    const stageToSearch = (status?.stage_formatted || status?.stage) as typeof STAGE_ORDER[number];
    const stageIndex = stageToSearch ? STAGE_ORDER.indexOf(stageToSearch) + 1 : 0;
    const totalStages = STAGE_ORDER.length;
    const progress = calculateProgress(status?.stage_formatted || status?.stage);

    // Map stage to next action route
    const getNextStageRoute = () => {
        const stage = status?.stage || '';
        const routes: Record<string, string> = {
            'procurement_initiation': `/bac-secretariat/pre-procurement-conference-upload/${pr_number}`,
            'pre_procurement_conference': `/bac-secretariat/bidding-documents-upload/${pr_number}`,
            'bidding_documents': `/bac-secretariat/pre-bid-conference-upload/${pr_number}`,
            'pre_bid_conference': `/bac-secretariat/supplemental-bid-bulletin-upload/${pr_number}`,
            'supplemental_bid_bulletin': `/bac-secretariat/bid-opening-upload/${pr_number}`,
            'bid_opening': `/bac-secretariat/bid-evaluation-upload/${pr_number}`,
            'bid_evaluation': `/bac-secretariat/post-qualification-upload/${pr_number}`,
            'post_qualification': `/bac-secretariat/bac-resolution-upload/${pr_number}`,
            'bac_resolution': `/bac-secretariat/noa-upload/${pr_number}`,
            'notice_of_award': `/bac-secretariat/performance-bond-contract-po-upload/${pr_number}`,
            'performance_bond_contract_and_po': `/bac-secretariat/ntp-upload/${pr_number}`,
            'notice_to_proceed': `/bac-secretariat/monitoring-upload/${pr_number}`,
            'monitoring': `/bac-secretariat/completion-upload/${pr_number}`,
        };
        return routes[stage];
    };

    const handleNextStage = () => {
        const nextRoute = getNextStageRoute();
        if (nextRoute) {
            router.visit(nextRoute);
        }
    };

    const canProceedToNextStage = userRole === 'bac_secretariat' && status?.current_status && getNextStageRoute();

    return (
        <Card className="mb-4 overflow-hidden border shadow-sm transition-shadow duration-200 hover:shadow-md sm:mb-6">
            {/* Header Accent Bar */}
            <div className="h-1 w-full bg-primary sm:h-1.5" aria-hidden="true"></div>

            <CardHeader className="space-y-4 p-4 pb-4 sm:space-y-6 sm:p-6 sm:pb-6">
                {/* Title and ID Section */}
                <div className="space-y-1.5 sm:space-y-2">
                    <CardTitle className="text-xl font-bold tracking-tight sm:text-2xl lg:text-3xl">{title}</CardTitle>
                    <CardDescription className="flex flex-wrap items-center gap-1.5 text-sm sm:gap-2 sm:text-base">
                        <Hash className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                        <span className="font-mono text-xs sm:text-sm">Procurement ID: {pr_number}</span>
                    </CardDescription>
                </div>

                {/* Progress Bar */}
                {progress > 0 && (
                    <div
                        className="w-full space-y-1.5 sm:space-y-2"
                        role="progressbar"
                        aria-valuenow={progress}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label={`Procurement progress: ${progress.toFixed(0)}%`}
                    >
                        <div className="text-muted-foreground flex justify-between text-xs font-medium sm:text-sm">
                            <span>Overall Progress</span>
                            <span className="font-semibold">{progress.toFixed(0)}%</span>
                        </div>
                        <div className="relative h-2.5 w-full overflow-hidden rounded-full bg-muted sm:h-3">
                            <div
                                className="absolute inset-y-0 left-0 rounded-full bg-primary shadow-sm transition-all duration-500 ease-out"
                                style={{ width: `${progress}%` }}
                            >
                                <div className="absolute inset-0 animate-pulse bg-white/20"></div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Status Information Grid */}
                <div className="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                    {/* Current Stage */}
                    {status?.stage && (
                        <div className="rounded-lg border bg-muted p-3 transition-all duration-200 hover:shadow-sm sm:p-4">
                            <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground sm:mb-2 sm:gap-2 sm:text-sm">
                                <FileCheck className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                Current Stage
                            </div>
                            <Badge variant="secondary" className="mb-1.5 text-xs font-medium sm:mb-2 sm:text-sm">
                                {status.stage_formatted || status.stage}
                            </Badge>
                            {status.stage_description && (
                                <p className="line-clamp-2 text-[11px] italic text-muted-foreground sm:text-xs">
                                    {status.stage_description}
                                </p>
                            )}
                            <div className="mt-1.5 text-[11px] text-muted-foreground sm:mt-2 sm:text-xs">
                                Stage {stageIndex} of {totalStages}
                            </div>
                        </div>
                    )}

                    {/* Current Status */}
                    {status?.status_formatted && (
                        <div className="rounded-lg border bg-muted p-3 transition-all duration-200 hover:shadow-sm sm:p-4">
                            <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground sm:mb-2 sm:gap-2 sm:text-sm">
                                <Clock className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                Status
                            </div>
                            <Badge
                                variant="default"
                                className="inline-flex w-fit items-center gap-1 text-xs font-medium sm:gap-1.5 sm:text-sm"
                            >
                                {status.status_formatted}
                            </Badge>
                        </div>
                    )}

                    {/* Last Updated */}
                    {status?.timestamp && (
                        <div className="rounded-lg border bg-muted p-3 transition-all duration-200 hover:shadow-sm sm:col-span-2 sm:p-4 lg:col-span-1">
                            <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground sm:mb-2 sm:gap-2 sm:text-sm">
                                <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                                Last Updated
                            </div>
                            <time
                                dateTime={status.timestamp}
                                className="block text-xs font-medium sm:text-sm"
                            >
                                {status.formatted_date}
                            </time>
                        </div>
                    )}
                </div>

                {/* Action Button for Next Stage */}
                {canProceedToNextStage && (
                    <div className="pt-2">
                        <Button 
                            onClick={handleNextStage}
                            className="w-full sm:w-auto"
                            size="lg"
                        >
                            Proceed to Next Stage
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                )}
            </CardHeader>
        </Card>
    );
}

