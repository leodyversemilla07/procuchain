import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { BarChart3, Download } from 'lucide-react';
import type { ReportFilters } from './report-utils';

interface ReportFilterFormProps {
    filters: ReportFilters;
    onFilterChange: (key: keyof ReportFilters, value: string | number | undefined) => void;
    onGenerate: () => void;
    onExport: (format: 'json' | 'csv' | 'pdf') => void;
    loading: boolean;
    hasData: boolean;
    error: string | null;
}

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const QUARTER_LABELS: Record<number, string> = {
    1: 'Q1 (Jan-Mar)',
    2: 'Q2 (Apr-Jun)',
    3: 'Q3 (Jul-Sep)',
    4: 'Q4 (Oct-Dec)',
};

const FILTER_TYPE_LABELS: Record<ReportFilters['filter_type'], string> = {
    month: 'Month',
    quarter: 'Quarter',
    year: 'Year',
    date_range: 'Date Range',
};

function YearSelect({ value, onChange, years }: { value?: number; onChange: (v: number) => void; years: number[] }) {
    return (
        <div className="flex flex-col gap-2">
            <Label>Year</Label>
            <Select value={value?.toString()} onValueChange={(v) => v && onChange(parseInt(v))}>
                <SelectTrigger className="w-full">
                    <SelectValue>{() => value?.toString() ?? 'Year'}</SelectValue>
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
    );
}

export function ReportFilterForm({ filters, onFilterChange, onGenerate, onExport, loading, hasData, error }: ReportFilterFormProps) {
    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 10 }, (_, i) => currentYear - i);

    const selectedFilterTypeLabel = FILTER_TYPE_LABELS[filters.filter_type] ?? 'Filter Type';
    const selectedMonthLabel = filters.month ? (MONTHS[filters.month - 1] ?? 'Month') : 'Month';
    const selectedQuarterLabel = filters.quarter ? (QUARTER_LABELS[filters.quarter] ?? 'Quarter') : 'Quarter';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Report Filters</CardTitle>
                <CardDescription>Configure filters to generate custom procurement reports</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div className="flex flex-col gap-2">
                        <Label>Filter Type</Label>
                        <Select value={filters.filter_type} onValueChange={(value) => value && onFilterChange('filter_type', value)}>
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
                            <div className="flex flex-col gap-2">
                                <Label>Month</Label>
                                <Select
                                    value={filters.month?.toString()}
                                    onValueChange={(value) => value && onFilterChange('month', parseInt(value))}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue>{() => selectedMonthLabel}</SelectValue>
                                    </SelectTrigger>
                                    <SelectContent className="max-h-60 overflow-y-auto">
                                        <SelectGroup>
                                            {MONTHS.map((month, index) => (
                                                <SelectItem key={index} value={(index + 1).toString()}>
                                                    {month}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </div>
                            <YearSelect value={filters.year} onChange={(v) => onFilterChange('year', v)} years={years} />
                        </>
                    )}

                    {filters.filter_type === 'quarter' && (
                        <>
                            <div className="flex flex-col gap-2">
                                <Label>Quarter</Label>
                                <Select
                                    value={filters.quarter?.toString()}
                                    onValueChange={(value) => value && onFilterChange('quarter', parseInt(value))}
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
                            <YearSelect value={filters.year} onChange={(v) => onFilterChange('year', v)} years={years} />
                        </>
                    )}

                    {filters.filter_type === 'year' && <YearSelect value={filters.year} onChange={(v) => onFilterChange('year', v)} years={years} />}

                    {filters.filter_type === 'date_range' && (
                        <>
                            <div className="flex flex-col gap-2">
                                <Label>Date From</Label>
                                <Input type="date" value={filters.date_from || ''} onChange={(e) => onFilterChange('date_from', e.target.value)} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label>Date To</Label>
                                <Input type="date" value={filters.date_to || ''} onChange={(e) => onFilterChange('date_to', e.target.value)} />
                            </div>
                        </>
                    )}

                    <div className="flex flex-col gap-2">
                        <Label>Search Query</Label>
                        <Input
                            placeholder="Search title, ID, description..."
                            value={filters.query || ''}
                            onChange={(e) => onFilterChange('query', e.target.value)}
                        />
                    </div>
                </div>

                <div className="flex gap-2">
                    <Button onClick={onGenerate} disabled={loading}>
                        {loading ? (
                            <>
                                <Spinner />
                                Generating...
                            </>
                        ) : (
                            <>
                                <BarChart3 />
                                Generate Report
                            </>
                        )}
                    </Button>

                    {hasData && (
                        <>
                            <Button onClick={() => onExport('csv')} variant="outline">
                                <Download />
                                Export CSV
                            </Button>
                            <Button onClick={() => onExport('json')} variant="outline">
                                <Download />
                                Export JSON
                            </Button>
                            <Button onClick={() => onExport('pdf')} variant="outline">
                                <Download />
                                Export PDF
                            </Button>
                        </>
                    )}
                </div>

                {error && <div className="bg-destructive/10 text-destructive rounded-md p-4 text-sm">{error}</div>}
            </CardContent>
        </Card>
    );
}
