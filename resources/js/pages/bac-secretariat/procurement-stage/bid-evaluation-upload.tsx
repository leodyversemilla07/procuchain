import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, Users, BarChart4, Eye } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import {
  Popover, PopoverContent, PopoverTrigger,
} from '@/components/ui/popover';
import {
  Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger,
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface BidEvaluationUploadProps {
  procurement: {
    id: string;
    title: string;
  };
  errors?: Record<string, string>;
}

export default function BidEvaluationUpload({ procurement, errors = {} }: BidEvaluationUploadProps) {
  const [isDraggingFile, setIsDraggingFile] = useState(false);
  const [isDraggingAbstract, setIsDraggingAbstract] = useState(false);
  const [showPreview, setShowPreview] = useState<{ summary: boolean; abstract: boolean }>({ summary: false, abstract: false });
  const [showConfirmDialog, setShowConfirmDialog] = useState(false);

  const { data, setData, post, processing } = useForm({
    procurement_id: procurement.id,
    procurement_title: procurement.title,
    summary_file: null as File | null,
    abstract_file: null as File | null,
    evaluation_date: format(new Date(), 'yyyy-MM-dd'),
    evaluator_names: '',
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Bid Evaluation Report - ${procurement.id}`, href: '#' },
  ];

  const validateFile = (file: File): string | null => {
    if (file.size > MAX_FILE_SIZE) {
      return `File size exceeds 10MB limit`;
    }
    if (file.type !== 'application/pdf') {
      return 'Only PDF files are allowed';
    }
    return null;
  };

  const handleFileDrop = (e: React.DragEvent, fileType: 'summary' | 'abstract') => {
    e.preventDefault();
    e.stopPropagation();
    if (fileType === 'summary') {
      setIsDraggingFile(false);
    } else {
      setIsDraggingAbstract(false);
    }

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      const error = validateFile(file);
      if (error) {
        toast.error(error);
        return;
      }
      setData(fileType === 'summary' ? 'summary_file' : 'abstract_file', file);
      toast.success(`${fileType === 'summary' ? 'Summary' : 'Abstract'} file uploaded successfully`);
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, fileType: 'summary' | 'abstract') => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      const error = validateFile(file);
      if (error) {
        toast.error(error);
        return;
      }
      setData(fileType === 'summary' ? 'summary_file' : 'abstract_file', file);
      toast.success(`${fileType === 'summary' ? 'Summary' : 'Abstract'} file uploaded successfully`);
    }
  };

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setShowConfirmDialog(true);
  };

  const handleConfirmSubmit = () => {
    setData('procurement_id', procurement.id);
    setData('procurement_title', procurement.title);

    post(
      '/bac-secretariat/upload-bid-evaluation-documents',
      {
        forceFormData: true,
        onSuccess: () => {
          toast.success("Bid evaluation report uploaded successfully!", {
            description: "Bid evaluation report has been submitted."
          });
        },
      }
    );
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const handleDragEvents = (e: React.DragEvent, isDragging = true, fileType: 'summary' | 'abstract') => {
    e.preventDefault();
    e.stopPropagation();
    if (fileType === 'summary') {
      setIsDraggingFile(isDragging);
    } else {
      setIsDraggingAbstract(isDragging);
    }
  };

  const FileUploadArea = ({ fileType, file, onFileChange, onFileDrop, isDragging, onDragEvents }: {
    fileType: 'summary' | 'abstract';
    file: File | null;
    onFileChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    onFileDrop: (e: React.DragEvent) => void;
    isDragging: boolean;
    onDragEvents: (e: React.DragEvent, isDragging: boolean) => void;
  }) => (
    <div
      className={`border-2 border-dashed rounded-lg p-4 sm:p-6 transition-all duration-200 min-h-[180px] sm:min-h-[220px] flex flex-col justify-center ${isDragging
        ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
        : file
          ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
          : errors[`${fileType}_file`]
            ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
            : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
        } cursor-pointer group`}
      onDragEnter={(e) => onDragEvents(e, true)}
      onDragLeave={(e) => onDragEvents(e, false)}
      onDragOver={(e) => onDragEvents(e, true)}
      onDrop={onFileDrop}
      onClick={() => document.getElementById(`${fileType}-file-input`)?.click()}
      role="button"
      tabIndex={0}
      aria-label={`Upload ${fileType} file`}
    >
      {!file ? (
        <div className="flex flex-col items-center justify-center text-center">
          <div className="rounded-full bg-muted p-2 sm:p-3 mb-2 sm:mb-3 group-hover:bg-primary/10 transition-colors">
            <FileUp className="h-5 w-5 sm:h-6 sm:w-6 text-muted-foreground group-hover:text-primary transition-colors" />
          </div>
          <p className="font-medium text-sm sm:text-base text-muted-foreground mb-1 sm:mb-2 group-hover:text-foreground transition-colors">
            Drag and drop your {fileType} here
          </p>
          <p className="text-xs sm:text-sm text-muted-foreground/70 mb-4 sm:mb-5">
            Only PDF files up to 10MB are supported
          </p>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="group-hover:bg-primary/5 transition-colors text-xs sm:text-sm"
            onClick={(e) => {
              e.stopPropagation();
              document.getElementById(`${fileType}-file-input`)?.click();
            }}
          >
            Browse Files
          </Button>
          <input
            id={`${fileType}-file-input`}
            type="file"
            accept="application/pdf"
            className="hidden"
            onChange={onFileChange}
          />
        </div>
      ) : (
        <div className="flex items-center justify-between">
          <div className="flex items-center">
            <div className="rounded-full bg-primary/10 p-2 sm:p-3 mr-3 sm:mr-4">
              <FileText className="h-5 w-5 sm:h-6 sm:w-6 text-primary" />
            </div>
            <div>
              <p className="font-medium text-sm sm:text-base">{file.name}</p>
              <p className="text-xs sm:text-sm text-muted-foreground">
                {formatFileSize(file.size)} • PDF
              </p>
            </div>
          </div>
          <div className="flex gap-1 sm:gap-2">
            <Dialog>
              <DialogTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="rounded-full hover:bg-primary/10 hover:text-primary transition-colors h-8 w-8 sm:h-9 sm:w-9"
                  onClick={(e) => {
                    e.stopPropagation();
                    setShowPreview({ ...showPreview, [fileType]: true });
                  }}
                >
                  <Eye className="h-4 w-4" />
                </Button>
              </DialogTrigger>
              <DialogContent className="max-w-[90vw] sm:max-w-4xl h-[80vh]">
                <DialogHeader>
                  <DialogTitle className="text-lg sm:text-xl">Preview {fileType === 'summary' ? 'Summary' : 'Abstract'}</DialogTitle>
                  <DialogDescription className="text-sm sm:text-base">
                    {file.name}
                  </DialogDescription>
                </DialogHeader>
                <iframe
                  src={URL.createObjectURL(file)}
                  className="w-full h-full border-0"
                  title={`${fileType} preview`}
                />
              </DialogContent>
            </Dialog>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors h-8 w-8 sm:h-9 sm:w-9"
              onClick={(e) => {
                e.stopPropagation();
                setData(`${fileType}_file`, null);
              }}
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Bid Evaluation Report" />

      <div className="flex h-full flex-1 flex-col gap-4 sm:gap-6 rounded-xl p-3 sm:p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <BarChart4 className="h-5 w-5 sm:h-6 sm:w-6" />
            <h1 className="text-xl sm:text-2xl font-bold">Bid Evaluation Report</h1>
          </div>
          <p className="text-sm sm:text-base text-muted-foreground max-w-3xl">
            Upload the bid evaluation report for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-2 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <BarChart4 className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription className="text-sm">
                  Please upload the bid evaluation summary and abstract in PDF format (max 10MB each)
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-6 sm:space-y-8">
                <div className="space-y-2">
                  <label className="flex items-center text-sm sm:text-base font-medium">
                    <FileText className="h-4 w-4 mr-2" />
                    Evaluation Summary
                  </label>
                  <FileUploadArea
                    fileType="summary"
                    file={data.summary_file}
                    onFileChange={(e) => handleFileChange(e, 'summary')}
                    onFileDrop={(e) => handleFileDrop(e, 'summary')}
                    isDragging={isDraggingFile}
                    onDragEvents={(e, isDragging) => handleDragEvents(e, isDragging, 'summary')}
                  />
                  {errors.summary_file && (
                    <InputError message={errors.summary_file} />
                  )}
                </div>

                <div className="space-y-2">
                  <label className="flex items-center text-sm sm:text-base font-medium">
                    <FileText className="h-4 w-4 mr-2" />
                    Bid Abstract
                  </label>
                  <FileUploadArea
                    fileType="abstract"
                    file={data.abstract_file}
                    onFileChange={(e) => handleFileChange(e, 'abstract')}
                    onFileDrop={(e) => handleFileDrop(e, 'abstract')}
                    isDragging={isDraggingAbstract}
                    onDragEvents={(e, isDragging) => handleDragEvents(e, isDragging, 'abstract')}
                  />
                  {errors.abstract_file && (
                    <InputError message={errors.abstract_file} />
                  )}
                </div>
              </CardContent>
            </Card>

            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-2 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Evaluation Details
                </CardTitle>
                <CardDescription className="text-sm">
                  Provide information about the evaluation
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 sm:space-y-6">
                <div className="space-y-2">
                  <label className="flex items-center text-sm sm:text-base font-medium">
                    <CalendarIcon className="h-4 w-4 mr-2" />
                    Evaluation Date
                  </label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        className="w-full justify-start text-left font-normal text-sm sm:text-base"
                      >
                        <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                        {data.evaluation_date ? format(new Date(data.evaluation_date), 'PPP') : <span>Pick a date</span>}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <Calendar
                        mode="single"
                        selected={data.evaluation_date ? new Date(data.evaluation_date) : undefined}
                        onSelect={(date) => date && setData('evaluation_date', format(date, 'yyyy-MM-dd'))}
                        initialFocus
                        className="rounded-md border shadow-md"
                      />
                    </PopoverContent>
                  </Popover>
                  {errors.evaluation_date && (
                    <InputError message={errors.evaluation_date} />
                  )}
                </div>

                <div className="space-y-2">
                  <label className="flex items-center text-sm sm:text-base font-medium">
                    <Users className="h-4 w-4 mr-2" />
                    Evaluator Names
                  </label>
                  <Textarea
                    placeholder="Enter evaluator names (one per line)"
                    rows={4}
                    className="min-h-[120px] sm:min-h-[150px] resize-none text-sm sm:text-base"
                    value={data.evaluator_names}
                    onChange={(e) => setData('evaluator_names', e.target.value)}
                  />
                  <p className="text-xs sm:text-sm text-muted-foreground">
                    Enter one evaluator name per line for better formatting
                  </p>
                  {errors.evaluator_names && (
                    <InputError message={errors.evaluator_names} />
                  )}
                </div>
              </CardContent>

              <CardFooter className="pt-3 sm:pt-4 border-t flex flex-col gap-2 sm:gap-3">
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
                      Submit Evaluation
                    </>
                  )}
                </Button>

                <Button
                  type="button"
                  variant="outline"
                  onClick={() => window.history.back()}
                  disabled={processing}
                  className="w-full h-9 sm:h-10 text-sm sm:text-base"
                >
                  Cancel
                </Button>
              </CardFooter>
            </Card>
          </div>
        </form>

        {Object.keys(errors).length > 0 && (
          <Card className="border-destructive/50 bg-destructive/5 dark:bg-destructive/10 shadow-md">
            <CardContent className="p-3 sm:p-4">
              <div className="flex items-start">
                <AlertCircle className="h-4 w-4 sm:h-5 sm:w-5 text-destructive mt-0.5 mr-2 sm:mr-3" />
                <div>
                  <h4 className="text-xs sm:text-sm font-medium text-destructive">
                    Please fix the following errors:
                  </h4>
                  <ul className="list-disc list-inside mt-1 sm:mt-2 text-xs sm:text-sm text-destructive/90 space-y-0.5 sm:space-y-1">
                    {Object.entries(errors).map(([field, message]) => (
                      <li key={field}>{message}</li>
                    ))}
                  </ul>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        <AlertDialog open={showConfirmDialog} onOpenChange={setShowConfirmDialog}>
          <AlertDialogContent className="max-w-[90vw] sm:max-w-md">
            <AlertDialogHeader>
              <AlertDialogTitle className="text-lg sm:text-xl">Confirm Submission</AlertDialogTitle>
              <AlertDialogDescription className="text-sm sm:text-base">
                Are you sure you want to submit the bid evaluation report? This action cannot be undone.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter className="gap-2 sm:gap-3">
              <AlertDialogCancel className="text-sm sm:text-base">Cancel</AlertDialogCancel>
              <AlertDialogAction onClick={handleConfirmSubmit} className="text-sm sm:text-base">
                Submit
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </AppLayout>
  );
}
