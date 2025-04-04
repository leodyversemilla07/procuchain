import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import * as z from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, Shield } from 'lucide-react';
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
  performanceBondFile: z.instanceof(File, {
    message: "Performance Bond file is required"
  }),
  submissionDate: z.date({
    required_error: "Submission date is required",
  }),
  bondAmount: z.string().min(1, {
    message: "Bond amount is required",
  }).refine((val) => !isNaN(parseFloat(val)) && parseFloat(val) > 0, {
    message: "Bond amount must be a positive number"
  }),
});

interface PerformanceBondUploadProps {
  procurement: {
    id: string;
    title: string;
    current_state: string;
    phase_identifier: string;
  };
  errors?: Record<string, string>;
}

export default function PerformanceBondUpload({ procurement, errors = {} }: PerformanceBondUploadProps) {
  const [isDraggingBond, setIsDraggingBond] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [serverErrors, setServerErrors] = useState<Record<string, string>>(errors);

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      performanceBondFile: undefined,
      submissionDate: new Date(),
      bondAmount: "",
    },
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Performance Bond - ${procurement.id}`, href: '#' },
  ];

  const onSubmit = (values: z.infer<typeof formSchema>) => {
    setIsSubmitting(true);

    const formData = new FormData();
    formData.append('procurement_id', procurement.id);
    formData.append('procurement_title', procurement.title);
    formData.append('performance_bond_file', values.performanceBondFile);
    formData.append('submission_date', format(values.submissionDate, 'yyyy-MM-dd'));
    formData.append('bond_amount', values.bondAmount);

    router.post('/bac-secretariat/upload-performance-bond-document', formData, {
      onSuccess: () => {
        toast.success("Performance Bond uploaded successfully!", {
          description: "Performance Bond document has been submitted."
        });
        // Remove the redirect if present - let server handle it
      },
      onError: (errors) => {
        setServerErrors(errors);
        setIsSubmitting(false);
      }
    });
  };

  const handleDragEvents = (
    e: React.DragEvent,
    setDragging: React.Dispatch<React.SetStateAction<boolean>>,
    isDragging = true
  ) => {
    e.preventDefault();
    e.stopPropagation();
    setDragging(isDragging);
  };

  const handleFileDrop = (
    e: React.DragEvent,
    fieldName: "performanceBondFile",
    setDragging: React.Dispatch<React.SetStateAction<boolean>>
  ) => {
    e.preventDefault();
    e.stopPropagation();
    setDragging(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
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

  const handleFileChange = (
    e: React.ChangeEvent<HTMLInputElement>,
    fieldName: "performanceBondFile"
  ) => {
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
      <Head title="Upload Performance Bond" />

      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <Shield className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Performance Bond Document</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload the Performance Bond document for procurement
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
                    <Shield className="h-5 w-5 text-primary" />
                    Required Document
                  </CardTitle>
                  <CardDescription>
                    Please upload the Performance Bond document in PDF format
                  </CardDescription>
                </CardHeader>

                <CardContent className="space-y-8">
                  <FormField
                    control={form.control}
                    name="performanceBondFile"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <Shield className="h-4 w-4 mr-2" />
                          Performance Bond Document
                        </FormLabel>
                        <FormControl>
                          <div
                            className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingBond
                              ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                              : field.value
                                ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                : form.formState.errors.performanceBondFile
                                  ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                  : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                              } cursor-pointer group`}
                            onDragEnter={(e) => handleDragEvents(e, setIsDraggingBond)}
                            onDragLeave={(e) => handleDragEvents(e, setIsDraggingBond, false)}
                            onDragOver={(e) => handleDragEvents(e, setIsDraggingBond)}
                            onDrop={(e) => handleFileDrop(e, "performanceBondFile", setIsDraggingBond)}
                            onClick={() => document.getElementById('bond-file-input')?.click()}
                          >
                            {!field.value ? (
                              <div className="flex flex-col items-center justify-center text-center">
                                <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                                  <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                                </div>
                                <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                                  Drag and drop your Performance Bond file here
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
                                    document.getElementById('bond-file-input')?.click();
                                  }}
                                >
                                  Browse Files
                                </Button>
                                <input
                                  id="bond-file-input"
                                  type="file"
                                  accept="application/pdf"
                                  className="hidden"
                                  onChange={(e) => handleFileChange(e, "performanceBondFile")}
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
                                    form.resetField("performanceBondFile");
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

                  <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-md border border-blue-200 dark:border-blue-800">
                    <div className="flex items-start">
                      <Shield className="h-5 w-5 text-blue-600 dark:text-blue-500 mt-0.5 mr-3 flex-shrink-0" />
                      <p className="text-sm text-blue-700 dark:text-blue-500">
                        The Performance Bond guarantees the faithful performance of the contract. After recording this document,
                        the system will proceed to the Contract And Purchase Order phase.
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
                <CardHeader className="pb-4 space-y-1">
                  <CardTitle className="text-xl font-semibold flex items-center gap-2">
                    <CalendarIcon className="h-5 w-5 text-primary" />
                    Bond Details
                  </CardTitle>
                  <CardDescription>
                    Provide information about the Performance Bond
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  <FormField
                    control={form.control}
                    name="submissionDate"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <CalendarIcon className="h-4 w-4 mr-2" />
                          Submission Date
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
                    name="bondAmount"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <FileText className="h-4 w-4 mr-2" />
                          Bond Amount
                        </FormLabel>
                        <FormControl>
                          <Input
                            placeholder="Enter bond amount in PHP (e.g., 500000)"
                            type="number"
                            min="0"
                            step="0.01"
                            className="h-10"
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
                    className="w-full flex items-center gap-2 h-11 bg-blue-600 hover:bg-blue-700"
                  >
                    {isSubmitting ? (
                      <div className="flex items-center gap-2">
                        <div className="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                        Processing...
                      </div>
                    ) : (
                      <>
                        <Upload className="h-4 w-4" />
                        Submit Performance Bond
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
