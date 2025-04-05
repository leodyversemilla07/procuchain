import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { CheckIcon, FileIcon, CheckCircle, EyeIcon } from "lucide-react";
import { Link } from "@inertiajs/react";
import { getStatusBadgeStyle, getStageBadgeStyle } from "@/lib/procurements-list-utils";
import type { RecentProcurement } from "@/types/dashboard";

interface RecentProcurementsTableProps {
    procurements: RecentProcurement[];
}

export function RecentProcurementsTable({ procurements }: RecentProcurementsTableProps) {
    if (procurements.length === 0) {
        return (
            <Card className="shadow-sm">
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>ID</TableHead>
                                <TableHead>Title</TableHead>
                                <TableHead>Stage</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow>
                                <TableCell colSpan={5} className="text-center py-8">
                                    No procurement data available
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        );
    }

    const getStatusIcon = (status: string) => {
        if (status === 'Pre-Procurement Conference Completed') return <CheckIcon className="h-3 w-3 mr-1" />;
        if (status === 'Bids Opened') return <FileIcon className="h-3 w-3 mr-1" />;
        if (status === 'Awarded') return <CheckCircle className="h-3 w-3 mr-1" />;
        return null;
    };

    return (
        <Card className="shadow-sm">
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Title</TableHead>
                            <TableHead>Stage</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {procurements.map(procurement => (
                            <TableRow key={procurement.id}>
                                <TableCell className="font-medium">{procurement.id}</TableCell>
                                <TableCell className="max-w-[140px] truncate" title={procurement.title}>
                                    {procurement.title}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline" className={getStageBadgeStyle(procurement.stage)}>
                                        {procurement.stage}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline" className={getStatusBadgeStyle(procurement.status)}>
                                        {getStatusIcon(procurement.status)}
                                        <span className="truncate max-w-[100px]" title={procurement.status}>
                                            {procurement.status}
                                        </span>
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-right">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                                className="h-8 px-2"
                                            >
                                                <Link href={`/bac-secretariat/procurements-list/${procurement.id}`}>
                                                    <EyeIcon className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>View Procurement Details</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}