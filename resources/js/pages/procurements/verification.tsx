import { format, formatDistanceToNow } from 'date-fns';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    ClipboardCheck,
    Download,
    FileCheck,
    FileText,
    FileWarning,
    Info,
    Link2,
    Printer,
    RefreshCw,
    Scale,
    ShieldCheck,
    XCircle,
} from 'lucide-react';

import { HeroCard } from '@/components/hero-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Spinner } from '@/components/ui/spinner';
import { IntegrityCheck, VerificationStatus, type VerificationStatusType } from '@/components/verification';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

// =============================================================================
// Types
// =============================================================================

interface IntegrityResult {
    is_valid: boolean;
    verification_type: string;
    file_key: string;
    expected_hash: string | null;
    actual_hash: string | null;
    hash_match: boolean;
    errors: string[];
    warnings: string[];
    verified_at: string;
}

interface CompletenessResult {
    is_complete: boolean;
    pr_number: string;
    stage: string;
    stage_display_name: string;
    completion_percentage: number;
    required_documents: string[];
    uploaded_documents: string[];
    missing_documents: string[];
    document_counts: {
        required: number;
        uploaded: number;
        missing: number;
    };
    can_complete_stage: boolean;
    errors: string[];
    warnings: string[];
    verified_at: string;
}

interface CrossReferenceResult {
    is_consistent: boolean;
    pr_number: string;
    pr_number_checks: Array<{
        document_type: string;
        document_type_display: string;
        file_key: string;
        pr_number_in_doc: string;
        expected_pr_number: string;
        matches: boolean;
    }>;
    amount_checks: unknown[];
    date_checks: unknown[];
    signatory_checks: unknown[];
    summary: {
        total_issues: number;
        total_warnings: number;
        has_pr_mismatch: boolean;
        has_amount_inconsistency: boolean;
    };
    errors: string[];
    warnings: string[];
    verified_at: string;
}

interface ComplianceResult {
    is_compliant: boolean;
    pr_number: string;
    stage: string;
    stage_display_name: string;
    document_type_checks: Array<{
        document_type: string;
        document_type_display: string;
        file_key: string;
        valid: boolean;
        is_required: boolean;
        stage: string;
    }>;
    timeline_checks: unknown[];
    procurement_mode_checks: unknown[];
    summary: {
        violations_count: number;
        warnings_count: number;
        has_document_violations: boolean;
        has_timeline_violations: boolean;
    };
    errors: string[];
    warnings: string[];
    verified_at: string;
}

interface VerificationReportData {
    pr_number: string;
    stage: string;
    stage_display_name: string;
    overall_valid: boolean;
    overall_status: string;
    integrity_results: IntegrityResult[];
    completeness_result: CompletenessResult;
    cross_reference_result: CrossReferenceResult;
    compliance_result: ComplianceResult;
    summary: {
        integrity_valid: boolean;
        documents_verified: number;
        completeness_percentage: number;
        cross_references_consistent: boolean;
        ra_12009_compliant: boolean;
        critical_issues: number;
        warnings: number;
    };
    generated_at: string;
    verified_by: number | null;
}

interface ProcurementStatusData {
    stage: string;
    stage_formatted: string;
    current_status: string;
    status_formatted: string;
    phase: string;
    phase_display_name: string;
}

interface VerificationPageProps {
    prNumber: string;
    report: VerificationReportData;
    procurementStatus?: ProcurementStatusData | null;
}

// =============================================================================
// Summary Card Component
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

function SummaryCard({ title, value, subtitle, isValid, icon: Icon, tooltipText, showProgress, progressValue }: SummaryCardProps) {
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
// Integrity Tab Component
// =============================================================================

function IntegrityTab({ results }: { results: IntegrityResult[] }) {
    if (results.length === 0) {
        return (
            <Card className="border-dashed">
                <CardContent className="py-12">
                    <div className="flex flex-col items-center justify-center text-center">
                        <div className="bg-muted mb-4 rounded-full p-3">
                            <FileWarning className="text-muted-foreground h-6 w-6" />
                        </div>
                        <h3 className="mb-1 font-semibold">No Documents to Verify</h3>
                        <p className="text-muted-foreground max-w-sm text-sm">
                            There are no documents uploaded for this procurement stage yet. Upload documents to enable integrity verification.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const validCount = results.filter((r) => r.is_valid).length;
    const invalidCount = results.length - validCount;

    return (
        <div className="space-y-4">
            {/* Quick stats */}
            <div className="flex items-center gap-4 text-sm">
                <div className="flex items-center gap-1.5">
                    <CheckCircle className="h-4 w-4 text-green-600" />
                    <span className="font-medium">{validCount} verified</span>
                </div>
                {invalidCount > 0 && (
                    <div className="flex items-center gap-1.5">
                        <XCircle className="h-4 w-4 text-red-600" />
                        <span className="font-medium text-red-600">{invalidCount} failed</span>
                    </div>
                )}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {results.map((result, index) => (
                    <IntegrityCheck key={index} result={result} />
                ))}
            </div>
        </div>
    );
}

// =============================================================================
// Completeness Tab Component
// =============================================================================

function CompletenessTab({ result }: { result: CompletenessResult }) {
    const progressColor = result.completion_percentage === 100 ? 'bg-green-500' : result.completion_percentage >= 50 ? 'bg-amber-500' : 'bg-red-500';

    return (
        <Card>
            <CardHeader className="pb-3 sm:pb-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <CardTitle className="flex items-center gap-2 text-base sm:text-lg">
                            <FileText className="text-muted-foreground h-4 w-4 sm:h-5 sm:w-5" />
                            Document Completeness
                        </CardTitle>
                        <CardDescription className="text-xs sm:text-sm">Stage: {result.stage_display_name}</CardDescription>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {/* Inline Stats */}
                        <div className="bg-muted/50 flex items-center gap-1.5 rounded-md border px-2 py-1 sm:px-2.5 sm:py-1.5">
                            <span className="text-muted-foreground text-xs">Required:</span>
                            <span className="text-xs font-semibold tabular-nums sm:text-sm">{result.document_counts.required}</span>
                        </div>
                        <div className="flex items-center gap-1.5 rounded-md border border-green-200 bg-green-50 px-2 py-1 sm:px-2.5 sm:py-1.5 dark:border-green-800 dark:bg-green-950/50">
                            <span className="text-xs text-green-600/70 dark:text-green-400/70">Uploaded:</span>
                            <span className="text-xs font-semibold text-green-600 tabular-nums sm:text-sm dark:text-green-400">
                                {result.document_counts.uploaded}
                            </span>
                        </div>
                        {result.document_counts.missing > 0 && (
                            <div className="flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-2 py-1 sm:px-2.5 sm:py-1.5 dark:border-red-800 dark:bg-red-950/50">
                                <span className="text-xs text-red-600/70 dark:text-red-400/70">Missing:</span>
                                <span className="text-xs font-semibold text-red-600 tabular-nums sm:text-sm dark:text-red-400">
                                    {result.document_counts.missing}
                                </span>
                            </div>
                        )}
                        <Badge variant={result.is_complete ? 'default' : 'destructive'} className="text-[10px] sm:text-xs">
                            {result.is_complete ? (
                                <>
                                    <CheckCircle className="mr-1 h-3 w-3" /> Complete
                                </>
                            ) : (
                                <>
                                    <AlertTriangle className="mr-1 h-3 w-3" /> Incomplete
                                </>
                            )}
                        </Badge>
                    </div>
                </div>
                {/* Progress bar in header */}
                <div className="mt-3 space-y-1.5 sm:mt-4">
                    <div className="flex items-center justify-between text-xs sm:text-sm">
                        <span className="text-muted-foreground">Progress</span>
                        <span className={cn('font-bold tabular-nums', result.completion_percentage === 100 ? 'text-green-600' : 'text-foreground')}>
                            {result.completion_percentage.toFixed(0)}%
                        </span>
                    </div>
                    <div className="bg-muted h-2 overflow-hidden rounded-full sm:h-2.5">
                        <div
                            className={cn('h-full rounded-full transition-all duration-500', progressColor)}
                            style={{ width: `${result.completion_percentage}%` }}
                            role="progressbar"
                            aria-valuenow={result.completion_percentage}
                            aria-valuemin={0}
                            aria-valuemax={100}
                        />
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4 pt-0 sm:space-y-6">
                {/* Success State when 100% complete */}
                {result.is_complete && result.missing_documents.length === 0 && (
                    <div className="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50/50 p-3 sm:p-4 dark:border-green-800 dark:bg-green-950/20">
                        <div className="shrink-0 rounded-full bg-green-100 p-2 dark:bg-green-900">
                            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium text-green-800 dark:text-green-200">All Documents Uploaded</p>
                            <p className="text-xs text-green-600/80 dark:text-green-400/80">
                                All {result.document_counts.required} required documents for this stage have been successfully uploaded.
                            </p>
                        </div>
                    </div>
                )}

                {/* Missing Documents */}
                {result.missing_documents.length > 0 && (
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="flex items-center gap-2 text-sm font-semibold">
                                <XCircle className="h-4 w-4 text-red-500" />
                                Missing Documents
                            </h4>
                            <Badge variant="outline" className="text-xs">
                                {result.missing_documents.length} remaining
                            </Badge>
                        </div>
                        <div className="divide-y divide-red-100 overflow-hidden rounded-lg border border-red-200 dark:divide-red-900 dark:border-red-800">
                            {result.missing_documents.map((doc, index) => (
                                <div
                                    key={index}
                                    className="flex items-center gap-3 bg-red-50/50 px-4 py-3 transition-colors hover:bg-red-50 dark:bg-red-950/20 dark:hover:bg-red-950/30"
                                >
                                    <FileWarning className="h-4 w-4 shrink-0 text-red-500" />
                                    <span className="text-sm">{doc}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Uploaded Documents (collapsed) */}
                {result.uploaded_documents.length > 0 && (
                    <details className="group">
                        <summary className="flex cursor-pointer list-none items-center justify-between">
                            <h4 className="flex items-center gap-2 text-sm font-semibold">
                                <CheckCircle className="h-4 w-4 text-green-500" />
                                Uploaded Documents
                            </h4>
                            <Badge variant="secondary" className="text-xs group-open:hidden">
                                {result.uploaded_documents.length} files
                            </Badge>
                        </summary>
                        <div className="mt-3 divide-y overflow-hidden rounded-lg border">
                            {result.uploaded_documents.map((doc, index) => (
                                <div key={index} className="hover:bg-muted/50 flex items-center gap-3 px-4 py-2.5 transition-colors">
                                    <FileCheck className="h-4 w-4 shrink-0 text-green-500" />
                                    <span className="text-sm">{doc}</span>
                                </div>
                            ))}
                        </div>
                    </details>
                )}

                {/* Warnings */}
                {result.warnings.length > 0 && (
                    <div className="space-y-2 pt-2">
                        <h4 className="flex items-center gap-2 text-sm font-semibold">
                            <AlertTriangle className="h-4 w-4 text-yellow-500" />
                            Warnings
                        </h4>
                        <div className="space-y-2">
                            {result.warnings.map((warning, index) => (
                                <div
                                    key={index}
                                    className="flex items-start gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm dark:border-yellow-800 dark:bg-yellow-950/30"
                                >
                                    <Info className="mt-0.5 h-4 w-4 shrink-0 text-yellow-600" />
                                    <span className="text-yellow-800 dark:text-yellow-200">{warning}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// =============================================================================
// Cross-Reference Tab Component
// =============================================================================

function CrossReferenceTab({ result }: { result: CrossReferenceResult }) {
    const hasChecks = result.pr_number_checks.length > 0;
    const matchCount = result.pr_number_checks.filter((c) => c.matches).length;
    const mismatchCount = result.pr_number_checks.length - matchCount;

    return (
        <Card>
            <CardHeader className="pb-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Link2 className="text-muted-foreground h-5 w-5" />
                            Cross-Reference Validation
                        </CardTitle>
                        <CardDescription>
                            Expected PR Number: <code className="bg-muted text-foreground rounded px-1.5 py-0.5 font-mono">{result.pr_number}</code>
                        </CardDescription>
                    </div>
                    <Badge variant={result.is_consistent ? 'default' : 'destructive'} className="w-fit">
                        {result.is_consistent ? (
                            <>
                                <CheckCircle className="mr-1 h-3 w-3" /> Consistent
                            </>
                        ) : (
                            <>
                                <XCircle className="mr-1 h-3 w-3" /> Inconsistent
                            </>
                        )}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Quick Stats */}
                {hasChecks && (
                    <div className="flex items-center gap-4 border-b pb-2 text-sm">
                        <div className="flex items-center gap-1.5">
                            <CheckCircle className="h-4 w-4 text-green-600" />
                            <span className="font-medium">{matchCount} matched</span>
                        </div>
                        {mismatchCount > 0 && (
                            <div className="flex items-center gap-1.5">
                                <XCircle className="h-4 w-4 text-red-600" />
                                <span className="font-medium text-red-600">{mismatchCount} mismatched</span>
                            </div>
                        )}
                    </div>
                )}

                {/* PR Number Checks */}
                {hasChecks ? (
                    <div className="space-y-3">
                        <h4 className="text-sm font-semibold">PR Number Verification</h4>
                        <div className="divide-y overflow-hidden rounded-lg border">
                            {result.pr_number_checks.map((check, index) => (
                                <div
                                    key={index}
                                    className={cn(
                                        'flex flex-col gap-2 px-3 py-2.5 transition-colors sm:flex-row sm:items-center sm:justify-between sm:px-4 sm:py-3',
                                        check.matches
                                            ? 'bg-green-50/50 hover:bg-green-50 dark:bg-green-950/20 dark:hover:bg-green-950/30'
                                            : 'bg-red-50/50 hover:bg-red-50 dark:bg-red-950/20 dark:hover:bg-red-950/30',
                                    )}
                                >
                                    <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                                        {check.matches ? (
                                            <CheckCircle className="h-4 w-4 shrink-0 text-green-600 sm:h-5 sm:w-5" />
                                        ) : (
                                            <XCircle className="h-4 w-4 shrink-0 text-red-600 sm:h-5 sm:w-5" />
                                        )}
                                        <div className="min-w-0">
                                            <p className="truncate text-xs font-medium sm:text-sm">{check.document_type_display}</p>
                                            <p className="text-muted-foreground truncate text-[10px] sm:text-xs">{check.file_key}</p>
                                        </div>
                                    </div>
                                    <div className="ml-6 shrink-0 sm:ml-4 sm:text-right">
                                        <code
                                            className={cn(
                                                'rounded px-1.5 py-0.5 font-mono text-[10px] sm:px-2 sm:py-1 sm:text-xs',
                                                check.matches
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                                    : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                            )}
                                        >
                                            {check.pr_number_in_doc}
                                        </code>
                                        {!check.matches && (
                                            <p className="mt-0.5 text-[10px] text-red-600 sm:mt-1 sm:text-xs">Expected: {check.expected_pr_number}</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="text-muted-foreground py-8 text-center">
                        <Link2 className="mx-auto mb-2 h-8 w-8 opacity-50" />
                        <p>No cross-reference checks available</p>
                    </div>
                )}

                {/* Errors */}
                {result.errors.length > 0 && (
                    <div className="space-y-3">
                        <h4 className="flex items-center gap-2 text-sm font-semibold">
                            <XCircle className="h-4 w-4 text-red-500" />
                            Issues Found
                        </h4>
                        <div className="space-y-2">
                            {result.errors.map((error, index) => (
                                <div
                                    key={index}
                                    className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm dark:border-red-800 dark:bg-red-950/30"
                                >
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                                    <span className="text-red-800 dark:text-red-200">{error}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Warnings */}
                {result.warnings.length > 0 && (
                    <div className="space-y-3">
                        <h4 className="flex items-center gap-2 text-sm font-semibold">
                            <AlertTriangle className="h-4 w-4 text-yellow-500" />
                            Warnings
                        </h4>
                        <div className="space-y-2">
                            {result.warnings.map((warning, index) => (
                                <div
                                    key={index}
                                    className="flex items-start gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm dark:border-yellow-800 dark:bg-yellow-950/30"
                                >
                                    <Info className="mt-0.5 h-4 w-4 shrink-0 text-yellow-600" />
                                    <span className="text-yellow-800 dark:text-yellow-200">{warning}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// =============================================================================
// Compliance Tab Component
// =============================================================================

function ComplianceTab({ result }: { result: ComplianceResult }) {
    const requiredDocs = result.document_type_checks.filter((c) => c.is_required);
    const optionalDocs = result.document_type_checks.filter((c) => !c.is_required);
    const validCount = result.document_type_checks.filter((c) => c.valid).length;

    return (
        <Card>
            <CardHeader className="pb-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Scale className="text-muted-foreground h-5 w-5" />
                            RA 12009 (NGPA) Compliance
                        </CardTitle>
                        <CardDescription>Stage: {result.stage_display_name}</CardDescription>
                    </div>
                    <Badge variant={result.is_compliant ? 'default' : 'destructive'} className="w-fit">
                        {result.is_compliant ? (
                            <>
                                <CheckCircle className="mr-1 h-3 w-3" /> Compliant
                            </>
                        ) : (
                            <>
                                <XCircle className="mr-1 h-3 w-3" /> Non-Compliant
                            </>
                        )}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Quick Stats */}
                {result.document_type_checks.length > 0 && (
                    <div className="flex items-center gap-4 border-b pb-2 text-sm">
                        <div className="flex items-center gap-1.5">
                            <CheckCircle className="h-4 w-4 text-green-600" />
                            <span className="font-medium">{validCount} valid</span>
                        </div>
                        <div className="text-muted-foreground flex items-center gap-1.5">
                            <FileText className="h-4 w-4" />
                            <span>
                                {requiredDocs.length} required, {optionalDocs.length} optional
                            </span>
                        </div>
                    </div>
                )}

                {/* Required Documents */}
                {requiredDocs.length > 0 && (
                    <div className="space-y-3">
                        <h4 className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                            Required Documents
                            <Badge variant="outline" className="text-[10px] font-normal sm:text-xs">
                                {requiredDocs.filter((c) => c.valid).length}/{requiredDocs.length} valid
                            </Badge>
                        </h4>
                        <div className="divide-y overflow-hidden rounded-lg border">
                            {requiredDocs.map((check, index) => (
                                <div
                                    key={index}
                                    className={cn(
                                        'flex flex-col gap-2 px-3 py-2.5 transition-colors sm:flex-row sm:items-center sm:justify-between sm:px-4 sm:py-3',
                                        check.valid
                                            ? 'bg-green-50/50 hover:bg-green-50 dark:bg-green-950/20 dark:hover:bg-green-950/30'
                                            : 'bg-amber-50/50 hover:bg-amber-50 dark:bg-amber-950/20 dark:hover:bg-amber-950/30',
                                    )}
                                >
                                    <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                                        {check.valid ? (
                                            <CheckCircle className="h-4 w-4 shrink-0 text-green-600 sm:h-5 sm:w-5" />
                                        ) : (
                                            <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 sm:h-5 sm:w-5" />
                                        )}
                                        <div className="min-w-0">
                                            <p className="truncate text-xs font-medium sm:text-sm">{check.document_type_display}</p>
                                            <p className="text-muted-foreground truncate text-[10px] sm:text-xs">{check.file_key}</p>
                                        </div>
                                    </div>
                                    <Badge
                                        variant={check.valid ? 'default' : 'outline'}
                                        className={cn(
                                            'ml-6 w-fit shrink-0 text-[10px] sm:ml-0 sm:text-xs',
                                            !check.valid && 'border-amber-300 text-amber-700 dark:border-amber-700 dark:text-amber-400',
                                        )}
                                    >
                                        {check.valid ? 'Valid' : 'Review'}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Optional Documents (collapsed) */}
                {optionalDocs.length > 0 && (
                    <details className="group">
                        <summary className="flex cursor-pointer list-none items-center justify-between">
                            <h4 className="flex items-center gap-2 text-sm font-semibold">Optional Documents</h4>
                            <Badge variant="secondary" className="text-xs group-open:hidden">
                                {optionalDocs.length} documents
                            </Badge>
                        </summary>
                        <div className="mt-3 divide-y overflow-hidden rounded-lg border">
                            {optionalDocs.map((check, index) => (
                                <div key={index} className="hover:bg-muted/50 flex items-center justify-between px-4 py-2.5 transition-colors">
                                    <div className="flex min-w-0 items-center gap-3">
                                        {check.valid ? (
                                            <CheckCircle className="h-4 w-4 shrink-0 text-green-600" />
                                        ) : (
                                            <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600" />
                                        )}
                                        <span className="truncate text-sm">{check.document_type_display}</span>
                                    </div>
                                    <Badge variant="secondary" className="shrink-0 text-xs">
                                        Optional
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </details>
                )}

                {/* No checks available */}
                {result.document_type_checks.length === 0 && (
                    <div className="text-muted-foreground py-8 text-center">
                        <Scale className="mx-auto mb-2 h-8 w-8 opacity-50" />
                        <p>No compliance checks available for this stage</p>
                    </div>
                )}

                {/* Errors */}
                {result.errors.length > 0 && (
                    <div className="space-y-3">
                        <h4 className="flex items-center gap-2 text-sm font-semibold">
                            <XCircle className="h-4 w-4 text-red-500" />
                            Compliance Violations
                        </h4>
                        <div className="space-y-2">
                            {result.errors.map((error, index) => (
                                <div
                                    key={index}
                                    className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm dark:border-red-800 dark:bg-red-950/30"
                                >
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                                    <span className="text-red-800 dark:text-red-200">{error}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Warnings */}
                {result.warnings.length > 0 && (
                    <div className="space-y-3">
                        <h4 className="flex items-center gap-2 text-sm font-semibold">
                            <AlertTriangle className="h-4 w-4 text-yellow-500" />
                            Compliance Warnings
                        </h4>
                        <div className="space-y-2">
                            {result.warnings.map((warning, index) => (
                                <div
                                    key={index}
                                    className="flex items-start gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm dark:border-yellow-800 dark:bg-yellow-950/30"
                                >
                                    <Info className="mt-0.5 h-4 w-4 shrink-0 text-yellow-600" />
                                    <span className="text-yellow-800 dark:text-yellow-200">{warning}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// =============================================================================
// Main Page Component
// =============================================================================

export default function VerificationPage({ prNumber, report, procurementStatus }: VerificationPageProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.role || auth?.user?.role || 'guest';

    // Build breadcrumbs based on user role
    const getRoleBasedUrl = (path: string) => {
        const rolePrefix =
            userRole === 'admin' ? '/admin' : userRole === 'bac_chairman' ? '/bac-chairman' : userRole === 'hope' ? '/hope' : '/bac-secretariat';
        return `${rolePrefix}${path}`;
    };

    const displayPrNumber = prNumber || 'Unknown';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: getRoleBasedUrl('/dashboard') },
        { title: 'Procurements', href: getRoleBasedUrl('/procurements-list') },
        { title: displayPrNumber, href: getRoleBasedUrl(`/procurements-list/${displayPrNumber}`) },
        { title: 'Verification Report', href: '#' },
    ];

    const handleExport = () => {
        window.print();
    };

    const handleRefresh = () => {
        window.location.reload();
    };

    // Handle loading/error state when report is not available
    if (!report) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={`Verification Report - ${displayPrNumber}`} />
                <div className="flex h-full flex-1 flex-col p-3 sm:p-4 md:p-6 lg:p-8">
                    {/* Back Button */}
                    <div className="mb-6">
                        <Button variant="ghost" size="sm" render={<Link href={getRoleBasedUrl(`/procurements-list/${displayPrNumber}`)} />}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Procurement
                        </Button>
                    </div>

                    {/* Loading Skeleton */}
                    <div className="space-y-6">
                        <div className="rounded-lg border p-6">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div className="space-y-2">
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
                                        <div className="space-y-2">
                                            <Skeleton className="h-4 w-24" />
                                            <Skeleton className="h-8 w-16" />
                                        </div>
                                        <Skeleton className="h-10 w-10 rounded-full" />
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="space-y-4">
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-64 w-full rounded-lg" />
                        </div>
                    </div>

                    <div className="flex items-center justify-center py-8">
                        <div className="text-muted-foreground flex items-center gap-3">
<Spinner className="size-5" />
 <span>Loading verification report...</span>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const overallStatus = report.overall_status as VerificationStatusType;
    const hasIssues = report.summary.critical_issues > 0 || report.summary.warnings > 0;
    const allValid = report.overall_valid;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Verification Report - ${displayPrNumber}`} />

            <div className="flex h-full flex-1 flex-col p-3 sm:p-4 md:p-6 lg:p-8 print:p-0">
                {/* Success Banner */}
                {allValid && (
                    <Alert className="mb-6 border-green-200 bg-green-50/50 dark:border-green-800 dark:bg-green-950/20 print:hidden">
                        <ShieldCheck className="h-5 w-5 text-green-600" />
                        <AlertTitle className="text-green-800 dark:text-green-200">Verification Successful</AlertTitle>
                        <AlertDescription className="text-green-700 dark:text-green-300">
                            All documents have passed integrity, completeness, cross-reference, and compliance checks.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Error Banner */}
                {!allValid && report.summary.critical_issues > 0 && (
                    <Alert variant="destructive" className="mb-6 print:hidden">
                        <AlertCircle className="h-5 w-5" />
                        <AlertTitle>Verification Issues Detected</AlertTitle>
                        <AlertDescription>
                            {report.summary.critical_issues} critical issue{report.summary.critical_issues !== 1 ? 's' : ''}
                            {report.summary.warnings > 0 && ` and ${report.summary.warnings} warning${report.summary.warnings !== 1 ? 's' : ''}`}{' '}
                            require attention before this procurement can proceed.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Main Content */}
                <div className="space-y-6 print:space-y-4">
                    {/* Header using HeroCard */}
                    <HeroCard
                        icon={allValid ? ShieldCheck : ClipboardCheck}
                        title="Verification Report"
                        description={
                            <span className="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm">
                                <span className="text-foreground font-medium">PR: {report.pr_number}</span>
                                <span className="bg-border h-4 w-px" aria-hidden="true" />
                                {procurementStatus && (
                                    <>
                                        <Badge variant="secondary" className="text-[10px] font-normal sm:text-xs">
                                            {procurementStatus.phase_display_name}
                                        </Badge>
                                        <Badge variant="outline" className="text-[10px] font-normal sm:text-xs">
                                            {procurementStatus.stage_formatted}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            className="border-blue-200 bg-blue-50/50 text-[10px] font-normal text-blue-700 sm:text-xs dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300"
                                        >
                                            {procurementStatus.status_formatted || procurementStatus.current_status}
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
                                ? 'border-green-200 bg-green-50/30 dark:border-green-800 dark:bg-green-950/20'
                                : 'border-amber-200 bg-amber-50/30 dark:border-amber-800 dark:bg-amber-950/20',
                        )}
                        iconWrapperClassName={allValid ? 'bg-green-100 dark:bg-green-900' : 'bg-amber-100 dark:bg-amber-900'}
                        iconClassName={allValid ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'}
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

                    {/* Issues Summary */}
                    {hasIssues && (
                        <Card
                            className={cn(
                                'border-l-4 transition-all',
                                report.summary.critical_issues > 0
                                    ? 'border-red-200 border-l-red-500 bg-red-50/50 dark:border-red-800 dark:bg-red-950/20'
                                    : 'border-yellow-200 border-l-yellow-500 bg-yellow-50/50 dark:border-yellow-800 dark:bg-yellow-950/20',
                            )}
                        >
                            <CardContent className="p-3 sm:px-6 sm:py-4">
                                <div className="flex items-start gap-2.5 sm:gap-4">
                                    <div
                                        className={cn(
                                            'mt-0.5 shrink-0 rounded-full p-1.5 sm:p-2',
                                            report.summary.critical_issues > 0 ? 'bg-red-100 dark:bg-red-900' : 'bg-yellow-100 dark:bg-yellow-900',
                                        )}
                                    >
                                        {report.summary.critical_issues > 0 ? (
                                            <XCircle className="h-4 w-4 text-red-600 sm:h-5 sm:w-5 dark:text-red-400" />
                                        ) : (
                                            <AlertTriangle className="h-4 w-4 text-yellow-600 sm:h-5 sm:w-5 dark:text-yellow-400" />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1 space-y-0.5 sm:space-y-1">
                                        <h4 className="text-sm font-semibold sm:text-base">
                                            {report.summary.critical_issues > 0 ? 'Action Required' : 'Attention Needed'}
                                        </h4>
                                        <p className="text-muted-foreground text-xs sm:text-sm">
                                            {report.summary.critical_issues > 0 && (
                                                <span className="font-medium text-red-600 dark:text-red-400">
                                                    {report.summary.critical_issues} critical issue
                                                    {report.summary.critical_issues !== 1 ? 's' : ''}
                                                </span>
                                            )}
                                            {report.summary.critical_issues > 0 && report.summary.warnings > 0 && ' and '}
                                            {report.summary.warnings > 0 && (
                                                <span className="font-medium text-yellow-600 dark:text-yellow-400">
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
                    <Tabs defaultValue="integrity" className="w-full">
                        <TabsList className="grid h-auto w-full grid-cols-4 gap-0.5 p-0.5 sm:gap-1 sm:p-1">
                            <TabsTrigger
                                value="integrity"
                                className="data-[state=active]:bg-background flex items-center gap-1 px-1.5 py-2 text-xs sm:gap-1.5 sm:px-3 sm:py-2.5 sm:text-sm"
                            >
                                <div className="relative">
                                    <FileCheck className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    {!report.summary.integrity_valid && (
                                        <span className="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-red-500 sm:-top-1 sm:-right-1 sm:h-2 sm:w-2" />
                                    )}
                                </div>
                                <span className="hidden sm:inline">Integrity</span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="completeness"
                                className="data-[state=active]:bg-background flex items-center gap-1 px-1.5 py-2 text-xs sm:gap-1.5 sm:px-3 sm:py-2.5 sm:text-sm"
                            >
                                <div className="relative">
                                    <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    {!report.completeness_result.is_complete && (
                                        <span className="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-amber-500 sm:-top-1 sm:-right-1 sm:h-2 sm:w-2" />
                                    )}
                                </div>
                                <span className="hidden sm:inline">Completeness</span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="crossref"
                                className="data-[state=active]:bg-background flex items-center gap-1 px-1.5 py-2 text-xs sm:gap-1.5 sm:px-3 sm:py-2.5 sm:text-sm"
                            >
                                <div className="relative">
                                    <Link2 className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    {!report.summary.cross_references_consistent && (
                                        <span className="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-red-500 sm:-top-1 sm:-right-1 sm:h-2 sm:w-2" />
                                    )}
                                </div>
                                <span className="hidden sm:inline">Cross-Ref</span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="compliance"
                                className="data-[state=active]:bg-background flex items-center gap-1 px-1.5 py-2 text-xs sm:gap-1.5 sm:px-3 sm:py-2.5 sm:text-sm"
                            >
                                <div className="relative">
                                    <Scale className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    {!report.summary.ra_12009_compliant && (
                                        <span className="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-amber-500 sm:-top-1 sm:-right-1 sm:h-2 sm:w-2" />
                                    )}
                                </div>
                                <span className="hidden sm:inline">Compliance</span>
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="integrity" className="mt-4 focus-visible:ring-0 focus-visible:outline-none">
                            <IntegrityTab results={report.integrity_results} />
                        </TabsContent>

                        <TabsContent value="completeness" className="mt-4 focus-visible:ring-0 focus-visible:outline-none">
                            <CompletenessTab result={report.completeness_result} />
                        </TabsContent>

                        <TabsContent value="crossref" className="mt-4 focus-visible:ring-0 focus-visible:outline-none">
                            <CrossReferenceTab result={report.cross_reference_result} />
                        </TabsContent>

                        <TabsContent value="compliance" className="mt-4 focus-visible:ring-0 focus-visible:outline-none">
                            <ComplianceTab result={report.compliance_result} />
                        </TabsContent>
                    </Tabs>

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
