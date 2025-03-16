import React from 'react';
import { FileUp, CalendarIcon, Building2, User2, Upload, Trash2, Files, FileCheck, FileX, FileText, AlertCircle, Plus } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Calendar } from '@/components/ui/calendar';
import { Badge } from '@/components/ui/badge';
import { MUNICIPAL_OFFICES } from '@/types/blockchain';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface SupportingDocument {
  document_type?: string;
  submission_date?: Date;
  municipal_offices?: string;
  signatory_details?: string;
}

interface SupportingDocumentsData {
  supporting_files: (File | null)[];
  supporting_metadata: {
    [key: number]: SupportingDocument;
  };
}

interface SupportingDocumentsStepProps {
  data: SupportingDocumentsData;
  supportingFileIndices: number[];
  errors: Record<string, string>;
  isDragging: boolean;
  addSupportingFile: () => void;
  removeSupportingFile: (index: number) => void;
  hasError: (field: string) => boolean;
  useCustomMetadata: { [key: number]: boolean };  // Kept in interface but won't destructure
  toggleCustomMetadata: (index: number) => void;  // Kept in interface but won't destructure
  handleSupportingFileChange: (e: React.ChangeEvent<HTMLInputElement>, index: number) => void;
  handleSupportingMetadataChange: (index: number, field: string, value: string) => void;
  handleSupportingDateChange: (index: number, date: Date | undefined) => void;
  handleDragEnter: (e: React.DragEvent) => void;
  handleDragLeave: (e: React.DragEvent) => void;
  handleDragOver: (e: React.DragEvent) => void;
  handleSupportingFileDrop: (e: React.DragEvent, index: number) => void;
  supportingDates: { [key: number]: Date | undefined };
  setData: (key: string, value: (File | null)[] | { [key: number]: SupportingDocument }) => void;
}

// Utility functions moved outside component to prevent recreation on each render
const formatBytes = (bytes: number, decimals = 2): string => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

const isPdfFile = (file: File | null): boolean => {
  if (!file) return false;
  return file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
};

export function SupportingDocumentsStep({
  data,
  supportingFileIndices,
  addSupportingFile,
  removeSupportingFile,
  errors,
  isDragging,
  hasError,
  handleSupportingFileChange,
  handleSupportingMetadataChange,
  handleSupportingDateChange,
  handleDragEnter,
  handleDragLeave,
  handleDragOver,
  handleSupportingFileDrop,
  supportingDates,
  setData
}: SupportingDocumentsStepProps) {
  return (
    <Card className="border-sidebar-border/70 dark:border-sidebar-border">
      <CardHeader>
        <div className="flex items-center gap-3">
          <Files className="h-5 w-5 text-primary" />
          <div>
            <CardTitle className="text-xl">Supporting Documents</CardTitle>
            <CardDescription>
              Upload supporting documents for your purchase request
              <Badge className="ml-2 text-xs">Min. 10 Required</Badge>
            </CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-6">
        <div className="flex justify-between items-center">
          <div className="flex items-center">
            <FileText className="h-4 w-4 mr-2 text-primary" />
            <span className="font-medium">Supporting Documents</span>
          </div>
          <Button
            onClick={(e) => {
              e.preventDefault();
              addSupportingFile();
            }}
            variant="outline"
            size="sm"
            className="gap-2"
          >
            <Plus className="h-3.5 w-3.5" />
            Add Document
          </Button>
        </div>

        {supportingFileIndices.length < 10 && (
          <Alert>
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              You need to upload at least {10 - supportingFileIndices.length} more supporting document{supportingFileIndices.length === 9 ? '' : 's'}.
            </AlertDescription>
          </Alert>
        )}

        <div className="space-y-6">
          {supportingFileIndices.map((index) => (
            <div key={index} className="border-sidebar-border/70 dark:border-sidebar-border relative border rounded-lg p-4">
              <div className="absolute -top-3 left-4 px-3 py-0.5 bg-background border border-sidebar-border/70 dark:border-sidebar-border rounded-full">
                <span className="text-sm font-medium">Document {index + 1}</span>
              </div>

              {/* Remove button */}
              <div className="absolute top-2 right-2">
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-8 w-8 p-0 text-destructive rounded-full"
                  onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    removeSupportingFile(index);
                  }}
                  type="button"
                >
                  <Trash2 className="h-4 w-4" />
                  <span className="sr-only">Remove document</span>
                </Button>
              </div>

              {/* File Upload */}
              <div className="space-y-4">
                <div className="flex justify-between items-center">
                  <Label htmlFor={`supporting_file_${index}`} className="flex items-center font-medium">
                    <span>Document File</span>
                    <Badge variant="outline" className="ml-2 text-xs">Required</Badge>
                  </Label>
                  {hasError(`supporting_files.${index}`) && (
                    <p className="text-xs text-destructive">
                      {errors[`supporting_files.${index}`]}
                    </p>
                  )}
                </div>

                <div
                  className={`relative border-2 border-dashed rounded-lg p-6 
                    ${isDragging
                      ? 'border-primary bg-primary/5'
                      : hasError(`supporting_files.${index}`)
                        ? 'border-destructive bg-destructive/5'
                        : data.supporting_files[index]
                          ? 'border-primary/50 bg-primary/5'
                          : 'border-muted hover:border-primary/50 hover:bg-primary/5'
                    }
                  text-center cursor-pointer group transition-all`}
                  onDragEnter={handleDragEnter}
                  onDragLeave={handleDragLeave}
                  onDragOver={handleDragOver}
                  onDrop={(e) => handleSupportingFileDrop(e, index)}
                >
                  <input
                    type="file"
                    id={`supporting_file_${index}`}
                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                    onChange={(e) => handleSupportingFileChange(e, index)}
                    accept=".pdf"
                    aria-label="Upload supporting document"
                  />

                  {data.supporting_files[index] ? (
                    <div className="space-y-3">
                      <div
                        className={`mx-auto w-16 h-16 ${isPdfFile(data.supporting_files[index])
                          ? 'bg-gradient-to-br from-green-100 via-green-50 to-emerald-100 dark:from-green-900/40 dark:via-green-800/30 dark:to-emerald-900/40'
                          : 'bg-gradient-to-br from-orange-100 via-orange-50 to-red-100 dark:from-orange-900/40 dark:via-orange-800/30 dark:to-red-900/40'
                          } rounded-full flex items-center justify-center shadow-lg`}
                      >
                        {isPdfFile(data.supporting_files[index]) ? (
                          <FileCheck className="h-8 w-8 text-green-600 dark:text-green-400" />
                        ) : (
                          <FileX className="h-8 w-8 text-orange-600 dark:text-orange-400" />
                        )}
                      </div>
                      <div>
                        <h4 className="text-base font-medium tracking-tight text-gray-900 dark:text-gray-100 truncate leading-relaxed">
                          {data.supporting_files[index].name}
                        </h4>
                        <p className="text-sm font-normal tracking-wide text-gray-600 dark:text-gray-400 mt-1.5 leading-relaxed">
                          {formatBytes(data.supporting_files[index].size)} • {data.supporting_files[index].type || 'Unknown type'}
                        </p>


                        {isPdfFile(data.supporting_files[index]) && (
                          <p className="text-xs font-medium tracking-wide text-green-600 dark:text-green-400 mt-3 bg-green-50 dark:bg-green-900/30 py-1.5 px-3 rounded-lg inline-flex items-center shadow-sm">
                            <FileCheck className="h-3.5 w-3.5 mr-1.5 flex-shrink-0" />
                            <span>PDF file successfully uploaded</span>
                          </p>
                        )}

                        {!isPdfFile(data.supporting_files[index]) && (
                          <p className="text-xs font-medium tracking-wide text-orange-600 dark:text-orange-400 mt-3 bg-orange-50 dark:bg-orange-900/30 py-1.5 px-3 rounded-lg inline-flex items-center shadow-sm">
                            <FileX className="h-3.5 w-3.5 mr-1.5 flex-shrink-0" />
                            <span>Only PDF files are accepted</span>
                          </p>
                        )}


                        <div>
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 mt-3 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/30 transition-all duration-300 text-xs font-medium px-3 py-1.5 rounded-lg shadow-sm"
                            onClick={() => {
                              const updatedFiles = [...data.supporting_files];
                              updatedFiles[index] = null;
                              setData('supporting_files', updatedFiles);
                            }}
                          >
                            <FileX className="h-3.5 w-3.5 mr-1.5" />
                            <span>Remove File</span>
                          </Button>
                        </div>
                      </div>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      <div className="mx-auto w-16 h-16 bg-gradient-to-br from-gray-100 via-purple-50/80 to-gray-100 dark:from-gray-800 dark:via-purple-900/30 dark:to-gray-800 rounded-full flex items-center justify-center shadow-md">
                        <Upload className="h-8 w-8 text-gray-500 dark:text-gray-400 group-hover:text-purple-500 dark:group-hover:text-purple-400 transition-colors duration-300" />
                      </div>
                      <div>
                        <h4 className="text-base font-medium text-gray-800 dark:text-gray-200 tracking-tight leading-relaxed">
                          {isDragging ? 'Drop the file here' : 'Drag and drop supporting document'}
                        </h4>
                        <p className="text-sm font-normal text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                          Or <span className="text-purple-600 dark:text-purple-400 font-medium underline underline-offset-4 decoration-2 hover:text-purple-700 dark:hover:text-purple-300 cursor-pointer transition-colors duration-200">browse</span> to select a file
                        </p>
                        <div className="inline-flex items-center mt-3 px-3 py-1.5 bg-gray-100/90 dark:bg-gray-800/90 rounded-lg text-xs font-medium tracking-wide text-gray-600 dark:text-gray-400 border border-gray-200/90 dark:border-gray-700/90 leading-relaxed shadow-sm">
                          <svg viewBox="0 0 24 24" className="w-3.5 h-3.5 mr-1.5 text-red-500 dark:text-red-400 flex-shrink-0">
                            <path fill="currentColor" d="M12,10.5H13V13.5H16V14.5H13V17.5H12V14.5H9V13.5H12V10.5M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                          </svg>
                          PDF files only (up to 10MB)
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>

              {/* Document Metadata */}
              {data.supporting_files[index] && (
                <div className="mt-4 rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-4 bg-muted/30">
                  <h4 className="font-medium mb-3 flex items-center gap-2">
                    <FileText className="h-4 w-4 text-primary" />
                    Document Metadata
                  </h4>

                  <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {/* Document Type */}
                      <div className="space-y-2">
                        <Label htmlFor={`document_type_${index}`} className="text-gray-700 dark:text-gray-300 flex items-center text-sm font-medium">
                          <span>Document Type</span>
                          <Badge variant="outline" className="ml-2 text-xs font-medium bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800 px-2 py-0.5">Required</Badge>
                        </Label>
                        <div className="relative">
                          <FileUp className="absolute left-3 top-2.5 h-4 w-4 text-gray-500 dark:text-gray-400" />
                          <Input
                            id={`document_type_${index}`}
                            placeholder="Enter document type"
                            value={data.supporting_metadata[index]?.document_type || ''}
                            onChange={(e) => handleSupportingMetadataChange(index, 'document_type', e.target.value)}
                            className="pl-9 py-2 border-gray-200 dark:border-gray-700 focus-visible:ring-purple-400"
                          />
                        </div>
                      </div>

                      {/* Submission Date */}
                      <div className="space-y-2">
                        <Label htmlFor={`submission_date_${index}`} className="text-gray-700 dark:text-gray-300 flex items-center text-sm font-medium">
                          <span>Submission Date</span>
                          <Badge variant="outline" className="ml-2 text-xs font-medium bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800 px-2 py-0.5">Required</Badge>
                        </Label>
                        <div className="relative">
                          <Popover>
                            <PopoverTrigger asChild>
                              <Button
                                variant={"outline"}
                                className={cn(
                                  "w-full justify-start text-left font-normal",
                                  !supportingDates[index] && "text-muted-foreground"
                                )}
                              >
                                <CalendarIcon className="mr-2 h-4 w-4" />
                                {supportingDates[index] ? format(supportingDates[index], "PPP") : <span>Pick a date</span>}
                              </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                              <Calendar
                                mode="single"
                                selected={supportingDates[index]}
                                onSelect={date => handleSupportingDateChange(index, date)}
                                initialFocus
                              />
                            </PopoverContent>
                          </Popover>
                          <p className="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            Select the submission date for this document
                          </p>
                        </div>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {/* Municipal Office */}
                      <div className="space-y-2">
                        <Label htmlFor={`municipal_offices_${index}`} className="text-gray-700 dark:text-gray-300 flex items-center text-sm font-medium">
                          <span>Relevant Office</span>
                          <Badge variant="outline" className="ml-2 text-xs font-medium bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800 px-2 py-0.5">Required</Badge>
                        </Label>
                        <div className="relative">
                          <Building2 className="absolute left-3 top-2.5 h-4 w-4 text-gray-500 dark:text-gray-400 pointer-events-none" />
                          <Select
                            value={data.supporting_metadata[index]?.municipal_offices || ''}
                            onValueChange={(value) => handleSupportingMetadataChange(index, 'municipal_offices', value)}
                          >
                            <SelectTrigger className={`pl-10 py-2.5 ${hasError('pr_metadata.municipal_offices')
                              ? 'border-red-300 dark:border-red-800 focus-visible:ring-red-500'
                              : 'border-gray-200 dark:border-gray-700 focus-visible:ring-blue-400'
                              } transition-all duration-200 shadow-sm rounded-lg text-base`}>
                              <SelectValue placeholder="Select municipal office" />
                            </SelectTrigger>
                            <SelectContent className="max-h-80">
                              {MUNICIPAL_OFFICES.map((office) => (
                                <SelectItem key={office.value} value={office.value}>{office.label}</SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                      </div>

                      {/* Signatory Details */}
                      <div className="space-y-2">
                        <Label htmlFor={`signatory_details_${index}`} className="text-gray-700 dark:text-gray-300 flex items-center text-sm font-medium">
                          <span>Signatory Details</span>
                          <Badge variant="outline" className="ml-2 text-xs font-medium bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800 px-2 py-0.5">Required</Badge>
                        </Label>
                        <div className="relative">
                          <User2 className="absolute left-3 top-2.5 h-4 w-4 text-gray-500 dark:text-gray-400" />
                          <Input
                            id={`signatory_details_${index}`}
                            placeholder="Name and position of signatory"
                            value={data.supporting_metadata[index]?.signatory_details || ''}
                            onChange={(e) => handleSupportingMetadataChange(index, 'signatory_details', e.target.value)}
                            className="pl-9 py-2 border-gray-200 dark:border-gray-700 focus-visible:ring-purple-400"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
