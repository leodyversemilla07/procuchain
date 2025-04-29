import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon, FileText, Upload, AlertCircle, X, FileUp, Shield, Briefcase, FileSpreadsheet } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import {
  Popover, PopoverContent, PopoverTrigger,
} from '@/components/ui/popover';
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';

interface PerformanceBondContractPOUploadProps {
  procurement: {
    id: string;
    title: string;
  };
  errors?: Record<string, string>; // Use initialErrors alias for clarity
}

export default function PerformanceBondContractPOUpload({ procurement, errors: initialErrors = {} }: PerformanceBondContractPOUploadProps) {
  const [isDraggingBond, setIsDraggingBond] = useState(false);
  const [isDraggingContract, setIsDraggingContract] = useState(false);
  const [isDraggingPO, setIsDraggingPO] = useState(false);

  // Initialize dates similar to noa-upload
  const currentDate = new Date();
  const formattedDate = format(currentDate, 'yyyy-MM-dd');

  // Include procurement_id and procurement_title in useForm state
  // Use separate Date objects for calendars and formatted strings for submission
  const { data, setData, post, processing, errors } = useForm({
    procurement_id: procurement.id,
    procurement_title: procurement.title,
    performance_bond_file: null as File | null,
    submission_date: formattedDate, // Formatted string for submission
    submission_date_object: currentDate, // Date object for Calendar UI
    bond_amount: '',
    contract_file: null as File | null,
    po_file: null as File | null,
    signing_date: formattedDate, // Formatted string for submission
    signing_date_object: currentDate, // Date object for Calendar UI
  });

  // Combine initial errors with Inertia validation errors
  const displayErrors = { ...initialErrors, ...errors };

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Procurements', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Performance Bond, Contract & PO - ${procurement.id}`, href: '#' },
  ];

  // Simplify onSubmit to let Inertia handle data from useForm state
  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    post('/bac-secretariat/upload-performance-bond-contract-po-documents', {
      forceFormData: true, // Still needed for file uploads
      preserveScroll: true, // Keep scroll position on error
      onSuccess: () => {
        toast.success("Documents uploaded successfully!", {
          description: "Performance bond, contract, and PO have been submitted."
        });
      },
      onError: (errorResponse) => {
        // Errors are automatically populated by useForm
        console.error("Submission Error:", errorResponse);
        toast.error("Submission failed.", {
          description: "Please check the form for errors."
        });
      }
    });
  };

  // ... existing handleDragEvents, handleFileDrop ...
  const handleDragEvents = (e: React.DragEvent, type: 'bond' | 'contract' | 'po', isDragging = true) => {
    e.preventDefault();
    e.stopPropagation();
    if (type === 'bond') setIsDraggingBond(isDragging);
    else if (type === 'contract') setIsDraggingContract(isDragging);
    else setIsDraggingPO(isDragging);
  };

  const handleFileDrop = (e: React.DragEvent, type: 'bond' | 'contract' | 'po') => {
    e.preventDefault();
    e.stopPropagation();
    if (type === 'bond') setIsDraggingBond(false);
    else if (type === 'contract') setIsDraggingContract(false);
    else setIsDraggingPO(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        if (type === 'bond') setData('performance_bond_file', file);
        else if (type === 'contract') setData('contract_file', file);
        else setData('po_file', file);
      } else {
        toast.error("Invalid file type", { description: "Please upload only PDF files." });
      }
    }
  };

  const handleFileChange = (type: 'bond' | 'contract' | 'po') => (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (file.type === 'application/pdf') {
        if (type === 'bond') setData('performance_bond_file', file);
        else if (type === 'contract') setData('contract_file', file);
        else setData('po_file', file);
      } else {
        toast.error("Invalid file type", { description: "Please upload only PDF files." });
        e.target.value = '';
      }
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Performance Bond, Contract & PO" />

      {/* ... existing header ... */}
      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <Briefcase className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Performance Bond, Contract & Purchase Order</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload the performance bond, contract, and purchase order documents for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <form onSubmit={onSubmit} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              {/* ... existing CardHeader ... */}
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <FileText className="h-5 w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription>
                  Please upload the Performance Bond, Contract, and Purchase Order documents in PDF format.
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-8">
                {/* Performance Bond Section - Update error display */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <Shield className="h-4 w-4 mr-2" />
                    Performance Bond Document
                  </label>
                  <div
                    className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingBond
                      ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                      : data.performance_bond_file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : displayErrors.performance_bond_file // Use displayErrors
                          ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                          : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                    // ... existing event handlers ...
                    onDragEnter={(e) => handleDragEvents(e, 'bond')}
                    onDragLeave={(e) => handleDragEvents(e, 'bond', false)}
                    onDragOver={(e) => handleDragEvents(e, 'bond')}
                    onDrop={(e) => handleFileDrop(e, 'bond')}
                    onClick={() => document.getElementById('bond-file-input')?.click()}
                  >
                    {/* ... existing file upload UI ... */}
                    {!data.performance_bond_file ? (
                      <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your performance bond here
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
                          onChange={handleFileChange('bond')}
                        />
                      </div>
                    ) : (
                      <div className="flex items-center justify-between">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium">{data.performance_bond_file.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {(data.performance_bond_file.size / 1024).toFixed(2)} KB • PDF
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
                            setData('performance_bond_file', null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {/* Update error display */}
                  {displayErrors.performance_bond_file && <InputError message={displayErrors.performance_bond_file} />}
                </div>

                {/* Contract Document Section - Update error display */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <Briefcase className="h-4 w-4 mr-2" />
                    Contract Document
                  </label>
                  <div
                    className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingContract
                      ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                      : data.contract_file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : displayErrors.contract_file // Use displayErrors
                          ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                          : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                    // ... existing event handlers ...
                    onDragEnter={(e) => handleDragEvents(e, 'contract')}
                    onDragLeave={(e) => handleDragEvents(e, 'contract', false)}
                    onDragOver={(e) => handleDragEvents(e, 'contract')}
                    onDrop={(e) => handleFileDrop(e, 'contract')}
                    onClick={() => document.getElementById('contract-file-input')?.click()}
                  >
                    {/* ... existing file upload UI ... */}
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
                          onChange={handleFileChange('contract')}
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
                  {/* Update error display */}
                  {displayErrors.contract_file && <InputError message={displayErrors.contract_file} />}
                </div>

                {/* Purchase Order Document Section - Update error display */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <FileSpreadsheet className="h-4 w-4 mr-2" />
                    Purchase Order Document
                  </label>
                  <div
                    className={`border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingPO
                      ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                      : data.po_file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : displayErrors.po_file // Use displayErrors
                          ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                          : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                    // ... existing event handlers ...
                    onDragEnter={(e) => handleDragEvents(e, 'po')}
                    onDragLeave={(e) => handleDragEvents(e, 'po', false)}
                    onDragOver={(e) => handleDragEvents(e, 'po')}
                    onDrop={(e) => handleFileDrop(e, 'po')}
                    onClick={() => document.getElementById('po-file-input')?.click()}
                  >
                    {/* ... existing file upload UI ... */}
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
                          onChange={handleFileChange('po')}
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
                  {/* Update error display */}
                  {displayErrors.po_file && <InputError message={displayErrors.po_file} />}
                </div>
              </CardContent>
            </Card>

            {/* Card for Dates and Amount */}
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              {/* ... existing CardHeader ... */}
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-5 w-5 text-primary" />
                  Details
                </CardTitle>
                <CardDescription>
                  Provide details for the documents
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                {/* Performance Bond Submission Date - Update date handling and error display */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <CalendarIcon className="h-4 w-4 mr-2" />
                    Bond Submission Date
                  </label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        className={`w-full justify-start text-left font-normal ${displayErrors.submission_date ? 'border-destructive' : ''}`}
                      >
                        <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                        {/* Display date from the Date object state */}
                        {data.submission_date_object ? format(data.submission_date_object, 'PPP') : <span>Pick a date</span>}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <Calendar
                        mode="single"
                        selected={data.submission_date_object} // Bind to the Date object state
                        // Update both Date object and formatted string state on select
                        onSelect={(date) => {
                          if (date) {
                            setData('submission_date_object', date);
                            setData('submission_date', format(date, 'yyyy-MM-dd'));
                          }
                        }}
                        initialFocus
                        className="rounded-md border shadow-md"
                      />
                    </PopoverContent>
                  </Popover>
                  {/* Update error display */}
                  {displayErrors.submission_date && <InputError message={displayErrors.submission_date} />}
                </div>

                {/* Bond Amount - Update error display */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <Shield className="h-4 w-4 mr-2" />
                    Bond Amount
                  </label>
                  <Input
                    placeholder="Enter bond amount in PHP (e.g., 500000)"
                    type="number"
                    min="0"
                    step="0.01"
                    className={`h-10 ${displayErrors.bond_amount ? 'border-destructive' : ''}`}
                    value={data.bond_amount}
                    onChange={(e) => setData('bond_amount', e.target.value)}
                  />
                  {/* Update error display */}
                  {displayErrors.bond_amount && <InputError message={displayErrors.bond_amount} />}
                </div>

                {/* Contract/PO Signing Date - Update date handling and error display */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <CalendarIcon className="h-4 w-4 mr-2" />
                    Contract/PO Signing Date
                  </label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        className={`w-full justify-start text-left font-normal ${displayErrors.signing_date ? 'border-destructive' : ''}`}
                      >
                        <CalendarIcon className="mr-2 h-4 w-4 text-muted-foreground" />
                        {/* Display date from the Date object state */}
                        {data.signing_date_object ? format(data.signing_date_object, 'PPP') : <span>Pick a date</span>}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <Calendar
                        mode="single"
                        selected={data.signing_date_object} // Bind to the Date object state
                        // Update both Date object and formatted string state on select
                        onSelect={(date) => {
                          if (date) {
                            setData('signing_date_object', date);
                            setData('signing_date', format(date, 'yyyy-MM-dd'));
                          }
                        }}
                        initialFocus
                        className="rounded-md border shadow-md"
                      />
                    </PopoverContent>
                  </Popover>
                  {/* Update error display */}
                  {displayErrors.signing_date && <InputError message={displayErrors.signing_date} />}
                </div>
              </CardContent>

              {/* ... existing CardFooter ... */}
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
                      Submit All Documents
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

        {/* Error Summary Box - Update error source */}
        {Object.keys(displayErrors).length > 0 && (
          <Card className="border-destructive/50 bg-destructive/5 dark:bg-destructive/10 shadow-md">
            <CardContent className="p-4">
              <div className="flex items-start">
                <AlertCircle className="h-5 w-5 text-destructive mt-0.5 mr-3" />
                <div>
                  <h4 className="text-sm font-medium text-destructive">
                    Please fix the following errors:
                  </h4>
                  <ul className="list-disc list-inside mt-2 text-sm text-destructive/90 space-y-1">
                    {/* Use displayErrors */}
                    {Object.entries(displayErrors).map(([field, message]) => (
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
