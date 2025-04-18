import React from 'react';
import { CalendarIcon, Building2, User2, Upload, Trash2, Files, FileCheck, FileX, FileText, Plus, AlertCircle } from 'lucide-react';
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

export function ProcurementDocuments({
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
}: DocumentsProps) {
  return (
    <Card className="border-sidebar-border/70 dark:border-sidebar-border">
      <CardHeader className="pb-6">
        <div className="flex items-center gap-4">
          <div className="p-2.5 rounded-lg bg-primary/10">
            <Files className="h-5 w-5 text-primary" />
          </div>
          <div>
            <CardTitle className="text-2xl tracking-tight font-semibold bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-transparent">Documents</CardTitle>
            <CardDescription className="text-base text-muted-foreground/90 mt-1">
              Upload procurement initiation documents for your procurement request
            </CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-8">
        <div className="flex justify-between items-center">
          <div className="flex items-center">
            <div className="p-2 rounded-md bg-primary/10 mr-3">
              <FileText className="h-4 w-4 text-primary shrink-0" />
            </div>
            <span className="font-medium text-foreground/90">Procurement Initiation Documents</span>
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={addFile}
            className="gap-2 hover:bg-primary/5 hover:text-primary transition-colors"
          >
            <Plus className="h-4 w-4 shrink-0" />
            Add Document
          </Button>
        </div>

        <div className="space-y-8">
          {fileIndices.map((index) => (
            <div key={index}
              className={cn(
                "relative border-2 rounded-xl p-6 transition-all duration-300",
                isDragging
                  ? "border-primary/50 bg-primary/5 ring-2 ring-primary/20"
                  : "border-border/50 hover:border-border/80 hover:shadow-sm",
                hasError(`files.${index}`) && "border-destructive/50 bg-destructive/5 ring-2 ring-destructive/10"
              )}
            >
              <div className="flex items-center justify-between mb-6">
                <Badge variant="outline" className="bg-background/80 backdrop-blur px-4 py-1.5 rounded-lg font-medium text-sm shadow-sm">
                  Document {index + 1}
                </Badge>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => removeFile(index)}
                  className="text-muted-foreground hover:text-destructive transition-colors gap-2"
                >
                  <Trash2 className="h-4 w-4 shrink-0" />
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
                    hasError(`files.${index}`) && "border-destructive/50 bg-destructive/5 ring-destructive/10"
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
                    accept=".pdf,application/pdf"
                    aria-label={`Upload document ${index + 1}`}
                  />

                  <div className="p-8">
                    {data.files[index] ? (
                      <div className="flex items-start gap-4">
                        <div className={cn(
                          "rounded-xl p-3",
                          isPdfFile(data.files[index])
                            ? "bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400"
                            : "bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400"
                        )}>
                          {isPdfFile(data.files[index])
                            ? <FileCheck className="h-6 w-6 shrink-0" />
                            : <FileX className="h-6 w-6 shrink-0" />
                          }
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="font-medium truncate text-foreground/90">
                            {data.files[index].name}
                          </p>
                          <p className="text-sm text-muted-foreground mt-1.5">
                            {formatBytes(data.files[index].size)} •
                            {isPdfFile(data.files[index])
                              ? " PDF Document"
                              : " Invalid file type"
                            }
                          </p>
                          {!isPdfFile(data.files[index]) && (
                            <p className="text-sm text-red-600 dark:text-red-400 mt-3 flex items-center gap-2">
                              <FileX className="h-4 w-4 shrink-0" />
                              Only PDF files are accepted
                            </p>
                          )}
                        </div>
                      </div>
                    ) : (
                      <div className="text-center">
                        <div className="mx-auto w-14 h-14 rounded-xl bg-primary/10 flex items-center justify-center mb-4">
                          <Upload className="h-7 w-7 text-primary" />
                        </div>
                        <h4 className="font-medium text-lg tracking-tight">
                          {isDragging ? "Drop PDF file here" : "Drop PDF file or click to upload"}
                        </h4>
                        <p className="text-sm text-muted-foreground/90 mt-2">
                          PDF files only, up to 10MB
                        </p>
                      </div>
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 animate-in slide-in-from-top duration-300">
                  <div className="space-y-5">
                    <div className="space-y-3">
                      <Label htmlFor={`document_type_${index}`} className="flex items-center gap-2 text-sm font-medium text-foreground/90">
                        Document Type
                        <Badge variant="destructive" className="text-[10px] px-2 py-0.5 rounded font-medium">Required</Badge>
                      </Label>
                      <div className="relative">
                        <FileText className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground/70 shrink-0" />
                        <Input
                          id={`document_type_${index}`}
                          value={data.metadata[index]?.document_type || ''}
                          onChange={(e) => handleMetadataChange(index, 'document_type', e.target.value)}
                          className="pl-10 h-11 transition-shadow duration-300 font-medium tracking-tight hover:shadow-sm focus:shadow-md"
                          placeholder="e.g., Purchase Request"
                        />
                      </div>
                    </div>

                    <div className="space-y-3">
                      <Label htmlFor={`municipal_office_${index}`} className="flex items-center gap-2 text-sm font-medium text-foreground/90">
                        Municipal Office
                        <Badge variant="destructive" className="text-[10px] px-2 py-0.5 rounded font-medium">Required</Badge>
                      </Label>
                      <Select
                        value={data.metadata[index]?.municipal_offices || ''}
                        onValueChange={(value) => handleMetadataChange(index, 'municipal_offices', value)}
                      >
                        <SelectTrigger id={`municipal_office_${index}`} className="w-full h-11 transition-shadow duration-300 hover:shadow-sm focus:shadow-md">
                          <div className="flex items-center gap-2">
                            <Building2 className="h-4 w-4 text-muted-foreground/70 shrink-0" />
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
                      <Label htmlFor={`submission_date_${index}`} className="flex items-center gap-2 text-sm font-medium text-foreground/90">
                        Submission Date
                        <Badge variant="destructive" className="text-[10px] px-2 py-0.5 rounded font-medium">Required</Badge>
                      </Label>
                      <div className="relative">
                        <Popover>
                          <PopoverTrigger asChild>
                            <Button
                              variant="outline"
                              role="combobox"
                              className={cn(
                                "w-full justify-start text-left h-11 font-medium tracking-tight transition-shadow duration-300 hover:shadow-sm focus:shadow-md",
                                !dates[index] && "text-muted-foreground"
                              )}
                            >
                              <CalendarIcon className="mr-2 h-4 w-4 shrink-0 text-muted-foreground/70" />
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
                              className="rounded-md border shadow-md"
                            />
                          </PopoverContent>
                        </Popover>
                      </div>
                      {hasError(`metadata.${index}.submission_date`) && (
                        <p className="text-sm text-destructive flex items-center gap-2">
                          <AlertCircle className="h-4 w-4 shrink-0" />
                          Please select a submission date
                        </p>
                      )}
                    </div>

                    <div className="space-y-3">
                      <Label htmlFor={`signatory_${index}`} className="flex items-center gap-2 text-sm font-medium text-foreground/90">
                        Signatory Details
                        <Badge variant="destructive" className="text-[10px] px-2 py-0.5 rounded font-medium">Required</Badge>
                      </Label>
                      <div className="relative">
                        <User2 className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground/70 shrink-0" />
                        <Input
                          id={`signatory_${index}`}
                          value={data.metadata[index]?.signatory_details || ''}
                          onChange={(e) => handleMetadataChange(index, 'signatory_details', e.target.value)}
                          className="pl-10 h-11 transition-shadow duration-300 font-medium tracking-tight hover:shadow-sm focus:shadow-md"
                          placeholder="Name and position"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
