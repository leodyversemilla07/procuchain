import { CheckCircle, Files, Edit, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

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

  // Function to handle edit button click based on whether we have steps
  const handleEditClick = (step: number) => {
    if (setCurrentStep) {
      setCurrentStep(step);
    }
  };

  // Use either the new data structure or the old one
  const procurementId = data.procurement_id || '';
  const procurementTitle = data.procurement_title || '';
  const files = data.files || [];
  const metadata = data.metadata?.slice(1) || data.metadata || [];

  // Function to add a document
  const handleAddDocument = () => {
    if (addFile) {
      addFile();
    }
  };

  // Check completion field
  const isDetailsComplete = formCompletion.details;

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
                  {isDetailsComplete && (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  )}
                </CardTitle>
                <CardDescription>Basic procurement information</CardDescription>
              </div>
              {setCurrentStep && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => handleEditClick(1)}
                  className="text-primary gap-1.5"
                >
                  <Edit className="h-3.5 w-3.5" />
                  <span>Edit</span>
                </Button>
              )}
            </div>
          </CardHeader>
          <CardContent className="p-4">
            <dl className="divide-y divide-border text-sm">
              <div className="py-2 flex flex-col sm:flex-row sm:gap-4">
                <dt className="font-medium sm:w-1/3">Procurement ID</dt>
                <dd className="text-muted-foreground mt-1 sm:mt-0 sm:w-2/3">
                  {procurementId || <span className="text-muted italic">Not provided</span>}
                </dd>
              </div>
              <div className="py-2 flex flex-col sm:flex-row sm:gap-4">
                <dt className="font-medium sm:w-1/3">Procurement Title</dt>
                <dd className="text-muted-foreground mt-1 sm:mt-0 sm:w-2/3">
                  {procurementTitle || <span className="text-muted italic">Not provided</span>}
                </dd>
              </div>
            </dl>
          </CardContent>
        </Card>

        <Card className="border-sidebar-border/70 dark:border-sidebar-border">
          <CardHeader className="border-b pb-3">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2 text-lg">
                  <span>Documents</span>
                  {formCompletion.document && (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  )}
                </CardTitle>
                <CardDescription>
                  {files.length > 0
                    ? `${files.filter(Boolean).length} document${files.filter(Boolean).length !== 1 ? 's' : ''} attached`
                    : 'Optional supporting files'}
                </CardDescription>
              </div>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleAddDocument}
                  className="gap-1.5"
                >
                  <Plus className="h-3.5 w-3.5" />
                  <span>Add File</span>
                </Button>
                {setCurrentStep && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleEditClick(3)}
                    className="text-primary gap-1.5"
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
              <div className="space-y-3">
                {files.map((file, index) => (
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

                      {metadata[index] && metadata[index].document_type && (
                        <div className="mt-2 text-sm">
                          <Badge variant="outline" className="text-xs">
                            {metadata[index].document_type}
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
                  onClick={handleAddDocument}
                  className="mt-2"
                >
                  Add Supporting Document
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
