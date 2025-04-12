import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, CheckCircle2, FileText, Tag, AlertTriangle } from 'lucide-react';
import { useState, useEffect } from 'react';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';

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
  const [touched, setTouched] = useState<Record<string, boolean>>({});

  useEffect(() => {
    setValidFields({
      procurement_id: !!data.procurement_id && !hasError('procurement_id'),
      procurement_title: !!data.procurement_title && !hasError('procurement_title')
    });
  }, [data, errors, hasError]);

  const handleBlur = (field: string) => {
    setTouched(prev => ({ ...prev, [field]: true }));
  };

  return (
    <Card className="border-sidebar-border/70 dark:border-sidebar-border">
      <CardHeader className="pb-6">
        <div className="flex items-center gap-4">
          <div className="bg-primary/10 p-2.5 rounded-lg">
            <FileText className="h-5 w-5 text-primary" />
          </div>
          <div className="space-y-1">
            <CardTitle className="text-2xl tracking-tight font-semibold bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-transparent">
              Procurement Details
            </CardTitle>
            <CardDescription className="text-base text-muted-foreground/90">
              Enter the basic information about this procurement request
            </CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent className="p-6">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div className="space-y-5">
            <div className="space-y-3">
              <Label 
                htmlFor="procurement_id" 
                className="text-sm font-medium flex items-center justify-between"
              >
                <div className="flex items-center gap-2">
                  <span className="text-foreground/90">Procurement ID</span>
                  <Badge variant="destructive" className="text-[10px] px-2 py-0.5 rounded font-medium">Required</Badge>
                </div>
                {validFields.procurement_id && (
                  <div className="flex items-center text-sm text-green-500 gap-1.5">
                    <CheckCircle2 className="h-4 w-4 shrink-0" />
                    <span>Valid</span>
                  </div>
                )}
              </Label>
              <div className="relative">
                <div className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/70">
                  <Tag className="h-4 w-4 shrink-0" />
                </div>
                <Input
                  id="procurement_id"
                  name="procurement_id"
                  value={data.procurement_id}
                  onChange={(e) => {
                    handleFieldChange('procurement_id', e.target.value);
                  }}
                  onBlur={() => handleBlur('procurement_id')}
                  className={cn(
                    "pl-10 h-11 transition-all font-medium tracking-tight",
                    hasError('procurement_id') && touched.procurement_id ? "border-destructive/70 ring-destructive/20" : "",
                    validFields.procurement_id ? "border-green-500/70 ring-green-200/20 bg-green-50/50 dark:bg-green-900/10" : ""
                  )}
                  placeholder="e.g., PR-2023-001"
                  aria-describedby="procurement_id_desc"
                  onFocus={() => clearErrors('procurement_id')}
                />
              </div>
              <div className="flex items-center gap-2 text-xs text-muted-foreground/90" id="procurement_id_desc">
                <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                Must be a unique identifier for this procurement request
              </div>
              {hasError('procurement_id') && touched.procurement_id && (
                <Alert variant="destructive" className="py-2.5 animate-in slide-in-from-top fade-in duration-200">
                  <AlertCircle className="h-4 w-4 shrink-0" />
                  <AlertDescription>{errors.procurement_id}</AlertDescription>
                </Alert>
              )}
            </div>
          </div>

          <div className="space-y-5">
            <div className="space-y-3">
              <Label 
                htmlFor="procurement_title" 
                className="text-sm font-medium flex items-center justify-between"
              >
                <div className="flex items-center gap-2">
                  <span className="text-foreground/90">Procurement Title</span>
                  <Badge variant="destructive" className="text-[10px] px-2 py-0.5 rounded font-medium">Required</Badge>
                </div>
                {validFields.procurement_title && (
                  <div className="flex items-center text-sm text-green-500 gap-1.5">
                    <CheckCircle2 className="h-4 w-4 shrink-0" />
                    <span>Valid</span>
                  </div>
                )}
              </Label>
              <div className="relative">
                <div className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/70">
                  <FileText className="h-4 w-4 shrink-0" />
                </div>
                <Input
                  id="procurement_title"
                  name="procurement_title"
                  value={data.procurement_title}
                  onChange={(e) => {
                    handleFieldChange('procurement_title', e.target.value);
                  }}
                  onBlur={() => handleBlur('procurement_title')}
                  className={cn(
                    "pl-10 h-11 transition-all font-medium tracking-tight",
                    hasError('procurement_title') && touched.procurement_title ? "border-destructive/70 ring-destructive/20" : "",
                    validFields.procurement_title ? "border-green-500/70 ring-green-200/20 bg-green-50/50 dark:bg-green-900/10" : ""
                  )}
                  placeholder="e.g., Office Supplies Procurement"
                  aria-describedby="procurement_title_desc"
                  onFocus={() => clearErrors('procurement_title')}
                />
              </div>
              <div className="flex items-center gap-2 text-xs text-muted-foreground/90" id="procurement_title_desc">
                <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                Enter a descriptive title for what is being procured
              </div>
              {hasError('procurement_title') && touched.procurement_title && (
                <Alert variant="destructive" className="py-2.5 animate-in slide-in-from-top fade-in duration-200">
                  <AlertCircle className="h-4 w-4 shrink-0" />
                  <AlertDescription>{errors.procurement_title}</AlertDescription>
                </Alert>
              )}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
