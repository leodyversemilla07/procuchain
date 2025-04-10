import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, Briefcase, FileSpreadsheet } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import {
  Popover, PopoverContent, PopoverTrigger,
} from '@/components/ui/popover';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';

interface ContractPOUploadProps {
  procurement: {
    id: string;
    title: string;
  };
  errors?: Record<string, string>;
}

export default function ContractPOUpload({ procurement, errors = {} }: ContractPOUploadProps) {
  const [isDraggingContract, setIsDraggingContract] = useState(false);
  const [isDraggingPO, setIsDraggingPO] = useState(false);

  const { data, setData, post, processing } = useForm({
    contract_file: null as File | null,
    po_file: null as File | null,
    signing_date: new Date(),
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Contract & PO - ${procurement.id}`, href: '#' },
  ];

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('procurement_id', procurement.id);
    formData.append('procurement_title', procurement.title);
    if (data.contract_file) {
      formData.append('contract_file', data.contract_file);
    }
    if (data.po_file) {
      formData.append('po_file', data.po_file);
    }
    formData.append('signing_date', format(data.signing_date, 'yyyy-MM-dd'));

    post('/bac-secretariat/upload-contract-po-document', {
      forceFormData: true,
      onSuccess: () => {
        toast.success("Contract and PO uploaded successfully!", {
          description: "Contract and PO documents have been submitted."
        });
      }
    });
  };

  const handleDragEvents = (e: React.DragEvent, type: 'contract' | 'po', isDragging = true) => {
    e.preventDefault();
    e.stopPropagation();
    if (type === 'contract') {
      setIsDraggingContract(isDragging);
    } else {
      setIsDraggingPO(isDragging);
    }
  };

  const handleFileDrop = (e: React.DragEvent, type: 'contract' | 'po') => {
    e.preventDefault();
    e.stopPropagation();
    if (type === 'contract') {
      setIsDraggingContract(false);
    } else {
      setIsDraggingPO(false);
    }

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        setData(type === 'contract' ? 'contract_file' : 'po_file', file);
      }
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, type: 'contract' | 'po') => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (file.type === 'application/pdf') {
        setData(type === 'contract' ? 'contract_file' : 'po_file', file);
      }
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Contract & PO" />

      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <Briefcase className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Contract & Purchase Order</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload the contract and purchase order documents for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <form onSubmit={onSubmit} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <Briefcase className="h-5 w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription>
                  Please upload both the Contract and Purchase Order documents in PDF format
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-8">
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <Briefcase className="h-4 w-4 mr-2" />
                    Contract Document
                  </label>
                  <div
                    className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${
                      isDraggingContract
                        ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                        : data.contract_file
                          ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                          : errors.contract_file
                            ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                            : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                    } cursor-pointer group`}
                    onDragEnter={(e) => handleDragEvents(e, 'contract')}
                    onDragLeave={(e) => handleDragEvents(e, 'contract', false)}
                    onDragOver={(e) => handleDragEvents(e, 'contract')}
                    onDrop={(e) => handleFileDrop(e, 'contract')}
                    onClick={() => document.getElementById('contract-file-input')?.click()}
                  >
                    {!data.contract_file ? (
                      <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your contract document here
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
                            document.getElementById('contract-file-input')?.click();
                          }}
                        >
                          Browse Files
                        </Button>
                        <input
                          id="contract-file-input"
                          type="file"
                          accept="application/pdf"
                          className="hidden"
                          onChange={(e) => handleFileChange(e, 'contract')}
                        />
                      </div>
                    ) : (
                      <div className="flex items-center justify-between">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium">{data.contract_file.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {(data.contract_file.size / 1024).toFixed(2)} KB • PDF
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
                            setData('contract_file', null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {errors.contract_file && <InputError message={errors.contract_file} />}
                </div>

                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <FileSpreadsheet className="h-4 w-4 mr-2" />
                    Purchase Order Document
                  </label>
                  <div
                    className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${
                      isDraggingPO
                        ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                        : data.po_file
                          ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                          : errors.po_file
                            ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                            : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                    } cursor-pointer group`}
                    onDragEnter={(e) => handleDragEvents(e, 'po')}
                    onDragLeave={(e) => handleDragEvents(e, 'po', false)}
                    onDragOver={(e) => handleDragEvents(e, 'po')}
                    onDrop={(e) => handleFileDrop(e, 'po')}
                    onClick={() => document.getElementById('po-file-input')?.click()}
                  >
                    {!data.po_file ? (
                      <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your purchase order here
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
                            document.getElementById('po-file-input')?.click();
                          }}
                        >
                          Browse Files
                        </Button>
                        <input
                          id="po-file-input"
                          type="file"
                          accept="application/pdf"
                          className="hidden"
                          onChange={(e) => handleFileChange(e, 'po')}
                        />
                      </div>
                    ) : (
                      <div className="flex items-center justify-between">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium">{data.po_file.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {(data.po_file.size / 1024).toFixed(2)} KB • PDF
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
                            setData('po_file', null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {errors.po_file && <InputError message={errors.po_file} />}
                </div>
              </CardContent>
            </Card>

            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-5 w-5 text-primary" />
                  Contract Details
                </CardTitle>
                <CardDescription>
                  Provide information about the contract signing
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <CalendarIcon className="h-4 w-4 mr-2" />
                    Signing Date
                  </label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        className="w-full justify-start text-left font-normal"
                      >
                        <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                        {data.signing_date ? format(data.signing_date, 'PPP') : <span>Pick a date</span>}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <Calendar
                        mode="single"
                        selected={data.signing_date}
                        onSelect={(date) => date && setData('signing_date', date)}
                        initialFocus
                        className="rounded-md border shadow-md"
                      />
                    </PopoverContent>
                  </Popover>
                  {errors.signing_date && <InputError message={errors.signing_date} />}
                </div>
              </CardContent>

              <CardFooter className="pt-4 border-t flex flex-col gap-3">
                <Button
                  type="submit"
                  disabled={processing}
                  className="w-full flex items-center gap-2 h-11 bg-blue-600 hover:bg-blue-700"
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
