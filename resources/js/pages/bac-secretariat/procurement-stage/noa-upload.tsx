import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { CalendarIcon, Upload, AlertCircle, Award } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import { BreadcrumbItem } from '@/types';
import FileUploadArea from '@/components/file-upload-area';
import { useFileDrop } from '@/hooks/use-file-drop';
import DatePicker from '@/components/date-picker';
import PeopleInput from '@/components/people-input';

const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface NoticeOfAwardUploadProps {
  procurement?: {
    id: string;
    title: string;
  };
}

// Helper for type-safe error access
function getFieldError<T extends object>(errors: T, field: keyof T): string | undefined {
  return errors && typeof errors === 'object' ? (errors as Record<string, string>)[field as string] : undefined;
}

export default function NoticeOfAwardUpload({ procurement = { id: '', title: '' } }: NoticeOfAwardUploadProps) {
  const currentDate = new Date();
  const formattedDate = format(currentDate, 'yyyy-MM-dd');

  const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
    procurement_id: procurement?.id || '',
    procurement_title: procurement?.title || '',
    noa_file: null as File | null,
    issuance_date: formattedDate,
    issuance_date_object: currentDate,
    signatories: [] as Array<{ name: string; affiliation: string }>,
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Notice of Award - ${procurement.id}: ${procurement.title}`, href: '#' },
  ];

  // File validation
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

  // File drop hook
  const noaDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('noa_file', file),
  });

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Client-side validation
    if (!data.noa_file) {
      toast.error("Missing file", { description: "Please upload the Notice of Award document." });
      return;
    }
    if (!data.issuance_date) {
      toast.error("Missing issuance date", { description: "Please select the issuance date." });
      return;
    }
    if (!data.signatories || (Array.isArray(data.signatories) && data.signatories.length === 0)) {
      toast.error("Missing signatories", { description: "Please enter at least one signatory." });
      return;
    }
    post('/bac-secretariat/upload-noa-document', {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast.success("Notice of Award uploaded successfully!", {
          description: "Notice of Award has been submitted."
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
      <Head title="Upload Notice of Award" />
      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <Award className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Notice of Award</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload the Notice of Award for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>
        <form onSubmit={onSubmit} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <Award className="h-5 w-5 text-primary" />
                  Required Document
                </CardTitle>
                <CardDescription>
                  Please upload the Notice of Award document in PDF format
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-8">
                <FileUploadArea
                  label="Notice of Award Document"
                  file={data.noa_file}
                  error={getFieldError(errors, 'noa_file')}
                  isDragging={noaDrop.isDragging}
                  onFileChange={e => {
                    if (e.target.files && e.target.files.length > 0) {
                      const file = e.target.files[0];
                      if (validateFile(file)) {
                        setData('noa_file', file);
                      }
                    }
                  }}
                  onDragEnter={noaDrop.handleDragEnter}
                  onDragLeave={noaDrop.handleDragLeave}
                  onDragOver={noaDrop.handleDragOver}
                  onDrop={noaDrop.handleDrop}
                  onRemove={() => setData('noa_file', null)}
                  inputId="noa-file-input"
                  required={true}
                />
                {getFieldError(errors, 'noa_file') && (
                  <div className="mt-2"><span className="text-destructive text-sm">{getFieldError(errors, 'noa_file')}</span></div>
                )}
              </CardContent>
            </Card>
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-5 w-5 text-primary" />
                  Award Details
                </CardTitle>
                <CardDescription>
                  Provide information about the Notice of Award
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <DatePicker
                  label="Issuance Date"
                  value={data.issuance_date_object}
                  onChange={date => {
                    if (date) {
                      setData('issuance_date_object', date);
                      setData('issuance_date', format(date, 'yyyy-MM-dd'));
                    }
                  }}
                  error={getFieldError(errors, 'issuance_date')}
                  required
                />
                {getFieldError(errors, 'issuance_date') && (
                  <div className="mt-2"><span className="text-destructive text-sm">{getFieldError(errors, 'issuance_date')}</span></div>
                )}
                <PeopleInput
                  label="Signatories"
                  value={data.signatories}
                  onChange={updated => setData('signatories', updated)}
                  error={getFieldError(errors, 'signatories')}
                  required
                  affiliationType="position"
                  namePlaceholder="Enter signatory name"
                />
                {getFieldError(errors, 'signatories') && (
                  <div className="mt-2"><span className="text-destructive text-sm">{getFieldError(errors, 'signatories')}</span></div>
                )}
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
                      Submit Award Notice
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
