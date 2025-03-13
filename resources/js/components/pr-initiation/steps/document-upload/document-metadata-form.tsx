import { Building2, User2, ClipboardList, FileUp, CalendarIcon } from 'lucide-react';
import { Calendar } from '@/components/ui/calendar';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PRDocument, MUNICIPAL_OFFICES } from '@/types/blockchain';

interface DocumentMetadataFormProps {
    metadata: PRDocument;
    updateMetadata: (key: keyof PRDocument, value: any) => void;
    errors: Record<string, string>;
    hasError: (field: string) => boolean;
    submissionDate: Date | undefined;
    onDateChange: (date: Date | undefined) => void;
}

/**
 * Component for collecting PR document metadata
 */
export function DocumentMetadataForm({
    metadata,
    updateMetadata,
    errors,
    hasError,
    submissionDate,
    onDateChange
}: DocumentMetadataFormProps) {
    return (
        <div className="space-y-6">
            <h3 className="text-base font-medium border-b pb-2 flex items-center gap-2">
                <ClipboardList className="h-4 w-4 text-primary" />
                Document Metadata
            </h3>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* First Column */}
                <div className="space-y-4 border rounded-lg p-4">
                    {/* Document Type Field */}
                    <div className="space-y-2">
                        <div className="flex justify-between items-center">
                            <Label htmlFor="document_type" className="flex items-center">
                                <span>Document Type</span>
                                <Badge variant="destructive" className="ml-2 text-xs">Required</Badge>
                            </Label>
                            {hasError('pr_metadata.document_type') && (
                                <p className="text-xs text-destructive">
                                    {errors['pr_metadata.document_type']}
                                </p>
                            )}
                        </div>
                        <div className="relative flex items-center">
                            <FileUp className="absolute left-3 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="document_type"
                                placeholder="Enter document type"
                                value={metadata.document_type || 'Purchase Request'}
                                onChange={(e) => updateMetadata('document_type', e.target.value)}
                                className="pl-10"
                            />
                        </div>
                    </div>

                    {/* Municipal Office Field */}
                    <div className="space-y-2">
                        <div className="flex justify-between items-center">
                            <Label htmlFor="municipal_offices" className="flex items-center">
                                <span>Municipal Office</span>
                                <Badge variant="destructive" className="ml-2 text-xs">Required</Badge>
                            </Label>
                            {hasError('pr_metadata.municipal_offices') && (
                                <p className="text-xs text-destructive">
                                    {errors['pr_metadata.municipal_offices']}
                                </p>
                            )}
                        </div>
                        <Select
                            value={metadata.municipal_offices || ''}
                            onValueChange={(value) => updateMetadata('municipal_offices', value)}
                        >
                            <SelectTrigger className="w-full">
                                <div className="flex items-center gap-2">
                                    <Building2 className="h-4 w-4 text-muted-foreground" />
                                    <SelectValue placeholder="Select municipal office" />
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                {MUNICIPAL_OFFICES.map((office) => (
                                    <SelectItem key={office.value} value={office.value}>{office.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Second Column */}
                <div className="space-y-4 border rounded-lg p-4">
                    {/* Submission Date Field */}
                    <div className="space-y-2">
                        <div className="flex justify-between items-center">
                            <Label htmlFor="submission_date" className="flex items-center">
                                <span>Submission Date</span>
                                <Badge variant="destructive" className="ml-2 text-xs">Required</Badge>
                            </Label>
                            {hasError('pr_metadata.submission_date') && (
                                <p className="text-xs text-destructive">
                                    {errors['pr_metadata.submission_date']}
                                </p>
                            )}
                        </div>
                        <div className="relative">
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className={cn(
                                            "w-full justify-start text-left",
                                            !submissionDate && "text-muted-foreground"
                                        )}
                                    >
                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                        {submissionDate ? format(submissionDate, "PPP") : <span>Pick a date</span>}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                    <Calendar
                                        mode="single"
                                        selected={submissionDate}
                                        onSelect={onDateChange}
                                        initialFocus
                                    />
                                </PopoverContent>
                            </Popover>
                            <p className="text-xs text-muted-foreground mt-1">
                                Select the submission date for this document
                            </p>
                        </div>
                    </div>

                    {/* Signatory Details Field */}
                    <div className="space-y-2">
                        <div className="flex justify-between items-center">
                            <Label htmlFor="signatory_details" className="flex items-center">
                                <span>Signatory Details</span>
                                <Badge variant="destructive" className="ml-2 text-xs">Required</Badge>
                            </Label>
                            {hasError('pr_metadata.signatory_details') && (
                                <p className="text-xs text-destructive">
                                    {errors['pr_metadata.signatory_details']}
                                </p>
                            )}
                        </div>
                        <div className="relative flex items-center">
                            <User2 className="absolute left-3 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="signatory_details"
                                placeholder="Name and position of signatory"
                                value={metadata.signatory_details || ''}
                                onChange={(e) => updateMetadata('signatory_details', e.target.value)}
                                className="pl-10"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}