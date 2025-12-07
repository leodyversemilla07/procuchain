import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { WorkflowInfo } from '@/types';
import { CheckCircle2, ChevronLeft, ChevronRight, Info, SkipForward } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface WorkflowProgressIndicatorProps {
    workflowInfo: WorkflowInfo;
    compact?: boolean;
}

export function WorkflowProgressIndicator({ workflowInfo, compact = false }: WorkflowProgressIndicatorProps) {
    const { mode, workflow } = workflowInfo;
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const [showLeftArrow, setShowLeftArrow] = useState(false);
    const [showRightArrow, setShowRightArrow] = useState(false);

    // Check scroll position for arrow visibility
    const checkScrollArrows = () => {
        const container = scrollContainerRef.current;
        if (container) {
            setShowLeftArrow(container.scrollLeft > 0);
            setShowRightArrow(container.scrollLeft < container.scrollWidth - container.clientWidth - 1);
        }
    };

    useEffect(() => {
        checkScrollArrows();
        window.addEventListener('resize', checkScrollArrows);
        return () => window.removeEventListener('resize', checkScrollArrows);
    }, [workflow.stages]);

    const scrollLeft = () => {
        scrollContainerRef.current?.scrollBy({ left: -100, behavior: 'smooth' });
    };

    const scrollRight = () => {
        scrollContainerRef.current?.scrollBy({ left: 100, behavior: 'smooth' });
    };

    if (!mode) {
        return null;
    }

    if (compact) {
        return (
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="outline" className="bg-primary/5 text-primary border-primary/20">
                    {mode.display_name}
                </Badge>
                <span className="text-muted-foreground text-xs">
                    Stage {workflow.current_index + 1} of {workflow.total_stages}
                </span>
            </div>
        );
    }

    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
            <CardContent className="p-3 sm:p-4 md:p-6">
                {/* Mode Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary" className="bg-primary/10 text-primary hover:bg-primary/20 text-xs sm:text-sm">
                            {mode.display_name}
                        </Badge>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button className="text-muted-foreground hover:text-foreground transition-colors">
                                        <Info className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent side="bottom" className="max-w-[280px] sm:max-w-sm">
                                    <p className="text-xs sm:text-sm">{mode.description}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Reference: NGPA IRR {mode.irr_section}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div className="text-muted-foreground text-xs sm:text-sm">
                        <span className="text-foreground font-medium">Stage {workflow.current_index + 1}</span> of {workflow.total_stages}
                    </div>
                </div>

                {/* Progress Bar */}
                <div className="mt-3 space-y-1.5 sm:mt-4 sm:space-y-2">
                    <Progress value={workflow.progress_percentage} className="h-1.5 sm:h-2" />
                    <p className="text-muted-foreground text-[10px] sm:text-xs">{workflow.progress_percentage}% complete</p>
                </div>

                {/* Stage Indicators - Mobile: Scrollable with arrows, Desktop: Wrap */}
                <div className="relative mt-3 sm:mt-4">
                    {/* Mobile: Scrollable container with navigation arrows */}
                    <div className="block lg:hidden">
                        {showLeftArrow && (
                            <button
                                onClick={scrollLeft}
                                className="bg-background/90 border-border hover:bg-muted absolute top-1/2 left-0 z-10 -translate-y-1/2 rounded-full border p-1 shadow-md backdrop-blur-sm transition-colors"
                                aria-label="Scroll left"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </button>
                        )}
                        <div ref={scrollContainerRef} className="scrollbar-hide overflow-x-auto px-6" onScroll={checkScrollArrows}>
                            <div className="flex gap-1.5 pb-2">
                                {workflow.stages.map((stage, index) => (
                                    <TooltipProvider key={stage.value}>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <div
                                                    className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 transition-colors sm:h-8 sm:w-8 ${
                                                        stage.is_current
                                                            ? 'bg-primary border-primary text-primary-foreground'
                                                            : stage.is_completed
                                                              ? 'border-green-500 bg-green-500 text-white dark:border-green-600 dark:bg-green-600'
                                                              : stage.is_optional
                                                                ? 'border-muted-foreground/50 text-muted-foreground/50 border-dashed'
                                                                : 'border-muted-foreground/30 text-muted-foreground/30'
                                                    }`}
                                                >
                                                    {stage.is_completed ? (
                                                        <CheckCircle2 className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                    ) : stage.is_optional ? (
                                                        <SkipForward className="h-2.5 w-2.5 sm:h-3 sm:w-3" />
                                                    ) : (
                                                        <span className="text-[10px] font-medium sm:text-xs">{index + 1}</span>
                                                    )}
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent side="bottom">
                                                <div className="space-y-1">
                                                    <p className="text-xs font-medium sm:text-sm">{stage.display_name}</p>
                                                    <div className="flex flex-wrap items-center gap-1 text-xs">
                                                        {stage.is_current && (
                                                            <Badge variant="default" className="px-1.5 py-0 text-[10px]">
                                                                Current
                                                            </Badge>
                                                        )}
                                                        {stage.is_completed && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="bg-green-100 px-1.5 py-0 text-[10px] text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                                            >
                                                                Completed
                                                            </Badge>
                                                        )}
                                                        {stage.is_optional && (
                                                            <Badge variant="outline" className="px-1.5 py-0 text-[10px]">
                                                                Optional
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                ))}
                            </div>
                        </div>
                        {showRightArrow && (
                            <button
                                onClick={scrollRight}
                                className="bg-background/90 border-border hover:bg-muted absolute top-1/2 right-0 z-10 -translate-y-1/2 rounded-full border p-1 shadow-md backdrop-blur-sm transition-colors"
                                aria-label="Scroll right"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        )}
                    </div>

                    {/* Desktop: Flex wrap */}
                    <div className="hidden lg:flex lg:flex-wrap lg:gap-1.5">
                        {workflow.stages.map((stage, index) => (
                            <TooltipProvider key={stage.value}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <div
                                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 transition-colors ${
                                                stage.is_current
                                                    ? 'bg-primary border-primary text-primary-foreground'
                                                    : stage.is_completed
                                                      ? 'border-green-500 bg-green-500 text-white dark:border-green-600 dark:bg-green-600'
                                                      : stage.is_optional
                                                        ? 'border-muted-foreground/50 text-muted-foreground/50 border-dashed'
                                                        : 'border-muted-foreground/30 text-muted-foreground/30'
                                            }`}
                                        >
                                            {stage.is_completed ? (
                                                <CheckCircle2 className="h-4 w-4" />
                                            ) : stage.is_optional ? (
                                                <SkipForward className="h-3 w-3" />
                                            ) : (
                                                <span className="text-xs font-medium">{index + 1}</span>
                                            )}
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <div className="space-y-1">
                                            <p className="font-medium">{stage.display_name}</p>
                                            <div className="flex items-center gap-2 text-xs">
                                                {stage.is_current && (
                                                    <Badge variant="default" className="text-xs">
                                                        Current
                                                    </Badge>
                                                )}
                                                {stage.is_completed && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                                    >
                                                        Completed
                                                    </Badge>
                                                )}
                                                {stage.is_optional && (
                                                    <Badge variant="outline" className="text-xs">
                                                        Optional
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        ))}
                    </div>
                </div>

                {/* Legend - Visible on all screens, compact on mobile */}
                <div className="text-muted-foreground mt-3 flex flex-wrap items-center gap-2 text-[10px] sm:mt-4 sm:gap-4 sm:text-xs">
                    <div className="flex items-center gap-1">
                        <div className="bg-primary h-2.5 w-2.5 rounded-full sm:h-3 sm:w-3" />
                        <span>Current</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-2.5 w-2.5 rounded-full bg-green-500 sm:h-3 sm:w-3 dark:bg-green-600" />
                        <span>Completed</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="border-muted-foreground/30 h-2.5 w-2.5 rounded-full border-2 sm:h-3 sm:w-3" />
                        <span>Pending</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="border-muted-foreground/50 h-2.5 w-2.5 rounded-full border-2 border-dashed sm:h-3 sm:w-3" />
                        <span>Optional</span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * Compact mode badge for display in page headers
 */
export function ModeBadge({ workflowInfo }: { workflowInfo: WorkflowInfo }) {
    const { mode } = workflowInfo;

    if (!mode) {
        return null;
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge variant="outline" className="bg-primary/5 text-primary border-primary/20 cursor-help">
                        {mode.display_name}
                    </Badge>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-sm">
                    <p className="text-sm">{mode.description}</p>
                    <p className="text-muted-foreground mt-1 text-xs">Reference: NGPA IRR {mode.irr_section}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
