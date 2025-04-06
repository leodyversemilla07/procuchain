import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, Users, ClipboardList } from 'lucide-react';
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

  const { data, setData, post, processing, errors } = useForm({
    procurement_id: procurement?.id || '',
    procurement_title: procurement?.title || '',
    minutes_file: null as File | null,
    attendance_file: null as File | null,
    meeting_date: new Date(),
    participants: "",
  });

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
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Pre-Procurement Documents - ${procurement.id}`, href: '#' },
  ];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    post(route('bac-secretariat.upload-pre-procurement-conference-documents'), {
      preserveState: true,
      forceFormData: true,
      onSuccess: () => {
        toast.success("Documents uploaded successfully!", {
          description: "Pre-procurement conference documents have been submitted."
        });
      },
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, fieldName: "minutes_file" | "attendance_file") => {
    if (e.target.files && e.target.files.length > 0) {
      setData(fieldName, e.target.files[0]);
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
      setData("minutes_file", e.dataTransfer.files[0]);
    }
  };

  const handleAttendanceDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingAttendance(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      setData("attendance_file", e.dataTransfer.files[0]);
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

      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <ClipboardList className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Pre-Procurement Conference Documents</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload meeting minutes and attendance records for the pre-procurement conference of procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <FileText className="h-5 w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription>
                  Please upload all required documents in PDF format
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-8">
                <div className="space-y-2">
                  <div className="flex items-center text-base font-medium">
                    <FileText className="h-4 w-4 mr-2" />
                    Minutes of Pre-Procurement Conference
                  </div>
                  <div
                    className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingMinutes
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
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your minutes file here
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
                      <div className="flex items-center justify-between">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium">{data.minutes_file.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {(data.minutes_file.size / 1024).toFixed(2)} KB • PDF
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
                            setData("minutes_file", null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {errors.minutes_file && <div className="text-destructive text-sm">{errors.minutes_file}</div>}
                </div>

                <div className="space-y-2">
                  <div className="flex items-center text-base font-medium">
                    <Users className="h-4 w-4 mr-2" />
                    Attendance Sheet
                  </div>
                  <div
                    className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingAttendance
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
                      <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your attendance file here
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
                      <div className="flex items-center justify-between">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium">{data.attendance_file.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {(data.attendance_file.size / 1024).toFixed(2)} KB • PDF
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
                            setData("attendance_file", null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {errors.attendance_file && <div className="text-destructive text-sm">{errors.attendance_file}</div>}
                </div>
              </CardContent>
            </Card>

            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-5 w-5 text-primary" />
                  Meeting Details
                </CardTitle>
                <CardDescription>
                  Provide information about the conference
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <div className="flex items-center text-base font-medium">
                    <CalendarIcon className="h-4 w-4 mr-2" />
                    Meeting Date
                  </div>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        className="w-full justify-start text-left font-normal"
                      >
                        <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                        {data.meeting_date ? formatDisplayDate(data.meeting_date) : <span>Pick a date</span>}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      {renderCalendar()}
                    </PopoverContent>
                  </Popover>
                  {errors.meeting_date && <div className="text-destructive text-sm">{errors.meeting_date}</div>}
                </div>

                <div className="space-y-2">
                  <div className="flex items-center text-base font-medium">
                    <Users className="h-4 w-4 mr-2" />
                    Participants
                  </div>
                  <Textarea
                    value={data.participants}
                    onChange={e => setData('participants', e.target.value)}
                    placeholder="Enter participant names separated by commas"
                    rows={5}
                    className="min-h-[150px] resize-none"
                  />
                  {errors.participants && <div className="text-destructive text-sm">{errors.participants}</div>}
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
