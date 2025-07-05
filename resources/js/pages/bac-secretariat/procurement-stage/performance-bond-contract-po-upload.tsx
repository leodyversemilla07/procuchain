import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { toast } from "sonner";
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { CalendarIcon, FileText, Upload, AlertCircle, Briefcase } from 'lucide-react';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from "@/components/ui/card";
import InputError from '@/components/input-error';
import { BreadcrumbItem } from '@/types';
import FileUploadArea from '@/components/file-upload-area';
import { useFileDrop } from '@/hooks/use-file-drop';
import DatePicker from '@/components/date-picker';
import { InputWithLabel } from '@/components/ui/input-with-label';

const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface PerformanceBondContractPOUploadProps {
  procurement?: {
    id: string;
    title: string;
  };
}

// Helper for type-safe error access
function getFieldError<T extends object>(errors: T, field: keyof T): string | undefined {
  return errors && typeof errors === 'object' ? (errors as Record<string, string>)[field as string] : undefined;
}

export default function PerformanceBondContractPOUpload({ procurement = { id: '', title: '' } }: PerformanceBondContractPOUploadProps) {
  const currentDate = new Date();
  const formattedDate = format(currentDate, 'yyyy-MM-dd');

  const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
    procurement_id: procurement.id || '',
    procurement_title: procurement.title || '',
    performance_bond_file: null as File | null,
    submission_date: formattedDate,
    submission_date_object: currentDate,
    bond_amount: '0.00',
    contract_file: null as File | null,
    po_file: null as File | null,
    signing_date: formattedDate,
    signing_date_object: currentDate,
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Performance Bond, Contract & PO - ${procurement.id}`, href: '#' },
  ];

  // File validation
  const validateFile = (file: File) => {
    if (!ALLOWED_FILE_TYPES.includes(file.type)) {
      toast.error("Invalid file type", { description: "Only PDF files are allowed." });
      return false;
    }
    if (file.size > MAX_FILE_SIZE) {
      toast.error("File too large", { description: "Maximum file size is 10MB." });
      return false;
    }
    return true;
  };

  // File drop hooks
  const bondDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('performance_bond_file', file),
  });
  const contractDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('contract_file', file),
  });
  const poDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('po_file', file),
  });

  const handleFileChange = (type: 'bond' | 'contract' | 'po') => (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (validateFile(file)) {
        if (type === 'bond') setData('performance_bond_file', file);
        else if (type === 'contract') setData('contract_file', file);
        else setData('po_file', file);
      } else {
        e.target.value = '';
      }
    }
  };

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Client-side validation
    if (!data.performance_bond_file) {
      toast.error('Missing Performance Bond', { description: 'Please upload the performance bond PDF.' });
      return;
    }
    if (!data.contract_file) {
      toast.error('Missing Contract', { description: 'Please upload the contract PDF.' });
      return;
    }
    if (!data.po_file) {
      toast.error('Missing Purchase Order', { description: 'Please upload the purchase order PDF.' });
      return;
    }
    if (!data.submission_date) {
      toast.error('Missing Bond Submission Date', { description: 'Please select the bond submission date.' });
      return;
    }
    if (!data.bond_amount || isNaN(Number(data.bond_amount))) {
      toast.error('Invalid Bond Amount', { description: 'Please enter a valid bond amount.' });
      return;
    }
    if (!data.signing_date) {
      toast.error('Missing Contract/PO Signing Date', { description: 'Please select the contract/PO signing date.' });
      return;
    }
    post('/bac-secretariat/upload-performance-bond-contract-po-documents', {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast.success("Documents uploaded successfully!", {
          description: "Performance bond, contract, and PO have been submitted."
        });
        reset();
        clearErrors();
      },
      onError: () => {
        toast.error("Submission failed.", {
          description: "Please check the form for errors."
        });
      }
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Performance Bond, Contract & PO" />
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
                <FileUploadArea
                  label="Performance Bond Document"
                  file={data.performance_bond_file}
                  error={getFieldError(errors, 'performance_bond_file')}
                  isDragging={bondDrop.isDragging}
                  onFileChange={handleFileChange('bond')}
                  onDragEnter={bondDrop.handleDragEnter}
                  onDragLeave={bondDrop.handleDragLeave}
                  onDragOver={bondDrop.handleDragOver}
                  onDrop={bondDrop.handleDrop}
                  onRemove={() => setData('performance_bond_file', null)}
                  inputId="bond-file-input"
                  required={true}
                />
                {getFieldError(errors, 'performance_bond_file') && (
                  <InputError message={getFieldError(errors, 'performance_bond_file')} />
                )}
                <FileUploadArea
                  label="Contract Document"
                  file={data.contract_file}
                  error={getFieldError(errors, 'contract_file')}
                  isDragging={contractDrop.isDragging}
                  onFileChange={handleFileChange('contract')}
                  onDragEnter={contractDrop.handleDragEnter}
                  onDragLeave={contractDrop.handleDragLeave}
                  onDragOver={contractDrop.handleDragOver}
                  onDrop={contractDrop.handleDrop}
                  onRemove={() => setData('contract_file', null)}
                  inputId="contract-file-input"
                  required={true}
                />
                {getFieldError(errors, 'contract_file') && (
                  <InputError message={getFieldError(errors, 'contract_file')} />
                )}
                <FileUploadArea
                  label="Purchase Order Document"
                  file={data.po_file}
                  error={getFieldError(errors, 'po_file')}
                  isDragging={poDrop.isDragging}
                  onFileChange={handleFileChange('po')}
                  onDragEnter={poDrop.handleDragEnter}
                  onDragLeave={poDrop.handleDragLeave}
                  onDragOver={poDrop.handleDragOver}
                  onDrop={poDrop.handleDrop}
                  onRemove={() => setData('po_file', null)}
                  inputId="po-file-input"
                  required={true}
                />
                {getFieldError(errors, 'po_file') && (
                  <InputError message={getFieldError(errors, 'po_file')} />
                )}
              </CardContent>
            </Card>
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
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
                <DatePicker
                  label="Bond Submission Date"
                  value={data.submission_date_object}
                  onChange={date => {
                    if (date) setData('submission_date', format(date, 'yyyy-MM-dd'));
                  }}
                  error={getFieldError(errors, 'submission_date')}
                  required
                />
                {getFieldError(errors, 'submission_date') && (
                  <InputError message={getFieldError(errors, 'submission_date')} />
                )}
                <InputWithLabel
                  id="bond-amount"
                  label="Bond Amount"
                  value={data.bond_amount}
                  onChange={(e) => {
                    const value = e.target.value;
                    if (/^\d*(\.\d{0,2})?$/.test(value)) {
                      setData('bond_amount', value);
                    }
                  }}
                  placeholder="Enter bond amount"
                  className="h-10"
                  required
                  error={getFieldError(errors, 'bond_amount')}
                  errorClassName="mt-1.5 sm:mt-2"
                  type="text"
                />
                <DatePicker
                  label="Contract/PO Signing Date"
                  value={data.signing_date_object}
                  onChange={date => {
                    if (date) setData('signing_date', format(date, 'yyyy-MM-dd'));
                  }}
                  error={getFieldError(errors, 'signing_date')}
                  required
                />
                {getFieldError(errors, 'signing_date') && (
                  <InputError message={getFieldError(errors, 'signing_date')} />
                )}
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
                      <li key={field}>{message as string}</li>
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
