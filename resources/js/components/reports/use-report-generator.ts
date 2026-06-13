import { exportMethod, generate } from '@/actions/App/Http/Controllers/ReportController';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { csrfHeaders, downloadBlob, resolveRouteFn, type ReportData, type ReportFilters } from './report-utils';

export function useReportGenerator() {
    const [filters, setFilters] = useState<ReportFilters>({
        filter_type: 'month',
        month: new Date().getMonth() + 1,
        year: new Date().getFullYear(),
    });

    const [reportData, setReportData] = useState<ReportData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const generateUrl = useMemo(() => resolveRouteFn(generate), []);
    const exportUrl = useMemo(() => resolveRouteFn(exportMethod), []);

    const handleFilterChange = (key: keyof ReportFilters, value: string | number | undefined) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    const generateReport = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(generateUrl(), {
                method: 'POST',
                headers: csrfHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(filters),
            });

            const contentType = response.headers.get('content-type') ?? '';
            const isJson = contentType.includes('application/json');
            const data = isJson ? await response.json() : null;

            if (!response.ok) {
                throw new Error(
                    isJson ? data?.message || `Report generation failed (HTTP ${response.status})` : `Server error (HTTP ${response.status})`,
                );
            }

            if (!data?.success) {
                throw new Error(data?.message || 'Failed to generate report');
            }

            setReportData(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setLoading(false);
        }
    };

    const exportReport = async (format: 'json' | 'csv' | 'pdf') => {
        try {
            await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });

            const headers = format === 'pdf' ? csrfHeaders({ Accept: 'application/pdf' }) : csrfHeaders();

            const response = await fetch(exportUrl(), {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({ ...filters, format }),
            });

            if (!response.ok) {
                let message = 'Export failed';
                try {
                    const data = await response.json();
                    message = data.message || message;
                } catch {
                    message = response.statusText || `Export failed (HTTP ${response.status})`;
                }
                toast.error(message);
                return;
            }

            const dateStr = new Date().toISOString().split('T')[0];
            const filename = `procurement-report-${dateStr}.${format}`;

            if (format === 'csv' || format === 'pdf') {
                const blob = await response.blob();
                downloadBlob(blob, filename, format === 'pdf' ? 'application/pdf' : 'text/csv');
            } else {
                const data = await response.json();
                downloadBlob(JSON.stringify(data, null, 2), filename, 'application/json');
            }
        } catch (err) {
            console.error('Export failed:', err);
            toast.error(err instanceof Error ? err.message : 'Export failed');
        }
    };

    return {
        filters,
        reportData,
        loading,
        error,
        handleFilterChange,
        generateReport,
        exportReport,
    };
}
