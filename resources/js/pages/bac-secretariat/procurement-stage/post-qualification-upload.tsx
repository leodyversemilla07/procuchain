import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, CheckCircle, XCircle, Building } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import {
  Popover, PopoverContent, PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';

interface PostQualificationUploadProps {
  procurement: {
    id: string;
    title: string;
  };
  errors?: Record<string, string>;
}

interface FileInputProps {
  id: string;
  label: string;
  icon: React.ElementType;
  file: File | null;
  error?: string;
  onFileChange: (file: File | null) => void;
  isDragging: boolean;
  onDragEnter: (e: React.DragEvent) => void;
  onDragLeave: (e: React.DragEvent) => void;
  onDragOver: (e: React.DragEvent) => void;
  onDrop: (e: React.DragEvent) => void;
}

const FileInputDisplay = ({ file, Icon, onFileChange }: { file: File, Icon: React.ElementType, onFileChange: (file: File | null) => void }) => (
  <div className="flex items-center justify-between">
    <div className="flex items-center overflow-hidden mr-2">
      <div className="rounded-full bg-primary/10 p-2.5 mr-3 flex-shrink-0">
        <Icon className="h-5 w-5 text-primary" />
      </div>
      <div className="overflow-hidden">
        <p className="font-medium text-sm truncate" title={file.name}>{file.name}</p>
        <p className="text-xs text-muted-foreground">
          {(file.size / 1024).toFixed(2)} KB • PDF
        </p>
      </div>
    </div>
    <Button
      type="button"
      variant="ghost"
      size="icon"
      className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors flex-shrink-0 h-7 w-7"
      onClick={(e) => {
        e.stopPropagation();
        onFileChange(null);
      }}
    >
      <X className="h-4 w-4" />
    </Button>
  </div>
);

const FileInput: React.FC<FileInputProps> = ({
  id, label, icon: Icon, file, error, onFileChange, isDragging,
  onDragEnter, onDragLeave, onDragOver, onDrop
}) => {
  const validateAndProcessFile = (inputFile: File) => {
    if (inputFile.type === 'application/pdf') {
      onFileChange(inputFile);
    } else {
      toast.error("Invalid file type. Please upload a PDF.");
    }
  };

  return (
    <div className="space-y-2">
      <label htmlFor={id} className="flex items-center text-base font-medium">
        <Icon className="h-4 w-4 mr-2" />
        {label}
      </label>
      <div
        className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[150px] flex flex-col justify-center ${isDragging
          ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
          : file
            ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
            : error
              ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
              : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
          } cursor-pointer group`}
        onDragEnter={onDragEnter}
        onDragLeave={onDragLeave}
        onDragOver={onDragOver}
        onDrop={(e) => {
          e.preventDefault();
          e.stopPropagation();
          const droppedFile = e.dataTransfer.files?.[0];
          if (droppedFile) {
            validateAndProcessFile(droppedFile);
          }
          onDrop(e);
        }}
        onClick={() => document.getElementById(id)?.click()}
      >
        {!file ? (
          <div className="flex flex-col items-center justify-center text-center">
            <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
              <FileUp className="h-5 w-5 text-muted-foreground group-hover:text-primary transition-colors" />
            </div>
            <p className="font-medium text-sm text-muted-foreground mb-1 group-hover:text-foreground transition-colors">
              Drag & drop PDF here
            </p>
            <p className="text-xs text-muted-foreground/70 mb-3">
              or click to browse
            </p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="text-xs h-7 group-hover:bg-primary/5 transition-colors"
              onClick={(e) => {
                e.stopPropagation();
                document.getElementById(id)?.click();
              }}
            >
              Browse File
            </Button>
            <input
              id={id}
              type="file"
              accept="application/pdf"
              className="hidden"
              onChange={(e) => {
                if (e.target.files?.[0]) {
                  validateAndProcessFile(e.target.files[0]);
                }
                e.target.value = '';
              }}
            />
          </div>
        ) : (
          <FileInputDisplay file={file} Icon={Icon} onFileChange={onFileChange} />
        )}
      </div>
      {error && <InputError message={error} />}
    </div>
  );
};

export default function PostQualificationUpload({ procurement, errors = {} }: PostQualificationUploadProps) {
  const [isDraggingFile, setIsDraggingFile] = useState(false);
  const [draggingOverField, setDraggingOverField] = useState<string | null>(null);

  const { data, setData, post, processing, progress } = useForm({
    procurement_id: procurement.id || '',
    procurement_title: procurement.title || '',
    post_qualification_report: null as File | null,
    twg_certification: null as File | null,
    notice_of_post_qualification: null as File | null,
    submission_date: format(new Date(), 'yyyy-MM-dd'),
    outcome: null as boolean | null,
    remarks: '',
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Post-Qualification Report - ${procurement.id}`, href: '#' },
  ];

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (data.outcome === null) {
      toast.error("Please select an outcome (Verified or Failed)");
      return;
    }
    
    const formData = new FormData();
    formData.append('procurement_id', procurement.id);
    formData.append('procurement_title', procurement.title);
    
    // Add document files
    if (data.post_qualification_report) {
      formData.append('post_qualification_report', data.post_qualification_report);
    }
    
    if (data.twg_certification) {
      formData.append('twg_certification', data.twg_certification);
    }
    
    if (data.notice_of_post_qualification) {
      formData.append('notice_of_post_qualification', data.notice_of_post_qualification);
    }
    
    formData.append('submission_date', data.submission_date);
    formData.append('outcome', String(data.outcome));
    if (data.remarks) {
      formData.append('remarks', data.remarks);
    }

    // Add metadata for each document
    const metadata = [];
    
    if (data.post_qualification_report) {
      metadata.push({
        document_type: 'Post Qualification Report',
        submission_date: data.submission_date
      });
    }
    
    if (data.twg_certification) {
      metadata.push({
        document_type: 'TWG Certification',
        submission_date: data.submission_date
      });
    }
    
    if (data.notice_of_post_qualification) {
      metadata.push({
        document_type: 'Notice of Post Qualification',
        submission_date: data.submission_date
      });
    }
    
    formData.append('metadata', JSON.stringify(metadata));

    post('/bac-secretariat/upload-post-qualification-documents', {
      forceFormData: true,
      onSuccess: () => {
        toast.success("Post-qualification report uploaded successfully!", {
          description: "Post-qualification report has been submitted."
        });
      },
      onError: (errors) => {
        toast.error("Failed to upload documents", {
          description: Object.values(errors)[0] as string
        });
      }
    });
  };

  const handleDragEvents = (e: React.DragEvent, isEntering = true, field: string | null = null) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingFile(isEntering);
    setDraggingOverField(isEntering ? field : null);
  };

  const handleFileDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingFile(false);
    setDraggingOverField(null);
  };

  const handleFileChange = (field: keyof typeof data, file: File | null) => {
    setData(field, file);
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
                <FileInput
                  id="post_qualification_report"
                  label="Post-Qualification Report (Required)"
                  icon={FileText}
                  file={data.post_qualification_report}
                  error={errors.post_qualification_report}
                  onFileChange={(file) => handleFileChange('post_qualification_report', file)}
                  isDragging={isDraggingFile && draggingOverField === 'post_qualification_report'}
                  onDragEnter={(e) => handleDragEvents(e, true, 'post_qualification_report')}
                  onDragLeave={(e) => handleDragEvents(e, false)}
                  onDragOver={(e) => handleDragEvents(e, true, 'post_qualification_report')}
                  onDrop={handleFileDrop}
                />

                <FileInput
                  id="twg_certification"
                  label="TWG Certification (If applicable)"
                  icon={Building}
                  file={data.twg_certification}
                  error={errors.twg_certification}
                  onFileChange={(file) => handleFileChange('twg_certification', file)}
                  isDragging={isDraggingFile && draggingOverField === 'twg_certification'}
                  onDragEnter={(e) => handleDragEvents(e, true, 'twg_certification')}
                  onDragLeave={(e) => handleDragEvents(e, false)}
                  onDragOver={(e) => handleDragEvents(e, true, 'twg_certification')}
                  onDrop={handleFileDrop}
                />

                <FileInput
                  id="notice_of_post_qualification"
                  label="Notice of Post-Qualification (Required)"
                  icon={FileText}
                  file={data.notice_of_post_qualification}
                  error={errors.notice_of_post_qualification}
                  onFileChange={(file) => handleFileChange('notice_of_post_qualification', file)}
                  isDragging={isDraggingFile && draggingOverField === 'notice_of_post_qualification'}
                  onDragEnter={(e) => handleDragEvents(e, true, 'notice_of_post_qualification')}
                  onDragLeave={(e) => handleDragEvents(e, false)}
                  onDragOver={(e) => handleDragEvents(e, true, 'notice_of_post_qualification')}
                  onDrop={handleFileDrop}
                />

                {progress && (
                  <div className="mt-4">
                    <p className="text-sm font-medium mb-1">Upload Progress:</p>
                    <progress value={progress.percentage} max="100" className="w-full h-2 [&::-webkit-progress-bar]:rounded-lg [&::-webkit-progress-value]:rounded-lg   [&::-webkit-progress-bar]:bg-slate-300 [&::-webkit-progress-value]:bg-primary [&::-moz-progress-bar]:bg-primary">
                      {progress.percentage}%
                    </progress>
                    <p className="text-xs text-muted-foreground text-right">{progress.percentage}%</p>
                  </div>
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
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <CalendarIcon className="h-4 w-4 mr-2" />
                    Submission Date
                  </label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        className="w-full justify-start text-left font-normal"
                      >
                        <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                        {data.submission_date ? format(new Date(data.submission_date), 'PPP') : <span>Pick a date</span>}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <Calendar
                        mode="single"
                        selected={new Date(data.submission_date)}
                        onSelect={(date) => date && setData('submission_date', format(date, 'yyyy-MM-dd'))}
                        initialFocus
                        className="rounded-md border shadow-md"
                      />
                    </PopoverContent>
                  </Popover>
                  {errors.submission_date && <InputError message={errors.submission_date} />}
                </div>

                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <FileText className="h-4 w-4 mr-2" />
                    Outcome
                  </label>
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
                      <label
                        htmlFor="passed"
                        className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-checked:border-primary cursor-pointer"
                      >
                        <CheckCircle className="mb-3 h-6 w-6 text-green-500" />
                        <span className="text-center">Verified</span>
                      </label>
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
                      <label
                        htmlFor="failed"
                        className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-checked:border-primary cursor-pointer"
                      >
                        <XCircle className="mb-3 h-6 w-6 text-red-500" />
                        <span className="text-center">Failed</span>
                      </label>
                    </div>
                  </div>
                  {errors.outcome && <InputError message={errors.outcome} />}
                </div>

                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <FileText className="h-4 w-4 mr-2" />
                    Remarks
                  </label>
                  <Textarea
                    placeholder="Enter any additional remarks about the evaluation"
                    rows={5}
                    className="min-h-[150px] resize-none"
                    value={data.remarks}
                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('remarks', e.target.value)}
                  />
                  {errors.remarks && <InputError message={errors.remarks} />}
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
