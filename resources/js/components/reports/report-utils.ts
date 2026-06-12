import { getXsrfToken } from '@/lib/csrf';

export interface ReportFilters {
    filter_type: 'month' | 'year' | 'quarter' | 'date_range';
    month?: number;
    year?: number;
    quarter?: number;
    date_from?: string;
    date_to?: string;
    query?: string;
    status?: string;
    stage?: string;
    mode?: string;
    category?: string;
}

export interface ReportData {
    success: boolean;
    report_generated_at: string;
    parameters: ReportFilters;
    summary: {
        total_count: number;
        by_status: Record<string, number>;
        by_stage: Record<string, number>;
        by_mode: Record<string, number>;
        by_category: Record<string, number>;
        total_abc_amount: number;
    };
    time_series: Array<{ date?: string; month?: string; count: number }>;
    data: Array<Record<string, unknown>>;
}

export function resolveRouteFn(routeMap: Record<string, { url: () => string }>): () => string {
    const prefix = window.location.pathname.replace(/\/reports.*/, '/reports');
    const key = Object.keys(routeMap).find((k) => k.startsWith(prefix));
    const routeFn = key ? routeMap[key] : Object.values(routeMap)[0];
    return () => routeFn.url();
}

export function downloadBlob(content: Blob | string, filename: string, mimeType: string) {
    const blob = content instanceof Blob ? content : new Blob([content], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

export function csrfHeaders(extra?: Record<string, string>): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getXsrfToken(),
        ...extra,
    };
}
