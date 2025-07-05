import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { CalendarIcon, FileText, Upload, AlertCircle, CheckCircle, XCircle } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import { TextareaWithLabel } from '@/components/ui/textarea-with-label';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';
import FileUploadArea from '@/components/file-upload-area';
import { useFileDrop } from '@/hooks/use-file-drop';
import DatePicker from '@/components/date-picker';
import { Label } from '@/components/ui/label';

const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface PostQualificationUploadProps {
  procurement?: {
    id: string;
    title: string;
  };
}

// Helper for type-safe error access
function getFieldError<T extends object>(errors: T, field: keyof T): string | undefined {
  return errors && typeof errors === 'object' ? (errors as Record<string, string>)[field as string] : undefined;
}

export default function PostQualificationUpload({ procurement = { id: '', title: '' } }: PostQualificationUploadProps) {
  const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
    procurement_id: procurement?.id || '',
    procurement_title: procurement?.title || '',
    post_qualification_report: null as File | null,
    twg_certification: null as File | null,
    notice_of_post_qualification: null as File | null,
    submission_date: format(new Date(), 'yyyy-MM-dd'),
    outcome: null as boolean | null,
    remarks: '',
  });

  // File validation
  const validateFile = (file: File) => {
    if (!ALLOWED_FILE_TYPES.includes(file.type)) {
      toast.error('Invalid file type', { description: 'Only PDF files are allowed.' });
      return false;
    }
    if (file.size > MAX_FILE_SIZE) {
      toast.error('File too large', { description: 'Maximum file size is 10MB.' });
      return false;
    }
    return true;
  };

  // Use custom hook for each file
  const pqReportDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('post_qualification_report', file),
  });
  const twgCertDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('twg_certification', file),
  });
  const noticeDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('notice_of_post_qualification', file),
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Post-Qualification Report - ${procurement.id}: ${procurement.title}`, href: '#' },
  ];

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Client-side validation
    if (!data.post_qualification_report) {
      toast.error('Missing Post-Qualification Report', { description: 'Please upload the post-qualification report PDF.' });
      return;
    }
    if (!data.notice_of_post_qualification) {
      toast.error('Missing Notice of Post-Qualification', { description: 'Please upload the notice of post-qualification PDF.' });
      return;
    }
    if (data.outcome === null) {
      toast.error('Please select an outcome (Verified or Failed)');
      return;
    }
    post('/bac-secretariat/upload-post-qualification-documents', {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast.success("Post-qualification report uploaded successfully!", {
          description: "Post-qualification report has been submitted."
        });
        reset();
        clearErrors();
      },
      onError: () => {
        toast.error('Failed to upload documents', {
          description: 'Please check the form for errors and try again.'
        });
      }
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Post-Qualification Report" />
      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <FileText className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Post-Qualification Report</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload the post-qualification report for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>
        <form onSubmit={onSubmit} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <FileText className="h-5 w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription>
                  Please upload the required post-qualification documents in PDF format.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <FileUploadArea
                  label="Post-Qualification Report (Required)"
                  file={data.post_qualification_report}
                  error={getFieldError(errors, 'post_qualification_report')}
                  isDragging={pqReportDrop.isDragging}
                  onFileChange={e => {
                    if (e.target.files && e.target.files.length > 0) {
                      const file = e.target.files[0];
                      if (validateFile(file)) setData('post_qualification_report', file);
                    }
                  }}
                  onDragEnter={pqReportDrop.handleDragEnter}
                  onDragLeave={pqReportDrop.handleDragLeave}
                  onDragOver={pqReportDrop.handleDragOver}
                  onDrop={pqReportDrop.handleDrop}
                  onRemove={() => setData('post_qualification_report', null)}
                  inputId="pq-report-input"
                  required={true}
                />
                {getFieldError(errors, 'post_qualification_report') && (
                  <InputError message={getFieldError(errors, 'post_qualification_report')} />
                )}
                <FileUploadArea
                  label="TWG Certification (If applicable)"
                  file={data.twg_certification}
                  error={getFieldError(errors, 'twg_certification')}
                  isDragging={twgCertDrop.isDragging}
                  onFileChange={e => {
                    if (e.target.files && e.target.files.length > 0) {
                      const file = e.target.files[0];
                      if (validateFile(file)) setData('twg_certification', file);
                    }
                  }}
                  onDragEnter={twgCertDrop.handleDragEnter}
                  onDragLeave={twgCertDrop.handleDragLeave}
                  onDragOver={twgCertDrop.handleDragOver}
                  onDrop={twgCertDrop.handleDrop}
                  onRemove={() => setData('twg_certification', null)}
                  inputId="twg-cert-input"
                />
                {getFieldError(errors, 'twg_certification') && (
                  <InputError message={getFieldError(errors, 'twg_certification')} />
                )}
                <FileUploadArea
                  label="Notice of Post-Qualification (Required)"
                  file={data.notice_of_post_qualification}
                  error={getFieldError(errors, 'notice_of_post_qualification')}
                  isDragging={noticeDrop.isDragging}
                  onFileChange={e => {
                    if (e.target.files && e.target.files.length > 0) {
                      const file = e.target.files[0];
                      if (validateFile(file)) setData('notice_of_post_qualification', file);
                    }
                  }}
                  onDragEnter={noticeDrop.handleDragEnter}
                  onDragLeave={noticeDrop.handleDragLeave}
                  onDragOver={noticeDrop.handleDragOver}
                  onDrop={noticeDrop.handleDrop}
                  onRemove={() => setData('notice_of_post_qualification', null)}
                  inputId="notice-input"
                  required={true}
                />
                {getFieldError(errors, 'notice_of_post_qualification') && (
                  <InputError message={getFieldError(errors, 'notice_of_post_qualification')} />
                )}
              </CardContent>
            </Card>
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-5 w-5 text-primary" />
                  Evaluation Details
                </CardTitle>
                <CardDescription>
                  Provide information about the post-qualification evaluation
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <DatePicker
                  label="Submission Date"
                  value={data.submission_date ? new Date(data.submission_date) : undefined}
                  onChange={date => date && setData('submission_date', format(date, 'yyyy-MM-dd'))}
                  error={getFieldError(errors, 'submission_date')}
                  required
                />
                {getFieldError(errors, 'submission_date') && (
                  <InputError message={getFieldError(errors, 'submission_date')} />
                )}
                <div className="space-y-2">
                  <Label className="flex items-center text-base font-medium">
                    <FileText className="h-4 w-4 mr-2" />
                    Outcome
                  </Label>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <input
                        type="radio"
                        id="passed"
                        value="true"
                        checked={data.outcome === true}
                        onChange={() => setData('outcome', true)}
                        className="peer hidden"
                      />
                      <Label
                        htmlFor="passed"
                        className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-checked:border-primary cursor-pointer"
                      >
                        <CheckCircle className="mb-3 h-6 w-6 text-green-500" />
                        <span className="text-center">Verified</span>
                      </Label>
                    </div>
                    <div>
                      <input
                        type="radio"
                        id="failed"
                        value="false"
                        checked={data.outcome === false}
                        onChange={() => setData('outcome', false)}
                        className="peer hidden"
                      />
                      <Label
                        htmlFor="failed"
                        className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-checked:border-primary cursor-pointer"
                      >
                        <XCircle className="mb-3 h-6 w-6 text-red-500" />
                        <span className="text-center">Failed</span>
                      </Label>
                    </div>
                  </div>
                  {getFieldError(errors, 'outcome') && <InputError message={getFieldError(errors, 'outcome')} />}
                </div>

                <div className="space-y-2">
                  <TextareaWithLabel
                    label="Remarks"
                    placeholder="Enter any additional remarks about the evaluation"
                    rows={5}
                    className="transition-all duration-200border-input focus:border-primary"
                    value={data.remarks}
                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('remarks', e.target.value)}
                    error={getFieldError(errors, 'remarks')}
                  />
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
