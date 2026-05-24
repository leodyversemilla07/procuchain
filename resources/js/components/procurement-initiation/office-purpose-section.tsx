import React from 'react';
import { Building2 } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import type { UseFormData } from '@/hooks/use-procurement-initiation';

interface OfficePurposeSectionProps {
  data: UseFormData;
  errors: Record<string, string>;
  hasError: (field: string) => boolean;
  handleFieldChange: (field: keyof UseFormData, value: string | Date | undefined) => void;
  selectedOfficeLabel: string;
  selectedEndUserLabel: string;
  MUNICIPAL_OFFICES: readonly { value: string; label: string }[];
}

export function OfficePurposeSection({
  data,
  errors,
  hasError,
  handleFieldChange,
  selectedOfficeLabel,
  selectedEndUserLabel,
  MUNICIPAL_OFFICES,
}: OfficePurposeSectionProps) {
  return (
    <Card className="border-sidebar-border/70 dark:border-sidebar-border shadow-md">
      <CardHeader className="space-y-1 pb-2 sm:pb-4">
        <CardTitle className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
          <Building2 className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
          Office &amp; Purpose
        </CardTitle>
        <CardDescription className="text-muted-foreground text-sm">Requesting office and procurement justification</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 p-4 sm:space-y-6 sm:p-6">
        <div className="grid gap-4 sm:gap-6 lg:grid-cols-2">
          {/* Office */}
          <Field>
            <FieldLabel htmlFor="office">
              Office
              <span className="text-destructive">*</span>
            </FieldLabel>
            <FieldDescription>Select the office requesting this procurement</FieldDescription>
            <Select value={data.office} onValueChange={(value) => value && handleFieldChange('office', value)}>
              <SelectTrigger className={hasError('office') ? 'border-destructive ring-destructive/30' : ''}>
                <SelectValue placeholder="Select office">{() => selectedOfficeLabel}</SelectValue>
              </SelectTrigger>
              <SelectContent className="max-h-60 overflow-y-auto">
                <SelectGroup>
                  {MUNICIPAL_OFFICES.map((office) => (
                    <SelectItem key={office.value} value={office.value}>
                      {office.label}
                    </SelectItem>
                  ))}
                </SelectGroup>
              </SelectContent>
            </Select>
            {hasError('office') && <FieldError>{errors.office}</FieldError>}
          </Field>

          {/* End User */}
          <Field>
            <FieldLabel htmlFor="end_user">End User (Optional)</FieldLabel>
            <FieldDescription>If different from the office, specify the actual end user</FieldDescription>
            <Select
              value={data.end_user}
              onValueChange={(value) => {
                if (!value) return;
                handleFieldChange('end_user', value);
                if (value !== 'Other') {
                  handleFieldChange('other_end_user', '');
                }
              }}
            >
              <SelectTrigger>
                <SelectValue placeholder="Same as Office">{() => selectedEndUserLabel}</SelectValue>
              </SelectTrigger>
              <SelectContent className="max-h-60 overflow-y-auto">
                <SelectGroup>
                  {MUNICIPAL_OFFICES.map((office) => (
                    <SelectItem key={office.value} value={office.value}>
                      {office.label}
                    </SelectItem>
                  ))}
                  <SelectItem value="Other">Other (Please specify)</SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            {data.end_user === 'Other' && (
              <div className="mt-3">
                <Input
                  id="other_end_user"
                  name="other_end_user"
                  type="text"
                  value={data.other_end_user}
                  onChange={(e) => handleFieldChange('other_end_user', e.target.value)}
                  className={hasError('other_end_user') ? 'border-destructive ring-destructive/30' : ''}
                  placeholder="Please specify the end user"
                />
                {hasError('other_end_user') && <FieldError>{errors.other_end_user}</FieldError>}
              </div>
            )}
          </Field>

          {/* Prepared By */}
          <Field>
            <FieldLabel htmlFor="prepared_by">
              Prepared By
              <span className="text-destructive ml-1 text-xs">*</span>
            </FieldLabel>
            <FieldDescription>Name of the person preparing this request</FieldDescription>
            <Input
              id="prepared_by"
              name="prepared_by"
              value={data.prepared_by}
              onChange={(e) => handleFieldChange('prepared_by', e.target.value)}
              className={hasError('prepared_by') ? 'border-destructive ring-destructive/30' : ''}
              placeholder="Full Name"
            />
            {hasError('prepared_by') && <FieldError>{errors.prepared_by}</FieldError>}
          </Field>
        </div>
      </CardContent>
    </Card>
  );
}
