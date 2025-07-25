import React, { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format, addDays } from 'date-fns';
import { toast } from "sonner";
import { DateRange } from 'react-day-picker';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { CalendarIcon, Upload, AlertCircle, ClipboardList, Shield } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';
import SmartContractFileUploadArea from '@/components/smart-contract-file-upload-area';
import SmartContractDashboard from '@/components/smart-contract-dashboard';
import { useFileDrop } from '@/hooks/use-file-drop';
import DatePicker from '@/components/date-picker';
import DateRangePicker from '@/components/date-range-picker';
import { SmartContractValidationResult } from '@/types/smart-contracts';

interface BiddingDocumentsUploadProps {
  procurement: {
    id: string;
    title: string;
  };
}

export default function BiddingDocumentsUpload({ procurement }: BiddingDocumentsUploadProps) {
  // Smart contract validation state - used in onValidationComplete callback
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const [biddingDocumentValidation, setBiddingDocumentValidation] = useState<SmartContractValidationResult | null>(null);

  const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
    procurement_id: procurement?.id || '',
    procurement_title: procurement?.title || '',
    bidding_document_file: null as File | null,
    issuance_date: new Date(),
    validity_period_start: format(new Date(), 'yyyy-MM-dd'),
    validity_period_end: format(addDays(new Date(), 7), 'yyyy-MM-dd'),
    validity_period: {
      from: new Date(),
      to: addDays(new Date(), 7),
    } as DateRange | undefined,
  });

  // File drop logic
  const validateFile = (file: File) => {
    if (file.type !== 'application/pdf') {
      toast.error('Invalid file type', {
        description: 'Only PDF files are allowed.'
      });
      return false;
    }
    if (file.size > 10 * 1024 * 1024) {
      toast.error('File too large', {
        description: 'Maximum file size is 10MB.'
      });
      return false;
    }
    return true;
  };
  const fileDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('bidding_document_file', file),
  });

  useEffect(() => {
    console.log('Procurement data received:', procurement);
  }, [procurement]);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Bidding Documents - ${procurement?.id || 'Unknown ID'}${procurement?.title ? ': ' + procurement.title : ''}`, href: '#' },
  ];

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!data.bidding_document_file) {
      toast.error('Missing file', {
        description: 'Please upload the bidding documents file.'
      });
      return;
    }
    if (!data.issuance_date) {
      toast.error('Missing issuance date', {
        description: 'Please select the issuance date.'
      });
      return;
    }
    if (!data.validity_period || !data.validity_period.from || !data.validity_period.to) {
      toast.error('Missing validity period', {
        description: 'Please select the validity period.'
      });
      return;
    }

    transform((formData) => ({
      ...formData,
      issuance_date: format(formData.issuance_date, 'yyyy-MM-dd'),
      validity_period_start: formData.validity_period && formData.validity_period.from
        ? format(formData.validity_period.from, 'yyyy-MM-dd')
        : '',
      validity_period_end: formData.validity_period && formData.validity_period.to
        ? format(formData.validity_period.to, 'yyyy-MM-dd')
        : '',
    }));

    post('/bac-secretariat/upload-bidding-documents', {
      preserveScroll: true,
      preserveState: true,
      forceFormData: true,
      onSuccess: () => {
        reset();
        clearErrors();
        toast.success("Bidding documents uploaded successfully!", {
          description: "Bidding documents have been submitted."
        });
      },
      onError: (errors) => {
        toast.error("Failed to upload bidding documents", {
          description: Object.values(errors)[0] as string
        });
      },
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (validateFile(file)) {
        setData('bidding_document_file', file);
      }
    }
  };

  // Handle date selection for validity period
  const handleValidityPeriodChange = (range: DateRange | undefined) => {
    setData('validity_period', range);
    if (range?.from) {
      setData('validity_period_start', format(range.from, 'yyyy-MM-dd'));
    }
    if (range?.to) {
      setData('validity_period_end', format(range.to, 'yyyy-MM-dd'));
    }
  };

  // Handle issuance date selection
  const handleIssuanceDateChange = (date: Date | undefined) => {
    if (date) {
      setData('issuance_date', date);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Bidding Documents" />

      <div className="flex h-full flex-1 flex-col gap-4 sm:gap-6 rounded-xl p-4 sm:p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <ClipboardList className="h-5 w-5 sm:h-6 sm:w-6" />
            <h1 className="text-xl sm:text-2xl font-bold">Bidding Documents</h1>
          </div>
          <p className="text-sm sm:text-base text-muted-foreground max-w-3xl">
            Upload the bidding documents for procurement
            <span className="font-medium text-foreground"> #{procurement?.id || 'Unknown'}</span>
            {procurement?.title && (
              <>
                :
                <span className="font-medium text-foreground italic"> {procurement.title}</span>
              </>
            )}
          </p>
        </div>

        {/* Smart Contract Dashboard */}
        <Card className="border-primary/20 bg-primary/5">
          <CardHeader className="pb-4">
            <CardTitle className="flex items-center gap-2 text-lg">
              <Shield className="h-5 w-5 text-primary" />
              Smart Contract Validation Status
            </CardTitle>
            <CardDescription>
              Real-time blockchain validation for document integrity and compliance
            </CardDescription>
          </CardHeader>
          <CardContent>
            <SmartContractDashboard procurementId={procurement.id} />
          </CardContent>
        </Card>

        <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-2 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <ClipboardList className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Required Document
                </CardTitle>
                <CardDescription className="text-sm">
                  Please upload the bidding documents in PDF format
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-6 sm:space-y-8">
                <SmartContractFileUploadArea
                  label="Bidding Documents"
                  file={data.bidding_document_file}
                  error={errors.bidding_document_file}
                  isDragging={fileDrop.isDragging}
                  onFileChange={handleFileChange}
                  onDragEnter={fileDrop.handleDragEnter}
                  onDragLeave={fileDrop.handleDragLeave}
                  onDragOver={fileDrop.handleDragOver}
                  onDrop={fileDrop.handleDrop}
                  onRemove={() => setData('bidding_document_file', null)}
                  inputId="file-input"
                  required={true}
                  documentType="Bidding Documents"
                  stage="Bidding Documents"
                  procurementId={procurement.id}
                  enableSmartValidation={true}
                  showValidationDetails={true}
                  onValidationComplete={(result) => {
                    setBiddingDocumentValidation(result);
                    if (!result.compliant) {
                      toast.error('Document validation failed', {
                        description: 'Please review the validation details and fix any issues.'
                      });
                    } else {
                      toast.success('Document validation passed', {
                        description: 'All validation checks passed successfully.'
                      });
                    }
                  }}
                />
              </CardContent>
            </Card>

            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-2 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Document Details
                </CardTitle>
                <CardDescription className="text-sm">
                  Provide information about the bidding documents
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 sm:space-y-6">
                <div className="space-y-2">
                  <DatePicker
                    label="Issuance Date"
                    value={data.issuance_date}
                    onChange={handleIssuanceDateChange}
                    error={errors.issuance_date}
                    required={true}
                  />
                </div>

                <div className="space-y-2">
                  <DateRangePicker
                    label="Validity Period"
                    value={data.validity_period}
                    onChange={handleValidityPeriodChange}
                    error={errors.validity_period_start || errors.validity_period_end}
                    required={true}
                  />
                  {errors.validity_period_start && (
                    <InputError message={errors.validity_period_start} />
                  )}
                  {errors.validity_period_end && (
                    <InputError message={errors.validity_period_end} />
                  )}
                </div>
              </CardContent>

              <CardFooter className="pt-4 border-t flex flex-col gap-3">
                <Button
                  type="submit"
                  disabled={processing}
                  className="w-full flex items-center gap-2 h-10 sm:h-11 text-sm sm:text-base"
                >
                  {processing ? (
                    <div className="flex items-center gap-2">
                      <div className="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                      Processing...
                    </div>
                  ) : (
                    <>
                      <Upload className="h-4 w-4" />
                      Submit Documents
                    </>
                  )}
                </Button>

                <Button
                  type="button"
                  variant="outline"
                  onClick={() => window.history.back()}
                  disabled={processing}
                  className="w-full h-10 text-sm sm:text-base"
                >
                  Cancel
                </Button>
              </CardFooter>
            </Card>
          </div>
        </form>

        {Object.keys(errors).length > 0 && (
          <Card className="border-destructive/50 bg-destructive/5 dark:bg-destructive/10 shadow-md">
            <CardContent className="p-4">
              <div className="flex items-start">
                <AlertCircle className="h-5 w-5 text-destructive mt-0.5 mr-3" />
                <div>
                  <h4 className="text-sm font-medium text-destructive">
                    Please fix the following errors:
                  </h4>
                  <ul className="list-disc list-inside mt-2 text-xs sm:text-sm text-destructive/90 space-y-1">
                    {Object.entries(errors).map(([field, message]) => (
                      <li key={field}>{message}</li>
                    ))}
                  </ul>
                </div>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
