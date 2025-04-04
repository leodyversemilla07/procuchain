import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
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
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { BreadcrumbItem } from '@/types';

interface PreProcurementUploadProps {
  procurement: {
    id: string;
    title: string;
    current_state: string;
  };
  errors?: Record<string, string>;
}

export default function PreProcurementUpload({ procurement }: PreProcurementUploadProps) {
  const [isDraggingMinutes, setIsDraggingMinutes] = useState(false);
  const [isDraggingAttendance, setIsDraggingAttendance] = useState(false);

  const form = useForm({
    procurement_id: procurement.id,
    procurement_title: procurement.title,
    minutes_file: null as File | null,
    attendance_file: null as File | null,
    meeting_date: new Date(),
    participants: "",
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Pre-Procurement Documents - ${procurement.id}`, href: '#' },
  ];

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post('/bac-secretariat/upload-pre-procurement-documents', {
      onSuccess: () => {
        toast.success("Documents uploaded successfully!", {
          description: "Pre-procurement conference documents have been submitted."
        });
      },
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, fieldName: "minutes_file" | "attendance_file") => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (file.type === 'application/pdf') {
        form.setData(fieldName, file);
      } else {
        form.setError(fieldName, "Only PDF files are allowed");
      }
    }
  };

  const handleMinutesDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingMinutes(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        form.setData("minutes_file", file);
      } else {
        form.setError("minutes_file", "Only PDF files are allowed");
      }
    }
  };

  const handleAttendanceDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingAttendance(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        form.setData("attendance_file", file);
      } else {
        form.setError("attendance_file", "Only PDF files are allowed");
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

        <form onSubmit={onSubmit} className="space-y-6">
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
                <FormField
                  name="minutes_file"
                  render={() => (
                    <FormItem className="space-y-2">
                      <FormLabel className="flex items-center text-base font-medium">
                        <FileText className="h-4 w-4 mr-2" />
                        Minutes of Pre-Procurement Conference
                      </FormLabel>
                      <FormControl>
                        <div
                          className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingMinutes
                            ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                            : form.data.minutes_file
                              ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                              : form.errors.minutes_file
                                ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                            } cursor-pointer group`}
                          onDragEnter={handleMinutesDragEnter}
                          onDragLeave={handleMinutesDragLeave}
                          onDragOver={handleMinutesDragOver}
                          onDrop={handleMinutesDrop}
                          onClick={() => document.getElementById('minutes-file-input')?.click()}
                        >
                          {!form.data.minutes_file ? (
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
                                  <p className="font-medium">{(form.data.minutes_file as File).name}</p>
                                  <p className="text-sm text-muted-foreground">
                                    {((form.data.minutes_file as File).size / 1024).toFixed(2)} KB • PDF
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
                                  form.setData("minutes_file", null);
                                }}
                              >
                                <X className="h-4 w-4" />
                              </Button>
                            </div>
                          )}
                        </div>
                      </FormControl>
                      {form.errors.minutes_file && (
                        <FormMessage>{form.errors.minutes_file}</FormMessage>
                      )}
                    </FormItem>
                  )}
                />

                <FormField
                  name="attendance_file"
                  render={() => (
                    <FormItem className="space-y-2">
                      <FormLabel className="flex items-center text-base font-medium">
                        <Users className="h-4 w-4 mr-2" />
                        Attendance Sheet
                      </FormLabel>
                      <FormControl>
                        <div
                          className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingAttendance
                            ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                            : form.data.attendance_file
                              ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                              : form.errors.attendance_file
                                ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                            } cursor-pointer group`}
                          onDragEnter={handleAttendanceDragEnter}
                          onDragLeave={handleAttendanceDragLeave}
                          onDragOver={handleAttendanceDragOver}
                          onDrop={handleAttendanceDrop}
                          onClick={() => document.getElementById('attendance-file-input')?.click()}
                        >
                          {!form.data.attendance_file ? (
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
                                  <p className="font-medium">{(form.data.attendance_file as File).name}</p>
                                  <p className="text-sm text-muted-foreground">
                                    {((form.data.attendance_file as File).size / 1024).toFixed(2)} KB • PDF
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
                                  form.setData("attendance_file", null);
                                }}
                              >
                                <X className="h-4 w-4" />
                              </Button>
                            </div>
                          )}
                        </div>
                      </FormControl>
                      {form.errors.attendance_file && (
                        <FormMessage>{form.errors.attendance_file}</FormMessage>
                      )}
                    </FormItem>
                  )}
                />
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
                <FormField
                  name="meeting_date"
                  render={() => (
                    <FormItem className="space-y-2">
                      <FormLabel className="flex items-center text-base font-medium">
                        <CalendarIcon className="h-4 w-4 mr-2" />
                        Meeting Date
                      </FormLabel>
                      <Popover>
                        <PopoverTrigger asChild>
                          <FormControl>
                            <Button
                              variant="outline"
                              className="w-full justify-start text-left font-normal"
                            >
                              <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                              {form.data.meeting_date ? format(form.data.meeting_date, 'PPP') : <span>Pick a date</span>}
                            </Button>
                          </FormControl>
                        </PopoverTrigger>
                        <PopoverContent className="w-auto p-0" align="start">
                          <Calendar
                            mode="single"
                            selected={form.data.meeting_date}
                            onSelect={(date) => date && form.setData('meeting_date', date)}
                            initialFocus
                            className="rounded-md border shadow-md"
                          />
                        </PopoverContent>
                      </Popover>
                      {form.errors.meeting_date && (
                        <FormMessage>{form.errors.meeting_date}</FormMessage>
                      )}
                    </FormItem>
                  )}
                />

                <FormField
                  name="participants"
                  render={() => (
                    <FormItem className="space-y-2">
                      <FormLabel className="flex items-center text-base font-medium">
                        <Users className="h-4 w-4 mr-2" />
                        Participants
                      </FormLabel>
                      <FormControl>
                        <Textarea
                          placeholder="Enter participant names separated by commas"
                          rows={5}
                          className="min-h-[150px] resize-none"
                          value={form.data.participants}
                          onChange={(e) => form.setData('participants', e.target.value)}
                        />
                      </FormControl>
                      {form.errors.participants && (
                        <FormMessage>{form.errors.participants}</FormMessage>
                      )}
                    </FormItem>
                  )}
                />
              </CardContent>

              <CardFooter className="pt-4 border-t flex flex-col gap-3">
                <Button
                  type="submit"
                  disabled={form.processing}
                  className="w-full flex items-center gap-2 h-11"
                >
                  {form.processing ? (
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
                  disabled={form.processing}
                  className="w-full h-10"
                >
                  Cancel
                </Button>
              </CardFooter>
            </Card>
          </div>
        </form>

        {Object.keys(form.errors).length > 0 && (
          <Card className="border-destructive/50 bg-destructive/5 dark:bg-destructive/10 shadow-md">
            <CardContent className="p-4">
              <div className="flex items-start">
                <AlertCircle className="h-5 w-5 text-destructive mt-0.5 mr-3" />
                <div>
                  <h4 className="text-sm font-medium text-destructive">
                    Please fix the following errors:
                  </h4>
                  <ul className="list-disc list-inside mt-2 text-sm text-destructive/90 space-y-1">
                    {Object.entries(form.errors).map(([field, message]) => (
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
