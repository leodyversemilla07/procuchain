import { ModeBadge } from '@/components/procurement/workflow-progress-indicator';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { DocumentGuide } from '@/types/document-guide';
import { Link } from '@inertiajs/react';
import { CheckCircle2, Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import type { StageUploadWorkflowProps } from './types';

interface WorkflowProgressPanelProps extends StageUploadWorkflowProps {
    documentGuide?: DocumentGuide;
    completionPercentage: number;
    uploadedRequiredCount: number;
    children?: ReactNode;
}

export function WorkflowProgressPanel({
    procurement,
    workflowInfo,
    documentGuide,
    completionPercentage,
    uploadedRequiredCount,
    children,
}: WorkflowProgressPanelProps) {
    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border h-fit shadow-md">
            <CardHeader className="border-b pb-4">
                <CardTitle className="text-muted-foreground flex items-center gap-2 text-xs font-semibold tracking-tight uppercase">
                    <Clock className="h-3.5 w-3.5" />
                    Workflow Progress
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6 p-6">
                {workflowInfo && (
                    <div className="space-y-6">
                        <div className="flex items-center justify-between gap-2">
                            <ModeBadge workflowInfo={workflowInfo} />
                        </div>

                        <div className="flex flex-wrap gap-2.5">
                            {workflowInfo.workflow.stages.map((stage, index) => (
                                <TooltipProvider key={stage.value}>
                                    <Tooltip>
                                        <TooltipTrigger
                                            render={
                                                <Link
                                                    href={stage.url || '#'}
                                                    className={`flex h-9 w-9 items-center justify-center rounded-full border-2 transition-all hover:scale-110 ${
                                                        stage.value === procurement.stage_value
                                                            ? 'bg-primary border-primary text-primary-foreground ring-primary/20 ring-4'
                                                            : stage.is_completed
                                                              ? 'border-green-500 bg-green-500 text-white shadow-sm'
                                                              : stage.is_optional
                                                                ? 'border-muted-foreground/40 text-muted-foreground/40 border-dashed'
                                                                : 'border-muted-foreground/20 text-muted-foreground/20 bg-muted/30'
                                                    }`}
                                                >
                                                    {stage.is_completed ? (
                                                        <CheckCircle2 className="h-4 w-4" />
                                                    ) : (
                                                        <span className="text-xs font-bold">{index + 1}</span>
                                                    )}
                                                </Link>
                                            }
                                        />
                                        <TooltipContent side="right" className="flex flex-col gap-1">
                                            <span className="font-bold">{stage.display_name}</span>
                                            <span className="text-[10px] opacity-70">Step {index + 1}</span>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            ))}
                        </div>

                        {documentGuide && (
                            <div className="space-y-3 border-t pt-4">
                                <div className="flex items-center justify-between text-xs font-bold">
                                    <span className="text-muted-foreground tracking-wider uppercase">Completion</span>
                                    <span className="text-primary">{completionPercentage}%</span>
                                </div>
                                <Progress value={completionPercentage} className="h-2 rounded-full" />
                                <p className="text-muted-foreground text-[10px] italic">
                                    {uploadedRequiredCount} of {documentGuide.counts.required_count} required documents uploaded
                                </p>
                            </div>
                        )}

                        <div className="space-y-2 border-t pt-4 text-[10px]">
                            <div className="flex items-center gap-2">
                                <div className="bg-primary h-2 w-2 rounded-full" /> <span>Current Stage</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="h-2 w-2 rounded-full bg-green-500" /> <span>Completed</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="bg-muted/30 border-muted-foreground/20 h-2 w-2 rounded-full border" />
                                <span>Pending</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="border-muted-foreground/50 h-2 w-2 rounded-full border border-dashed" />
                                <span>Optional</span>
                            </div>
                        </div>
                    </div>
                )}

                {children}
            </CardContent>
        </Card>
    );
}
