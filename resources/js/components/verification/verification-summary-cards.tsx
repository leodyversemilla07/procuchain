import { FileCheck, FileText, Link2, Scale } from 'lucide-react';

import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

// =============================================================================
// Types
// =============================================================================

interface SummaryCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    isValid: boolean;
    icon: React.ComponentType<{ className?: string }>;
    tooltipText?: string;
    showProgress?: boolean;
    progressValue?: number;
}

// =============================================================================
// SummaryCard Component
// =============================================================================

export function SummaryCard({ title, value, subtitle, isValid, icon: Icon, tooltipText, showProgress, progressValue }: SummaryCardProps) {
    const content = (
        <Card
            className={cn(
                'transition-all hover:shadow-md',
                isValid ? 'hover:border-green-300 dark:hover:border-green-700' : 'hover:border-amber-300 dark:hover:border-amber-700',
            )}
        >
            <CardContent className="p-3 pt-4 sm:px-6 sm:pt-6 sm:pb-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1 space-y-0.5 sm:space-y-1">
                        <p className="text-muted-foreground truncate text-xs font-medium sm:text-sm">{title}</p>
                        <p
                            className={cn(
                                'text-xl font-bold tabular-nums sm:text-2xl',
                                isValid ? 'text-foreground' : 'text-amber-600 dark:text-amber-400',
                            )}
                        >
                            {value}
                        </p>
                        {subtitle && <p className="text-muted-foreground truncate text-[10px] sm:text-xs">{subtitle}</p>}
                        {showProgress && progressValue !== undefined && (
                            <Progress value={progressValue} className="mt-1.5 h-1 sm:mt-2 sm:h-1.5" aria-label={`${title}: ${progressValue}%`} />
                        )}
                    </div>
                    <div
                        className={cn(
                            'shrink-0 rounded-full p-1.5 sm:p-2.5',
                            isValid ? 'bg-green-100 dark:bg-green-900/50' : 'bg-amber-100 dark:bg-amber-900/50',
                        )}
                    >
                        <Icon
                            className={cn(
                                'h-4 w-4 sm:h-5 sm:w-5',
                                isValid ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400',
                            )}
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    );

    if (tooltipText) {
        return (
            <Tooltip>
                <TooltipTrigger render={content} />
                <TooltipContent side="bottom" className="max-w-xs">
                    <p>{tooltipText}</p>
                </TooltipContent>
            </Tooltip>
        );
    }

    return content;
}

// =============================================================================
// VerificationSummaryCards Component
// =============================================================================

interface VerificationSummaryCardsProps {
    report: {
        summary: {
            integrity_valid: boolean;
            documents_verified: number;
            completeness_percentage: number;
            cross_references_consistent: boolean;
            ra_12009_compliant: boolean;
            critical_issues: number;
            warnings: number;
        };
        completeness_result: {
            is_complete: boolean;
            document_counts: {
                required: number;
                uploaded: number;
                uploaded_optional: number;
                missing: number;
            };
        };
    };
}

export function VerificationSummaryCards({ report }: VerificationSummaryCardsProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <SummaryCard
                title="Documents Verified"
                value={report.summary.documents_verified}
                subtitle={report.summary.integrity_valid ? 'All hashes match' : 'Hash mismatch detected'}
                isValid={report.summary.integrity_valid}
                icon={FileCheck}
                tooltipText="Number of documents with verified blockchain integrity"
            />
            <SummaryCard
                title="Completeness"
                value={`${report.summary.completeness_percentage.toFixed(0)}%`}
                subtitle={`${report.completeness_result.document_counts.uploaded}/${report.completeness_result.document_counts.required} documents`}
                isValid={report.completeness_result.is_complete}
                icon={FileText}
                tooltipText="Percentage of required documents uploaded for this stage"
                showProgress
                progressValue={report.summary.completeness_percentage}
            />
            <SummaryCard
                title="Cross-References"
                value={report.summary.cross_references_consistent ? 'Valid' : 'Issues'}
                subtitle={report.summary.cross_references_consistent ? 'All references match' : 'Inconsistencies found'}
                isValid={report.summary.cross_references_consistent}
                icon={Link2}
                tooltipText="Validates PR numbers and amounts across all documents"
            />
            <SummaryCard
                title="RA 12009"
                value={report.summary.ra_12009_compliant ? 'Compliant' : 'Issues'}
                subtitle={report.summary.ra_12009_compliant ? 'Meets NGPA requirements' : 'Review required'}
                isValid={report.summary.ra_12009_compliant}
                icon={Scale}
                tooltipText="Compliance with Philippine Government Procurement Reform Act"
            />
        </div>
    );
}
