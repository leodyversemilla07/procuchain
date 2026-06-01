import { AlertTriangle, CheckCircle, FileCheck, FileText, FileWarning, Info, Link2, Scale, XCircle } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { IntegrityCheck } from '@/components/verification';
import { cn } from '@/lib/utils';

import type {
    CompletenessResult,
    ComplianceResult,
    CrossReferenceResult,
    IntegrityResult,
    VerificationReportData,
} from '@/hooks/use-verification-report';

// =============================================================================
// Integrity Tab
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
// Completeness Tab
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
                        {result.document_counts.uploaded_optional > 0 && (
                            <div className="flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-2 py-1 sm:px-2.5 sm:py-1.5 dark:border-blue-800 dark:bg-blue-950/50">
                                <span className="text-xs text-blue-600/70 dark:text-blue-400/70">Optional:</span>
                                <span className="text-xs font-semibold text-blue-600 tabular-nums sm:text-sm dark:text-blue-400">
                                    {result.document_counts.uploaded_optional}
                                </span>
                            </div>
                        )}
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
// Cross-Reference Tab
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
// Compliance Tab
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

                {result.document_type_checks.length === 0 && (
                    <div className="text-muted-foreground py-8 text-center">
                        <Scale className="mx-auto mb-2 h-8 w-8 opacity-50" />
                        <p>No compliance checks available for this stage</p>
                    </div>
                )}

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
// Detail Tabs (exported)
// =============================================================================

interface VerificationDetailTabsProps {
    report: VerificationReportData;
}

export function VerificationDetailTabs({ report }: VerificationDetailTabsProps) {
    return (
        <Tabs defaultValue="integrity" className="w-full">
            <TabsList variant="line" className="w-full">
                <TabsTrigger value="integrity">
                    <div className="relative">
                        <FileCheck className="size-4" />
                        {!report.summary.integrity_valid && (
                            <span className="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-red-500 sm:-top-1 sm:-right-1 sm:h-2 sm:w-2" />
                        )}
                    </div>
                    <span className="hidden sm:inline">Integrity</span>
                </TabsTrigger>
                <TabsTrigger value="completeness">
                    <div className="relative">
                        <FileText className="size-4" />
                        {!report.completeness_result.is_complete && (
                            <span className="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-amber-500 sm:-top-1 sm:-right-1 sm:h-2 sm:w-2" />
                        )}
                    </div>
                    <span className="hidden sm:inline">Completeness</span>
                </TabsTrigger>
                <TabsTrigger value="crossref">
                    <div className="relative">
                        <Link2 className="size-4" />
                        {!report.summary.cross_references_consistent && (
                            <span className="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-red-500 sm:-top-1 sm:-right-1 sm:h-2 sm:w-2" />
                        )}
                    </div>
                    <span className="hidden sm:inline">Cross-Ref</span>
                </TabsTrigger>
                <TabsTrigger value="compliance">
                    <div className="relative">
                        <Scale className="size-4" />
                        {!report.summary.ra_12009_compliant && (
                            <span className="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-amber-500 sm:-top-1 sm:-right-1 sm:h-2 sm:w-2" />
                        )}
                    </div>
                    <span className="hidden sm:inline">Compliance</span>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="integrity" className="mt-4">
                <IntegrityTab results={report.integrity_results} />
            </TabsContent>

            <TabsContent value="completeness" className="mt-4">
                <CompletenessTab result={report.completeness_result} />
            </TabsContent>

            <TabsContent value="crossref" className="mt-4">
                <CrossReferenceTab result={report.cross_reference_result} />
            </TabsContent>

            <TabsContent value="compliance" className="mt-4">
                <ComplianceTab result={report.compliance_result} />
            </TabsContent>
        </Tabs>
    );
}
