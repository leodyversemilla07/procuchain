import { useState } from 'react';
import { useFormContext } from 'react-hook-form';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { FormField, FormItem, FormLabel, FormControl } from '@/components/ui/form';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  Info,
  CheckCircle2,
  AlertCircle,
  FileText,
  Tag,
  HelpCircle,
  XCircle
} from 'lucide-react';

interface ProcurementDetailsStepProps {
  data: Record<string, string | undefined>;
  errors: Record<string, string>;
  hasError: (field: string) => boolean;
  handleFieldChange: (field: string, value: string) => void;
  clearErrors: (field: string) => void;
}

export function ProcurementDetailsStep({
  data,
  errors,
  hasError,
  handleFieldChange,
  clearErrors,
}: ProcurementDetailsStepProps) {
  const form = useFormContext();
  const [showValidationSummary, setShowValidationSummary] = useState(false);
  const requiredFields = ['procurement_id', 'procurement_title'];

  // Helper function to render field validation state
  const getFieldStatus = (fieldName: string) => {
    if (hasError(fieldName)) return 'error';
    if (data[fieldName] && data[fieldName]!.length > 0) return 'valid';
    return 'required';
  };

  return (
    <div>
      <Card className="border-sidebar-border/70 dark:border-sidebar-border">
        <CardHeader>
          <div className="flex items-center gap-3">
            <Tag className="h-5 w-5 text-primary" />
            <CardTitle className="text-xl">Procurement Details</CardTitle>
          </div>
          <CardDescription>
            Enter the basic information for this procurement request
          </CardDescription>
        </CardHeader>

        <ScrollArea className="max-h-[600px]">
          <CardContent className="space-y-6">
            {/* Validation summary alert */}
            {showValidationSummary && requiredFields.some(field => hasError(field)) && (
              <Alert variant="destructive" className="mb-4">
                <div className="flex gap-3">
                  <AlertCircle className="h-4 w-4 mt-0.5 flex-shrink-0" />
                  <AlertDescription>
                    <p className="font-medium mb-2">Please correct the following issues:</p>
                    <ul className="list-disc list-inside space-y-1 text-sm">
                      {requiredFields.map(field =>
                        hasError(field) ? (
                          <li key={field}>{errors[field]}</li>
                        ) : null
                      )}
                    </ul>
                  </AlertDescription>
                </div>
              </Alert>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              {/* Procurement ID */}
              <div className="space-y-4 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-6 transition-all hover:shadow-md">
                <FormField
                  control={form.control}
                  name="procurement_id"
                  rules={{ required: "Procurement ID is required" }}
                  render={({ field }) => (
                    <FormItem className="space-y-3">
                      <div className="flex items-center justify-between">
                        <FormLabel className="flex items-center gap-2 text-base font-medium">
                          Procurement ID
                          <Badge variant="destructive" className="font-normal">Required</Badge>
                        </FormLabel>
                        {hasError('procurement_id') && (
                          <span className="text-xs font-medium text-destructive">
                            {errors.procurement_id}
                          </span>
                        )}
                      </div>

                      <div className="relative">
                        <FileText className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <FormControl>
                          <Input
                            {...field}
                            value={data.procurement_id || ''}
                            onChange={(e) => {
                              field.onChange(e);
                              handleFieldChange('procurement_id', e.target.value);
                              clearErrors('procurement_id');
                              setShowValidationSummary(false);
                            }}
                            placeholder="Enter procurement ID (e.g. PR-2023-001)"
                            className={`pl-10 ${hasError('procurement_id')
                              ? 'border-destructive focus-visible:ring-destructive'
                              : getFieldStatus('procurement_id') === 'valid'
                                ? 'border-green-500 dark:border-green-600'
                                : ''
                              }`}
                          />
                        </FormControl>
                        {getFieldStatus('procurement_id') === 'valid' && (
                          <CheckCircle2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-green-500" />
                        )}
                      </div>

                      <TooltipProvider>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                              <HelpCircle className="h-3.5 w-3.5 text-primary" />
                              <span>Unique identifier for tracking</span>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent side="bottom" className="max-w-xs">
                            <p className="font-medium">Procurement ID</p>
                            <p className="text-xs opacity-90">The unique identifier used to track this procurement request in the system.</p>
                          </TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                    </FormItem>
                  )}
                />
              </div>

              {/* Procurement Title */}
              <div className="space-y-4 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-6 transition-all hover:shadow-md">
                <FormField
                  control={form.control}
                  name="procurement_title"
                  rules={{ required: "Procurement Title is required" }}
                  render={({ field }) => (
                    <FormItem className="space-y-3">
                      <div className="flex items-center justify-between">
                        <FormLabel className="flex items-center gap-2 text-base font-medium">
                          Procurement Title
                          <Badge variant="destructive" className="font-normal">Required</Badge>
                        </FormLabel>
                        {hasError('procurement_title') && (
                          <span className="text-xs font-medium text-destructive">
                            {errors.procurement_title}
                          </span>
                        )}
                      </div>

                      <div className="relative">
                        <Tag className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <FormControl>
                          <div className="relative">
                            <Input
                              {...field}
                              value={data.procurement_title || ''}
                              onChange={(e) => {
                                field.onChange(e);
                                handleFieldChange('procurement_title', e.target.value);
                                clearErrors('procurement_title');
                                setShowValidationSummary(false);
                              }}
                              placeholder="Enter descriptive title for this procurement"
                              className={`pl-10 ${hasError('procurement_title')
                                ? 'border-destructive focus-visible:ring-destructive'
                                : getFieldStatus('procurement_title') === 'valid'
                                  ? 'border-green-500 dark:border-green-600'
                                  : ''
                                }`}
                            />
                            {data.procurement_title && (
                              <button
                                type="button"
                                onClick={() => {
                                  handleFieldChange('procurement_title', '');
                                  field.onChange('');
                                }}
                                className="absolute right-10 top-1/2 -translate-y-1/2 rounded-full p-1 text-muted-foreground hover:bg-muted hover:text-destructive"
                                aria-label="Clear procurement title"
                              >
                                <XCircle className="h-4 w-4" />
                              </button>
                            )}
                          </div>
                        </FormControl>
                        {getFieldStatus('procurement_title') === 'valid' && (
                          <CheckCircle2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-green-500" />
                        )}
                      </div>

                      <TooltipProvider>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                              <HelpCircle className="h-3.5 w-3.5 text-primary" />
                              <span>Descriptive title for the procurement</span>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent side="bottom" className="max-w-xs">
                            <p className="font-medium">Procurement Title</p>
                            <p className="text-xs opacity-90">A clear and descriptive title that identifies the purpose of this procurement request.</p>
                          </TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                    </FormItem>
                  )}
                />
              </div>
            </div>
          </CardContent>
        </ScrollArea>

        <CardFooter className="bg-muted/50 border-t px-6 py-4">
          <div className="w-full flex justify-between items-center">
            <div className="flex items-center gap-2">
              <Info className="h-4 w-4 text-primary" />
              <span className="text-sm text-muted-foreground">
                All fields marked with <span className="text-destructive mx-1">Required</span> must be filled
              </span>
            </div>
          </div>
        </CardFooter>
      </Card>
    </div>
  );
}
