import { AlertTriangle, CheckCircle, Download, FileText, Shield, XCircle } from 'lucide-react';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { IntegrityCheck } from './integrity-check';
import { VerificationStatus, type VerificationStatusType } from './verification-status';

interface VerificationReportProps {
    report: VerificationReportData;
    onExport?: () => void;
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
        ra_9184_compliant: boolean;
        critical_issues: number;
        warnings: number;
    };
    generated_at: string;
    verified_by: number | null;
}

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

export function VerificationReport({ report, onExport }: VerificationReportProps) {
    const overallStatus = report.overall_status as VerificationStatusType;

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Verification Report</h2>
                    <p className="text-muted-foreground">
                        PR: {report.pr_number} • {report.stage_display_name}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <VerificationStatus
                        status={overallStatus}
                        lastVerified={report.generated_at}
                        size="lg"
                    />
                    {onExport && (
                        <Button variant="outline" size="sm" onClick={onExport}>
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    )}
                </div>
            </div>

            {/* Summary Cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <SummaryCard
                    title="Documents Verified"
                    value={report.summary.documents_verified}
                    isValid={report.summary.integrity_valid}
                    icon={FileText}
                />
                <SummaryCard
                    title="Completeness"
                    value={`${report.summary.completeness_percentage.toFixed(1)}%`}
                    isValid={report.completeness_result.is_complete}
                    icon={CheckCircle}
                />
                <SummaryCard
                    title="Cross-References"
                    value={report.summary.cross_references_consistent ? 'Consistent' : 'Issues Found'}
                    isValid={report.summary.cross_references_consistent}
                    icon={Shield}
                />
                <SummaryCard
                    title="RA 9184 Compliance"
                    value={report.summary.ra_9184_compliant ? 'Compliant' : 'Non-Compliant'}
                    isValid={report.summary.ra_9184_compliant}
                    icon={Shield}
                />
            </div>

            {/* Issues Summary */}
            {(report.summary.critical_issues > 0 || report.summary.warnings > 0) && (
                <Card className="border-yellow-200 dark:border-yellow-800 bg-yellow-50/50 dark:bg-yellow-950/20">
                    <CardContent className="pt-6">
                        <div className="flex items-start gap-4">
                            <AlertTriangle className="h-5 w-5 text-yellow-600 mt-0.5" />
                            <div>
                                <h4 className="font-medium">Issues Found</h4>
                                <p className="text-sm text-muted-foreground mt-1">
                                    {report.summary.critical_issues} critical issue(s), {report.summary.warnings} warning(s) detected
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Detailed Results Tabs */}
            <Tabs defaultValue="integrity" className="w-full">
                <TabsList className="grid w-full grid-cols-4">
                    <TabsTrigger value="integrity" className="flex items-center gap-1">
                        <CheckCircle className="h-4 w-4" />
                        <span className="hidden sm:inline">Integrity</span>
                    </TabsTrigger>
                    <TabsTrigger value="completeness" className="flex items-center gap-1">
                        <FileText className="h-4 w-4" />
                        <span className="hidden sm:inline">Completeness</span>
                    </TabsTrigger>
                    <TabsTrigger value="crossref" className="flex items-center gap-1">
                        <Shield className="h-4 w-4" />
                        <span className="hidden sm:inline">Cross-Ref</span>
                    </TabsTrigger>
                    <TabsTrigger value="compliance" className="flex items-center gap-1">
                        <Shield className="h-4 w-4" />
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

            {/* Footer */}
            <div className="text-center text-sm text-muted-foreground">
                Report generated on {format(new Date(report.generated_at), 'PPpp')}
                {report.verified_by && ` by User #${report.verified_by}`}
            </div>
        </div>
    );
}

interface SummaryCardProps {
    title: string;
    value: string | number;
    isValid: boolean;
    icon: React.ComponentType<{ className?: string }>;
}

function SummaryCard({ title, value, isValid, icon: Icon }: SummaryCardProps) {
    return (
        <Card>
            <CardContent className="pt-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">{title}</p>
                        <p className="text-2xl font-bold">{value}</p>
                    </div>
                    <div className={cn(
                        'rounded-full p-2',
                        isValid ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900'
                    )}>
                        <Icon className={cn(
                            'h-5 w-5',
                            isValid ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                        )} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function IntegrityTab({ results }: { results: IntegrityResult[] }) {
    if (results.length === 0) {
        return (
            <Card>
                <CardContent className="pt-6 text-center text-muted-foreground">
                    No documents to verify
                </CardContent>
            </Card>
        );
    }

    return (
        <ScrollArea className="h-[400px]">
            <div className="grid gap-4 sm:grid-cols-2">
                {results.map((result, index) => (
                    <IntegrityCheck key={index} result={result} />
                ))}
            </div>
        </ScrollArea>
    );
}

function CompletenessTab({ result }: { result: CompletenessResult }) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-lg">Document Completeness</CardTitle>
                        <CardDescription>Stage: {result.stage_display_name}</CardDescription>
                    </div>
                    <Badge variant={result.is_complete ? 'default' : 'destructive'}>
                        {result.is_complete ? 'Complete' : 'Incomplete'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Progress */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                        <span>Completion Progress</span>
                        <span className="font-medium">{result.completion_percentage.toFixed(1)}%</span>
                    </div>
                    <Progress value={result.completion_percentage} />
                </div>

                {/* Counts */}
                <div className="grid grid-cols-3 gap-4 text-center">
                    <div className="rounded-lg bg-muted p-3">
                        <p className="text-2xl font-bold">{result.document_counts.required}</p>
                        <p className="text-xs text-muted-foreground">Required</p>
                    </div>
                    <div className="rounded-lg bg-green-50 dark:bg-green-950 p-3">
                        <p className="text-2xl font-bold text-green-600">{result.document_counts.uploaded}</p>
                        <p className="text-xs text-muted-foreground">Uploaded</p>
                    </div>
                    <div className="rounded-lg bg-red-50 dark:bg-red-950 p-3">
                        <p className="text-2xl font-bold text-red-600">{result.document_counts.missing}</p>
                        <p className="text-xs text-muted-foreground">Missing</p>
                    </div>
                </div>

                {/* Missing Documents */}
                {result.missing_documents.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm">Missing Documents</h4>
                        <div className="space-y-1">
                            {result.missing_documents.map((doc, index) => (
                                <div key={index} className="flex items-center gap-2 text-sm text-red-600">
                                    <XCircle className="h-4 w-4" />
                                    <span>{doc}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Warnings */}
                {result.warnings.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm">Warnings</h4>
                        <div className="space-y-1">
                            {result.warnings.map((warning, index) => (
                                <div key={index} className="flex items-start gap-2 text-sm text-yellow-600">
                                    <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
                                    <span>{warning}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function CrossReferenceTab({ result }: { result: CrossReferenceResult }) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-lg">Cross-Reference Validation</CardTitle>
                        <CardDescription>PR Number: {result.pr_number}</CardDescription>
                    </div>
                    <Badge variant={result.is_consistent ? 'default' : 'destructive'}>
                        {result.is_consistent ? 'Consistent' : 'Inconsistent'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* PR Number Checks */}
                {result.pr_number_checks.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm">PR Number Verification</h4>
                        <ScrollArea className="h-[200px]">
                            <div className="space-y-2">
                                {result.pr_number_checks.map((check, index) => (
                                    <div
                                        key={index}
                                        className={cn(
                                            'flex items-center justify-between rounded-lg border p-3',
                                            check.matches
                                                ? 'border-green-200 dark:border-green-800'
                                                : 'border-red-200 dark:border-red-800'
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            {check.matches ? (
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                            ) : (
                                                <XCircle className="h-4 w-4 text-red-600" />
                                            )}
                                            <span className="text-sm">{check.document_type}</span>
                                        </div>
                                        <span className="text-xs text-muted-foreground font-mono">
                                            {check.pr_number_in_doc}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </ScrollArea>
                    </div>
                )}

                {/* Errors */}
                {result.errors.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm">Issues</h4>
                        <div className="space-y-1">
                            {result.errors.map((error, index) => (
                                <div key={index} className="flex items-start gap-2 text-sm text-red-600">
                                    <XCircle className="h-4 w-4 mt-0.5 shrink-0" />
                                    <span>{error}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Warnings */}
                {result.warnings.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm">Warnings</h4>
                        <div className="space-y-1">
                            {result.warnings.map((warning, index) => (
                                <div key={index} className="flex items-start gap-2 text-sm text-yellow-600">
                                    <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
                                    <span>{warning}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ComplianceTab({ result }: { result: ComplianceResult }) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-lg">RA 9184/RA 12009 Compliance</CardTitle>
                        <CardDescription>Stage: {result.stage_display_name}</CardDescription>
                    </div>
                    <Badge variant={result.is_compliant ? 'default' : 'destructive'}>
                        {result.is_compliant ? 'Compliant' : 'Non-Compliant'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Document Type Checks */}
                {result.document_type_checks.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm">Document Type Validation</h4>
                        <ScrollArea className="h-[200px]">
                            <div className="space-y-2">
                                {result.document_type_checks.map((check, index) => (
                                    <div
                                        key={index}
                                        className={cn(
                                            'flex items-center justify-between rounded-lg border p-3',
                                            check.valid
                                                ? 'border-green-200 dark:border-green-800'
                                                : 'border-yellow-200 dark:border-yellow-800'
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            {check.valid ? (
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                            ) : (
                                                <AlertTriangle className="h-4 w-4 text-yellow-600" />
                                            )}
                                            <span className="text-sm">{check.document_type}</span>
                                        </div>
                                        <Badge variant={check.is_required ? 'default' : 'secondary'} className="text-xs">
                                            {check.is_required ? 'Required' : 'Optional'}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </ScrollArea>
                    </div>
                )}

                {/* Errors */}
                {result.errors.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm">Compliance Violations</h4>
                        <div className="space-y-1">
                            {result.errors.map((error, index) => (
                                <div key={index} className="flex items-start gap-2 text-sm text-red-600">
                                    <XCircle className="h-4 w-4 mt-0.5 shrink-0" />
                                    <span>{error}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Warnings */}
                {result.warnings.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="font-medium text-sm">Compliance Warnings</h4>
                        <div className="space-y-1">
                            {result.warnings.map((warning, index) => (
                                <div key={index} className="flex items-start gap-2 text-sm text-yellow-600">
                                    <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
                                    <span>{warning}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default VerificationReport;
