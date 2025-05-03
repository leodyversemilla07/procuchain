import React from 'react';
import { CheckCircle, File, FileText, Edit2, Upload, AlertTriangle, Calendar, Building, FileSignature } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

export interface FormSummaryProps {
  data: {
    procurement_id: string;
    procurement_title: string;
    files: (File | undefined)[];
    metadata: Array<{
      document_type: string;
      submission_date: string;
      municipal_offices: string;
      signatory_details: string;
    }>;
  };
  formCompletion: {
    details: boolean;
    document: boolean;
    documents: boolean;
  };
  addFile: () => void;
  setCurrentStep: (step: number) => void;
}

export function FormSummary({
  data,
  formCompletion,
  setCurrentStep,
}: FormSummaryProps) {
  const incompleteFields = [];

  if (!data.procurement_id || !data.procurement_title) {
    incompleteFields.push('Basic procurement details');
  }

  const hasDocuments = data.files.some((file) => file);
  if (!hasDocuments) {
    incompleteFields.push('Document uploads');
  }

  const allMetadataComplete = data.metadata.every(
    (meta) =>
      meta.document_type &&
      meta.submission_date &&
      meta.municipal_offices &&
      meta.signatory_details
  );

  if (!allMetadataComplete) {
    incompleteFields.push('Document metadata');
  }

  const allComplete = formCompletion.details && formCompletion.documents;

  return (
    <div className="space-y-4 sm:space-y-6 animate-fadeIn">
      <div>
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 mb-4 sm:mb-6">
          <h2 className="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">
            Review Procurement Details
          </h2>

          {!allComplete && (
            <div className="bg-amber-50 dark:bg-amber-900/20 py-1.5 sm:py-2 px-2 sm:px-3 rounded-lg border border-amber-100 dark:border-amber-900/30 flex items-center gap-1.5 sm:gap-2">
              <AlertTriangle className="h-3.5 sm:h-4 w-3.5 sm:w-4 text-amber-600 dark:text-amber-400" />
              <p className="text-xs sm:text-sm text-amber-700 dark:text-amber-400">
                {incompleteFields.length} item{incompleteFields.length !== 1 ? 's' : ''} need attention
              </p>
            </div>
          )}
        </div>

        {!allComplete && (
          <div className="mb-4 sm:mb-6 bg-amber-50/50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-lg p-3 sm:p-4">
            <h3 className="font-medium text-amber-800 dark:text-amber-400 mb-1.5 sm:mb-2 flex items-center gap-1.5 sm:gap-2 text-sm sm:text-base">
              <AlertTriangle className="h-4 sm:h-5 w-4 sm:w-5" />
              <span>Missing Information</span>
            </h3>
            <ul className="space-y-1 sm:space-y-1.5 text-xs sm:text-sm text-amber-700 dark:text-amber-400">
              {incompleteFields.map((field, i) => (
                <li key={i} className="flex items-center gap-1.5 sm:gap-2">
                  <span className="inline-block w-1 sm:w-1.5 h-1 sm:h-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                  {field}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      <div className="space-y-4 sm:space-y-8">
        {/* Procurement Details Section */}
        <div className="bg-white dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
          <div className="bg-gray-50 dark:bg-gray-800 p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0">
            <div className="flex items-center gap-2 sm:gap-3">
              <div className="p-1.5 sm:p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                <FileText className="h-4 sm:h-5 w-4 sm:w-5 text-blue-600 dark:text-blue-400" />
              </div>
              <h3 className="font-medium text-base sm:text-lg">Procurement Details</h3>
              {formCompletion.details ? (
                <Badge variant="outline" className="bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 border-green-200 dark:border-green-800 text-xs">
                  Complete
                </Badge>
              ) : (
                <Badge variant="outline" className="bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 border-amber-200 dark:border-amber-800 text-xs">
                  Incomplete
                </Badge>
              )}
            </div>

            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => setCurrentStep(1)}
              className="text-primary self-end sm:self-auto"
            >
              <Edit2 className="h-3.5 sm:h-4 w-3.5 sm:w-4 mr-1" /> Edit
            </Button>
          </div>

          <Separator />

          <div className="p-3 sm:p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
              <div>
                <h4 className="text-xs sm:text-sm font-medium text-muted-foreground mb-1 sm:mb-1.5">Procurement ID</h4>
                <p className={cn(
                  "text-base sm:text-lg font-medium",
                  data.procurement_id
                    ? "text-gray-900 dark:text-gray-50"
                    : "text-red-500 dark:text-red-400 italic"
                )}>
                  {data.procurement_id || "Not provided"}
                </p>
              </div>

              <div>
                <h4 className="text-xs sm:text-sm font-medium text-muted-foreground mb-1 sm:mb-1.5">Procurement Title</h4>
                <p className={cn(
                  "text-base sm:text-lg font-medium",
                  data.procurement_title
                    ? "text-gray-900 dark:text-gray-50"
                    : "text-red-500 dark:text-red-400 italic"
                )}>
                  {data.procurement_title || "Not provided"}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Documents Section */}
        <div className="bg-white dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
          <div className="bg-gray-50 dark:bg-gray-800 p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0">
            <div className="flex items-center gap-2 sm:gap-3">
              <div className="p-1.5 sm:p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                <Upload className="h-4 sm:h-5 w-4 sm:w-5 text-indigo-600 dark:text-indigo-400" />
              </div>
              <h3 className="font-medium text-base sm:text-lg">Procurement Documents</h3>
              {formCompletion.documents ? (
                <Badge variant="outline" className="bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 border-green-200 dark:border-green-800 text-xs">
                  Complete
                </Badge>
              ) : (
                <Badge variant="outline" className="bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 border-amber-200 dark:border-amber-800 text-xs">
                  Incomplete
                </Badge>
              )}
            </div>

            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => setCurrentStep(2)}
              className="text-primary self-end sm:self-auto"
            >
              <Edit2 className="h-3.5 sm:h-4 w-3.5 sm:w-4 mr-1" /> Edit
            </Button>
          </div>

          <Separator />

          <div className="p-3 sm:p-6">
            {data.files.length > 0 ? (
              <div className="space-y-4 sm:space-y-6">
                {data.files.map((file, index) => {
                  const meta = data.metadata[index];
                  const hasFile = !!file;

                  return (
                    <div key={index} className={cn(
                      "border rounded-lg overflow-hidden",
                      hasFile && meta?.document_type && meta?.submission_date && meta?.municipal_offices && meta?.signatory_details
                        ? "border-green-200 dark:border-green-900/30"
                        : "border-amber-200 dark:border-amber-900/30"
                    )}>
                      <div className={cn(
                        "p-2 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-0",
                        hasFile && meta?.document_type && meta?.submission_date && meta?.municipal_offices && meta?.signatory_details
                          ? "bg-green-50 dark:bg-green-900/10"
                          : "bg-amber-50 dark:bg-amber-900/10"
                      )}>
                        <div className="flex items-center gap-2 sm:gap-3">
                          <File className={cn(
                            "h-4 sm:h-5 w-4 sm:w-5",
                            hasFile && meta?.document_type && meta?.submission_date && meta?.municipal_offices && meta?.signatory_details
                              ? "text-green-600 dark:text-green-400"
                              : "text-amber-600 dark:text-amber-400"
                          )} />
                          <span className="font-medium text-sm sm:text-base">Document {index + 1}</span>
                        </div>
                        {hasFile && meta?.document_type && meta?.submission_date && meta?.municipal_offices && meta?.signatory_details ? (
                          <div className="flex items-center gap-1 sm:gap-2 text-green-600 dark:text-green-400">
                            <CheckCircle className="h-3.5 sm:h-4 w-3.5 sm:w-4" />
                            <span className="text-[10px] sm:text-xs font-medium">Complete</span>
                          </div>
                        ) : (
                          <div className="flex items-center gap-1 sm:gap-2 text-amber-600 dark:text-amber-400">
                            <AlertTriangle className="h-3.5 sm:h-4 w-3.5 sm:w-4" />
                            <span className="text-[10px] sm:text-xs font-medium">Incomplete</span>
                          </div>
                        )}
                      </div>

                      <div className="p-3 sm:p-4 bg-white dark:bg-gray-800/50">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-y-3 sm:gap-y-4 gap-x-4 sm:gap-x-8">
                          <div>
                            <h4 className="text-[10px] sm:text-xs uppercase font-medium text-muted-foreground mb-1 flex items-center gap-1">
                              <File className="h-3 sm:h-3.5 w-3 sm:w-3.5" /> File
                            </h4>
                            <p className={cn(
                              "text-xs sm:text-sm",
                              hasFile
                                ? "text-gray-800 dark:text-gray-200"
                                : "text-red-500 dark:text-red-400 italic"
                            )}>
                              {hasFile ? file.name : "No file uploaded"}
                            </p>
                          </div>

                          <div>
                            <h4 className="text-[10px] sm:text-xs uppercase font-medium text-muted-foreground mb-1">Document Type</h4>
                            <p className={cn(
                              "text-xs sm:text-sm",
                              meta?.document_type
                                ? "text-gray-800 dark:text-gray-200"
                                : "text-red-500 dark:text-red-400 italic"
                            )}>
                              {meta?.document_type || "Not specified"}
                            </p>
                          </div>

                          <div>
                            <h4 className="text-[10px] sm:text-xs uppercase font-medium text-muted-foreground mb-1 flex items-center gap-1">
                              <Calendar className="h-3 sm:h-3.5 w-3 sm:w-3.5" /> Submission Date
                            </h4>
                            <p className={cn(
                              "text-xs sm:text-sm",
                              meta?.submission_date
                                ? "text-gray-800 dark:text-gray-200"
                                : "text-red-500 dark:text-red-400 italic"
                            )}>
                              {meta?.submission_date || "Not specified"}
                            </p>
                          </div>

                          <div>
                            <h4 className="text-[10px] sm:text-xs uppercase font-medium text-muted-foreground mb-1 flex items-center gap-1">
                              <Building className="h-3 sm:h-3.5 w-3 sm:w-3.5" /> Municipal Offices
                            </h4>
                            <p className={cn(
                              "text-xs sm:text-sm",
                              meta?.municipal_offices
                                ? "text-gray-800 dark:text-gray-200"
                                : "text-red-500 dark:text-red-400 italic"
                            )}>
                              {meta?.municipal_offices || "Not specified"}
                            </p>
                          </div>

                          <div className="sm:col-span-2">
                            <h4 className="text-[10px] sm:text-xs uppercase font-medium text-muted-foreground mb-1 flex items-center gap-1">
                              <FileSignature className="h-3 sm:h-3.5 w-3 sm:w-3.5" /> Signatory Details
                            </h4>
                            <p className={cn(
                              "text-xs sm:text-sm whitespace-pre-line",
                              meta?.signatory_details
                                ? "text-gray-800 dark:text-gray-200"
                                : "text-red-500 dark:text-red-400 italic"
                            )}>
                              {meta?.signatory_details || "Not specified"}
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="text-center p-4 sm:p-6 bg-amber-50/50 dark:bg-amber-900/10 rounded-lg">
                <AlertTriangle className="h-6 sm:h-8 w-6 sm:w-8 text-amber-500 dark:text-amber-400 mx-auto mb-1.5 sm:mb-2" />
                <p className="text-amber-700 dark:text-amber-400 font-medium text-sm sm:text-base">No documents have been uploaded</p>
                <Button
                  variant="outline"
                  onClick={() => setCurrentStep(2)}
                  className="mt-3 sm:mt-4 border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-900/20 text-xs sm:text-sm px-3 sm:px-4 py-1 sm:py-2"
                >
                  Add Documents
                </Button>
              </div>
            )}
          </div>
        </div>

        {/* Final Instructions */}
        <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/30 rounded-xl p-4 sm:p-5">
          <div className="flex flex-col sm:flex-row sm:items-start gap-3 sm:gap-4">
            <div className="flex-shrink-0 bg-blue-100 dark:bg-blue-900/30 p-2 sm:p-3 rounded-full self-start">
              <CheckCircle className="h-5 sm:h-6 w-5 sm:w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <h3 className="text-base sm:text-lg font-medium text-blue-800 dark:text-blue-300 mb-1">
                Ready to Submit?
              </h3>
              <p className="text-xs sm:text-sm text-blue-700 dark:text-blue-400">
                Please verify all information is correct before submitting. Once submitted to the blockchain,
                this information becomes a permanent record and cannot be easily modified.
              </p>

              {!allComplete && (
                <div className="mt-3 sm:mt-4 py-2 sm:py-3 px-3 sm:px-4 bg-white dark:bg-gray-800 rounded-lg border border-blue-200 dark:border-blue-800/50">
                  <p className="text-xs sm:text-sm font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1.5 sm:gap-2">
                    <AlertTriangle className="h-3.5 sm:h-4 w-3.5 sm:w-4" />
                    Please complete all required information before submitting.
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
