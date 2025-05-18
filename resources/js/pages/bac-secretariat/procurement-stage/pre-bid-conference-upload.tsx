import { Head, useForm } from '@inertiajs/react';
import { FileUp, FileText, X, ClipboardList, CalendarIcon, Users, Upload, Eye } from 'lucide-react';
import { useState } from 'react';
import { format } from 'date-fns';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { Popover, PopoverTrigger, PopoverContent } from '@/components/ui/popover';
import { Calendar as UICalendar } from '@/components/ui/calendar';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

interface PreBidConferenceUploadProps {
  procurement: {
    id: string;
    title: string;
  };
  errors?: {
    minutes_file?: string;
    attendance_file?: string;
    meeting_date?: string;
    participants?: string;
  };
}

export default function PreBidConferenceUpload({ procurement, errors = {} }: PreBidConferenceUploadProps) {
  const [isDraggingMinutes, setIsDraggingMinutes] = useState(false);
  const [isDraggingAttendance, setIsDraggingAttendance] = useState(false);
  const [evaluatorInput, setEvaluatorInput] = useState('');
  const [evaluators, setEvaluators] = useState<string[]>([]);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);

  const { data, setData, post, processing } = useForm<{
    procurement_id: string;
    procurement_title: string;
    minutes_file: File | null;
    attendance_file: File | null;
    meeting_date: string;
    participants: string;
  }>({
    procurement_id: procurement.id,
    procurement_title: procurement.title,
    minutes_file: null,
    attendance_file: null,
    meeting_date: format(new Date(), 'yyyy-MM-dd'),
    participants: '',
  });

  const handleEvaluatorInput = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && evaluatorInput.trim()) {
      e.preventDefault();
      const newEvaluator = evaluatorInput.trim();
      if (!evaluators.includes(newEvaluator)) {
        setEvaluators([...evaluators, newEvaluator]);
        setData('participants', [...evaluators, newEvaluator].join(', '));
      }
      setEvaluatorInput('');
    }
  };

  const removeEvaluator = (index: number) => {
    const newEvaluators = evaluators.filter((_, i) => i !== index);
    setEvaluators(newEvaluators);
    setData('participants', newEvaluators.join(', '));
  };

  const breadcrumbs = [
    { title: 'Dashboard', href: '/bac-secretariat/procurements' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Pre-Bid Conference Documents - ${procurement.id}: ${procurement.title}`, href: '#' },
  ];

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    post('/bac-secretariat/upload-pre-bid-conference-documents', {
      preserveScroll: true,
      preserveState: true,
      forceFormData: true,
      onSuccess: () => {
        toast.success('Pre-bid conference documents uploaded successfully!', {
          description: 'The documents have been submitted.',
        });
      },
      onError: (errors) => {
        toast.error('Failed to upload pre-bid conference documents', {
          description: Object.values(errors)[0] as string,
        });
      },
    });
  };

  const handleFileChange = (field: 'minutes_file' | 'attendance_file') => (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      setData(field, file);
    }
  };

  const handleFileDrop = (field: 'minutes_file' | 'attendance_file') => (e: React.DragEvent) => {
    e.preventDefault();
    setIsDraggingMinutes(false);
    setIsDraggingAttendance(false);

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'application/pdf') {
        setData(field, file);
      } else {
        toast.error('Invalid file type', {
          description: 'Please upload a PDF file',
        });
      }
    }
  };

  const handleFilePreview = (file: File | null) => {
    if (!file) return;
    const url = URL.createObjectURL(file);
    setPreviewUrl(url);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Pre-Bid Conference Documents" />

      <div className="flex h-full flex-1 flex-col gap-4 sm:gap-6 p-4 sm:p-6 rounded-xl bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <Users className="h-5 w-5 sm:h-6 sm:w-6" />
            <h1 className="text-xl sm:text-2xl font-bold">Pre-Bid Conference Documents</h1>
          </div>
          <p className="text-sm sm:text-base text-muted-foreground max-w-3xl">
            Upload the pre-bid conference documents for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <form onSubmit={onSubmit} className="space-y-4 sm:space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-3 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <ClipboardList className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription className="text-sm">
                  Please upload the minutes and attendance files in PDF format
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-6 sm:space-y-8">
                {/* Minutes File Upload */}
                <div className="space-y-2">
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div className="flex items-center text-base font-medium">
                      <ClipboardList className="h-4 w-4 mr-2" />
                      Minutes File
                    </div>
                    {data.minutes_file && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => handleFilePreview(data.minutes_file)}
                        className="text-primary w-full sm:w-auto"
                      >
                        <Eye className="h-4 w-4 mr-2" />
                        Preview
                      </Button>
                    )}
                  </div>
                  <div
                    className={`relative border-2 border-dashed rounded-lg p-4 sm:p-6 transition-all duration-200 min-h-[180px] sm:min-h-[220px] flex flex-col justify-center ${isDraggingMinutes
                      ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                      : data.minutes_file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : errors.minutes_file
                          ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                          : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                    onDragEnter={(e) => { e.preventDefault(); setIsDraggingMinutes(true); }}
                    onDragLeave={(e) => { e.preventDefault(); setIsDraggingMinutes(false); }}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={handleFileDrop('minutes_file')}
                    onClick={() => document.getElementById('minutes-file-input')?.click()}
                  >
                    {!data.minutes_file ? (
                      <div className="flex flex-col items-center justify-center text-center px-2 sm:px-4">
                        <div className="rounded-full bg-muted p-2 sm:p-3 mb-2 sm:mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-5 w-5 sm:h-6 sm:w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-sm sm:text-base text-muted-foreground mb-1 sm:mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your minutes file here
                        </p>
                        <p className="text-xs sm:text-sm text-muted-foreground/70 mb-4 sm:mb-5">
                          Only PDF files are supported
                        </p>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="group-hover:bg-primary/5 transition-colors"
                          onClick={(e) => {
                            e.stopPropagation();
                            document.getElementById('minutes-file-input')?.click();
                          }}
                        >
                          Browse Files
                        </Button>
                        <input
                          id="minutes-file-input"
                          type="file"
                          accept="application/pdf"
                          className="hidden"
                          onChange={handleFileChange('minutes_file')}
                        />
                      </div>
                    ) : (
                      <div className="flex items-center justify-between gap-3 p-2">                        <div className="flex items-center min-w-0">
                        <div className="rounded-full bg-primary/10 p-2 sm:p-3 mr-3 sm:mr-4 flex-shrink-0">
                          <FileText className="h-5 w-5 sm:h-6 sm:w-6 text-primary" />
                        </div>
                        <div className="min-w-0">
                          <p className="font-medium text-sm sm:text-base truncate">{data.minutes_file.name}</p>
                          <p className="text-xs sm:text-sm text-muted-foreground truncate">
                            {(data.minutes_file.size / 1024).toFixed(2)} KB • PDF
                          </p>
                        </div>
                      </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors flex-shrink-0"
                          onClick={(e) => {
                            e.stopPropagation();
                            setData('minutes_file', null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {errors.minutes_file && (
                    <InputError message={errors.minutes_file} />
                  )}
                </div>

                {/* Attendance File Upload */}
                <div className="space-y-2">
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div className="flex items-center text-base font-medium">
                      <Users className="h-4 w-4 mr-2" />
                      Attendance File
                    </div>
                    {data.attendance_file && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => handleFilePreview(data.attendance_file)}
                        className="text-primary w-full sm:w-auto"
                      >
                        <Eye className="h-4 w-4 mr-2" />
                        Preview
                      </Button>
                    )}
                  </div>
                  <div
                    className={`relative border-2 border-dashed rounded-lg p-4 sm:p-6 transition-all duration-200 min-h-[180px] sm:min-h-[220px] flex flex-col justify-center ${isDraggingAttendance
                      ? 'border-primary bg-primary/5 scale-[1.01] shadow-md'
                      : data.attendance_file
                        ? 'border-green-500/50 bg-green-50 dark:bg-green-900/20'
                        : errors.attendance_file
                          ? 'border-destructive/50 bg-destructive/5 dark:bg-destructive/10'
                          : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                      } cursor-pointer group`}
                    onDragEnter={(e) => { e.preventDefault(); setIsDraggingAttendance(true); }}
                    onDragLeave={(e) => { e.preventDefault(); setIsDraggingAttendance(false); }}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={handleFileDrop('attendance_file')}
                    onClick={() => document.getElementById('attendance-file-input')?.click()}
                  >
                    {!data.attendance_file ? (
                      <div className="flex flex-col items-center justify-center text-center px-2 sm:px-4">
                        <div className="rounded-full bg-muted p-2 sm:p-3 mb-2 sm:mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your attendance file here
                        </p>
                        <p className="text-xs sm:text-sm text-muted-foreground/70 mb-4 sm:mb-5">
                          Only PDF files are supported
                        </p>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="group-hover:bg-primary/5 transition-colors"
                          onClick={(e) => {
                            e.stopPropagation();
                            document.getElementById('attendance-file-input')?.click();
                          }}
                        >
                          Browse Files
                        </Button>
                        <input
                          id="attendance-file-input"
                          type="file"
                          accept="application/pdf"
                          className="hidden"
                          onChange={handleFileChange('attendance_file')}
                        />
                      </div>
                    ) : (
                      <div className="flex items-center justify-between gap-3 p-2">                        <div className="flex items-center min-w-0">
                        <div className="rounded-full bg-primary/10 p-2 sm:p-3 mr-3 sm:mr-4 flex-shrink-0">
                          <FileText className="h-6 w-6 text-primary" />
                        </div>
                        <div className="min-w-0">
                          <p className="font-medium text-sm sm:text-base truncate">{data.attendance_file.name}</p>
                          <p className="text-xs sm:text-sm text-muted-foreground truncate">
                            {(data.attendance_file.size / 1024).toFixed(2)} KB • PDF
                          </p>
                        </div>
                      </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors flex-shrink-0"
                          onClick={(e) => {
                            e.stopPropagation();
                            setData('attendance_file', null);
                          }}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {errors.attendance_file && (
                    <InputError message={errors.attendance_file} />
                  )}
                </div>
              </CardContent>
            </Card>

            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-3 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Conference Details
                </CardTitle>
                <CardDescription className="text-sm">
                  Provide details about the pre-bid conference
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-4 sm:space-y-6">
                <div className="space-y-2">
                  <label className="text-sm font-medium flex items-center gap-2">
                    <CalendarIcon className="h-4 w-4" />
                    Meeting Date
                  </label>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button variant="outline" className="w-full justify-start text-left text-sm sm:text-base h-9 sm:h-10">
                        {data.meeting_date
                          ? format(new Date(data.meeting_date), 'PPP')
                          : 'Pick a date'}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <UICalendar
                        mode="single"
                        selected={data.meeting_date ? new Date(data.meeting_date) : undefined}
                        onSelect={(date) => {
                          if (date) setData('meeting_date', format(date, 'yyyy-MM-dd'));
                        }}
                        initialFocus
                      />
                    </PopoverContent>
                  </Popover>
                  {errors.meeting_date && <InputError message={errors.meeting_date} />}
                </div>

                <div className="space-y-2">
                  <label htmlFor="participants" className="flex items-center text-base font-medium">
                    <Users className="h-4 w-4 mr-2" />
                    Evaluators
                  </label>
                  <div className="space-y-3">
                    <Input
                      id="evaluator-input"
                      value={evaluatorInput}
                      onChange={(e) => setEvaluatorInput(e.target.value)}
                      onKeyDown={handleEvaluatorInput}
                      placeholder="Type evaluator name and press Enter"
                      className="h-10"
                    />
                    <div className="flex flex-wrap gap-2">
                      {evaluators.map((evaluator, index) => (
                        <Badge
                          key={index}
                          variant="secondary"
                          className="flex items-center gap-1 py-1 px-2 text-xs sm:text-sm"
                        >
                          <Users className="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                          {evaluator}
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-4 w-4 sm:h-5 sm:w-5 hover:bg-destructive/10 hover:text-destructive ml-1 -mr-1"
                            onClick={() => removeEvaluator(index)}
                          >
                            <X className="h-3 w-3" />
                          </Button>
                        </Badge>
                      ))}
                    </div>
                  </div>
                  {errors.participants && (
                    <InputError message={errors.participants} />
                  )}
                </div>
              </CardContent>

              <CardFooter className="pt-4 border-t flex flex-col gap-3">
                <Button
                  type="submit"
                  disabled={processing}
                  className="w-full flex items-center gap-2 h-9 sm:h-11 text-sm sm:text-base"
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
                  className="w-full h-9 sm:h-10 text-sm sm:text-base"
                >
                  Cancel
                </Button>
              </CardFooter>
            </Card>
          </div>
        </form>

        {/* File Preview Modal */}
        {previewUrl && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
            <div className="bg-white rounded-lg shadow-lg max-w-lg w-full p-4 sm:p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg sm:text-xl font-semibold">File Preview</h3>
                <Button
                  variant="ghost"
                  size="icon"
                  className="rounded-full hover:bg-destructive/10 hover:text-destructive"
                  onClick={() => setPreviewUrl(null)}
                >
                  <X className="h-5 w-5" />
                </Button>
              </div>
              <div className="flex justify-center mb-4">
                <iframe
                  src={previewUrl}
                  className="w-full h-96 rounded-lg"
                  frameBorder="0"
                  allowFullScreen
                ></iframe>
              </div>
              <div className="flex justify-end gap-2">
                <Button
                  variant="outline"
                  onClick={() => setPreviewUrl(null)}
                  className="flex-1"
                >
                  Close
                </Button>
              </div>
            </div>
          </div>
        )}

        {/* PDF Preview Modal */}
        {previewUrl && (
          <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2 sm:p-4">
            <div className="bg-background rounded-lg p-3 sm:p-4 w-full h-[90vh] sm:w-[90vw] sm:h-[90vh] flex flex-col max-w-7xl mx-auto">
              <div className="flex items-center justify-between mb-2 sm:mb-4">
                <h3 className="text-base sm:text-lg font-semibold">Document Preview</h3>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => {
                    URL.revokeObjectURL(previewUrl);
                    setPreviewUrl(null);
                  }}
                  className="hover:bg-destructive/10 hover:text-destructive transition-colors"
                >
                  <X className="h-4 w-4 sm:h-5 sm:w-5" />
                </Button>
              </div>
              <div className="flex-1 bg-muted rounded-md overflow-hidden">
                <iframe
                  src={previewUrl}
                  className="w-full h-full"
                  title="PDF Preview"
                />
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
