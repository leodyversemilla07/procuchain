import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { TextareaWithLabel } from '@/components/textarea-with-label';
import { CalendarIcon, Upload, AlertCircle, ClipboardCheck } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';
import SmartContractFileUploadArea from '@/components/smart-contract-file-upload-area';
import { useFileDrop } from '@/hooks/use-file-drop';
import DatePicker from '@/components/date-picker';
import { SmartContractValidationResult } from '@/types/smart-contracts';

const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface MonitoringUploadProps {
  procurement?: {
    id: string;
    title: string;
  };
}

// Helper for type-safe error access
function getFieldError<T extends object>(errors: T, field: keyof T): string | undefined {
  return errors && typeof errors === 'object' ? (errors as Record<string, string>)[field as string] : undefined;
}

export default function MonitoringUpload({ procurement = { id: '', title: '' } }: MonitoringUploadProps) {
  const currentDate = new Date();

  const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm({
    procurement_id: procurement?.id || '',
    procurement_title: procurement?.title || '',
    compliance_file: null as File | null,
    report_date: currentDate,
    report_notes: '',
  });

  // Smart contract validation states - used in onValidationComplete callback
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const [documentValidation, setDocumentValidation] = useState<SmartContractValidationResult | null>(null);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Compliance Report - ${procurement.id}: ${procurement.title}`, href: '#' },
  ];

  const validateFile = (file: File) => {
    if (!ALLOWED_FILE_TYPES.includes(file.type)) {
      toast.error("Invalid file type", { description: "Only PDF files are allowed." });
      return false;
    }
    if (file.size > MAX_FILE_SIZE) {
      toast.error("File too large", { description: "Maximum file size is 10MB." });
      return false;
    }
    return true;
  };

  const complianceDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('compliance_file', file),
  });

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (validateFile(file)) {
        setData('compliance_file', file);
      }
    }
  };

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Client-side validation
    if (!data.compliance_file) {
      toast.error("Missing file", { description: "Please upload the compliance report." });
      return;
    }
    if (!data.report_date) {
      toast.error("Missing report date", { description: "Please select the report date." });
      return;
    }

    transform((formData) => ({
      ...formData,
      report_date: formData.report_date ? format(formData.report_date, 'yyyy-MM-dd') : '',
    }));
    
    post('/bac-secretariat/upload-monitoring-document', {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast.success("Compliance report uploaded successfully!", {
          description: "Compliance report has been submitted."
        });
        reset();
        clearErrors();
      },
      onError: () => {
        toast.error("Submission failed.", {
          description: "Please check the form for errors."
        });
      },
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Compliance Report" />
      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <ClipboardCheck className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Compliance Report</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload the compliance report for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>
        <form onSubmit={onSubmit} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <ClipboardCheck className="h-5 w-5 text-primary" />
                  Required Document
                </CardTitle>
                <CardDescription>
                  Please upload the compliance report in PDF format
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-8">
                <SmartContractFileUploadArea
                  label="Compliance Report Document"
                  file={data.compliance_file}
                  error={getFieldError(errors, 'compliance_file')}
                  isDragging={complianceDrop.isDragging}
                  onFileChange={handleFileChange}
                  onDragEnter={complianceDrop.handleDragEnter}
                  onDragLeave={complianceDrop.handleDragLeave}
                  onDragOver={complianceDrop.handleDragOver}
                  onDrop={complianceDrop.handleDrop}
                  onRemove={() => setData('compliance_file', null)}
                  inputId="compliance-file-input"
                  required={true}
                  documentType="Certificate of Completion"
                  stage="Monitoring"
                  procurementId={procurement.id}
                  enableSmartValidation={true}
                  showValidationDetails={true}
                  onValidationComplete={(result) => {
                    setDocumentValidation(result);
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
                {getFieldError(errors, 'compliance_file') && (
                  <InputError message={getFieldError(errors, 'compliance_file')} />
                )}
              </CardContent>
            </Card>
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-5 w-5 text-primary" />
                  Report Details
                </CardTitle>
                <CardDescription>
                  Provide information about the compliance report
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <DatePicker
                  label="Report Date"
                  value={data.report_date instanceof Date ? data.report_date : new Date(data.report_date)}
                  onChange={date => {
                    if (date) setData('report_date', date);
                  }}
                  error={getFieldError(errors, 'report_date')}
                  required
                />
                {getFieldError(errors, 'report_date') && (
                  <InputError message={getFieldError(errors, 'report_date')} />
                )}
                <div className="space-y-2">
                  <TextareaWithLabel
                    label="Report Notes"
                    value={data.report_notes}
                    onChange={(e) => setData('report_notes', e.target.value)}
                    placeholder="Enter any additional notes or comments about the compliance report"
                    rows={5}
                    required={false}
                    error={getFieldError(errors, 'report_notes')}
                    errorClassName="mt-1.5 sm:mt-2"
                  />
                  {getFieldError(errors, 'report_notes') && <InputError message={getFieldError(errors, 'report_notes')} />}
                </div>
              </CardContent>
              <CardFooter className="pt-4 border-t flex flex-col gap-3">
                <Button
                  type="submit"
                  disabled={processing}
                  className="w-full flex items-center gap-2 h-11"
                >
                  {processing ? (
                    <div className="flex items-center gap-2">
                      <div className="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                      Processing...
                    </div>
                  ) : (
                    <>
                      <Upload className="h-4 w-4" />
                      Submit Report
                    </>
                  )}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => window.history.back()}
                  disabled={processing}
                  className="w-full h-10"
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
                  <ul className="list-disc list-inside mt-2 text-sm text-destructive/90 space-y-1">
                    {Object.entries(errors).map(([field, message]) => (
                      <li key={field}>{message as string}</li>
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
