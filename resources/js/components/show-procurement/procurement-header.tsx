import { Calendar, Clock, FileCheck, Hash, Tag } from 'lucide-react';

import { Stepper } from '@/components/stepper';
import { TruncateBadge } from '@/components/truncate-badge';
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
    phase: string;
    phase_display_name: string;
}

interface WorkflowStage {
    value: string;
    display_name: string;
    url: string;
    is_completed: boolean;
    is_current: boolean;
    is_optional: boolean;
}

interface WorkflowInfo {
    mode: string;
    name: string;
    stages: WorkflowStage[];
}

interface ProcurementHeaderProps {
    title: string;
    pr_number: string;
    status: ProcurementStatus;
    procurementMode?: string;
    procurementModeLabel?: string;
    workflow?: WorkflowInfo;
}

export function ProcurementHeader({ title, pr_number, status, procurementModeLabel, workflow }: ProcurementHeaderProps) {
    const stageToSearch = (status?.stage_formatted || status?.stage) as (typeof STAGE_ORDER)[number];
    const stageIndex = stageToSearch ? STAGE_ORDER.indexOf(stageToSearch) + 1 : 0;
    const totalStages = STAGE_ORDER.length;
    const progress = calculateProgress(status?.stage_formatted || status?.stage);

    return (
        <div className="mb-4 sm:mb-6">
            <Card className="shadow-sm transition-shadow duration-200 hover:shadow-md">
                <CardHeader className="p-4 sm:p-6">
                    {/* Title and ID Section */}
                    <div className="flex flex-col gap-1.5 sm:flex flex-col gap-2">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <CardTitle className="min-w-0 text-xl font-bold tracking-tight sm:text-2xl lg:text-3xl">{title}</CardTitle>
                            {procurementModeLabel && (
                                <TruncateBadge
                                    variant="secondary"
                                    icon={<Tag className="h-3 w-3 sm:h-3.5 sm:w-3.5" aria-hidden="true" />}
                                    className="flex shrink-0 items-center gap-1.5 text-xs font-medium sm:text-sm"
                                    maxChars={22}
                                >
                                    {procurementModeLabel}
                                </TruncateBadge>
                            )}
                        </div>
                        <CardDescription className="flex flex-wrap items-center gap-1.5 text-sm sm:gap-2 sm:text-base">
                            <Hash className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden="true" />
                            <span className="font-mono text-xs sm:text-sm">Procurement ID: {pr_number}</span>
                        </CardDescription>
                    </div>
                </CardHeader>
            </Card>

            {/* Workflow Visualization or Progress Bar */}
            {workflow ? (
                <div className="mt-3 rounded-xl bg-card p-4 shadow-sm ring-1 ring-foreground/10 sm:mt-4 sm:p-6">
                    <div className="flex flex-col gap-4">
                        <div className="text-muted-foreground text-xs font-medium sm:text-sm">
                            <span className="tracking-wider uppercase">Workflow Progress</span>
                        </div>
                        <div className="overflow-x-auto">
                            <Stepper
                                steps={workflow.stages.map((stage, index) => ({
                                    id: index + 1,
                                    title: stage.display_name,
                                    description: stage.is_optional ? '(Optional)' : undefined,
                                }))}
                                currentStep={workflow.stages.findIndex((s) => s.is_current) + 1 || 1}
                            />
                        </div>
                    </div>
                </div>
            ) : progress > 0 ? (
                <Card className="mt-3 shadow-sm sm:mt-4">
                    <CardHeader className="p-4 sm:p-6">
                        <div
                            className="flex flex-col gap-2"
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
                            <div className="bg-muted relative h-2.5 w-full overflow-hidden rounded-full sm:h-3">
                                <div
                                    className="bg-primary absolute inset-y-0 left-0 rounded-full shadow-sm transition-all duration-500 ease-out"
                                    style={{ width: `${progress}%` }}
                                >
                                    <div className="absolute inset-0 animate-pulse bg-white/20"></div>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                </Card>
            ) : null}

            {/* Status Information Cards */}
            <div className="mt-3 grid grid-cols-1 gap-3 sm:mt-4 sm:grid-cols-2 sm:gap-4">
                {/* Current Phase */}
                {status?.phase && (
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="text-muted-foreground mb-2 flex items-center gap-2 text-xs font-medium sm:text-sm">
                            <FileCheck aria-hidden="true" />
                            Current Phase
                        </div>
                        <TruncateBadge variant="outline" className="text-xs font-medium sm:text-sm" maxChars={20}>
                            {status.phase_display_name}
                        </TruncateBadge>
                    </div>
                )}

                {/* Current Stage */}
                {status?.stage && (
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="text-muted-foreground mb-2 flex items-center gap-2 text-xs font-medium sm:text-sm">
                            <FileCheck aria-hidden="true" />
                            Current Stage
                        </div>
                        <div className="flex flex-col gap-1">
                            <TruncateBadge variant="secondary" className="text-xs font-medium sm:text-sm" maxChars={20}>
                                {status.stage_formatted || status.stage}
                            </TruncateBadge>
                            {status.stage_description && (
                                <p className="text-muted-foreground line-clamp-2 text-xs italic">{status.stage_description}</p>
                            )}
                            <div className="text-muted-foreground text-xs">
                                Stage {stageIndex} of {totalStages}
                            </div>
                        </div>
                    </div>
                )}

                {/* Current Status */}
                {status?.status_formatted && (
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="text-muted-foreground mb-2 flex items-center gap-2 text-xs font-medium sm:text-sm">
                            <Clock aria-hidden="true" />
                            Status
                        </div>
                        <TruncateBadge
                            variant="default"
                            className="inline-flex w-fit items-center gap-1.5 text-xs font-medium sm:text-sm"
                            maxChars={16}
                        >
                            {status.status_formatted}
                        </TruncateBadge>
                    </div>
                )}

                {/* Last Updated */}
                {status?.timestamp && (
                    <div className="bg-card rounded-lg border p-4 shadow-sm">
                        <div className="text-muted-foreground mb-2 flex items-center gap-2 text-xs font-medium sm:text-sm">
                            <Calendar aria-hidden="true" />
                            Last Updated
                        </div>
                        <time className="text-foreground text-xs font-medium sm:text-sm">{status.formatted_date}</time>
                    </div>
                )}
            </div>
        </div>
    );
}
