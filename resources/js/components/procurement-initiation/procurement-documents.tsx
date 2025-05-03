import React from 'react';
import { Upload, File, X, Plus, Calendar, AlertCircle, Info, Building, FileSignature } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { MUNICIPAL_OFFICES } from '@/types/blockchain';

interface ProcurementDocumentsProps {
  data: {
    file: File | null;
    files: (File | null)[];
    metadata: Record<
      number,
      {
        document_type: string;
        submission_date: Date | undefined;
        municipal_offices: string;
        signatory_details: string;
      }
    >;
  };
  fileIndices: number[];
  errors: Record<string, string>;
  hasError: (field: string) => boolean;
  isDragging: boolean;
  handleFileChange: (e: React.ChangeEvent<HTMLInputElement>, index: number) => void;
  handleMainFileChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  handleMetadataChange: (index: number, field: string, value: string) => void;
  handleDateChange: (index: number, date: Date | undefined) => void;
  handleDragEnter: (e: React.DragEvent) => void;
  handleDragLeave: (e: React.DragEvent) => void;
  handleDragOver: (e: React.DragEvent) => void;
  handleFileDrop: (e: React.DragEvent, index?: number) => void;
  addFile: () => void;
  removeFile: (index: number) => void;
  validateFile: (file: File) => boolean;
  dates: Record<number, Date | undefined>;
  setData: (key: string, value: unknown) => void;
}

export function ProcurementDocuments({
  data,
  fileIndices,
  errors,
  hasError,
  isDragging,
  handleFileChange,
  handleMetadataChange,
  handleDateChange,
  handleDragEnter,
  handleDragLeave,
  handleDragOver,
  handleFileDrop,
  addFile,
  removeFile,
  dates
}: ProcurementDocumentsProps) {
  return (
    <div className="space-y-8 animate-fadeIn">
      <div className="flex flex-col sm:flex-row sm:items-center gap-2 mb-4 sm:mb-6">
        <h2 className="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">
          Upload Documents
        </h2>
        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger asChild>
              <Info className="h-4 w-4 text-muted-foreground cursor-help" />
            </TooltipTrigger>
            <TooltipContent className="max-w-xs">
              <p className="text-xs">Upload all required procurement documents with their associated metadata</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>
      </div>

      <div className="space-y-6 sm:space-y-8">
        {fileIndices.map((index) => {
          const file = data.files[index];
          const meta = data.metadata[index];
          const date = dates[index];

          return (
            <div
              key={index}
              className={cn(
                "border dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-800/50 transition-all duration-200",
                hasError(`files.${index}`) || hasError(`metadata.${index}`)
                  ? 'ring-2 ring-red-500/30 border-red-300 dark:border-red-800'
                  : 'shadow-sm hover:shadow-md'
              )}
            >
              <div className="bg-gray-50 dark:bg-gray-800 px-3 sm:px-6 py-3 sm:py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div className="flex items-center gap-2 sm:gap-3">
                  <File className="h-4 sm:h-5 w-4 sm:w-5 text-primary" />
                  <h3 className="font-medium text-base sm:text-lg">Document {index + 1}</h3>
                  {file && (
                    <Badge variant="outline" className="hidden sm:inline-flex bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 border-green-200 dark:border-green-800">
                      File Selected
                    </Badge>
                  )}
                </div>
                {fileIndices.length > 1 && (
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => removeFile(index)}
                    className="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
                  >
                    <X className="h-4 w-4 mr-1 sm:mr-0" />
                    <span className="sm:sr-only">Remove Document</span>
                  </Button>
                )}
              </div>

              <Separator />

              <div className="p-3 sm:p-6">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-8">
                  <div className="space-y-4 sm:space-y-6">
                    <div>
                      <div className="flex items-baseline justify-between mb-2">
                        <Label
                          htmlFor={`file-${index}`}
                          className="text-sm sm:text-base font-medium text-gray-700 dark:text-gray-300"
                        >
                          Document File
                        </Label>
                        <span className="text-[0.65rem] sm:text-[0.7rem] text-muted-foreground">Required • PDF Only</span>
                      </div>

                      {!file ? (
                        <div
                          className={cn(
                            "border-2 border-dashed rounded-lg p-4 sm:p-8 text-center cursor-pointer transition-colors",
                            isDragging
                              ? "border-primary bg-primary/5"
                              : "border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                          )}
                          onDragEnter={handleDragEnter}
                          onDragLeave={handleDragLeave}
                          onDragOver={handleDragOver}
                          onDrop={(e) => handleFileDrop(e, index)}
                          onClick={() => document.getElementById(`file-${index}`)?.click()}
                        >
                          <div className="flex flex-col items-center justify-center space-y-2 sm:space-y-4">
                            <div className="p-3 sm:p-4 bg-primary/10 rounded-full">
                              <Upload className="h-6 sm:h-8 w-6 sm:w-8 text-primary" />
                            </div>
                            <div>
                              <p className="text-base sm:text-lg font-medium text-gray-700 dark:text-gray-200">
                                Drop your file here, or <span className="text-primary">browse</span>
                              </p>
                              <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                                PDF files only, max 10MB
                              </p>
                            </div>
                          </div>
                          <Input
                            id={`file-${index}`}
                            type="file"
                            accept=".pdf"
                            onChange={(e) => handleFileChange(e, index)}
                            className="hidden"
                          />
                        </div>
                      ) : (
                        <div className="border rounded-lg p-3 sm:p-4 bg-green-50 dark:bg-green-900/10 relative">
                          <div className="flex items-center gap-3">
                            <div className="bg-white dark:bg-gray-800 p-2 sm:p-3 rounded-md shadow-sm">
                              <File className="h-6 sm:h-8 w-6 sm:w-8 text-primary" />
                            </div>
                            <div className="text-left flex-grow min-w-0">
                              <p className="font-medium text-gray-900 dark:text-gray-50 text-sm truncate max-w-[calc(100%-60px)]">
                                {file.name}
                              </p>
                              <p className="text-xs text-gray-500 dark:text-gray-400">
                                {(file.size / 1024 / 1024).toFixed(2)} MB • PDF
                              </p>
                            </div>
                            <Button
                              type="button"
                              size="sm"
                              variant="ghost"
                              onClick={() => {
                                const fileInput = document.getElementById(`file-${index}`) as HTMLInputElement;
                                if (fileInput) {
                                  fileInput.value = '';
                                  handleFileChange({ target: { files: null } } as unknown as React.ChangeEvent<HTMLInputElement>, index);
                                }
                              }}
                              className="ml-auto text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 flex-shrink-0"
                            >
                              <X className="h-4 w-4" />
                              <span className="sr-only">Remove</span>
                            </Button>
                          </div>
                          <Input
                            id={`file-${index}`}
                            type="file"
                            accept=".pdf"
                            onChange={(e) => handleFileChange(e, index)}
                            className="hidden"
                          />
                        </div>
                      )}

                      {hasError(`files.${index}`) && (
                        <p className="mt-2 text-xs sm:text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                          <AlertCircle className="h-3 w-3" />
                          {errors[`files.${index}`]}
                        </p>
                      )}
                    </div>

                    <div>
                      <div className="flex items-baseline justify-between mb-2">
                        <Label
                          htmlFor={`document-type-${index}`}
                          className="text-sm sm:text-base font-medium text-gray-700 dark:text-gray-300"
                        >
                          Document Type
                        </Label>
                        <span className="text-[0.65rem] sm:text-[0.7rem] text-muted-foreground">Required</span>
                      </div>

                      <Input
                        id={`document-type-${index}`}
                        type="text"
                        value={meta?.document_type || ''}
                        onChange={(e) => handleMetadataChange(index, 'document_type', e.target.value)}
                        placeholder="Enter document type"
                        className={cn(
                          "transition-all duration-200",
                          hasError(`metadata.${index}.document_type`)
                            ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30'
                            : 'border-gray-200 dark:border-gray-700 focus:border-primary'
                        )}
                      />

                      {hasError(`metadata.${index}.document_type`) && (
                        <p className="mt-2 text-xs sm:text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                          <AlertCircle className="h-3 w-3" />
                          {errors[`metadata.${index}.document_type`]}
                        </p>
                      )}

                      <p className="mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                        Enter the type of document being uploaded (e.g., Project Proposal, Technical Requirements)
                      </p>
                    </div>

                    <div>
                      <div className="flex items-baseline justify-between mb-2">
                        <Label
                          htmlFor={`submission-date-${index}`}
                          className="text-sm sm:text-base font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2"
                        >
                          <Calendar className="h-4 w-4 text-primary/70" />
                          Submission Date
                        </Label>
                        <span className="text-[0.65rem] sm:text-[0.7rem] text-muted-foreground">Required</span>
                      </div>

                      <div className="relative">
                        <DatePicker
                          date={date}
                          onSelect={(newDate: Date | undefined) => handleDateChange(index, newDate)}
                          className={cn(
                            "w-full",
                            hasError(`metadata.${index}.submission_date`)
                              ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30'
                              : ''
                          )}
                        />
                      </div>

                      {hasError(`metadata.${index}.submission_date`) && (
                        <p className="mt-2 text-xs sm:text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                          <AlertCircle className="h-3 w-3" />
                          {errors[`metadata.${index}.submission_date`]}
                        </p>
                      )}
                    </div>
                  </div>

                  <div className="space-y-4 sm:space-y-6">
                    <div>
                      <div className="flex items-baseline justify-between mb-2">
                        <Label
                          htmlFor={`municipal-offices-${index}`}
                          className="text-sm sm:text-base font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2"
                        >
                          <Building className="h-4 w-4 text-primary/70" />
                          Municipal Offices
                        </Label>
                        <span className="text-[0.65rem] sm:text-[0.7rem] text-muted-foreground">Required</span>
                      </div>

                      <Select
                        value={meta?.municipal_offices || ''}
                        onValueChange={(value) => handleMetadataChange(index, 'municipal_offices', value)}
                      >
                        <SelectTrigger
                          id={`municipal-offices-${index}`}
                          className={cn(
                            hasError(`metadata.${index}.municipal_offices`)
                              ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30'
                              : 'border-gray-200 dark:border-gray-700'
                          )}
                        >
                          <SelectValue placeholder="Select municipal office" />
                        </SelectTrigger>
                        <SelectContent>
                          {MUNICIPAL_OFFICES.map((office) => (
                            <SelectItem key={office.value} value={office.value}>
                              {office.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>

                      {hasError(`metadata.${index}.municipal_offices`) && (
                        <p className="mt-2 text-xs sm:text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                          <AlertCircle className="h-3 w-3" />
                          {errors[`metadata.${index}.municipal_offices`]}
                        </p>
                      )}

                      <p className="mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                        Select the municipal office involved in this document.
                      </p>
                    </div>

                    <div>
                      <div className="flex items-baseline justify-between mb-2">
                        <Label
                          htmlFor={`signatory-${index}`}
                          className="text-sm sm:text-base font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2"
                        >
                          <FileSignature className="h-4 w-4 text-primary/70" />
                          Signatory Details
                        </Label>
                        <span className="text-[0.65rem] sm:text-[0.7rem] text-muted-foreground">Required</span>
                      </div>

                      <Textarea
                        id={`signatory-${index}`}
                        value={meta?.signatory_details || ''}
                        onChange={(e) => handleMetadataChange(index, 'signatory_details', e.target.value)}
                        placeholder="Enter signatory names and positions"
                        className={cn(
                          "min-h-[80px] sm:min-h-[100px] resize-y transition-all duration-200",
                          hasError(`metadata.${index}.signatory_details`)
                            ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30'
                            : 'border-gray-200 dark:border-gray-700 focus:border-primary'
                        )}
                      />

                      {hasError(`metadata.${index}.signatory_details`) && (
                        <p className="mt-2 text-xs sm:text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                          <AlertCircle className="h-3 w-3" />
                          {errors[`metadata.${index}.signatory_details`]}
                        </p>
                      )}

                      <p className="mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                        Enter the names and positions of all signatories on this document.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <div className="flex justify-center pt-4 sm:pt-6">
        <Button
          type="button"
          variant="outline"
          onClick={addFile}
          className="flex items-center gap-2 px-4 sm:px-8 py-4 sm:py-6 border-dashed hover:bg-primary/5 transition-colors duration-200 w-full sm:w-auto"
        >
          <Plus className="h-4 w-4" />
          <span>Add Another Document</span>
        </Button>
      </div>

      <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
        <div className="bg-amber-50 dark:bg-amber-900/20 p-3 sm:p-4 rounded-lg border border-amber-100 dark:border-amber-900/30">
          <div className="flex items-start sm:items-center gap-2 sm:gap-3">
            <div className="p-1.5 sm:p-2 bg-amber-100 dark:bg-amber-800/30 rounded-full flex-shrink-0 mt-0.5 sm:mt-0">
              <AlertCircle className="h-4 sm:h-5 w-4 sm:w-5 text-amber-600 dark:text-amber-500" />
            </div>
            <p className="text-xs sm:text-sm text-amber-700 dark:text-amber-400">
              Please ensure all document metadata is accurate. This information will be stored in the blockchain and cannot be easily modified once submitted.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
