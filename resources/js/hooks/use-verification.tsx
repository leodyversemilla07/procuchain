import { useCallback } from 'react';
import { usePage } from '@inertiajs/react';
import type { BreadcrumbItem, SharedData } from '@/types';

// =============================================================================
// Types
// =============================================================================

export interface IntegrityResult {
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

export interface CompletenessResult {
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
    uploaded_optional: number;
    missing: number;
  };
  can_complete_stage: boolean;
  errors: string[];
  warnings: string[];
  verified_at: string;
}

export interface CrossReferenceResult {
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

export interface ComplianceResult {
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

export interface VerificationReportData {
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

export interface ProcurementStatusData {
  stage: string;
  stage_formatted: string;
  current_status: string;
  status_formatted: string;
  phase: string;
  phase_display_name: string;
}

export interface VerificationPageProps {
  prNumber: string;
  report: VerificationReportData;
  procurementStatus?: ProcurementStatusData | null;
}

// =============================================================================
// Hook
// =============================================================================

export function useVerification({ prNumber, report, procurementStatus }: VerificationPageProps) {
  const { auth } = usePage<SharedData>().props;
  const userRole = auth?.role || auth?.user?.role || 'guest';

  const getRoleBasedUrl = useCallback(
    (path: string) => {
      const rolePrefix =
        userRole === 'admin'
          ? '/admin'
          : userRole === 'bac_chairman'
            ? '/bac-chairman'
            : userRole === 'hope'
              ? '/hope'
              : '/bac-secretariat';
      return `${rolePrefix}${path}`;
    },
    [userRole],
  );

  const displayPrNumber = prNumber || 'Unknown';

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: getRoleBasedUrl('/dashboard') },
    { title: 'Procurements', href: getRoleBasedUrl('/procurements-list') },
    { title: displayPrNumber, href: getRoleBasedUrl(`/procurements-list/${displayPrNumber}`) },
    { title: 'Verification Report', href: '#' },
  ];

  const handleExport = useCallback(() => {
    window.print();
  }, []);

  const handleRefresh = useCallback(() => {
    window.location.reload();
  }, []);

  const overallStatus = report?.overall_status ?? '';
  const hasIssues = report ? report.summary.critical_issues > 0 || report.summary.warnings > 0 : false;
  const allValid = report?.overall_valid ?? false;

  return {
    userRole,
    displayPrNumber,
    breadcrumbs,
    handleExport,
    handleRefresh,
    overallStatus,
    hasIssues,
    allValid,
    getRoleBasedUrl,
  };
}
