import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import * as z from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, ClipboardList, CheckCircle, XCircle } from 'lucide-react';
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { BreadcrumbItem } from '@/types';

const formSchema = z.object({
  tax_return_file: z.instanceof(File, {
    message: "Tax return file is required"
  }).optional(),
  financial_statement_file: z.instanceof(File, {
    message: "Financial statement file is required"
  }).optional(),
  verification_report_file: z.instanceof(File, {
    message: "Verification report file is required"
  }).optional(),
  submission_date: z.date({
    required_error: "Submission date is required",
  }),
  outcome: z.string().min(1, {
    message: "Outcome selection is required",
  }),
}).refine(data => data.tax_return_file || data.financial_statement_file || data.verification_report_file, {
  message: "At least one document must be uploaded",
  path: ["verification_report_file"]
});

interface PostQualificationUploadProps {
  procurement: {
    id: string;
    title: string;
    current_state: string;
    phase_identifier: string;
  };
  errors?: Record<string, string>;
}

export default function PostQualificationUpload({ procurement, errors = {} }: PostQualificationUploadProps) {
  const [isDraggingTaxReturn, setIsDraggingTaxReturn] = useState(false);
  const [isDraggingFinancial, setIsDraggingFinancial] = useState(false);
  const [isDraggingVerification, setIsDraggingVerification] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [serverErrors, setServerErrors] = useState<Record<string, string>>(errors);

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      tax_return_file: undefined,
      financial_statement_file: undefined,
      verification_report_file: undefined,
      submission_date: new Date(),
      outcome: "Verified",
    },
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Post-Qualification Documents - ${procurement.id}`, href: '#' },
  ];

  const onSubmit = (values: z.infer<typeof formSchema>) => {
    setIsSubmitting(true);

    const formData = new FormData();
    formData.append('procurement_id', procurement.id);
    formData.append('procurement_title', procurement.title);

    if (values.tax_return_file) {
      formData.append('tax_return_file', values.tax_return_file);
    }

    if (values.financial_statement_file) {
      formData.append('financial_statement_file', values.financial_statement_file);
    }

    if (values.verification_report_file) {
      formData.append('verification_report_file', values.verification_report_file);
    }

    formData.append('submission_date', format(values.submission_date, 'yyyy-MM-dd'));
    formData.append('outcome', values.outcome);

    router.post('/bac-secretariat/upload-post-qualification-documents', formData, {
      onSuccess: () => {
        toast.success("Post-qualification documents uploaded successfully!", {
          description: `Outcome recorded as "${values.outcome}".`
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
    fieldName: "tax_return_file" | "financial_statement_file" | "verification_report_file",
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
    fieldName: "tax_return_file" | "financial_statement_file" | "verification_report_file"
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
      <Head title="Upload Post-Qualification Documents" />

      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <ClipboardList className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Post-Qualification Documents</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload post-qualification verification documents for procurement
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
                    name="tax_return_file"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <FileText className="h-4 w-4 mr-2" />
                          Tax Return Document
                        </FormLabel>
                        <FormControl>
                          <div
                            className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[180px] flex flex-col justify-center ${isDraggingTaxReturn
                              ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                              : field.value
                                ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                : form.formState.errors.tax_return_file
                                  ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                  : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                              } cursor-pointer group`}
                            onDragEnter={(e) => handleDragEvents(e, setIsDraggingTaxReturn)}
                            onDragLeave={(e) => handleDragEvents(e, setIsDraggingTaxReturn, false)}
                            onDragOver={(e) => handleDragEvents(e, setIsDraggingTaxReturn)}
                            onDrop={(e) => handleFileDrop(e, "tax_return_file", setIsDraggingTaxReturn)}
                            onClick={() => document.getElementById('tax-return-file-input')?.click()}
                          >
                            {!field.value ? (
                              <div className="flex flex-col items-center justify-center text-center">
                                <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                                  <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                                </div>
                                <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                                  Drag and drop your tax return file here
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
                                    document.getElementById('tax-return-file-input')?.click();
                                  }}
                                >
                                  Browse Files
                                </Button>
                                <input
                                  id="tax-return-file-input"
                                  type="file"
                                  accept="application/pdf"
                                  className="hidden"
                                  onChange={(e) => handleFileChange(e, "tax_return_file")}
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
                                    form.resetField("tax_return_file");
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
                    name="financial_statement_file"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <FileText className="h-4 w-4 mr-2" />
                          Financial Statement Document
                        </FormLabel>
                        <FormControl>
                          <div
                            className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[180px] flex flex-col justify-center ${isDraggingFinancial
                              ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                              : field.value
                                ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                : form.formState.errors.financial_statement_file
                                  ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                  : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                              } cursor-pointer group`}
                            onDragEnter={(e) => handleDragEvents(e, setIsDraggingFinancial)}
                            onDragLeave={(e) => handleDragEvents(e, setIsDraggingFinancial, false)}
                            onDragOver={(e) => handleDragEvents(e, setIsDraggingFinancial)}
                            onDrop={(e) => handleFileDrop(e, "financial_statement_file", setIsDraggingFinancial)}
                            onClick={() => document.getElementById('financial-statement-file-input')?.click()}
                          >
                            {!field.value ? (
                              <div className="flex flex-col items-center justify-center text-center">
                                <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                                  <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                                </div>
                                <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                                  Drag and drop your financial statement file here
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
                                    document.getElementById('financial-statement-file-input')?.click();
                                  }}
                                >
                                  Browse Files
                                </Button>
                                <input
                                  id="financial-statement-file-input"
                                  type="file"
                                  accept="application/pdf"
                                  className="hidden"
                                  onChange={(e) => handleFileChange(e, "financial_statement_file")}
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
                                    form.resetField("financial_statement_file");
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
                    name="verification_report_file"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <FileText className="h-4 w-4 mr-2" />
                          Verification Report Document
                        </FormLabel>
                        <FormControl>
                          <div
                            className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[180px] flex flex-col justify-center ${isDraggingVerification
                              ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                              : field.value
                                ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                : form.formState.errors.verification_report_file
                                  ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                  : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                              } cursor-pointer group`}
                            onDragEnter={(e) => handleDragEvents(e, setIsDraggingVerification)}
                            onDragLeave={(e) => handleDragEvents(e, setIsDraggingVerification, false)}
                            onDragOver={(e) => handleDragEvents(e, setIsDraggingVerification)}
                            onDrop={(e) => handleFileDrop(e, "verification_report_file", setIsDraggingVerification)}
                            onClick={() => document.getElementById('verification-report-file-input')?.click()}
                          >
                            {!field.value ? (
                              <div className="flex flex-col items-center justify-center text-center">
                                <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                                  <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                                </div>
                                <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                                  Drag and drop your verification report here
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
                                    document.getElementById('verification-report-file-input')?.click();
                                  }}
                                >
                                  Browse Files
                                </Button>
                                <input
                                  id="verification-report-file-input"
                                  type="file"
                                  accept="application/pdf"
                                  className="hidden"
                                  onChange={(e) => handleFileChange(e, "verification_report_file")}
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
                                    form.resetField("verification_report_file");
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
                    Verification Details
                  </CardTitle>
                  <CardDescription>
                    Provide details about the qualification verification
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  <FormField
                    control={form.control}
                    name="submission_date"
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
                    name="outcome"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          {field.value === 'Verified' ? (
                            <CheckCircle className="h-4 w-4 mr-2 text-green-500" />
                          ) : (
                            <XCircle className="h-4 w-4 mr-2 text-red-500" />
                          )}
                          Verification Outcome
                        </FormLabel>
                        <Select
                          value={field.value}
                          onValueChange={field.onChange}
                        >
                          <FormControl>
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder="Select verification outcome" />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            <SelectItem value="Verified">
                              <div className="flex items-center">
                                <CheckCircle className="h-4 w-4 mr-2 text-green-500" />
                                <span>Verified</span>
                              </div>
                            </SelectItem>
                            <SelectItem value="Failed">
                              <div className="flex items-center">
                                <XCircle className="h-4 w-4 mr-2 text-red-500" />
                                <span>Failed</span>
                              </div>
                            </SelectItem>
                          </SelectContent>
                        </Select>
                        <FormMessage />

                        <div className="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md">
                          <p className="text-sm text-blue-800 dark:text-blue-200">
                            <strong>Note:</strong> If "Failed" is selected, the procurement process will be halted
                            and no further phases will be processed.
                          </p>
                        </div>
                      </FormItem>
                    )}
                  />
                </CardContent>

                <CardFooter className="pt-4 border-t flex flex-col gap-3">
                  <Button
                    type="submit"
                    disabled={isSubmitting}
                    className="w-full flex items-center gap-2 h-11"
                    variant={form.watch("outcome") === 'Verified' ? 'default' : 'destructive'}
                  >
                    {isSubmitting ? (
                      <div className="flex items-center gap-2">
                        <div className="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                        Processing...
                      </div>
                    ) : (
                      <>
                        <Upload className="h-4 w-4" />
                        {form.watch("outcome") === 'Verified' ? 'Submit and Proceed' : 'Submit and Halt Process'}
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
