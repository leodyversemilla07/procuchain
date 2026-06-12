import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';

interface ReportTimeSeriesChartProps {
    timeSeries: Array<{ date?: string; month?: string; count: number }>;
}

export function ReportTimeSeriesChart({ timeSeries }: ReportTimeSeriesChartProps) {
    if (timeSeries.length === 0) return null;

    const dataKey = timeSeries[0]?.date ? 'date' : 'month';

    return (
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
                    <AreaChart data={timeSeries} margin={{ left: 12, right: 12, top: 12, bottom: 12 }}>
                        <defs>
                            <linearGradient id="fillCount" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.8} />
                                <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0.1} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                        <XAxis
                            dataKey={dataKey}
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
    );
}
