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
  data: any;
  errors: Record<string, any>;
  hasError: (field: string) => boolean;
  handleFieldChange: (field: string, value: any) => void;
  clearErrors: any;
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
    if (data[fieldName]?.length > 0) return 'valid';
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

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Procurement ID */}
              <div className="space-y-4 border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <FormField
                  control={form.control}
                  name="procurement_id"
                  rules={{ required: "Procurement ID is required" }}
                  render={({ field }) => (
                    <FormItem className="space-y-2">
                      <div className="flex justify-between items-center">
                        <FormLabel className="flex items-center text-base">
                          <span>Procurement ID</span>
                          <Badge variant="outline" className="ml-2 text-xs">Required</Badge>
                        </FormLabel>
                        {hasError('procurement_id') && (
                          <p className="text-xs text-destructive">
                            {errors.procurement_id}
                          </p>
                        )}
                      </div>

                      <div className="relative">
                        <FileText className="absolute left-3 h-4 w-4 text-muted-foreground top-3" />
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
                            className="pl-10"
                            required
                          />
                        </FormControl>
                        {getFieldStatus('procurement_id') === 'valid' && (
                          <CheckCircle2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-green-500" />
                        )}
                      </div>

                      <TooltipProvider>
                        <Tooltip delayDuration={300}>
                          <TooltipTrigger asChild>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground cursor-help">
                              <HelpCircle className="h-3.5 w-3.5 text-primary" />
                              <span>Unique identifier for tracking (Required)</span>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent side="bottom" align="center">
                            <p className="text-sm font-medium">Procurement ID</p>
                            <p className="text-xs">The unique identifier for this procurement request.</p>
                          </TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                    </FormItem>
                  )}
                />
              </div>

              {/* Procurement Title */}
              <div className="space-y-4 border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <FormField
                  control={form.control}
                  name="procurement_title"
                  rules={{ required: "Procurement Title is required" }}
                  render={({ field }) => (
                    <FormItem className="space-y-2">
                      <div className="flex justify-between items-center">
                        <FormLabel className="flex items-center text-base">
                          <span>Procurement Title</span>
                          <Badge variant="outline" className="ml-2 text-xs">Required</Badge>
                        </FormLabel>
                        {hasError('procurement_title') && (
                          <p className="text-xs text-destructive">
                            {errors.procurement_title}
                          </p>
                        )}
                      </div>

                      <div className="relative">
                        <Tag className="absolute left-3.5 h-4.5 w-4.5 text-gray-500 dark:text-gray-400 top-3" />
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
                              className={`pl-10 py-2.5 transition-all duration-200 shadow-sm rounded-lg text-base ${hasError('procurement_title')
                                ? 'border-red-300 dark:border-red-800 focus-visible:ring-red-500'
                                : data.procurement_title?.length > 0
                                  ? 'border-green-300 dark:border-green-800/60'
                                  : 'border-gray-200 dark:border-gray-700 focus-visible:ring-blue-400'
                                }`}
                              required
                            />
                            {data.procurement_title?.length > 0 && (
                              <button
                                type="button"
                                onClick={() => {
                                  handleFieldChange('procurement_title', '');
                                  field.onChange({ target: { value: '' } });
                                }}
                                className="absolute right-10 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 p-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800"
                                aria-label="Clear procurement title"
                              >
                                <XCircle className="h-4 w-4" />
                              </button>
                            )}
                          </div>
                        </FormControl>
                        {getFieldStatus('procurement_title') === 'valid' && (
                          <CheckCircle2 className="absolute right-3.5 top-1/2 -translate-y-1/2 h-[18px] w-[18px] text-green-500" />
                        )}
                      </div>

                      <TooltipProvider>
                        <Tooltip delayDuration={300}>
                          <TooltipTrigger asChild>
                            <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 cursor-help">
                              <HelpCircle className="h-3.5 w-3.5 text-blue-500" />
                              <span>Descriptive title for the procurement (Required)</span>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent
                            side="bottom"
                            align="center"
                            className="bg-blue-50 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 border-blue-100 dark:border-blue-800 text-sm font-medium px-3 py-2 shadow-lg"
                          >
                            <p className="text-sm font-medium">Procurement Title</p>
                            <p className="text-xs">A descriptive title that clearly identifies what is being procured.</p>
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
