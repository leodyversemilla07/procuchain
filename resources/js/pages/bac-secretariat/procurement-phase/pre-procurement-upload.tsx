import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import * as z from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
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
  Form,
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

const formSchema = z.object({
  minutes_file: z.instanceof(File, {
    message: "Minutes file is required"
  }),
  attendance_file: z.instanceof(File, {
    message: "Attendance file is required"
  }),
  meeting_date: z.date({
    required_error: "Meeting date is required",
  }),
  participants: z.string().min(1, {
    message: "Participants information is required",
  }),
});

interface PreProcurementUploadProps {
  procurement: {
    id: string;
    title: string;
    current_state: string;
  };
  errors?: Record<string, string>;
}

export default function PreProcurementUpload({ procurement, errors = {} }: PreProcurementUploadProps) {
  const [isDraggingMinutes, setIsDraggingMinutes] = useState(false);
  const [isDraggingAttendance, setIsDraggingAttendance] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [serverErrors, setServerErrors] = useState<Record<string, string>>(errors);

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      minutes_file: undefined,
      attendance_file: undefined,
      meeting_date: new Date(),
      participants: "",
    },
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Pre-Procurement Documents - ${procurement.id}`, href: '#' },
  ];

  const onSubmit = (values: z.infer<typeof formSchema>) => {
    setIsSubmitting(true);

    const formData = new FormData();
    formData.append('procurement_id', procurement.id);
    formData.append('procurement_title', procurement.title);
    formData.append('minutes_file', values.minutes_file);
    formData.append('attendance_file', values.attendance_file);
    formData.append('meeting_date', format(values.meeting_date, 'yyyy-MM-dd'));
    formData.append('participants', values.participants);

    router.post('/bac-secretariat/upload-pre-procurement-documents', formData, {
      onSuccess: () => {
        toast.success("Documents uploaded successfully!", {
          description: "Pre-procurement conference documents have been submitted."
        });
      },
      onError: (errors) => {
        setServerErrors(errors);
        setIsSubmitting(false);
      }
    });
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

  const handleMinutesDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingMinutes(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        form.setValue("minutes_file", file, { shouldValidate: true });
      } else {
        form.setError("minutes_file", {
          type: "manual",
          message: "Only PDF files are allowed"
        });
      }
    }
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

  const handleAttendanceDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingAttendance(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        form.setValue("attendance_file", file, { shouldValidate: true });
      } else {
        form.setError("attendance_file", {
          type: "manual",
          message: "Only PDF files are allowed"
        });
      }
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, fieldName: "minutes_file" | "attendance_file") => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (file.type === 'application/pdf') {
        form.setValue(fieldName, file, { shouldValidate: true });
      } else {
        form.setError(fieldName, {
          type: "manual",
          message: "Only PDF files are allowed"
        });
      }
    }
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

        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
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
                    control={form.control}
                    name="minutes_file"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <FileText className="h-4 w-4 mr-2" />
                          Minutes of Pre-Procurement Conference
                        </FormLabel>
                        <FormControl>
                          <div
                            className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingMinutes
                              ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                              : field.value
                                ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                : form.formState.errors.minutes_file
                                  ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                  : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                              } cursor-pointer group`}
                            onDragEnter={handleMinutesDragEnter}
                            onDragLeave={handleMinutesDragLeave}
                            onDragOver={handleMinutesDragOver}
                            onDrop={handleMinutesDrop}
                            onClick={() => document.getElementById('minutes-file-input')?.click()}
                          >
                            {!field.value ? (
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
                                    <p className="font-medium">{(field.value as File).name}</p>
                                    <p className="text-sm text-muted-foreground">
                                      {((field.value as File).size / 1024).toFixed(2)} KB • PDF
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
                                    form.resetField("minutes_file");
                                  }}
                                >
                                  <X className="h-4 w-4" />
                                </Button>
                              </div>
                            )}
                          </div>
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name="attendance_file"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <Users className="h-4 w-4 mr-2" />
                          Attendance Sheet
                        </FormLabel>
                        <FormControl>
                          <div
                            className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingAttendance
                              ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                              : field.value
                                ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                : form.formState.errors.attendance_file
                                  ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                  : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                              } cursor-pointer group`}
                            onDragEnter={handleAttendanceDragEnter}
                            onDragLeave={handleAttendanceDragLeave}
                            onDragOver={handleAttendanceDragOver}
                            onDrop={handleAttendanceDrop}
                            onClick={() => document.getElementById('attendance-file-input')?.click()}
                          >
                            {!field.value ? (
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
                                    <p className="font-medium">{(field.value as File).name}</p>
                                    <p className="text-sm text-muted-foreground">
                                      {((field.value as File).size / 1024).toFixed(2)} KB • PDF
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
                                    form.resetField("attendance_file");
                                  }}
                                >
                                  <X className="h-4 w-4" />
                                </Button>
                              </div>
                            )}
                          </div>
                        </FormControl>
                        <FormMessage />
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
                    control={form.control}
                    name="meeting_date"
                    render={({ field }) => (
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
                                {field.value ? format(field.value, 'PPP') : <span>Pick a date</span>}
                              </Button>
                            </FormControl>
                          </PopoverTrigger>
                          <PopoverContent className="w-auto p-0" align="start">
                            <Calendar
                              mode="single"
                              selected={field.value}
                              onSelect={field.onChange}
                              initialFocus
                              className="rounded-md border shadow-md"
                            />
                          </PopoverContent>
                        </Popover>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name="participants"
                    render={({ field }) => (
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
                            {...field}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </CardContent>

                <CardFooter className="pt-4 border-t flex flex-col gap-3">
                  <Button
                    type="submit"
                    disabled={isSubmitting}
                    className="w-full flex items-center gap-2 h-11"
                  >
                    {isSubmitting ? (
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
                    disabled={isSubmitting}
                    className="w-full h-10"
                  >
                    Cancel
                  </Button>
                </CardFooter>
              </Card>
            </div>
          </form>
        </Form>

        {Object.keys(serverErrors).length > 0 && (
          <Card className="border-destructive/50 bg-destructive/5 dark:bg-destructive/10 shadow-md">
            <CardContent className="p-4">
              <div className="flex items-start">
                <AlertCircle className="h-5 w-5 text-destructive mt-0.5 mr-3" />
                <div>
                  <h4 className="text-sm font-medium text-destructive">
                    Please fix the following errors:
                  </h4>
                  <ul className="list-disc list-inside mt-2 text-sm text-destructive/90 space-y-1">
                    {Object.entries(serverErrors).map(([field, message]) => (
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
