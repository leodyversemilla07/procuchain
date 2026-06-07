import { format, formatDistanceToNow } from 'date-fns';
import { AlertCircle, AlertTriangle, ArrowLeft, ClipboardCheck, Download, Info, Printer, RefreshCw, ShieldCheck, XCircle } from 'lucide-react';

import { HeroCard } from '@/components/hero-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { VerificationStatus } from '@/components/verification';
import { VerificationDetailTabs } from '@/components/verification/verification-detail-tabs';
import { VerificationSummaryCards } from '@/components/verification/verification-summary-cards';
import { useVerificationReport, type VerificationPageProps } from '@/hooks/use-verification-report';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';

export default function VerificationPage(props: VerificationPageProps) {
    const { displayPrNumber, breadcrumbs, handleExport, handleRefresh, overallStatus, hasIssues, allValid, getRoleBasedUrl, report } =
        useVerificationReport(props);

    // Loading skeleton when report is not available
    if (!report) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={`Verification Report - ${displayPrNumber}`} />
                <div className="flex h-full flex-1 flex-col p-3 sm:p-4 md:p-6 lg:p-8">
                    <div className="mb-6">
                        <Button variant="ghost" size="sm" render={<Link href={getRoleBasedUrl(`/procurements-list/${displayPrNumber}`)} />}>
                            <ArrowLeft data-icon="inline-start" />
                            Back to Procurement
                        </Button>
                    </div>
                    <div className="flex flex-col gap-6">
                        <div className="rounded-lg border p-6">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div className="flex flex-col gap-2">
                                    <Skeleton className="h-8 w-64" />
                                    <Skeleton className="h-4 w-48" />
                                </div>
                                <Skeleton className="h-10 w-32" />
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {[...Array(4)].map((_, i) => (
                                <div key={i} className="rounded-lg border p-6">
                                    <div className="flex items-center justify-between">
                                        <div className="flex flex-col gap-2">
                                            <Skeleton className="h-4 w-24" />
                                            <Skeleton className="h-8 w-16" />
                                        </div>
                                        <Skeleton className="h-10 w-10 rounded-full" />
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="flex flex-col gap-4">
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-64 w-full rounded-lg" />
                        </div>
                    </div>
                    <div className="flex items-center justify-center py-8">
                        <div className="text-muted-foreground flex items-center gap-3">
                            <Spinner />
                            <span>Loading verification report...</span>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Verification Report - ${displayPrNumber}`} />

            <div className="flex h-full flex-1 flex-col p-3 sm:p-4 md:p-6 lg:p-8 print:p-0">
                {/* Success Banner */}
                {allValid && (
                    <Alert className="mb-6 print:hidden">
                        <ShieldCheck />
                        <AlertTitle>Verification Successful</AlertTitle>
                        <AlertDescription>
                            All documents have passed integrity, completeness, cross-reference, and compliance checks.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Error Banner */}
                {!allValid && report.summary.critical_issues > 0 && (
                    <Alert variant="destructive" className="mb-6 print:hidden">
                        <AlertCircle />
                        <AlertTitle>Verification Issues Detected</AlertTitle>
                        <AlertDescription>
                            {report.summary.critical_issues} critical issue{report.summary.critical_issues !== 1 ? 's' : ''}
                            {report.summary.warnings > 0 && ` and ${report.summary.warnings} warning${report.summary.warnings !== 1 ? 's' : ''}`}{' '}
                            require attention before this procurement can proceed.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-6 print:gap-4">
                    {/* Header */}
                    <HeroCard
                        icon={allValid ? ShieldCheck : ClipboardCheck}
                        title="Verification Report"
                        description={
                            <span className="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm">
                                <span className="text-foreground font-medium">PR: {report.pr_number}</span>
                                <span className="bg-border h-4 w-px" aria-hidden="true" />
                                {props.procurementStatus && (
                                    <>
                                        <Badge variant="secondary" className="text-[10px] font-normal sm:text-xs">
                                            {props.procurementStatus.phase_display_name}
                                        </Badge>
                                        <Badge variant="outline" className="text-[10px] font-normal sm:text-xs">
                                            {props.procurementStatus.stage_formatted}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            className="text-[10px] font-normal sm:text-xs"
                                        >
                                            {props.procurementStatus.status_formatted || props.procurementStatus.current_status}
                                        </Badge>
                                        <span className="bg-border hidden h-4 w-px sm:block" aria-hidden="true" />
                                    </>
                                )}
                                <Tooltip>
                                    <TooltipTrigger
                                        render={
                                            <span className="text-muted-foreground hidden cursor-help sm:inline">
                                                Generated {formatDistanceToNow(new Date(report.generated_at), { addSuffix: true })}
                                            </span>
                                        }
                                    />
                                    <TooltipContent>{format(new Date(report.generated_at), 'PPpp')}</TooltipContent>
                                </Tooltip>
                            </span>
                        }
                        className={cn(
                            'border-2 transition-colors',
                            allValid
                                ? 'border-primary/30 bg-primary/5'
                                : 'border-muted bg-muted/50',
                        )}
                        iconWrapperClassName={allValid ? 'bg-primary/10' : 'bg-muted'}
                        iconClassName={allValid ? 'text-primary' : 'text-muted-foreground'}
                        actions={
                            <div className="flex flex-col items-end gap-2 sm:flex-row sm:items-center">
                                <VerificationStatus status={overallStatus} lastVerified={report.generated_at} size="lg" />
                                <div className="flex gap-1 print:hidden">
                                    <Tooltip>
                                        <TooltipTrigger
                                            render={
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    className="h-8 w-8 sm:h-9 sm:w-9"
                                                    onClick={() => window.print()}
                                                />
                                            }
                                        >
                                            <Printer className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                            <span className="sr-only">Print report</span>
                                        </TooltipTrigger>
                                        <TooltipContent>Print Report</TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger
                                            render={<Button variant="outline" size="icon" className="h-8 w-8 sm:h-9 sm:w-9" onClick={handleExport} />}
                                        >
                                            <Download className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                            <span className="sr-only">Export report</span>
                                        </TooltipTrigger>
                                        <TooltipContent>Export as PDF</TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger
                                            render={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleRefresh}
                                                    className="h-8 gap-1.5 px-2 sm:h-9 sm:gap-2 sm:px-3"
                                                />
                                            }
                                        >
                                            <RefreshCw className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                            <span className="hidden sm:inline">Refresh</span>
                                        </TooltipTrigger>
                                        <TooltipContent>Refresh Report</TooltipContent>
                                    </Tooltip>
                                </div>
                            </div>
                        }
                    />

                    {/* Summary Cards */}
                    <VerificationSummaryCards report={report} />

                    {/* Issues Summary */}
                    {hasIssues && (
                        <Card
                            className={cn(
                                'border-l-4 transition-all',
                                report.summary.critical_issues > 0
                                    ? 'border-destructive/30 border-l-destructive bg-destructive/5'
                                    : 'border-muted border-l-muted-foreground bg-muted/50',
                            )}
                        >
                            <CardContent className="p-3 sm:px-6 sm:py-4">
                                <div className="flex items-start gap-2.5 sm:gap-4">
                                    <div
                                        className={cn(
                                            'mt-0.5 shrink-0 rounded-full p-1.5 sm:p-2',
                                            report.summary.critical_issues > 0 ? 'bg-destructive/10' : 'bg-muted',
                                        )}
                                    >
                                        {report.summary.critical_issues > 0 ? (
                                            <XCircle className="text-destructive" />
                                        ) : (
                                            <AlertTriangle className="text-muted-foreground" />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1 flex flex-col gap-0.5 sm:gap-1">
                                        <h4 className="text-sm font-semibold sm:text-base">
                                            {report.summary.critical_issues > 0 ? 'Action Required' : 'Attention Needed'}
                                        </h4>
                                        <p className="text-muted-foreground text-xs sm:text-sm">
                                            {report.summary.critical_issues > 0 && (
                                                <span className="font-medium text-destructive">
                                                    {report.summary.critical_issues} critical issue{report.summary.critical_issues !== 1 ? 's' : ''}
                                                </span>
                                            )}
                                            {report.summary.critical_issues > 0 && report.summary.warnings > 0 && ' and '}
                                            {report.summary.warnings > 0 && (
                                                <span className="font-medium text-muted-foreground">
                                                    {report.summary.warnings} warning{report.summary.warnings !== 1 ? 's' : ''}
                                                </span>
                                            )}{' '}
                                            detected. Review the tabs below for details.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Detailed Results Tabs */}
                    <VerificationDetailTabs report={report} />

                    {/* Footer */}
                    <Card className="bg-muted/30 print:bg-transparent">
                        <CardContent className="p-3 sm:px-6 sm:py-4">
                            <div className="text-muted-foreground flex flex-col gap-1.5 text-xs sm:flex-row sm:items-center sm:justify-between sm:gap-2 sm:text-sm">
                                <div className="flex items-center gap-1.5 sm:gap-2">
                                    <Info className="h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4" />
                                    <span className="leading-tight">
                                        Report generated on {format(new Date(report.generated_at), 'MMM d, yyyy')} at{' '}
                                        {format(new Date(report.generated_at), 'h:mm a')}
                                    </span>
                                </div>
                                {report.verified_by && <span className="ml-5 sm:ml-0">Verified by User #{report.verified_by}</span>}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
