import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { PageProps } from '@inertiajs/core';
import { Head, usePage } from '@inertiajs/react';
import { BarChart3, Calendar, Download, FileText, Search, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';

interface ReportIndexProps extends PageProps {
    now: string;
}

interface ReportFilters {
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

interface ReportData {
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
    data: Array<any>;
}

export default function ReportIndex({ now }: ReportIndexProps) {
    const { auth } = usePage<PageProps<SharedData>>().props;

    const [filters, setFilters] = useState<ReportFilters>({
        filter_type: 'month',
        month: new Date().getMonth() + 1,
        year: new Date().getFullYear(),
    });

    const [reportData, setReportData] = useState<ReportData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { name: 'Reports', href: '/reports', current: true },
    ];

    const handleFilterChange = (key: keyof ReportFilters, value: any) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    const generateReport = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch('/reports/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(filters),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to generate report');
            }

            setReportData(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setLoading(false);
        }
    };

    const exportReport = async (format: 'json' | 'csv') => {
        try {
            const response = await fetch('/reports/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ ...filters, format }),
            });

            if (format === 'csv') {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `procurement-report-${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
            } else {
                const data = await response.json();
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `procurement-report-${new Date().toISOString().split('T')[0]}.json`;
                a.click();
                window.URL.revokeObjectURL(url);
            }
        } catch (err) {
            console.error('Export failed:', err);
        }
    };

    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 10 }, (_, i) => currentYear - i);
    const months = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Semantic Search & Reports" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Semantic Search & Reports</h1>
                    <p className="text-muted-foreground">Generate procurement reports with advanced filtering options</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Report Filters</CardTitle>
                        <CardDescription>Configure filters to generate custom procurement reports</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div className="space-y-2">
                                <Label>Filter Type</Label>
                                <Select
                                    value={filters.filter_type}
                                    onValueChange={(value) => handleFilterChange('filter_type', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="month">Month</SelectItem>
                                        <SelectItem value="quarter">Quarter</SelectItem>
                                        <SelectItem value="year">Year</SelectItem>
                                        <SelectItem value="date_range">Date Range</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {filters.filter_type === 'month' && (
                                <>
                                    <div className="space-y-2">
                                        <Label>Month</Label>
                                        <Select
                                            value={filters.month?.toString()}
                                            onValueChange={(value) => handleFilterChange('month', parseInt(value))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {months.map((month, index) => (
                                                    <SelectItem key={index} value={(index + 1).toString()}>
                                                        {month}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Year</Label>
                                        <Select
                                            value={filters.year?.toString()}
                                            onValueChange={(value) => handleFilterChange('year', parseInt(value))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {years.map((year) => (
                                                    <SelectItem key={year} value={year.toString()}>
                                                        {year}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </>
                            )}

                            {filters.filter_type === 'quarter' && (
                                <>
                                    <div className="space-y-2">
                                        <Label>Quarter</Label>
                                        <Select
                                            value={filters.quarter?.toString()}
                                            onValueChange={(value) => handleFilterChange('quarter', parseInt(value))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">Q1 (Jan-Mar)</SelectItem>
                                                <SelectItem value="2">Q2 (Apr-Jun)</SelectItem>
                                                <SelectItem value="3">Q3 (Jul-Sep)</SelectItem>
                                                <SelectItem value="4">Q4 (Oct-Dec)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Year</Label>
                                        <Select
                                            value={filters.year?.toString()}
                                            onValueChange={(value) => handleFilterChange('year', parseInt(value))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {years.map((year) => (
                                                    <SelectItem key={year} value={year.toString()}>
                                                        {year}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </>
                            )}

                            {filters.filter_type === 'year' && (
                                <div className="space-y-2">
                                    <Label>Year</Label>
                                    <Select
                                        value={filters.year?.toString()}
                                        onValueChange={(value) => handleFilterChange('year', parseInt(value))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {years.map((year) => (
                                                <SelectItem key={year} value={year.toString()}>
                                                    {year}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {filters.filter_type === 'date_range' && (
                                <>
                                    <div className="space-y-2">
                                        <Label>Date From</Label>
                                        <Input
                                            type="date"
                                            value={filters.date_from || ''}
                                            onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Date To</Label>
                                        <Input
                                            type="date"
                                            value={filters.date_to || ''}
                                            onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                        />
                                    </div>
                                </>
                            )}

                            <div className="space-y-2">
                                <Label>Search Query</Label>
                                <Input
                                    placeholder="Search title, ID, description..."
                                    value={filters.query || ''}
                                    onChange={(e) => handleFilterChange('query', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="flex gap-2">
                            <Button onClick={generateReport} disabled={loading}>
                                {loading ? (
                                    <>
                                        <Spinner className="mr-2 h-4 w-4" />
                                        Generating...
                                    </>
                                ) : (
                                    <>
                                        <BarChart3 className="mr-2 h-4 w-4" />
                                        Generate Report
                                    </>
                                )}
                            </Button>

                            {reportData && (
                                <>
                                    <Button onClick={() => exportReport('csv')} variant="outline">
                                        <Download className="mr-2 h-4 w-4" />
                                        Export CSV
                                    </Button>
                                    <Button onClick={() => exportReport('json')} variant="outline">
                                        <Download className="mr-2 h-4 w-4" />
                                        Export JSON
                                    </Button>
                                </>
                            )}
                        </div>

                        {error && (
                            <div className="rounded-md bg-destructive/10 p-4 text-sm text-destructive">{error}</div>
                        )}
                    </CardContent>
                </Card>

                {reportData && (
                    <>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Total Procurements</CardTitle>
                                    <FileText className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{reportData.summary.total_count}</div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Total ABC Amount</CardTitle>
                                    <TrendingUp className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        ₱{reportData.summary.total_abc_amount.toLocaleString()}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Unique Stages</CardTitle>
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {Object.keys(reportData.summary.by_stage).length}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Procurement Modes</CardTitle>
                                    <BarChart3 className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {Object.keys(reportData.summary.by_mode).length}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {reportData.time_series.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Time Series Analysis</CardTitle>
                                    <CardDescription>Procurement trends over selected period</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ChartContainer
                                        config={{
                                            count: {
                                                label: 'Procurements',
                                                color: 'hsl(var(--primary))',
                                            },
                                        }}
                                        className="h-[300px]"
                                    >
                                        <LineChart data={reportData.time_series}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis dataKey={reportData.time_series[0]?.date ? 'date' : 'month'} />
                                            <YAxis />
                                            <ChartTooltip content={<ChartTooltipContent />} />
                                            <Line
                                                type="monotone"
                                                dataKey="count"
                                                stroke="hsl(var(--primary))"
                                                strokeWidth={2}
                                            />
                                        </LineChart>
                                    </ChartContainer>
                                </CardContent>
                            </Card>
                        )}

                        <div className="grid gap-4 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Distribution by Status</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {Object.entries(reportData.summary.by_status).map(([status, count]) => (
                                            <div key={status} className="flex items-center justify-between">
                                                <span className="text-sm">{status}</span>
                                                <Badge variant="secondary">{count}</Badge>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Distribution by Mode</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {Object.entries(reportData.summary.by_mode).map(([mode, count]) => (
                                            <div key={mode} className="flex items-center justify-between">
                                                <span className="text-sm">{mode}</span>
                                                <Badge variant="secondary">{count}</Badge>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
