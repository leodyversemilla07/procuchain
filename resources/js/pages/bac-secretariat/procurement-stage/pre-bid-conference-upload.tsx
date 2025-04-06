import { Head, useForm } from '@inertiajs/react';
import { FileUp, FileText, X, ClipboardList, CalendarIcon, Users, Upload } from 'lucide-react';
import { useState } from 'react';
import { format } from 'date-fns';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';

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

  const breadcrumbs = [
    { title: 'Procurements', href: '/bac-secretariat/procurements' },
    { title: `Procurement ${procurement.id}`, href: `/bac-secretariat/procurements/${procurement.id}` },
    { title: `Upload Pre-Bid Conference Documents - ${procurement.id}`, href: '#' },
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

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Pre-Bid Conference Documents" />

      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 text-primary">
            <Users className="h-6 w-6" />
            <h1 className="text-2xl font-bold">Pre-Bid Conference Documents</h1>
          </div>
          <p className="text-muted-foreground max-w-3xl">
            Upload the pre-bid conference documents for procurement
            <span className="font-medium text-foreground"> #{procurement.id}</span>:
            <span className="font-medium text-foreground italic"> {procurement.title}</span>
          </p>
        </div>

        <form onSubmit={onSubmit} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md lg:col-span-2">
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <ClipboardList className="h-5 w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription>
                  Please upload the minutes and attendance files in PDF format
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-8">
                {/* Minutes File Upload */}
                <div className="space-y-2">
                  <label className="flex items-center text-base font-medium">
                    <ClipboardList className="h-4 w-4 mr-2" />
                    Minutes File
                  </label>
                  <div
                    className={`relative border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingMinutes
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
                      <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your minutes file here
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
                      <div className="flex items-center justify-between">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium">{data.minutes_file.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {(data.minutes_file.size / 1024).toFixed(2)} KB • PDF
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
                  <label className="flex items-center text-base font-medium">
                    <Users className="h-4 w-4 mr-2" />
                    Attendance File
                  </label>
                  <div
                    className={`relative border-2 border-dashed rounded-lg p-6 transition-all duration-200 min-h-[220px] flex flex-col justify-center ${isDraggingAttendance
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
                      <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-3 mb-3 group-hover:bg-primary/10 transition-colors">
                          <FileUp className="h-6 w-6 text-muted-foreground group-hover:text-primary transition-colors" />
                        </div>
                        <p className="font-medium text-muted-foreground mb-2 group-hover:text-foreground transition-colors">
                          Drag and drop your attendance file here
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
                      <div className="flex items-center justify-between">
                        <div className="flex items-center">
                          <div className="rounded-full bg-primary/10 p-3 mr-4">
                            <FileText className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <p className="font-medium">{data.attendance_file.name}</p>
                            <p className="text-sm text-muted-foreground">
                              {(data.attendance_file.size / 1024).toFixed(2)} KB • PDF
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
              <CardHeader className="pb-4 space-y-1">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-5 w-5 text-primary" />
                  Conference Details
                </CardTitle>
                <CardDescription>
                  Provide details about the pre-bid conference
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <label htmlFor="meeting_date" className="text-sm font-medium">
                    Meeting Date
                  </label>
                  <Input
                    id="meeting_date"
                    type="date"
                    value={data.meeting_date}
                    onChange={(e) => setData('meeting_date', e.target.value)}
                    max={new Date().toISOString().split('T')[0]}
                  />
                  {errors.meeting_date && (
                    <InputError message={errors.meeting_date} />
                  )}
                </div>

                <div className="space-y-2">
                  <label htmlFor="participants" className="text-sm font-medium">
                    Participants
                  </label>
                  <Textarea
                    id="participants"
                    value={data.participants}
                    onChange={(e) => setData('participants', e.target.value)}
                    placeholder="Enter the names of conference participants"
                    rows={4}
                  />
                  {errors.participants && (
                    <InputError message={errors.participants} />
                  )}
                </div>
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
      </div>
    </AppLayout>
  );
}