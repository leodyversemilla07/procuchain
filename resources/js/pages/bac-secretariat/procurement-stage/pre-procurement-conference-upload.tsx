import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, Users, ClipboardList, Eye, Loader2 } from 'lucide-react';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Constants
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_FILE_TYPES = ['application/pdf'];

interface PreProcurementUploadProps {
  procurement?: {
    id: string;
    title: string;
    status: string;
    stage?: string;
  };
  errors?: Record<string, string>;
}

export default function PreProcurementUpload({ procurement = { id: '', title: '', status: '' } }: PreProcurementUploadProps) {
  const [isDraggingMinutes, setIsDraggingMinutes] = useState(false);
  const [isDraggingAttendance, setIsDraggingAttendance] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [participantInput, setParticipantInput] = useState('');
  const [participants, setParticipants] = useState<string[]>([]);

  const { data, setData, post, processing, errors } = useForm({
    procurement_id: procurement?.id || '',
    procurement_title: procurement?.title || '',
    minutes_file: null as File | null,
    attendance_file: null as File | null,
    meeting_date: new Date(),
    participants: "",
  });

  // File validation
  const validateFile = (file: File) => {
    if (!ALLOWED_FILE_TYPES.includes(file.type)) {
      toast.error("Invalid file type", {
        description: "Only PDF files are allowed."
      });
      return false;
    }
    if (file.size > MAX_FILE_SIZE) {
      toast.error("File too large", {
        description: "Maximum file size is 10MB."
      });
      return false;
    }
    return true;
  };

  // Handle file preview
  const handleFilePreview = (file: File) => {
    const url = URL.createObjectURL(file);
    setPreviewUrl(url);
  };

  // Handle participant input
  const handleParticipantInput = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && participantInput.trim()) {
      e.preventDefault();
      const newParticipant = participantInput.trim();
      if (!participants.includes(newParticipant)) {
        setParticipants([...participants, newParticipant]);
        setData('participants', [...participants, newParticipant].join(', '));
      }
      setParticipantInput('');
    }
  };

  const removeParticipant = (index: number) => {
    const newParticipants = participants.filter((_, i) => i !== index);
    setParticipants(newParticipants);
    setData('participants', newParticipants.join(', '));
  };

  // Calendar display formatter
  const formatDisplayDate = (date: Date) => {
    return format(date, 'PPP');
  };

  // Calendar component
  const renderCalendar = () => (
    <Calendar
      mode="single"
      selected={data.meeting_date instanceof Date ? data.meeting_date : new Date(data.meeting_date)}
      onSelect={handleDateSelect}
      className="rounded-md border shadow-md"
    />
  );

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Pre-Procurement Documents - ${procurement.id}`, href: '#' },
  ];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!data.minutes_file || !data.attendance_file) {
      toast.error("Missing files", {
        description: "Please upload both minutes and attendance files."
      });
      return;
    }

    if (!data.participants.trim()) {
      toast.error("Missing participants", {
        description: "Please add at least one participant."
      });
      return;
    }

    post(route('bac-secretariat.upload-pre-procurement-conference-documents'), {
      preserveState: true,
      forceFormData: true,
      onProgress: (progress) => {
        if (progress?.percentage !== undefined) {
          setUploadProgress(progress.percentage);
        }
      },
      onSuccess: () => {
        toast.success("Documents uploaded successfully!", {
          description: "Pre-procurement conference documents have been submitted."
        });
        setUploadProgress(0);
      },
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, fieldName: "minutes_file" | "attendance_file") => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (validateFile(file)) {
        setData(fieldName, file);
      }
    }
  };

  const handleDateSelect = (date: Date | undefined) => {
    if (date) {
      setData('meeting_date', date);
    }
  };

  const handleMinutesDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingMinutes(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (validateFile(file)) {
        setData("minutes_file", file);
      }
    }
  };

  const handleAttendanceDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingAttendance(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (validateFile(file)) {
        setData("attendance_file", file);
      }
    }
  };

  const handleMinutesDragEnter = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingMinutes(true);
  };

  const handleMinutesDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingMinutes(false);
  };

  const handleMinutesDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (!isDraggingMinutes) setIsDraggingMinutes(true);
  };

  const handleAttendanceDragEnter = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingAttendance(true);
  };

  const handleAttendanceDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingAttendance(false);
  };

  const handleAttendanceDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (!isDraggingAttendance) setIsDraggingAttendance(true);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Pre-Procurement Documents" />

      <div className="flex h-full flex-1 flex-col gap-4 sm:gap-6 rounded-xl p-3 sm:p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <ClipboardList className="h-5 w-5 sm:h-6 sm:w-6" />
            <h1 className="text-xl sm:text-2xl font-bold">Pre-Procurement Conference Documents</h1>
          </div>
          <p className="text-sm sm:text-base text-muted-foreground max-w-3xl">
            Upload meeting minutes and attendance records for the pre-procurement conference of procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4 sm:space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-2 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <FileText className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription className="text-sm">
                  Please upload all required documents in PDF format (max 10MB)
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-6 sm:space-y-8">
                <div className="space-y-2">
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div className="flex items-center text-base font-medium">
                      <FileText className="h-4 w-4 mr-2" />
                      Minutes of Pre-Procurement Conference
                    </div>
                    {data.minutes_file && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => handleFilePreview(data.minutes_file!)}
                        className="text-primary w-full sm:w-auto"
                      >
                        <Eye className="h-4 w-4 mr-2" />
                        Preview
                      </Button>
                    )}
                  </div>
                  <div
                    className={`border-2 border-dashed rounded-lg p-4 sm:p-6 transition-all duration-200 min-h-[180px] sm:min-h-[220px] flex flex-col justify-center ${isDraggingMinutes
                      ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                      : data.minutes_file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : errors.minutes_file
                          ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                          : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                    onDragEnter={handleMinutesDragEnter}
                    onDragLeave={handleMinutesDragLeave}
                    onDragOver={handleMinutesDragOver}
                    onDrop={handleMinutesDrop}
                    onClick={() => document.getElementById('minutes-file-input')?.click()}
                  >
                    {!data.minutes_file ? (
                      <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-2 sm:p-3 mb-2 sm:mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-5 w-5 sm:h-6 sm:w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-sm sm:text-base text-muted-foreground mb-1 sm:mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your minutes file here
                        </p>
                        <p className="text-xs sm:text-sm text-muted-foreground/70 mb-4 sm:mb-5">
                          Only PDF files are supported
                        </p>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="group-hover:bg-primary/5 transition-colors"
                          onClick={(e) => {
                            e.stopPropagation();
                            document.getElementById('minutes-file-input')?.click();
                          }}
                        >
                          Browse Files
                        </Button>
                        <input
                          id="minutes-file-input"
                          type="file"
                          accept="application/pdf"
                          className="hidden"
                          onChange={(e) => handleFileChange(e, "minutes_file")}
                        />
                      </div>
                    ) : (
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-2 sm:p-3 mr-3 sm:mr-4">
                            <FileText className="h-5 w-5 sm:h-6 sm:w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium text-sm sm:text-base break-all">{data.minutes_file.name}</p>
                            <p className="text-xs sm:text-sm text-muted-foreground">
                              {(data.minutes_file.size / 1024).toFixed(2)} KB • PDF
                            </p>
                          </div>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors self-end sm:self-auto"
                          onClick={(e) => {
                            e.stopPropagation();
                            setData("minutes_file", null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {errors.minutes_file && <div className="text-destructive text-xs sm:text-sm">{errors.minutes_file}</div>}
                </div>

                <div className="space-y-2">
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div className="flex items-center text-base font-medium">
                      <Users className="h-4 w-4 mr-2" />
                      Attendance Sheet
                    </div>
                    {data.attendance_file && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => handleFilePreview(data.attendance_file!)}
                        className="text-primary w-full sm:w-auto"
                      >
                        <Eye className="h-4 w-4 mr-2" />
                        Preview
                      </Button>
                    )}
                  </div>
                  <div
                    className={`border-2 border-dashed rounded-lg p-4 sm:p-6 transition-all duration-200 min-h-[180px] sm:min-h-[220px] flex flex-col justify-center ${isDraggingAttendance
                      ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                      : data.attendance_file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : errors.attendance_file
                          ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                          : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                    onDragEnter={handleAttendanceDragEnter}
                    onDragLeave={handleAttendanceDragLeave}
                    onDragOver={handleAttendanceDragOver}
                    onDrop={handleAttendanceDrop}
                    onClick={() => document.getElementById('attendance-file-input')?.click()}
                  >
                    {!data.attendance_file ? (
                      <div className="flex flex-col items-center justify-center text-center px-2 sm:px-4">
                        <div className="rounded-full bg-muted p-2 sm:p-3 mb-2 sm:mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-5 w-5 sm:h-6 sm:w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-sm sm:text-base text-muted-foreground mb-1 sm:mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your attendance file here
                        </p>
                        <p className="text-xs sm:text-sm text-muted-foreground/70 mb-4 sm:mb-5">
                          Only PDF files are supported
                        </p>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="group-hover:bg-primary/5 transition-colors"
                          onClick={(e) => {
                            e.stopPropagation();
                            document.getElementById('attendance-file-input')?.click();
                          }}
                        >
                          Browse Files
                        </Button>
                        <input
                          id="attendance-file-input"
                          type="file"
                          accept="application/pdf"
                          className="hidden"
                          onChange={(e) => handleFileChange(e, "attendance_file")}
                        />
                      </div>
                    ) : (
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-center min-w-0">
                          <div className="rounded-full bg-primary/10 p-2 sm:p-3 mr-3 sm:mr-4 flex-shrink-0">
                            <FileText className="h-5 w-5 sm:h-6 sm:w-6 text-primary" />
                          </div>
                          <div className="min-w-0">
                            <p className="font-medium text-sm sm:text-base truncate">
                              {data.attendance_file.name}
                            </p>
                            <p className="text-xs sm:text-sm text-muted-foreground truncate">
                              {(data.attendance_file.size / 1024).toFixed(2)} KB • PDF
                            </p>
                          </div>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors self-end sm:self-auto flex-shrink-0"
                          onClick={(e) => {
                            e.stopPropagation();
                            setData("attendance_file", null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {errors.attendance_file && <div className="text-destructive text-xs sm:text-sm">{errors.attendance_file}</div>}
                </div>

                {uploadProgress > 0 && (
                  <div className="space-y-2">
                    <div className="flex items-center justify-between text-xs sm:text-sm">
                      <span className="text-muted-foreground">Uploading documents...</span>
                      <span className="font-medium">{uploadProgress}%</span>
                    </div>
                    <Progress value={uploadProgress} className="h-1.5 sm:h-2" />
                  </div>
                )}
              </CardContent>
            </Card>

            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-2 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Meeting Details
                </CardTitle>
                <CardDescription className="text-sm">
                  Provide information about the conference
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 sm:space-y-6">
                <div className="space-y-2">
                  <Label className="flex items-center text-base font-medium">
                    <CalendarIcon className="h-4 w-4 mr-2" />
                    Meeting Date
                  </Label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        className="w-full justify-start text-left font-normal h-10"
                      >
                        <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                        {data.meeting_date ? formatDisplayDate(data.meeting_date) : <span>Pick a date</span>}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      {renderCalendar()}
                    </PopoverContent>
                  </Popover>
                  {errors.meeting_date && <div className="text-destructive text-xs sm:text-sm">{errors.meeting_date}</div>}
                </div>

                <div className="space-y-2">
                  <Label className="flex items-center text-base font-medium">
                    <Users className="h-4 w-4 mr-2" />
                    Participants
                  </Label>
                  <div className="space-y-3">
                    <Input
                      value={participantInput}
                      onChange={(e) => setParticipantInput(e.target.value)}
                      onKeyDown={handleParticipantInput}
                      placeholder="Type participant name and press Enter"
                      className="h-10"
                    />
                    <div className="flex flex-wrap gap-2">
                      {participants.map((participant, index) => (
                        <Badge
                          key={index}
                          variant="secondary"
                          className="flex items-center gap-1 py-1 px-2 text-xs sm:text-sm"
                        >
                          <Users className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                          {participant}
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-4 w-4 sm:h-5 sm:w-5 hover:bg-destructive/10 hover:text-destructive ml-1 -mr-1"
                            onClick={() => removeParticipant(index)}
                          >
                            <X className="h-3 w-3" />
                          </Button>
                        </Badge>
                      ))}
                    </div>
                  </div>
                  {errors.participants && <div className="text-destructive text-xs sm:text-sm">{errors.participants}</div>}
                </div>
              </CardContent>

              <CardFooter className="pt-4 border-t flex flex-col gap-3">
                <Button
                  type="submit"
                  disabled={processing}
                  className="w-full flex items-center gap-2 h-10 sm:h-11"
                >
                  {processing ? (
                    <div className="flex items-center gap-2">
                      <Loader2 className="h-4 w-4 animate-spin" />
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
                  onClick={() => router.visit('/bac-secretariat/procurements-list')}
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
            <CardContent className="p-3 sm:p-4">
              <div className="flex items-start">
                <AlertCircle className="h-4 w-4 sm:h-5 sm:w-5 text-destructive mt-0.5 mr-2 sm:mr-3" />
                <div>
                  <h4 className="text-xs sm:text-sm font-medium text-destructive">
                    Please fix the following errors:
                  </h4>
                  <ul className="list-disc list-inside mt-1 sm:mt-2 text-xs sm:text-sm text-destructive/90 space-y-1">
                    {Object.entries(errors).map(([field, message]) => (
                      <li key={field} className="text-destructive/90">
                        <span className="font-medium text-xs sm:text-sm">{field.replace('_', ' ').toUpperCase()}</span>
                        <span className="text-xs sm:text-sm">: {message}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </CardContent>
          </Card>
        )}
      </div>

      {/* PDF Preview Modal */}
      {previewUrl && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2 sm:p-4">
          <div className="bg-background rounded-lg p-3 sm:p-4 w-full h-[90vh] sm:w-[90vw] sm:h-[90vh] flex flex-col max-w-7xl mx-auto">
            <div className="flex items-center justify-between mb-2 sm:mb-4">
              <h3 className="text-base sm:text-lg font-semibold">Document Preview</h3>
              <Button
                variant="ghost"
                size="icon"
                onClick={() => {
                  URL.revokeObjectURL(previewUrl);
                  setPreviewUrl(null);
                }}
                className="hover:bg-destructive/10 hover:text-destructive transition-colors"
              >
                <X className="h-4 w-4 sm:h-5 sm:w-5" />
              </Button>
            </div>
            <div className="flex-1 bg-muted rounded-md overflow-hidden">
              <iframe
                src={previewUrl}
                className="w-full h-full"
                title="PDF Preview"
              />
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
