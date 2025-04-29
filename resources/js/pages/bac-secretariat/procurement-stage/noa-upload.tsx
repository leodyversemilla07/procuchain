import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, Award, Edit3 } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import {
  Popover, PopoverContent, PopoverTrigger,
} from '@/components/ui/popover';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';

interface NoticeOfAwardUploadProps {
  procurement: {
    id: string;
    title: string;
  };
  errors?: Record<string, string>;
}

export default function NoticeOfAwardUpload({ procurement, errors: initialErrors = {} }: NoticeOfAwardUploadProps) {
  const [isDraggingFile, setIsDraggingFile] = useState(false);

  // Mirror bac-resolution-upload state management
  const currentDate = new Date();
  const formattedDate = format(currentDate, 'yyyy-MM-dd');

  const { data, setData, post, processing, errors } = useForm({
    procurement_id: procurement.id,
    procurement_title: procurement.title,
    noa_file: null as File | null,
    issuance_date: formattedDate, // String for submission (matches backend expectation)
    issuance_date_object: currentDate, // Date object for Calendar UI
    signatory_details: '',
  });

  const displayErrors = { ...initialErrors, ...errors };

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Notice of Award - ${procurement.id}`, href: '#' },
  ];

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // Use post(url, options) signature, like bac-resolution-upload
    // Data is implicitly taken from useForm state
    // No transform needed as issuance_date is already formatted string in state
    post('/bac-secretariat/upload-noa-document', {
      forceFormData: true,
      preserveScroll: true, // Keep scroll position on error
      // preserveState: true, // Optional: Keep component state on validation errors
      onSuccess: () => {
        toast.success("Notice of Award uploaded successfully!", {
          description: "Notice of Award has been submitted."
        });
      },
      onError: (errorResponse) => {
        // Errors are automatically populated by useForm
        console.error("Submission Error:", errorResponse);
        toast.error("Submission failed.", {
          description: "Please check the form for errors."
        });
      },
    });
  };

  const handleDragEvents = (e: React.DragEvent, isDragging = true) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingFile(isDragging);
  };

  const handleFileDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingFile(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        setData('noa_file', file);
      }
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (file.type === 'application/pdf') {
        setData('noa_file', file);
      }
    }
  };


  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Notice of Award" />

      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        {/* ... existing header ... */}
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
              {/* ... existing CardHeader ... */}
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
                {/* ... File Upload Section ... */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <Award className="h-4 w-4 mr-2" />
                    Notice of Award Document
                  </label>
                  <div
                    className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingFile
                      ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                      : data.noa_file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : displayErrors.noa_file
                          ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                          : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                    onDragEnter={(e) => handleDragEvents(e)}
                    onDragLeave={(e) => handleDragEvents(e, false)}
                    onDragOver={(e) => handleDragEvents(e)}
                    onDrop={handleFileDrop}
                    onClick={() => document.getElementById('file-input')?.click()}
                  >
                    {!data.noa_file ? (
                      <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your Notice of Award here
                        </p>
                        <p className="text-sm text-muted-foreground/70 mb-5">
                          Only PDF files are supported
                        </p>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="group-hover:bg-primary/5 transition-colors"
                          onClick={(e) => {
                            e.stopPropagation();
                            document.getElementById('file-input')?.click();
                          }}
                        >
                          Browse Files
                        </Button>
                        <input
                          id="file-input"
                          type="file"
                          accept="application/pdf"
                          className="hidden"
                          onChange={handleFileChange}
                        />
                      </div>
                    ) : (
                      <div className="flex items-center justify-between">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium">{data.noa_file.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {(data.noa_file.size / 1024).toFixed(2)} KB • PDF
                            </p>
                          </div>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors"
                          onClick={(e) => {
                            e.stopPropagation();
                            setData('noa_file', null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {displayErrors.noa_file && <InputError message={displayErrors.noa_file} />}
                </div>

                {/* Signatory Details Section */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <Edit3 className="h-4 w-4 mr-2" />
                    Signatory Details
                  </label>
                  <Textarea
                    placeholder="Enter signatory details (e.g., Name, Position)"
                    value={data.signatory_details}
                    onChange={(e) => setData('signatory_details', e.target.value)}
                    className={`min-h-[100px] ${displayErrors.signatory_details ? 'border-destructive' : ''}`}
                  />
                  {displayErrors.signatory_details && <InputError message={displayErrors.signatory_details} />}
                </div>
              </CardContent>
            </Card>

            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              {/* ... existing CardHeader ... */}
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
                {/* Date Picker Section */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <CalendarIcon className="h-4 w-4 mr-2" />
                    Issuance Date
                  </label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        className={`w-full justify-start text-left font-normal ${displayErrors.issuance_date ? 'border-destructive' : ''}`}
                      >
                        <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                        {/* Display date from the Date object state */}
                        {data.issuance_date_object ? format(data.issuance_date_object, 'PPP') : <span>Pick a date</span>}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <Calendar
                        mode="single"
                        selected={data.issuance_date_object} // Bind to the Date object state
                        // Update both Date object and formatted string state on select
                        onSelect={(date) => {
                          if (date) {
                            setData('issuance_date_object', date);
                            setData('issuance_date', format(date, 'yyyy-MM-dd'));
                          }
                        }}
                        initialFocus
                        className="rounded-md border shadow-md"
                      />
                    </PopoverContent>
                  </Popover>
                  {/* Error message still refers to the submission field name */}
                  {displayErrors.issuance_date && <InputError message={displayErrors.issuance_date} />}
                </div>
              </CardContent>

              <CardFooter className="pt-4 border-t flex flex-col gap-3">
                {/* ... existing buttons ... */}
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

        {/* Error Summary Box */}
        {Object.keys(displayErrors).length > 0 && (
          <Card className="border-destructive/50 bg-destructive/5 dark:bg-destructive/10 shadow-md">
            <CardContent className="p-4">
              <div className="flex items-start">
                <AlertCircle className="h-5 w-5 text-destructive mt-0.5 mr-3" />
                <div>
                  <h4 className="text-sm font-medium text-destructive">
                    Please fix the following errors:
                  </h4>
                  <ul className="list-disc list-inside mt-2 text-sm text-destructive/90 space-y-1">
                    {Object.entries(displayErrors).map(([field, message]) => (
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
