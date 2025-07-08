import { Head, useForm } from '@inertiajs/react';
import { ClipboardList, CalendarIcon, Users, Upload } from 'lucide-react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import FileUploadArea from '@/components/file-upload-area';
import { useFileDrop } from '@/hooks/use-file-drop';
import DatePicker from '@/components/date-picker';
import PeopleInput from '@/components/people-input';

// Allowed file types and max file size for uploads
const ALLOWED_FILE_TYPES = ['application/pdf'];
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

interface PreBidConferenceUploadProps {
  procurement: {
    id: string;
    title: string;
  };
}

export default function PreBidConferenceUpload({ procurement = { id: '', title: '' } }: PreBidConferenceUploadProps) {
  const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
    procurement_id: procurement?.id || '',
    procurement_title: procurement?.title || '',
    minutes_file: null as File | null,
    attendance_file: null as File | null,
    meeting_date: new Date(),
    participants: [] as Array<{ name: string; affiliation: string }>,
  });

  // File validation
  const validateFile = (file: File) => {
    if (!ALLOWED_FILE_TYPES.includes(file.type)) {
      toast.error('Invalid file type', {
        description: 'Only PDF files are allowed.',
      });
      return false;
    }
    if (file.size > MAX_FILE_SIZE) {
      toast.error('File too large', {
        description: 'Maximum file size is 10MB.',
      });
      return false;
    }
    return true;
  };

  const breadcrumbs = [
    { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
    { title: 'Procurements List', href: '/bac-secretariat/procurements-list' },
    { title: `Upload Pre-Bid Conference Documents - ${procurement.id}: ${procurement.title}`, href: '#' },
  ];

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!data.minutes_file || !data.attendance_file) {
      toast.error('Missing files', {
        description: 'Please upload both minutes and attendance files.'
      });
      return;
    }

    if (!Array.isArray(data.participants) || data.participants.length === 0) {
      toast.error('Missing participants', {
        description: 'Please add at least one participant.'
      });
      return;
    }

    post('/bac-secretariat/upload-pre-bid-conference-documents', {
      preserveScroll: true,
      preserveState: true,
      forceFormData: true,
      onSuccess: () => {
        toast.success('Pre-bid conference documents uploaded successfully!', {
          description: 'The documents have been submitted.',
        });
        reset();
        clearErrors();
      },
      onError: (errors) => {
        toast.error('Failed to upload pre-bid conference documents', {
          description: Object.values(errors)[0] as string,
        });
      },
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, field: 'minutes_file' | 'attendance_file') => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      if (validateFile(file)) {
        setData(field, file);
      }
    }
  };

  const handleDateSelect = (date: Date | undefined) => {
    if (date) {
      setData('meeting_date', date);
    }
  };

  // Use custom hook for minutes file
  const minutesDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('minutes_file', file),
  });
  // Use custom hook for attendance file
  const attendanceDrop = useFileDrop({
    validateFile,
    setFile: (file) => setData('attendance_file', file),
  });

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload Pre-Bid Conference Documents" />
      <div className="flex h-full flex-1 flex-col gap-4 sm:gap-6 rounded-xl p-3 sm:p-6 bg-gradient-to-b from-background to-muted/20">
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
              <CardHeader className="pb-2 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <ClipboardList className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Required Documents
                </CardTitle>
                <CardDescription className="text-sm">
                  Please upload the minutes and attendance files in PDF format
                </CardDescription>
              </CardHeader>

              <CardContent className="space-y-6 sm:space-y-8">
                <FileUploadArea
                  label="Minutes of Pre-Bid Conference"
                  file={data.minutes_file}
                  error={errors.minutes_file}
                  isDragging={minutesDrop.isDragging}
                  onFileChange={e => handleFileChange(e, 'minutes_file')}
                  onDragEnter={minutesDrop.handleDragEnter}
                  onDragLeave={minutesDrop.handleDragLeave}
                  onDragOver={minutesDrop.handleDragOver}
                  onDrop={minutesDrop.handleDrop}
                  onRemove={() => setData('minutes_file', null)}
                  inputId="minutes-file-input"
                  required={true}
                />
                <FileUploadArea
                  label="Attendance Sheet"
                  file={data.attendance_file}
                  error={errors.attendance_file}
                  isDragging={attendanceDrop.isDragging}
                  onFileChange={e => handleFileChange(e, 'attendance_file')}
                  onDragEnter={attendanceDrop.handleDragEnter}
                  onDragLeave={attendanceDrop.handleDragLeave}
                  onDragOver={attendanceDrop.handleDragOver}
                  onDrop={attendanceDrop.handleDrop}
                  onRemove={() => setData('attendance_file', null)}
                  inputId="attendance-file-input"
                  required={true}
                />
              </CardContent>
            </Card>

            <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md h-fit">
              <CardHeader className="pb-2 sm:pb-4 space-y-1">
                <CardTitle className="text-lg sm:text-xl font-semibold flex items-center gap-2">
                  <CalendarIcon className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
                  Meeting Details
                </CardTitle>
                <CardDescription className="text-sm">
                  Provide information about the conference
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 sm:space-y-6">
                <DatePicker
                  label="Meeting Date"
                  value={data.meeting_date instanceof Date ? data.meeting_date : new Date(data.meeting_date)}
                  onChange={handleDateSelect}
                  error={errors.meeting_date}
                  required
                />
                <PeopleInput
                  label="Participants"
                  value={data.participants}
                  onChange={updated => setData('participants', updated)}
                  error={errors.participants}
                  required
                  affiliationType="organization"
                  namePlaceholder="Enter participant name"
                />
              </CardContent>
              <CardFooter className="pt-4 border-t flex flex-col gap-3">
                <Button
                  type="submit"
                  disabled={processing}
                  className="w-full flex items-center gap-2 h-10 sm:h-11"
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
