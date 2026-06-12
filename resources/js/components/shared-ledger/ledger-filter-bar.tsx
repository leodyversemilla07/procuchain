import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { NodeOption, StreamOption } from '@/types/blockchain';
import { format } from 'date-fns';
import { ArrowDownUp, CalendarIcon, Download, FilterX, ScrollText, Server, ServerCrash } from 'lucide-react';
import React from 'react';
import { type DateRange } from 'react-day-picker';
import { STREAM_CONFIG } from './stream-config';

interface LedgerFilterBarProps {
    available_streams: StreamOption[];
    available_nodes: NodeOption[];
    entriesCount: number;
    buildQuery: (overrides?: Record<string, string | undefined>) => Record<string, string>;
    navigate: (query: Record<string, string>) => void;
    setIsFiltering: (value: boolean) => void;
    prNumber: string;
    setPrNumber: (value: string) => void;
    stream: string;
    setStream: (value: string) => void;
    node: string;
    setNode: (value: string) => void;
    dateRange: DateRange | undefined;
    setDateRange: (value: DateRange | undefined) => void;
    hasActiveFilters: boolean;
    clearFilters: () => void;
}

export function LedgerFilterBar({
    prNumber,
    setPrNumber,
    stream,
    setStream,
    node,
    setNode,
    dateRange,
    setDateRange,
    available_streams,
    available_nodes,
    entriesCount,
    buildQuery,
    navigate,
    setIsFiltering,
    hasActiveFilters,
    clearFilters,
}: LedgerFilterBarProps) {
    const selectedStreamLabel = stream && stream !== 'all' ? (STREAM_CONFIG[stream]?.label ?? stream) : 'All streams';

    const handleExport = (entries: Array<{ formatted_timestamp: string; stream_display: string; pr_number: string; action: string; summary: string; actor_address: string; txid: string; procurement_title?: string }>) => {
        const headers = ['Timestamp', 'Stream', 'PR Number', 'Action', 'Summary', 'Actor', 'TX ID', 'Procurement Title'];
        const rows = entries.map((e) => [
            e.formatted_timestamp,
            e.stream_display,
            e.pr_number,
            e.action,
            e.summary,
            e.actor_address,
            e.txid,
            e.procurement_title ?? '',
        ]);

        const csv = [headers.join(','), ...rows.map((r) => r.map((v) => `"${v.replace(/"/g, '""')}"`).join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `shared-ledger-${format(new Date(), 'yyyy-MM-dd')}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <ArrowDownUp />
                    Filters
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Input
                        type="text"
                        placeholder="PR Number (e.g. PR-2026-001)"
                        value={prNumber}
                        onChange={(e) => setPrNumber(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                setIsFiltering(true);
                                navigate(buildQuery());
                            }
                        }}
                    />

                    <Select
                        value={node || 'all'}
                        onValueChange={(value) => {
                            if (!value) return;
                            setNode(value);
                            setIsFiltering(true);
                            navigate(buildQuery({ node: value !== 'all' ? value : undefined }));
                        }}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="All nodes">
                                {() => (node && node !== 'all' ? (available_nodes.find((n) => n.id === node)?.name ?? node) : 'All nodes')}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    <div className="flex items-center gap-2">
                                        <Server className="h-3.5 w-3.5" />
                                        All nodes (shared)
                                    </div>
                                </SelectItem>
                                {available_nodes.map((n) => (
                                    <SelectItem key={n.id} value={n.id}>
                                        <div className="flex items-center gap-2">
                                            {n.is_purged ? (
                                                <ServerCrash className="text-destructive" />
                                            ) : (
                                                <Server className="h-3.5 w-3.5" />
                                            )}
                                            {n.name}
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <Select
                        value={stream || 'all'}
                        onValueChange={(value) => {
                            if (!value) return;
                            setStream(value);
                            setIsFiltering(true);
                            navigate(buildQuery({ stream: value !== 'all' ? value : undefined }));
                        }}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="All transactions">{() => selectedStreamLabel}</SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">All transactions</SelectItem>
                                {available_streams.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        <div className="flex items-center gap-2">
                                            {React.createElement(STREAM_CONFIG[s.value]?.icon ?? ScrollText, { className: 'h-3.5 w-3.5' })}
                                            {s.label}
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <Popover>
                        <PopoverTrigger
                            render={
                                <Button
                                    variant="outline"
                                    className={cn('w-full justify-start text-left font-normal', !dateRange?.from && 'text-muted-foreground')}
                                />
                            }
                        >
                            <CalendarIcon />
                            {dateRange?.from ? (
                                dateRange.to ? (
                                    <>
                                        {format(dateRange.from, 'MMM d, yyyy')} - {format(dateRange.to, 'MMM d, yyyy')}
                                    </>
                                ) : (
                                    format(dateRange.from, 'MMM d, yyyy')
                                )
                            ) : (
                                <span>Date range</span>
                            )}
                        </PopoverTrigger>
                        <PopoverContent className="w-auto p-0" align="start">
                            <Calendar
                                autoFocus
                                mode="range"
                                defaultMonth={dateRange?.from}
                                selected={dateRange}
                                onSelect={setDateRange}
                                numberOfMonths={2}
                            />
                        </PopoverContent>
                    </Popover>
                </div>
            </CardContent>
            <CardFooter className="flex gap-2">
                {hasActiveFilters && (
                    <Button variant="outline" onClick={clearFilters}>
                        <FilterX />
                        Clear
                    </Button>
                )}
                <div className="ml-auto">
                    <Button variant="outline" onClick={() => handleExport([])} disabled={entriesCount === 0}>
                        <Download />
                        Export CSV
                    </Button>
                </div>
            </CardFooter>
        </Card>
    );
}
