import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, CheckCircle2, FileText, Tag } from 'lucide-react';
import { useState, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface ProcurementDetailsStepProps {
  data: {
    procurement_id: string;
    procurement_title: string;
  };
  errors: Record<string, string>;
  hasError: (field: string) => boolean;
  handleFieldChange: (field: string, value: string) => void;
  clearErrors: (field: string) => void;
}

export function ProcurementDetails({
  data,
  errors,
  hasError,
  handleFieldChange,
  clearErrors,
}: ProcurementDetailsStepProps) {
  const [validFields, setValidFields] = useState<Record<string, boolean>>({});

  // Set a field as valid when it has value and no errors
  useEffect(() => {
    setValidFields({
      procurement_id: !!data.procurement_id && !hasError('procurement_id'),
      procurement_title: !!data.procurement_title && !hasError('procurement_title')
    });
  }, [data, errors, hasError]);

  return (
    <div className="space-y-6">
      <Card className="shadow-sm border-border">
        <CardHeader>
          <CardTitle className="text-xl flex items-center gap-2">
            <FileText className="h-5 w-5" />
            Procurement Details
          </CardTitle>
          <CardDescription>
            Enter the basic information about this procurement request
          </CardDescription>
        </CardHeader>
        <CardContent className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Procurement ID Field */}
            <div className="space-y-3">
              <Label htmlFor="procurement_id" className="text-sm font-medium flex items-center justify-between">
                <span>
                  Procurement ID <span className="text-red-500">*</span>
                </span>
                {validFields.procurement_id && (
                  <CheckCircle2 className="h-4 w-4 text-green-500" />
                )}
              </Label>
              <div className="relative">
                <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                  <Tag className="h-4 w-4" />
                </div>
                <Input
                  id="procurement_id"
                  name="procurement_id"
                  value={data.procurement_id}
                  onChange={(e) => {
                    handleFieldChange('procurement_id', e.target.value);
                  }}
                  className={cn(
                    "pl-10 transition-all",
                    hasError('procurement_id') ? 'border-red-500 ring-red-100' : '',
                    validFields.procurement_id ? 'border-green-500 ring-green-100' : ''
                  )}
                  placeholder="e.g., PR-2023-001"
                  aria-describedby="procurement_id_desc"
                  onFocus={() => clearErrors('procurement_id')}
                />
              </div>
              <p id="procurement_id_desc" className="text-xs text-muted-foreground">
                A unique identifier for this procurement request
              </p>
              {errors.procurement_id && (
                <Alert variant="destructive" className="py-2 animate-in slide-in-from-top fade-in duration-200">
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>{errors.procurement_id}</AlertDescription>
                </Alert>
              )}
            </div>

            {/* Procurement Title Field */}
            <div className="space-y-3">
              <Label htmlFor="procurement_title" className="text-sm font-medium flex items-center justify-between">
                <span>
                  Procurement Title <span className="text-red-500">*</span>
                </span>
                {validFields.procurement_title && (
                  <CheckCircle2 className="h-4 w-4 text-green-500" />
                )}
              </Label>
              <div className="relative">
                <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                  <FileText className="h-4 w-4" />
                </div>
                <Input
                  id="procurement_title"
                  name="procurement_title"
                  value={data.procurement_title}
                  onChange={(e) => {
                    handleFieldChange('procurement_title', e.target.value);
                  }}
                  className={cn(
                    "pl-10 transition-all",
                    hasError('procurement_title') ? 'border-red-500 ring-red-100' : '',
                    validFields.procurement_title ? 'border-green-500 ring-green-100' : ''
                  )}
                  placeholder="e.g., Office Supplies Procurement"
                  aria-describedby="procurement_title_desc"
                  onFocus={() => clearErrors('procurement_title')}
                />
              </div>
              <p id="procurement_title_desc" className="text-xs text-muted-foreground">
                A descriptive title for what is being procured
              </p>
              {errors.procurement_title && (
                <Alert variant="destructive" className="py-2 animate-in slide-in-from-top fade-in duration-200">
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>{errors.procurement_title}</AlertDescription>
                </Alert>
              )}
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
