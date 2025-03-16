import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import * as z from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useFieldArray } from "react-hook-form";
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, Plus, Trash2, Clock, PhilippinePeso, User, FileUp, X, ClipboardList } from 'lucide-react';
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

interface BidSubmissionUploadProps {
  procurement: {
    id: string;
    title: string;
    current_state: string;
    phase_identifier: string;
  };
  errors?: Record<string, string>;
}

const bidderSchema = z.object({
  file: z.custom<File>((v) => v instanceof File, { message: "Bid document is required" })
    .refine((file) => file?.type === 'application/pdf', { message: "Only PDF files are allowed" })
    .nullable(),
  bidder_name: z.string().min(1, { message: "Bidder name is required" }),
  bid_value: z.string().min(1, { message: "Bid value is required" })
    .refine((val) => !isNaN(parseFloat(val)), { message: "Bid value must be a number" })
});

const formSchema = z.object({
  opening_date: z.date({ required_error: "Opening date is required" }),
  bidders: z.array(bidderSchema).min(1, { message: "At least one bidder is required" })
});

export default function BidSubmissionUpload({ procurement, errors = {} }: BidSubmissionUploadProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [serverErrors, setServerErrors] = useState<Record<string, string>>(errors);
  const [isDraggingFiles, setIsDraggingFiles] = useState<number[]>([]);

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      opening_date: new Date(),
      bidders: [{ file: undefined, bidder_name: '', bid_value: '' }]
    }
  });

  const { fields, append, remove } = useFieldArray<z.infer<typeof formSchema>>({
    control: form.control,
    name: "bidders"
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Bid Submission Documents - ${procurement.id}`, href: '#' },
  ];

  const addBidder = () => {
    append({ file: null, bidder_name: '', bid_value: '' });
  };

  const handleFileDragEnter = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingFiles(prev => [...prev, index]);
  };

  const handleFileDragLeave = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingFiles(prev => prev.filter(i => i !== index));
  };

  const handleFileDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
  };

  const handleFileDrop = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingFiles(prev => prev.filter(i => i !== index));

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        form.setValue(`bidders.${index}.file`, file, { shouldValidate: true });
      } else {
        form.setError(`bidders.${index}.file`, {
          type: "manual",
          message: "Only PDF files are allowed"
        });
      }
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (file.type === 'application/pdf') {
        form.setValue(`bidders.${index}.file`, file, { shouldValidate: true });
      } else {
        form.setError(`bidders.${index}.file`, {
          type: "manual",
          message: "Only PDF files are allowed"
        });
      }
    }
  };

  const onSubmit = (values: z.infer<typeof formSchema>) => {
    setIsSubmitting(true);

    const formData = new FormData();
    formData.append('procurement_id', procurement.id);
    formData.append('procurement_title', procurement.title);

    formData.append('opening_date_time', format(values.opening_date, "yyyy-MM-dd'T'HH:mm:ss"));

    values.bidders.forEach((bidder, index) => {
      if (bidder.file) {
        formData.append(`bid_documents[${index}]`, bidder.file);
      }
      formData.append(`bidders_data[${index}][bidder_name]`, bidder.bidder_name);
      formData.append(`bidders_data[${index}][bid_value]`, bidder.bid_value);
    });

    router.post('/bac-secretariat/upload-bid-submission-documents', formData, {
      forceFormData: true,
      onSuccess: () => {
        toast.success("Documents uploaded successfully!", {
          description: "Bid submission documents have been submitted."
        });
      },
      onError: (errors) => {
        setServerErrors(errors);
        setIsSubmitting(false);
      },
      onFinish: () => {
        setIsSubmitting(false);
      }
    });
  };

  const isDragging = (index: number) => isDraggingFiles.includes(index);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Bid Submission Documents" />

      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <ClipboardList className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Bid Submission Documents</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload bid documents for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
                <CardHeader className="pb-4 space-y-1">
                  <CardTitle className="text-xl font-semibold flex items-center gap-2">
                    <CalendarIcon className="h-5 w-5 text-primary" />
                    Bid Opening Details
                  </CardTitle>
                  <CardDescription>
                    Set the date and time for bid opening
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  <FormField
                    control={form.control}
                    name="opening_date"
                    render={({ field }) => (
                      <FormItem className="space-y-2">
                        <FormLabel className="flex items-center text-base font-medium">
                          <Clock className="h-4 w-4 mr-2" />
                          Opening Date & Time
                        </FormLabel>
                        <Popover>
                          <PopoverTrigger asChild>
                            <FormControl>
                              <Button
                                variant="outline"
                                className="w-full justify-start text-left font-normal"
                              >
                                <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                                {field.value ? format(field.value, 'PPP, h:mm a') : <span>Select date and time</span>}
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
                            <div className="border-t p-3 flex justify-between">
                              <Label htmlFor="time" className="mt-2">Time:</Label>
                              <Input
                                id="time"
                                type="time"
                                className="w-32"
                                onChange={(e) => {
                                  if (field.value) {
                                    const [hours, minutes] = e.target.value.split(':').map(Number);
                                    const newDate = new Date(field.value);
                                    newDate.setHours(hours, minutes);
                                    field.onChange(newDate);
                                  }
                                }}
                                value={field.value ? `${field.value.getHours().toString().padStart(2, '0')}:${field.value.getMinutes().toString().padStart(2, '0')}` : ''}
                              />
                            </div>
                          </PopoverContent>
                        </Popover>
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

              <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
                <CardHeader className="pb-4 space-y-1">
                  <div className="flex justify-between items-center">
                    <CardTitle className="text-xl font-semibold flex items-center gap-2">
                      <FileText className="h-5 w-5 text-primary" />
                      Bidder Documents
                    </CardTitle>
                    <Button
                      type="button"
                      onClick={addBidder}
                      variant="outline"
                      size="sm"
                      className="flex items-center gap-1"
                    >
                      <Plus className="h-4 w-4" />
                      Add Bidder
                    </Button>
                  </div>
                  <CardDescription>
                    Upload documents for each participating bidder
                  </CardDescription>
                </CardHeader>

                <CardContent className="space-y-6">
                  {fields.length === 0 && (
                    <div className="text-center p-6 border border-dashed rounded-lg">
                      <p className="text-muted-foreground">No bidders added yet. Click "Add Bidder" to begin.</p>
                    </div>
                  )}

                  {fields.map((field, index) => (
                    <Card key={field.id} className="border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                      <CardHeader className="pb-2 flex flex-row items-center justify-between">
                        <CardTitle className="text-base flex items-center">
                          <User className="h-4 w-4 mr-2 text-primary" />
                          Bidder {index + 1}
                        </CardTitle>
                        {fields.length > 1 && (
                          <Button
                            type="button"
                            onClick={() => remove(index)}
                            variant="ghost"
                            size="sm"
                            className="h-8 w-8 p-0 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/20"
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        )}
                      </CardHeader>
                      <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <FormField
                            control={form.control}
                            name={`bidders.${index}.bidder_name`}
                            render={({ field }) => (
                              <FormItem>
                                <FormLabel className="flex items-center">
                                  <User className="h-4 w-4 mr-2" />
                                  Bidder Name
                                </FormLabel>
                                <FormControl>
                                  <Input
                                    {...field}
                                    placeholder="Enter company or bidder name"
                                  />
                                </FormControl>
                                <FormMessage />
                              </FormItem>
                            )}
                          />

                          <FormField
                            control={form.control}
                            name={`bidders.${index}.bid_value`}
                            render={({ field }) => (
                              <FormItem>
                                <FormLabel className="flex items-center">
                                  <PhilippinePeso className="h-4 w-4 mr-2" />
                                  Bid Value
                                </FormLabel>
                                <FormControl>
                                  <Input
                                    {...field}
                                    placeholder="Enter bid amount"
                                  />
                                </FormControl>
                                <FormMessage />
                              </FormItem>
                            )}
                          />
                        </div>

                        <FormField
                          control={form.control}
                          name={`bidders.${index}.file`}
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel className="flex items-center">
                                <FileText className="h-4 w-4 mr-2" />
                                Bid Document
                              </FormLabel>
                              <FormControl>
                                <div
                                  className={`border-2 border-dashed rounded-lg transition-all duration-200 min-h-[140px] flex flex-col justify-center ${isDragging(index)
                                    ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                                    : field.value
                                      ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                                      : form.formState.errors.bidders?.[index]?.file
                                        ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                                        : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                                    } cursor-pointer group`}
                                  onDragEnter={(e) => handleFileDragEnter(e, index)}
                                  onDragLeave={(e) => handleFileDragLeave(e, index)}
                                  onDragOver={handleFileDragOver}
                                  onDrop={(e) => handleFileDrop(e, index)}
                                  onClick={() => document.getElementById(`file-input-${index}`)?.click()}
                                >
                                  {!field.value ? (
                                    <div className="flex flex-col items-center justify-center text-center p-6 h-full">
                                      <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                                        <FileUp className="h-5 w-5 text-muted-foreground group-hover:text-primary transition-colors" />
                                      </div>
                                      <p className="text-sm font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                                        Drag and drop bid document here
                                      </p>
                                      <p className="text-xs text-muted-foreground/70 mb-4">
                                        Only PDF files are supported
                                      </p>
                                      <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="group-hover:bg-primary/5 transition-colors"
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          document.getElementById(`file-input-${index}`)?.click();
                                        }}
                                      >
                                        Browse Files
                                      </Button>
                                      <input
                                        id={`file-input-${index}`}
                                        type="file"
                                        accept="application/pdf"
                                        className="hidden"
                                        onChange={(e) => handleFileChange(e, index)}
                                      />
                                    </div>
                                  ) : (
                                    <div className="flex items-center justify-between p-6 h-full">
                                      <div className="flex items-center">
                                        <div className="rounded-full bg-primary/10 p-3 mr-4">
                                          <FileText className="h-5 w-5 text-primary" />
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
                                        className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors h-8 w-8"
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          form.setValue(`bidders.${index}.file`, null);
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
                  ))}
                </CardContent>
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
