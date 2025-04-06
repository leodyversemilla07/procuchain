import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, Plus, Trash2, Clock, PhilippinePeso, User, FileUp, X } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle,
} from "@/components/ui/card";
import {
  Popover, PopoverContent, PopoverTrigger,
} from '@/components/ui/popover';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';

interface Bidder {
  file: File | null;
  bidder_name: string;
  bid_value: string;
  [key: string]: FormDataConvertible | null;
}

interface FormData {
  opening_date: Date;
  bidders: Bidder[];
  [key: string]: Date | Bidder[] | FormDataConvertible;
}

interface BidSubmissionUploadProps {
  procurement: {
    id: string;
    title: string;
  };
  errors?: Record<string, string>;
}

export default function BidSubmissionUpload({ procurement, errors = {} }: BidSubmissionUploadProps) {
  const [isDraggingFiles, setIsDraggingFiles] = useState<number[]>([]);

  const { data, setData, post, processing, reset } = useForm<FormData>({
    opening_date: new Date(),
    bidders: [{ file: null, bidder_name: '', bid_value: '' }]
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Bid Submissions - ${procurement.id}`, href: '#' },
  ];

  const addBidder = () => {
    setData('bidders', [...(data.bidders || []), { file: null, bidder_name: '', bid_value: '' }]);
  };

  const removeBidder = (index: number) => {
    const updatedBidders = [...(data.bidders || [])];
    updatedBidders.splice(index, 1);
    setData('bidders', updatedBidders);
  };

  const handleDragEvents = (e: React.DragEvent, index: number, isDragging = true) => {
    e.preventDefault();
    e.stopPropagation();
    const newDraggingFiles = [...isDraggingFiles];
    if (isDragging) {
      newDraggingFiles[index] = 1;
    } else {
      newDraggingFiles[index] = 0;
    }
    setIsDraggingFiles(newDraggingFiles);
  };

  const handleFileDrop = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    e.stopPropagation();
    
    const newDraggingFiles = [...isDraggingFiles];
    newDraggingFiles[index] = 0;
    setIsDraggingFiles(newDraggingFiles);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        const updatedBidders = [...(data.bidders || [])];
        updatedBidders[index].file = file;
        setData('bidders', updatedBidders);
      } else {
        toast.error('Invalid file type', {
          description: 'Please upload only PDF files'
        });
      }
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (file.type === 'application/pdf') {
        const updatedBidders = [...(data.bidders || [])];
        updatedBidders[index].file = file;
        setData('bidders', updatedBidders);
      } else {
        toast.error('Invalid file type', {
          description: 'Please upload only PDF files'
        });
      }
    }
  };

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    post('/bac-secretariat/upload-bid-submission-documents', {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast.success("Bid submissions uploaded successfully!", {
          description: "Bid submission documents have been recorded."
        });
        reset();
      },
      onError: () => {
        toast.error("Failed to upload bid submissions", {
          description: "Please check the form for errors and try again."
        });
      },
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Bid Submissions" />

      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <FileText className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Bid Submissions</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload the bid submission documents for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <form onSubmit={onSubmit} className="space-y-6">
          <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
            <CardHeader className="pb-4 space-y-1">
              <CardTitle className="text-xl font-semibold flex items-center gap-2">
                <Clock className="h-5 w-5 text-primary" />
                Bid Opening Details
              </CardTitle>
              <CardDescription>
                Set the date when the bids were opened
              </CardDescription>
            </CardHeader>

            <CardContent>
              <div className="space-y-2">
                <label className="flex items-center text-base font-medium">
                  <CalendarIcon className="h-4 w-4 mr-2" />
                  Opening Date
                </label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button
                      variant="outline"
                      className="w-full justify-start text-left font-normal"
                    >
                      <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                      {data.opening_date ? format(data.opening_date, 'PPP') : <span>Pick a date</span>}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                      mode="single"
                      selected={data.opening_date}
                      onSelect={(date) => setData('opening_date', date || new Date())}
                      initialFocus
                      className="rounded-md border shadow-md"
                    />
                  </PopoverContent>
                </Popover>
                {errors.opening_date && <InputError message={errors.opening_date} />}
              </div>
            </CardContent>
          </Card>

          <div className="space-y-4">
            {data.bidders?.map((bidder, index) => (
              <Card key={index} className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
                <CardHeader className="pb-4 space-y-1">
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-xl font-semibold flex items-center gap-2">
                      <FileText className="h-5 w-5 text-primary" />
                      Bidder #{index + 1}
                    </CardTitle>
                    {index > 0 && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors"
                        onClick={() => removeBidder(index)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                </CardHeader>

                <CardContent className="space-y-6">
                  <div className="space-y-2">
                    <label className="flex items-center text-base font-medium">
                      <FileText className="h-4 w-4 mr-2" />
                      Bid Document
                    </label>
                    <div
                      className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${
                        isDraggingFiles[index]
                          ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                          : bidder.file
                            ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                            : errors[`bidders.${index}.file`]
                              ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                              : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                      onDragEnter={(e) => handleDragEvents(e, index)}
                      onDragLeave={(e) => handleDragEvents(e, index, false)}
                      onDragOver={(e) => handleDragEvents(e, index)}
                      onDrop={(e) => handleFileDrop(e, index)}
                      onClick={() => document.getElementById(`file-input-${index}`)?.click()}
                    >
                      {!bidder.file ? (
                        <div className="flex flex-col items-center justify-center text-center">
                          <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                            <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                          </div>
                          <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                            Drag and drop the bid document here
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
                        <div className="flex items-center justify-between">
                          <div className="flex items-center">
                            <div className="rounded-full bg-primary/10 p-3 mr-4">
                              <FileText className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                              <p className="font-medium">{bidder.file.name}</p>
                              <p className="text-sm text-muted-foreground">
                                {(bidder.file.size / 1024).toFixed(2)} KB • PDF
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
                              const updatedBidders = [...(data.bidders || [])];
                              updatedBidders[index].file = null;
                              setData('bidders', updatedBidders);
                            }}
                          >
                            <X className="h-4 w-4" />
                          </Button>
                        </div>
                      )}
                    </div>
                    {errors[`bidders.${index}.file`] && (
                      <InputError message={errors[`bidders.${index}.file`]} />
                    )}
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                      <label className="flex items-center text-base font-medium">
                        <User className="h-4 w-4 mr-2" />
                        Bidder Name
                      </label>
                      <Input
                        placeholder="Enter company or bidder name"
                        value={bidder.bidder_name}
                        onChange={(e) => {
                          const updatedBidders = [...(data.bidders || [])];
                          updatedBidders[index].bidder_name = e.target.value;
                          setData('bidders', updatedBidders);
                        }}
                      />
                      {errors[`bidders.${index}.bidder_name`] && (
                        <InputError message={errors[`bidders.${index}.bidder_name`]} />
                      )}
                    </div>

                    <div className="space-y-2">
                      <label className="flex items-center text-base font-medium">
                        <PhilippinePeso className="h-4 w-4 mr-2" />
                        Bid Value
                      </label>
                      <Input
                        placeholder="Enter bid amount"
                        type="number"
                        min="0"
                        step="0.01"
                        value={bidder.bid_value}
                        onChange={(e) => {
                          const updatedBidders = [...(data.bidders || [])];
                          updatedBidders[index].bid_value = e.target.value;
                          setData('bidders', updatedBidders);
                        }}
                      />
                      {errors[`bidders.${index}.bid_value`] && (
                        <InputError message={errors[`bidders.${index}.bid_value`]} />
                      )}
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}

            <Button
              type="button"
              variant="outline"
              className="w-full"
              onClick={addBidder}
            >
              <Plus className="h-4 w-4 mr-2" />
              Add Another Bidder
            </Button>
          </div>

          <div className="flex justify-end gap-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => window.history.back()}
              disabled={processing}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={processing}
              className="bg-blue-600 hover:bg-blue-700"
            >
              {processing ? (
                <div className="flex items-center gap-2">
                  <div className="h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin"></div>
                  Processing...
                </div>
              ) : (
                <>
                  <Upload className="h-4 w-4 mr-2" />
                  Submit Bid Documents
                </>
              )}
            </Button>
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
