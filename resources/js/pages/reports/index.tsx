import { exportMethod, generate } from '@/actions/App/Http/Controllers/ReportController';
import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BarChart3, Calendar, Download, FileText, Search, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';

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
    data: Array<Record<string, unknown>>;
}

export default function ReportIndex() {
    const [filters, setFilters] = useState<ReportFilters>({
        filter_type: 'month',
        month: new Date().getMonth() + 1,
        year: new Date().getFullYear(),
    });

    const [reportData, setReportData] = useState<ReportData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

    const handleFilterChange = (key: keyof ReportFilters, value: string | number | undefined) => {
        setFilters((prev) => ({ ...prev, [key]: value }));
    };

    const generateReport = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(generate.url(), {
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

    const exportReport = async (format: 'json' | 'csv' | 'pdf') => {
        try {
            const response = await fetch(exportMethod.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ ...filters, format }),
            });

            if (format === 'csv' || format === 'pdf') {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `procurement-report-${new Date().toISOString().split('T')[0]}.${format}`;
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
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    const filterTypeLabels: Record<ReportFilters['filter_type'], string> = {
        month: 'Month',
        quarter: 'Quarter',
        year: 'Year',
        date_range: 'Date Range',
    };

    const quarterLabels: Record<number, string> = {
        1: 'Q1 (Jan-Mar)',
        2: 'Q2 (Apr-Jun)',
        3: 'Q3 (Jul-Sep)',
        4: 'Q4 (Oct-Dec)',
    };

    const selectedFilterTypeLabel = filterTypeLabels[filters.filter_type] ?? 'Filter Type';
    const selectedMonthLabel = filters.month ? (months[filters.month - 1] ?? 'Month') : 'Month';
    const selectedQuarterLabel = filters.quarter ? (quarterLabels[filters.quarter] ?? 'Quarter') : 'Quarter';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Semantic Search & Reports" />

            <div className="from-background to-muted/20 flex h-full flex-1 flex-col gap-4 rounded-xl bg-linear-to-b p-4 sm:gap-6 sm:p-6">
                <HeroCard
                    icon={Search}
                    title="Semantic Search & Reports"
                    description="Generate procurement reports with advanced filtering options"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Report Filters</CardTitle>
                        <CardDescription>Configure filters to generate custom procurement reports</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div className="space-y-2">
                                <Label>Filter Type</Label>
                                <Select value={filters.filter_type} onValueChange={(value) => value && handleFilterChange('filter_type', value)}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue>{() => selectedFilterTypeLabel}</SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="month">Month</SelectItem>
                                            <SelectItem value="quarter">Quarter</SelectItem>
                                            <SelectItem value="year">Year</SelectItem>
                                            <SelectItem value="date_range">Date Range</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>

                            {filters.filter_type === 'month' && (
                                <>
                                    <div className="space-y-2">
                                        <Label>Month</Label>
                                        <Select
                                            value={filters.month?.toString()}
                                            onValueChange={(value) => value && handleFilterChange('month', parseInt(value))}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue>{() => selectedMonthLabel}</SelectValue>
                                            </SelectTrigger>
                                            <SelectContent className="max-h-60 overflow-y-auto">
                                                <SelectGroup>
                                                    {months.map((month, index) => (
                                                        <SelectItem key={index} value={(index + 1).toString()}>
                                                            {month}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Year</Label>
                                        <Select
                                            value={filters.year?.toString()}
                                            onValueChange={(value) => value && handleFilterChange('year', parseInt(value))}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue>{() => filters.year?.toString() ?? 'Year'}</SelectValue>
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {years.map((year) => (
                                                        <SelectItem key={year} value={year.toString()}>
                                                            {year}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
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
                                            onValueChange={(value) => value && handleFilterChange('quarter', parseInt(value))}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue>{() => selectedQuarterLabel}</SelectValue>
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="1">Q1 (Jan-Mar)</SelectItem>
                                                    <SelectItem value="2">Q2 (Apr-Jun)</SelectItem>
                                                    <SelectItem value="3">Q3 (Jul-Sep)</SelectItem>
                                                    <SelectItem value="4">Q4 (Oct-Dec)</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Year</Label>
                                        <Select
                                            value={filters.year?.toString()}
                                            onValueChange={(value) => value && handleFilterChange('year', parseInt(value))}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue>{() => filters.year?.toString() ?? 'Year'}</SelectValue>
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {years.map((year) => (
                                                        <SelectItem key={year} value={year.toString()}>
                                                            {year}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </>
                            )}

                            {filters.filter_type === 'year' && (
                                <div className="space-y-2">
                                    <Label>Year</Label>
                                    <Select value={filters.year?.toString()} onValueChange={(value) => value && handleFilterChange('year', parseInt(value))}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue>{() => filters.year?.toString() ?? 'Year'}</SelectValue>
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {years.map((year) => (
                                                    <SelectItem key={year} value={year.toString()}>
                                                        {year}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
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
                                    <Button onClick={() => exportReport('pdf')} variant="outline">
                                        <Download className="mr-2 h-4 w-4" />
                                        Export PDF
                                    </Button>
                                </>
                            )}
                        </div>

                        {error && <div className="bg-destructive/10 text-destructive rounded-md p-4 text-sm">{error}</div>}
                    </CardContent>
                </Card>

                {reportData && (
                    <>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium">Total Procurements</CardTitle>
                                    <FileText className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{reportData.summary.total_count}</div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium">Total ABC Amount</CardTitle>
                                    <TrendingUp className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">₱{reportData.summary.total_abc_amount.toLocaleString()}</div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium">Unique Stages</CardTitle>
                                    <Calendar className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{Object.keys(reportData.summary.by_stage).length}</div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium">Procurement Modes</CardTitle>
                                    <BarChart3 className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{Object.keys(reportData.summary.by_mode).length}</div>
                                </CardContent>
                            </Card>
                        </div>

                        {reportData.time_series.length > 0 && (
                            <Card className="w-full">
                                <CardHeader>
                                    <CardTitle>Procurement Trends</CardTitle>
                                    <CardDescription>Procurement activity over the selected period</CardDescription>
                                </CardHeader>
                                <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                                    <ChartContainer
                                        config={{
                                            count: {
                                                label: 'Procurements',
                                                color: 'hsl(var(--primary))',
                                            },
                                        }}
                                        className="aspect-auto h-[350px] w-full"
                                    >
                                        <AreaChart data={reportData.time_series} margin={{ left: 12, right: 12, top: 12, bottom: 12 }}>
                                            <defs>
                                                <linearGradient id="fillCount" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.8} />
                                                    <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0.1} />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                            <XAxis
                                                dataKey={reportData.time_series[0]?.date ? 'date' : 'month'}
                                                tickLine={false}
                                                axisLine={false}
                                                tickMargin={8}
                                                minTickGap={32}
                                                tickFormatter={(value) => {
                                                    const date = new Date(value);
                                                    return date.toLocaleDateString('en-US', {
                                                        month: 'short',
                                                        day: 'numeric',
                                                    });
                                                }}
                                            />
                                            <YAxis tickLine={false} axisLine={false} tickMargin={8} />
                                            <ChartTooltip
                                                cursor={false}
                                                content={
                                                    <ChartTooltipContent
                                                        labelFormatter={(value) => {
                                                            return new Date(value).toLocaleDateString('en-US', {
                                                                month: 'short',
                                                                day: 'numeric',
                                                                year: 'numeric',
                                                            });
                                                        }}
                                                        indicator="line"
                                                    />
                                                }
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="count"
                                                stroke="hsl(var(--primary))"
                                                fill="url(#fillCount)"
                                                fillOpacity={0.6}
                                                strokeWidth={2}
                                            />
                                        </AreaChart>
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
                                    <div className="flex flex-col gap-2">
                                        {Object.entries(reportData.summary.by_status).map(([status, count]) => (
                                            <div key={status} className="flex items-center justify-between">
                                                <span className="text-sm capitalize">{status.replace(/_/g, ' ')}</span>
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
                                    <div className="flex flex-col gap-2">
                                        {Object.entries(reportData.summary.by_mode).map(([mode, count]) => (
                                            <div key={mode} className="flex items-center justify-between">
                                                <span className="text-sm capitalize">{mode.replace(/_/g, ' ')}</span>
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
