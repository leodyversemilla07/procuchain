import React from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { DatePicker } from '@/components/ui/date-picker';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { MapPin, Info } from 'lucide-react';
import type { StepProps } from '../types';

export function DeliveryDetailsStep({ data, setData, errors, clearErrors, hasError }: StepProps) {
    const handleFieldChange = (field: keyof typeof data, value: string | Date | undefined): void => {
        clearErrors(field);
        setData(field, value as string & Date & undefined);
    };

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <Card className="border-2 border-primary/20 bg-primary/5">
                <CardHeader className="p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                        <div className="rounded-lg bg-primary/10 p-3">
                            <MapPin className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-base font-semibold sm:text-lg">Delivery Details</h3>
                            <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                Specify the delivery location, date, and terms for this procurement.
                            </p>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Form Fields */}
            <Card>
                <CardContent className="space-y-4 p-4 pt-4 sm:space-y-6 sm:p-6 sm:pt-6">
                    {/* Delivery Location, Date, and Term Days - Grid */}
                    <div className="grid gap-4 sm:gap-6 lg:grid-cols-3">
                        {/* Delivery Location */}
                        <Field>
                            <FieldLabel>
                                Delivery Location
                                <span className="text-destructive">*</span>
                            </FieldLabel>
                        <FieldDescription>
                            Where should the goods/services be delivered?
                        </FieldDescription>
                        <Input
                            value={data.delivery_location}
                            onChange={(e) => handleFieldChange('delivery_location', e.target.value)}
                            className={
                                hasError('delivery_location')
                                    ? 'border-destructive ring-destructive/30'
                                    : ''
                            }
                            placeholder="e.g., Municipal Hall, Main Office"
                        />
                            {hasError('delivery_location') && (
                                <FieldError>{errors.delivery_location}</FieldError>
                            )}
                        </Field>

                        {/* Delivery Date */}
                        <Field>
                            <FieldLabel>
                                Delivery Date
                                <span className="text-destructive">*</span>
                            </FieldLabel>
                        <FieldDescription>
                            Expected date for delivery or completion
                        </FieldDescription>
                        <DatePicker
                            date={data.delivery_date}
                            onDateChange={(date: Date | undefined) => handleFieldChange('delivery_date', date)}
                            minDate={new Date()}
                            className={
                                hasError('delivery_date')
                                    ? 'border-destructive ring-destructive/30'
                                    : ''
                            }
                        />
                            {hasError('delivery_date') && (
                                <FieldError>{errors.delivery_date}</FieldError>
                            )}
                        </Field>

                        {/* Delivery Term Days */}
                        <Field>
                            <FieldLabel>Delivery Term (Days)</FieldLabel>
                            <FieldDescription>
                                Number of calendar days for delivery from contract signing (optional)
                            </FieldDescription>
                            <Input
                                type="number"
                                value={data.delivery_term_days}
                                onChange={(e) => handleFieldChange('delivery_term_days', e.target.value)}
                                placeholder="e.g., 30"
                                min="0"
                            />
                        </Field>
                    </div>
                </CardContent>
            </Card>

            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Timeline:</strong> Ensure the delivery date allows sufficient time for the procurement process and contractor preparation.
                </AlertDescription>
            </Alert>
        </div>
    );
}
