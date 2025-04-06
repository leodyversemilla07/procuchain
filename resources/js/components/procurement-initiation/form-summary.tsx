import { CheckCircle, Files, Edit, FileClock, Building2, User2, Calendar } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';

interface Metadata {
  document_type?: string;
  submission_date?: string;
  municipal_offices?: string;
  signatory_details?: string;
}

interface FormData {
  procurement_id?: string;
  procurement_title?: string;
  files?: (File | undefined)[];
  metadata?: Metadata[];
}

interface FormCompletionState {
  details: boolean;
  document?: boolean;
}

export interface FormSummaryProps {
  data: FormData;
  setCurrentStep?: (step: number) => void;
  formCompletion: FormCompletionState;
  addFile?: () => void;
}

export function FormSummary({ data, setCurrentStep, formCompletion, addFile }: FormSummaryProps) {
  const formatBytes = (bytes: number, decimals = 2) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
  };

  const handleEditClick = (step: number) => {
    if (setCurrentStep) {
      setCurrentStep(step);
    }
  };

  const procurementId = data.procurement_id || '';
  const procurementTitle = data.procurement_title || '';
  const files = data.files || [];
  const metadata = data.metadata?.slice(1) || data.metadata || [];

  const isDetailsComplete = formCompletion.details;
  const isDocumentsComplete = formCompletion.document;

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 className="text-2xl font-semibold">Review Procurement Details</h2>
        <Badge variant="outline" className={cn(
          "px-3 py-1.5",
          isDetailsComplete && isDocumentsComplete 
            ? "bg-green-50 text-green-700 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800"
            : "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800"
        )}>
          <div className="flex items-center gap-1.5">
            {isDetailsComplete && isDocumentsComplete ? (
              <CheckCircle className="h-4 w-4" />
            ) : (
              <FileClock className="h-4 w-4" />
            )}
            <span className="font-medium">
              {isDetailsComplete && isDocumentsComplete ? "Ready to Submit" : "Incomplete"}
            </span>
          </div>
        </Badge>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
          <CardHeader className="border-b pb-3">
            <div className="flex items-center justify-between">
              <div className="space-y-1">
                <CardTitle className="flex items-center gap-2 text-lg">
                  <span>Basic Information</span>
                  {isDetailsComplete && (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  )}
                </CardTitle>
                <CardDescription>
                  Core procurement identification details
                </CardDescription>
              </div>
              {setCurrentStep && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => handleEditClick(1)}
                  className="text-primary hover:text-primary/90 gap-1.5"
                >
                  <Edit className="h-3.5 w-3.5" />
                  <span>Edit</span>
                </Button>
              )}
            </div>
          </CardHeader>
          <CardContent className="p-4 space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <div className="flex items-center gap-2 text-muted-foreground">
                  <Building2 className="h-4 w-4" />
                  <span className="text-sm font-medium">Procurement ID</span>
                </div>
                <p className="font-semibold">{procurementId || "Not provided"}</p>
              </div>
              <div className="space-y-1.5">
                <div className="flex items-center gap-2 text-muted-foreground">
                  <Files className="h-4 w-4" />
                  <span className="text-sm font-medium">Title</span>
                </div>
                <p className="font-semibold">{procurementTitle || "Not provided"}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
          <CardHeader className="border-b pb-3">
            <div className="flex items-center justify-between">
              <div className="space-y-1">
                <CardTitle className="flex items-center gap-2 text-lg">
                  <span>Documents & Files</span>
                  {formCompletion.document && (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  )}
                </CardTitle>
                <CardDescription>
                  {files.length > 0
                    ? `${files.filter(Boolean).length} document${files.filter(Boolean).length !== 1 ? 's' : ''} attached`
                    : 'No documents attached yet'}
                </CardDescription>
              </div>
              <div className="flex gap-2">
                {setCurrentStep && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleEditClick(2)}
                    className="text-primary hover:text-primary/90 gap-1.5"
                  >
                    <Edit className="h-3.5 w-3.5" />
                    <span>Edit</span>
                  </Button>
                )}
              </div>
            </div>
          </CardHeader>
          <CardContent className="p-4">
            {files.length > 0 ? (
              <ScrollArea className="h-[240px] pr-4">
                <div className="space-y-4">
                  {files.map((file, index) => file && (
                    <div key={index} className="relative">
                      {index > 0 && <Separator className="my-4" />}
                      <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-start gap-3">
                          <div className="bg-primary/10 rounded-md p-2">
                            <Files className="h-5 w-5 text-primary" />
                          </div>
                          <div className="flex-1 space-y-1">
                            <p className="font-medium">{file.name}</p>
                            <p className="text-xs text-muted-foreground">
                              {formatBytes(file.size)} • {file.type || 'Unknown type'}
                            </p>
                          </div>
                        </div>

                        {metadata[index] && (
                          <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div className="space-y-1">
                              <div className="flex items-center gap-2 text-muted-foreground">
                                <FileClock className="h-4 w-4" />
                                <span>Document Type</span>
                              </div>
                              <p className="font-medium">{metadata[index].document_type || "Not specified"}</p>
                            </div>
                            <div className="space-y-1">
                              <div className="flex items-center gap-2 text-muted-foreground">
                                <Building2 className="h-4 w-4" />
                                <span>Office</span>
                              </div>
                              <p className="font-medium">{metadata[index].municipal_offices || "Not specified"}</p>
                            </div>
                            <div className="space-y-1">
                              <div className="flex items-center gap-2 text-muted-foreground">
                                <Calendar className="h-4 w-4" />
                                <span>Date</span>
                              </div>
                              <p className="font-medium">{metadata[index].submission_date || "Not specified"}</p>
                            </div>
                            <div className="space-y-1">
                              <div className="flex items-center gap-2 text-muted-foreground">
                                <User2 className="h-4 w-4" />
                                <span>Signatory</span>
                              </div>
                              <p className="font-medium">{metadata[index].signatory_details || "Not specified"}</p>
                            </div>
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </ScrollArea>
            ) : (
              <div className="flex flex-col items-center justify-center p-6 text-center">
                <Files className="h-8 w-8 text-muted-foreground mb-2" />
                <p className="text-muted-foreground mb-4">No documents attached yet</p>
                {addFile && (
                  <Button
                    variant="outline"
                    onClick={addFile}
                    className="gap-2"
                  >
                    <Files className="h-4 w-4" />
                    Add Document
                  </Button>
                )}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
