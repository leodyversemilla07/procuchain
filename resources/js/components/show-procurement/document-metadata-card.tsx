import { Building, Calendar, CheckCircle, FileCheck, FileText, PoundSterlingIcon as PhilippinePeso, UserRound, Users, XCircle } from 'lucide-react';
import { type FC, type JSX } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { StageMetadata } from '@/types';

interface DocumentMetadataCardProps {
    metadata: StageMetadata;
    documentType?: string;
}

export const DocumentMetadataCard: FC<DocumentMetadataCardProps> = ({ metadata, documentType }) => {
    if (!metadata || Object.values(metadata).every((v) => !v)) {
        return null;
    }

    const metadataMap: Array<{
        key: keyof StageMetadata;
        label: string;
        icon: JSX.Element;
        useFormatted?: boolean;
    }> = [
        { key: 'pr_number', label: 'PR Number', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'pr_purpose', label: 'PR Purpose', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'requested_by', label: 'Requested By', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'approved_by', label: 'Approved By', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'appropriation',
            label: 'Appropriation',
            icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        { key: 'funding_source', label: 'Funding Source', icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'meeting_date',
            label: 'Meeting Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        { key: 'participants', label: 'Participants', icon: <Users className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'submission_date',
            label: 'Submission Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        {
            key: 'issuance_date',
            label: 'Issuance Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        {
            key: 'opening_date',
            label: 'Opening Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        { key: 'bidder_name', label: 'Bidder Name', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'bid_value',
            label: 'Bid Value',
            icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        {
            key: 'evaluation_date',
            label: 'Evaluation Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        { key: 'evaluator_names', label: 'Evaluator Names', icon: <Users className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'outcome',
            label: 'Verification Outcome',
            icon:
                metadata?.outcome === 'Verified' ? (
                    <CheckCircle className="text-primary h-3.5 w-3.5 sm:h-4 sm:w-4" />
                ) : (
                    <XCircle className="text-destructive h-3.5 w-3.5 sm:h-4 sm:w-4" />
                ),
        },
        { key: 'signatory_details', label: 'Signatory Details', icon: <UserRound className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'bond_amount',
            label: 'Bond Amount',
            icon: <PhilippinePeso className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        {
            key: 'signing_date',
            label: 'Signing Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        {
            key: 'report_date',
            label: 'Report Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        { key: 'report_notes', label: 'Report Notes', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'municipal_offices', label: 'Municipal Offices', icon: <Building className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'bulletin_number', label: 'Bulletin Number', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        { key: 'bulletin_title', label: 'Bulletin Title', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
        {
            key: 'issue_date',
            label: 'Issue Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        {
            key: 'completion_date',
            label: 'Completion Date',
            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
            useFormatted: true,
        },
        { key: 'completion_notes', label: 'Completion Notes', icon: <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" /> },
    ];

    const renderMetadataItem = (key: keyof StageMetadata, item: { label: string; icon: JSX.Element; useFormatted?: boolean }) => {
        if (key === 'validity_period' && metadata.validity_period) {
            const startFormatted = metadata.validity_period.start_date_formatted || 'Invalid Date';
            const endFormatted = metadata.validity_period.end_date_formatted || 'Invalid Date';

      return (
        <div key={key} className="col-span-1 sm:col-span-2">
                    <div className="group flex items-start rounded-md p-2 transition-colors duration-200 ease-in-out hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                        <div className="text-primary bg-primary/10 mt-0.5 mr-2 shrink-0 rounded-md p-1.5 sm:mr-2.5">
                            <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <span className="text-xs font-medium tracking-wide text-neutral-700 uppercase dark:text-neutral-300">
                                Validity Period
                            </span>
                            <div className="mt-1 leading-relaxed font-medium wrap-break-word text-neutral-800 dark:text-neutral-200">
                                <div className="line-clamp-2 transition-all duration-200 ease-in-out group-hover:line-clamp-none">
                                    {`${startFormatted} - ${endFormatted}`}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        if ((key === 'bidder_name' || key === 'bid_value') && documentType === 'Bid Document') {
            const formattedKey = `${key}_formatted` as keyof StageMetadata;
            const value = item.useFormatted && metadata[formattedKey] ? metadata[formattedKey] : metadata[key];

            if (!value || String(value).trim() === '') {
                return null;
            }

            return (
                <div key={`${key}-${metadata[key]}`} className="bg-primary/5 border-primary/20 flex items-start gap-3 border-b p-3 last:border-b-0">
                    <div className="text-muted-foreground mt-0.5">{item.icon}</div>
                    <div className="min-w-0 flex-1">
                        <div className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">{item.label}</div>
                        <div className="text-primary text-sm font-semibold wrap-break-word">{value as string}</div>
                    </div>
                </div>
            );
        }

        if (metadata[key]) {
            const formattedKey = `${key}_formatted` as keyof StageMetadata;
            const value = item.useFormatted && metadata[formattedKey] ? metadata[formattedKey] : metadata[key];

            if (!value || String(value).trim() === '') {
                return null;
            }

            return (
                <div key={key} className="flex items-start gap-3 border-b p-3 last:border-b-0">
                    <div className="text-muted-foreground mt-0.5">{item.icon}</div>
                    <div className="min-w-0 flex-1">
                        <div className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">{item.label}</div>
                        <div className="text-foreground text-sm wrap-break-word">{value as string}</div>
                    </div>
                </div>
            );
        }

        return null;
    };

    return (
        <Card className="bg-card border-border shadow-sm transition-all duration-200 hover:shadow">
            <CardHeader className="p-3 pb-1.5 sm:p-4 sm:pb-2">
                <CardTitle className="text-foreground flex items-center text-xs font-semibold sm:text-sm">
                    <FileCheck className="text-primary mr-1.5 h-3.5 w-3.5 sm:mr-2 sm:h-4 sm:w-4" />
                    Document Metadata
                    {documentType === 'Bid Document' && (
                        <Badge variant="outline" className="ml-2">
                            Bid Document
                        </Badge>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent className="p-3 pt-0 sm:p-4 md:p-5">
                <div className="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    {documentType === 'Bid Document' &&
                        metadata.opening_date &&
                        metadata.opening_date_formatted &&
                        metadata.opening_date_formatted.trim() !== '' && (
                            <div className="bg-primary/5 border-primary/20 flex items-start gap-3 border-b p-3 last:border-b-0">
                                <div className="text-muted-foreground mt-0.5">
                                    <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">Opening Date</div>
                                    <div className="text-primary text-sm font-semibold wrap-break-word">
                                        {metadata.opening_date_formatted || 'Invalid Date'}
                                    </div>
                                </div>
                            </div>
                        )}

                    {metadataMap.map((item) => renderMetadataItem(item.key, item))}

                    {metadata.validity_period &&
                        renderMetadataItem('validity_period', {
                            label: 'Validity Period',
                            icon: <Calendar className="h-3.5 w-3.5 sm:h-4 sm:w-4" />,
                        })}
                </div>
            </CardContent>
        </Card>
    );
};
