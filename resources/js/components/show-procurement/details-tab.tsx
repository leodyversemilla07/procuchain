import { Banknote, Building2, Calendar, ClipboardList, Clock, Copy, FileText, MapPin, User } from 'lucide-react';
import { type FC } from 'react';
import { toast } from 'sonner';

import { TruncateBadge } from '@/components/truncate-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

interface ProcurementDetails {
    pr_number: string;
    app_reference?: string;
    title: string;
    description: string;
    abc_amount: number;
    abc_amount_formatted: string;
    funding_source: string;
    category: string;
    category_label: string;
    procurement_mode: string;
    procurement_mode_label: string;
    office: string;
    end_user?: string;
    delivery_location?: string;
    delivery_date?: string;
    delivery_date_formatted?: string;
    delivery_term_days?: number;
    prepared_by?: string;
    bac_resolution_number?: string;
    bac_resolution_date?: string;
    bac_resolution_date_formatted?: string;
    philgeps_reference?: string;
    philgeps_posting_date?: string;
    philgeps_posting_date_formatted?: string;
    approved_by?: string;
    approval_date?: string;
    approval_date_formatted?: string;
    created_at: string;
    created_at_formatted: string;
}

interface DetailsTabProps {
    details?: ProcurementDetails;
}

export const DetailsTab: FC<DetailsTabProps> = ({ details }) => {
    if (!details) return null;

    const copyToClipboard = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        toast.success(`${label} copied to clipboard`);
    };

    return (
        <Card className="border shadow-sm">
            <CardHeader className="p-4 pb-2 sm:p-6 sm:pb-4">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 flex h-8 w-8 items-center justify-center rounded-lg sm:h-10 sm:w-10">
                        <FileText />
                    </div>
                    <div>
                        <CardTitle className="text-base sm:text-lg">Procurement Details</CardTitle>
                        <CardDescription className="text-xs sm:text-sm">Comprehensive information about this procurement</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="grid gap-6 p-4 sm:p-6">
                    {/* Primary Info Section */}
                    <div className="bg-muted/30 flex flex-col gap-4 rounded-lg border p-4">
                        <div className="flex flex-col gap-1">
                            <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Procurement Title</label>
                            <h3 className="text-base leading-tight font-semibold sm:text-lg">{details.title}</h3>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                            <div className="flex flex-col gap-1">
                                <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">PR Number</label>
                                <div className="flex items-center gap-2">
                                    <span className="font-mono font-medium">{details.pr_number}</span>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="text-muted-foreground hover:text-foreground h-6 w-6"
                                        onClick={() => copyToClipboard(details.pr_number, 'PR Number')}
                                    >
                                        <Copy />
                                    </Button>
                                </div>
                            </div>
                            {details.app_reference && (
                                <div className="flex flex-col gap-1">
                                    <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">AIP Code Reference</label>
                                    <p className="font-medium">{details.app_reference}</p>
                                </div>
                            )}
                            <div className="flex flex-col gap-1 sm:col-span-2">
                                <label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Description</label>
                                <p className="text-muted-foreground line-clamp-3 text-sm transition-all hover:line-clamp-none">
                                    {details.description}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        {/* Financial & Classification */}
                        <div className="flex flex-col gap-4">
                            <div className="text-primary flex items-center gap-2 border-b pb-2 font-semibold">
                                <Banknote />
                                <span>Financial & Classification</span>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="ABC Amount" value={details.abc_amount_formatted} className="text-primary text-lg font-bold" />
                                <DetailItem label="Funding Source" value={details.funding_source} />
                                <DetailItem
                                    label="Category"
                                    value={
                                        <TruncateBadge variant="secondary" maxChars={22}>
                                            {details.category_label}
                                        </TruncateBadge>
                                    }
                                />
                                <DetailItem
                                    label="Procurement Mode"
                                    value={
                                        <TruncateBadge variant="outline" maxChars={22}>
                                            {details.procurement_mode_label}
                                        </TruncateBadge>
                                    }
                                />
                            </div>
                        </div>

                        {/* Office & Stakeholders */}
                        <div className="flex flex-col gap-4">
                            <div className="text-primary flex items-center gap-2 border-b pb-2 font-semibold">
                                <Building2 />
                                <span>Office & Stakeholders</span>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="Requesting Office" value={details.office} icon={<MapPin />} />
                                <DetailItem label="End User" value={details.end_user} icon={<User />} />
                                <DetailItem label="Prepared By" value={details.prepared_by} icon={<User />} />
                                <DetailItem label="Approved By" value={details.approved_by} icon={<User />} />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Additional Details Grid */}
                    <div className="grid gap-6 md:grid-cols-2">
                        {/* Legal & Reference */}
                        <div className="flex flex-col gap-4">
                            <div className="text-primary flex items-center gap-2 border-b pb-2 font-semibold">
                                <ClipboardList />
                                <span>Reference & Legal</span>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="BAC Resolution No." value={details.bac_resolution_number} />
                                <DetailItem
                                    label="BAC Res. Date"
                                    value={details.bac_resolution_date_formatted}
                                    icon={<Calendar />}
                                />
                                <DetailItem label="PhilGEPS Ref." value={details.philgeps_reference} />
                                <DetailItem
                                    label="PhilGEPS Posting"
                                    value={details.philgeps_posting_date_formatted}
                                    icon={<Calendar />}
                                />
                            </div>
                        </div>

                        {/* Timeline & Delivery */}
                        <div className="flex flex-col gap-4">
                            <div className="text-primary flex items-center gap-2 border-b pb-2 font-semibold">
                                <Clock />
                                <span>Timeline & Delivery</span>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="Created At" value={details.created_at_formatted} icon={<Calendar />} />
                                <DetailItem label="Approval Date" value={details.approval_date_formatted} icon={<Calendar />} />
                                {details.delivery_location && (
                                    <DetailItem label="Delivery Location" value={details.delivery_location} icon={<MapPin />} />
                                )}
                                {(details.delivery_date_formatted || details.delivery_term_days) && (
                                    <DetailItem
                                        label="Delivery Term"
                                        value={details.delivery_date_formatted || `${details.delivery_term_days} Days`}
                                        icon={<Calendar />}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};

const DetailItem = ({
    label,
    value,
    icon,
    className,
}: {
    label: string;
    value?: string | number | React.ReactNode;
    icon?: React.ReactNode;
    className?: string;
}) => {
    if (!value) return null;

    return (
        <div className="min-w-0 flex flex-col gap-1">
            <label className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase">
                {icon}
                {label}
            </label>
            <div className={cn('min-w-0 text-sm font-medium', typeof value === 'string' ? 'truncate' : '', className)}>{value}</div>
        </div>
    );
};
