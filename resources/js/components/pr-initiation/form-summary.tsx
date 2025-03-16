import { CheckCircle, FileCheck, Files, Edit, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface PRMetadata {
  document_type?: string;
  submission_date?: string;
  municipal_offices?: string;
}

interface SupportingMetadata {
  document_type?: string;
}

interface FormData {
  procurement_id?: string;
  procurement_title?: string;
  pr_file?: File;
  pr_metadata: PRMetadata;
  supporting_files: (File | null)[];
  supporting_metadata: SupportingMetadata[];
}

interface FormCompletionState {
  details: boolean;
  prDocument: boolean;
  supporting: boolean;
}

export interface FormSummaryProps {
  data: FormData;
  setCurrentStep: (step: number) => void;
  formCompletion: FormCompletionState;
  addSupportingFile: () => void;
}

export function FormSummary({ data, setCurrentStep, formCompletion, addSupportingFile }: FormSummaryProps) {
  const formatBytes = (bytes: number, decimals = 2) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
  };

  return (
    <div className="space-y-4">
      <h2 className="text-xl font-semibold mb-2">Form Summary</h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Procurement Details Summary */}
        <Card className="border-sidebar-border/70 dark:border-sidebar-border">
          <CardHeader className="border-b pb-3">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2 text-lg">
                  <span>Procurement Details</span>
                  {formCompletion.details && (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  )}
                </CardTitle>
                <CardDescription>Basic procurement information</CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setCurrentStep(1)}
                className="text-primary gap-1.5"
              >
                <Edit className="h-3.5 w-3.5" />
                <span>Edit</span>
              </Button>
            </div>
          </CardHeader>
          <CardContent className="p-4">
            <dl className="divide-y divide-border text-sm">
              <div className="py-2 flex flex-col sm:flex-row sm:gap-4">
                <dt className="font-medium sm:w-1/3">Procurement ID</dt>
                <dd className="text-muted-foreground mt-1 sm:mt-0 sm:w-2/3">
                  {data.procurement_id || <span className="text-muted italic">Not provided</span>}
                </dd>
              </div>
              <div className="py-2 flex flex-col sm:flex-row sm:gap-4">
                <dt className="font-medium sm:w-1/3">Procurement Title</dt>
                <dd className="text-muted-foreground mt-1 sm:mt-0 sm:w-2/3">
                  {data.procurement_title || <span className="text-muted italic">Not provided</span>}
                </dd>
              </div>
            </dl>
          </CardContent>
        </Card>

        {/* PR Document Summary */}
        <Card className="border-sidebar-border/70 dark:border-sidebar-border">
          <CardHeader className="border-b pb-3">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2 text-lg">
                  <span>PR Document</span>
                  {formCompletion.prDocument && (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  )}
                </CardTitle>
                <CardDescription>Purchase Request document</CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setCurrentStep(2)}
                className="text-primary gap-1.5"
              >
                <Edit className="h-3.5 w-3.5" />
                <span>Edit</span>
              </Button>
            </div>
          </CardHeader>
          <CardContent className="p-4">
            {data.pr_file ? (
              <div className="rounded-md border p-3">
                <div className="flex items-center gap-3">
                  <div className="bg-primary/10 rounded-md p-2">
                    <FileCheck className="h-5 w-5 text-primary" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium truncate">{data.pr_file.name}</p>
                    <p className="text-xs text-muted-foreground">
                      {formatBytes(data.pr_file.size)} • {data.pr_file.type || 'Unknown type'}
                    </p>
                  </div>
                </div>

                <div className="mt-3 grid grid-cols-1 gap-2 text-sm">
                  <div className="flex items-center gap-2">
                    <Badge variant="outline" className="text-xs">
                      {data.pr_metadata.document_type || 'PR'}
                    </Badge>
                    <span className="text-muted-foreground">
                      {data.pr_metadata.submission_date || 'No date specified'}
                    </span>
                  </div>
                  {data.pr_metadata.municipal_offices && (
                    <div className="text-muted-foreground truncate">
                      Office: {data.pr_metadata.municipal_offices}
                    </div>
                  )}
                </div>
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center p-6 text-center">
                <FileCheck className="h-8 w-8 text-muted mb-2" />
                <p className="text-muted-foreground">No PR document uploaded</p>
                <Button
                  variant="link"
                  onClick={() => setCurrentStep(2)}
                  className="mt-2"
                >
                  Upload Document
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Supporting Documents Summary */}
      <Card className="border-sidebar-border/70 dark:border-sidebar-border">
        <CardHeader className="border-b pb-3">
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2 text-lg">
                <span>Supporting Documents</span>
                {formCompletion.supporting && (
                  <CheckCircle className="h-4 w-4 text-green-500" />
                )}
              </CardTitle>
              <CardDescription>
                {data.supporting_files.length > 0
                  ? `${data.supporting_files.filter(Boolean).length} document${data.supporting_files.filter(Boolean).length !== 1 ? 's' : ''} attached`
                  : 'Optional supporting files'}
              </CardDescription>
            </div>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={addSupportingFile}
                className="gap-1.5"
              >
                <Plus className="h-3.5 w-3.5" />
                <span>Add File</span>
              </Button>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setCurrentStep(3)}
                className="text-primary gap-1.5"
              >
                <Edit className="h-3.5 w-3.5" />
                <span>Edit</span>
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent className="p-4">
          {data.supporting_files.length > 0 ? (
            <div className="space-y-3">
              {data.supporting_files.map((file: File | null, index: number) => (
                file && (
                  <div key={index} className="rounded-md border p-3">
                    <div className="flex items-center gap-3">
                      <div className="bg-primary/10 rounded-md p-2">
                        <Files className="h-5 w-5 text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium truncate">{file.name}</p>
                        <p className="text-xs text-muted-foreground">
                          {formatBytes(file.size)} • {file.type || 'Unknown type'}
                        </p>
                      </div>
                    </div>

                    {data.supporting_metadata[index] && data.supporting_metadata[index].document_type && (
                      <div className="mt-2 text-sm">
                        <Badge variant="outline" className="text-xs">
                          {data.supporting_metadata[index].document_type}
                        </Badge>
                      </div>
                    )}
                  </div>
                )
              ))}
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center p-6 text-center">
              <Files className="h-8 w-8 text-muted mb-2" />
              <p className="text-muted-foreground">No supporting documents attached</p>
              <Button
                variant="link"
                onClick={addSupportingFile}
                className="mt-2"
              >
                Add Supporting Document
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
