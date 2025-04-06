import React from 'react';
import { CalendarIcon, Building2, User2, Upload, Trash2, Files, FileCheck, FileX, FileText, Plus } from 'lucide-react';
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

interface Document {
  document_type?: string;
  submission_date?: Date;
  municipal_offices?: string;
  signatory_details?: string;
}

interface DocumentsData {
  files: (File | null)[];
  metadata: {
    [key: number]: Document;
  };
}

interface DocumentsProps {
  data: DocumentsData;
  fileIndices: number[];
  isDragging: boolean;
  addFile: () => void;
  removeFile: (index: number) => void;
  hasError: (field: string) => boolean;
  handleFileChange: (e: React.ChangeEvent<HTMLInputElement>, index: number) => void;
  handleMetadataChange: (index: number, field: string, value: string) => void;
  handleDateChange: (index: number, date: Date | undefined) => void;
  handleDragEnter: (e: React.DragEvent) => void;
  handleDragLeave: (e: React.DragEvent) => void;
  handleDragOver: (e: React.DragEvent) => void;
  handleFileDrop: (e: React.DragEvent, index: number) => void;
  dates: { [key: number]: Date | undefined };
  copyMetadataFromPrevious: (index: number) => void;
}

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

export function Documents({
  data,
  fileIndices,
  addFile,
  removeFile,
  isDragging,
  hasError,
  handleFileChange,
  handleMetadataChange,
  handleDateChange,
  handleDragEnter,
  handleDragLeave,
  handleDragOver,
  handleFileDrop,
  dates,
  copyMetadataFromPrevious,
}: DocumentsProps) {
  return (
    <Card className="border-sidebar-border/70 dark:border-sidebar-border">
      <CardHeader className="pb-6">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-primary/10">
            <Files className="h-5 w-5 text-primary" />
          </div>
          <div>
            <CardTitle className="text-xl">Documents</CardTitle>
            <CardDescription className="mt-1">
              Upload supporting documents for your procurement request
            </CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-8">
        <div className="flex justify-between items-center">
          <div className="flex items-center">
            <div className="p-1.5 rounded-md bg-primary/10 mr-3">
              <FileText className="h-4 w-4 text-primary" />
            </div>
            <span className="font-medium">Supporting Documents</span>
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={addFile}
            className="gap-2 hover:bg-primary/5 hover:text-primary transition-colors"
          >
            <Plus className="h-4 w-4" />
            Add Document
          </Button>
        </div>

        <div className="space-y-8">
          {fileIndices.map((index) => (
            <div key={index} 
              className={cn(
                "relative border-2 rounded-xl p-6 transition-all duration-300 shadow-sm hover:shadow-md",
                isDragging ? "border-primary/50 bg-primary/5 ring-2 ring-primary/20" : "border-border/50",
                hasError(`files.${index}`) && "border-destructive/50 bg-destructive/5"
              )}
            >
              <div className="flex items-center justify-between mb-6">
                <Badge variant="outline" className="bg-background px-3 py-1 font-medium shadow-sm">
                  Document {index + 1}
                </Badge>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => removeFile(index)}
                  className="text-muted-foreground hover:text-destructive transition-colors gap-2"
                >
                  <Trash2 className="h-4 w-4" />
                  <span>Remove</span>
                </Button>
              </div>

              <div className="space-y-6">
                <div
                  className={cn(
                    "relative border-2 border-dashed rounded-xl transition-all duration-300",
                    data.files[index] 
                      ? "bg-primary/5 border-primary/30 hover:border-primary/50" 
                      : "border-muted-foreground/20 hover:border-muted-foreground/40",
                    isDragging && "border-primary/70 bg-primary/10 ring-2 ring-primary/20",
                    hasError(`files.${index}`) && "border-destructive/50 bg-destructive/5"
                  )}
                  onDragEnter={handleDragEnter}
                  onDragLeave={handleDragLeave}
                  onDragOver={handleDragOver}
                  onDrop={(e) => handleFileDrop(e, index)}
                >
                  <input
                    type="file"
                    id={`document_${index}`}
                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                    onChange={(e) => handleFileChange(e, index)}
                    accept=".pdf,.doc,.docx"
                    aria-label={`Upload document ${index + 1}`}
                  />
                  
                  <div className="p-8">
                    {data.files[index] ? (
                      <div className="flex items-start gap-4">
                        <div className={cn(
                          "rounded-xl p-3 transition-colors duration-300",
                          isPdfFile(data.files[index])
                            ? "bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400"
                            : "bg-yellow-50 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400"
                        )}>
                          {isPdfFile(data.files[index]) 
                            ? <FileCheck className="h-6 w-6" />
                            : <FileX className="h-6 w-6" />
                          }
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="font-medium truncate">
                            {data.files[index].name}
                          </p>
                          <p className="text-sm text-muted-foreground mt-1.5">
                            {formatBytes(data.files[index].size)} • 
                            {isPdfFile(data.files[index]) 
                              ? " PDF Document" 
                              : data.files[index].type || "Unknown type"
                            }
                          </p>
                          {!isPdfFile(data.files[index]) && (
                            <p className="text-sm text-yellow-600 dark:text-yellow-400 mt-3 flex items-center gap-2">
                              <FileX className="h-4 w-4" />
                              Please upload a PDF file for better compatibility
                            </p>
                          )}
                        </div>
                      </div>
                    ) : (
                      <div className="text-center">
                        <div className="mx-auto w-14 h-14 rounded-xl bg-primary/10 flex items-center justify-center mb-4">
                          <Upload className="h-7 w-7 text-primary" />
                        </div>
                        <h4 className="font-medium text-lg">
                          {isDragging ? "Drop file here" : "Drop file or click to upload"}
                        </h4>
                        <p className="text-sm text-muted-foreground mt-2">
                          PDF files up to 10MB
                        </p>
                      </div>
                    )}
                  </div>
                </div>

                {data.files[index] && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-8 animate-in slide-in-from-top duration-300">
                    {index > 0 && (
                      <div className="md:col-span-2 -mt-2 mb-4">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => copyMetadataFromPrevious(index)}
                          className="w-full gap-2 hover:bg-primary/5 hover:text-primary transition-colors"
                        >
                          <FileText className="h-4 w-4" />
                          Copy Details from Previous Document
                        </Button>
                      </div>
                    )}
                    
                    <div className="space-y-5">
                      <div className="space-y-3">
                        <Label htmlFor={`document_type_${index}`} className="flex items-center gap-2 text-sm font-medium">
                          Document Type
                          <Badge variant="destructive" className="text-[10px]">Required</Badge>
                        </Label>
                        <div className="relative">
                          <FileText className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                          <Input
                            id={`document_type_${index}`}
                            value={data.metadata[index]?.document_type || ''}
                            onChange={(e) => handleMetadataChange(index, 'document_type', e.target.value)}
                            className="pl-9 transition-shadow duration-300 hover:shadow-sm focus:shadow-md"
                            placeholder="e.g., Purchase Request"
                          />
                        </div>
                      </div>

                      <div className="space-y-3">
                        <Label htmlFor={`municipal_office_${index}`} className="flex items-center gap-2 text-sm font-medium">
                          Municipal Office
                          <Badge variant="destructive" className="text-[10px]">Required</Badge>
                        </Label>
                        <Select
                          value={data.metadata[index]?.municipal_offices || ''}
                          onValueChange={(value) => handleMetadataChange(index, 'municipal_offices', value)}
                        >
                          <SelectTrigger id={`municipal_office_${index}`} className="w-full transition-shadow duration-300 hover:shadow-sm focus:shadow-md">
                            <div className="flex items-center gap-2">
                              <Building2 className="h-4 w-4 text-muted-foreground" />
                              <SelectValue placeholder="Select office" />
                            </div>
                          </SelectTrigger>
                          <SelectContent>
                            {MUNICIPAL_OFFICES.map((office) => (
                              <SelectItem 
                                key={office.value} 
                                value={office.value}
                                className="transition-colors hover:bg-primary/5"
                              >
                                {office.label}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    <div className="space-y-5">
                      <div className="space-y-3">
                        <Label htmlFor={`submission_date_${index}`} className="flex items-center gap-2 text-sm font-medium">
                          Submission Date
                          <Badge variant="destructive" className="text-[10px]">Required</Badge>
                        </Label>
                        <div className="relative">
                          <Popover>
                            <PopoverTrigger asChild>
                              <Button
                                variant="outline"
                                role="combobox"
                                className={cn(
                                  "w-full justify-start text-left font-normal",
                                  !dates[index] && "text-muted-foreground"
                                )}
                              >
                                <CalendarIcon className="mr-2 h-4 w-4" />
                                {dates[index] ? format(dates[index], "PPP") : <span>Pick a date</span>}
                              </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                              <Calendar
                                mode="single"
                                selected={dates[index]}
                                onSelect={(date) => handleDateChange(index, date)}
                                disabled={{ after: new Date() }}
                                initialFocus
                              />
                            </PopoverContent>
                          </Popover>
                        </div>
                        {hasError(`metadata.${index}.submission_date`) && (
                          <p className="text-sm text-destructive">Please select a submission date</p>
                        )}
                      </div>

                      <div className="space-y-3">
                        <Label htmlFor={`signatory_${index}`} className="flex items-center gap-2 text-sm font-medium">
                          Signatory Details
                          <Badge variant="destructive" className="text-[10px]">Required</Badge>
                        </Label>
                        <div className="relative">
                          <User2 className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                          <Input
                            id={`signatory_${index}`}
                            value={data.metadata[index]?.signatory_details || ''}
                            onChange={(e) => handleMetadataChange(index, 'signatory_details', e.target.value)}
                            className="pl-9 transition-shadow duration-300 hover:shadow-sm focus:shadow-md"
                            placeholder="Name and position"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
