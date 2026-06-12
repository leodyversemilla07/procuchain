import { ReportDistributionCard, ReportFilterForm, ReportSummaryCards, ReportTimeSeriesChart, useReportGenerator } from '@/components/reports';
import { HeroCard } from '@/components/hero-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Search } from 'lucide-react';

export default function ReportIndex() {
    const { filters, reportData, loading, error, handleFilterChange, generateReport, exportReport } = useReportGenerator();

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Semantic Search & Reports" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={Search}
                    title="Semantic Search & Reports"
                    description="Generate procurement reports with advanced filtering options"
                />

                <ReportFilterForm
                    filters={filters}
                    onFilterChange={handleFilterChange}
                    onGenerate={generateReport}
                    onExport={exportReport}
                    loading={loading}
                    hasData={!!reportData}
                    error={error}
                />

                {reportData && (
                    <>
                        <ReportSummaryCards
                            totalCount={reportData.summary.total_count}
                            totalAbcAmount={reportData.summary.total_abc_amount}
                            stageCount={Object.keys(reportData.summary.by_stage).length}
                            modeCount={Object.keys(reportData.summary.by_mode).length}
                        />

                        <ReportTimeSeriesChart timeSeries={reportData.time_series} />

                        <div className="grid gap-4 md:grid-cols-2">
                            <ReportDistributionCard title="Distribution by Status" data={reportData.summary.by_status} />
                            <ReportDistributionCard title="Distribution by Mode" data={reportData.summary.by_mode} />
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
