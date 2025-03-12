import React, { useCallback } from 'react';
import { ClipboardList, Info } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { PRDocumentUploader } from '@/components/pr-initiation/steps/document-upload/pr-document-uploader';
import { DocumentMetadataForm } from '@/components/pr-initiation/steps/document-upload/document-metadata-form';
import { PRDocument, PRDocumentData } from '@/types/blockchain';

interface PRDocumentStepProps {
  data: PRDocumentData;
  setData: (key: string, value: any) => void;
  errors: Record<string, string>;
  isDragging: boolean;
  hasError: (field: string) => boolean;
  submissionDate: Date | undefined;
  handleDateChange: (date: Date | undefined) => void;
  handleDragEnter: (e: React.DragEvent) => void;
  handleDragLeave: (e: React.DragEvent) => void;
  handleDragOver: (e: React.DragEvent) => void;
  handleDrop: (e: React.DragEvent) => void;
}

/**
 * PRDocumentStep component handles the Purchase Request document upload and metadata collection
 * This is part of the procurement workflow and allows users to upload PR documents and
 * provide necessary metadata information
 */
export function PRDocumentStep({
  data,
  setData,
  errors,
  isDragging,
  hasError,
  submissionDate,
  handleDateChange,
  handleDragEnter,
  handleDragLeave,
  handleDragOver,
  handleDrop,
}: PRDocumentStepProps) {
  // Get current metadata or initialize empty object if undefined
  const getMetadata = useCallback(() => {
    return data.pr_document_metadata || {};
  }, [data.pr_document_metadata]);

  // Update metadata with new value while preserving existing values
  const updateMetadata = useCallback((key: keyof PRDocument, value: any) => {
    setData('pr_document_metadata', {
      ...getMetadata(),
      [key]: value
    });
  }, [getMetadata, setData]);

  // Handle date change and update metadata
  const onDateChange = useCallback((date: Date | undefined) => {
    handleDateChange(date);
    updateMetadata('submission_date', date);
  }, [handleDateChange, updateMetadata]);

  // Handle file upload actions
  const handlePRFileChange = useCallback((file: File | null) => {
    setData('pr_document_file', file);
  }, [setData]);

  return (
    <Card className="border-sidebar-border/70 dark:border-sidebar-border">
      <CardHeader>
        <div className="flex items-center gap-3">
          <ClipboardList className="h-5 w-5 text-primary" />
          <CardTitle className="text-xl">Purchase Request Document</CardTitle>
        </div>
        <CardDescription>
          Upload your PR document and complete the required information
        </CardDescription>
      </CardHeader>

      <CardContent className="space-y-8">
        {/* File Upload Section */}
        <PRDocumentUploader
          documentFile={data.pr_document_file}
          onChange={handlePRFileChange}
          errors={errors}
          hasError={hasError}
          isDragging={isDragging}
          onDragEnter={handleDragEnter}
          onDragLeave={handleDragLeave}
          onDragOver={handleDragOver}
          onDrop={handleDrop}
        />

        {/* Metadata Form Section */}
        <DocumentMetadataForm
          metadata={getMetadata()}
          updateMetadata={updateMetadata}
          errors={errors}
          hasError={hasError}
          submissionDate={submissionDate}
          onDateChange={onDateChange}
        />
      </CardContent>

      <CardFooter className="bg-muted/50 border-t p-4">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Info className="h-4 w-4 text-primary" />
          <span>All fields marked as Required must be filled</span>
        </div>
      </CardFooter>
    </Card>
  );
}